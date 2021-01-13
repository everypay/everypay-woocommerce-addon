<?php

if (!defined('ABSPATH'))
    exit;


class WC_Everypay_Gateway extends WC_Payment_Gateway
{

    /**
     * Everypay public key
     * @var type String
     */
    private $everypayPublicKey;

    /**
     * Everypay secret key
     * @var type String
     */
    private $everypaySecretKey;

    public function __construct()
    {
        $this->id = 'everypay';
        $this->icon = apply_filters('woocommerce_everypay_icon', EVERYPAY_IMAGES_URL.'/everypay.png');
        $this->has_fields = true;
        $this->method_title = pll__('Everypay Payment Gateway');;
        $this->init_form_fields();
        $this->init_settings();

        $this->supports = array('products', 'refunds');
        $this->nag_name = 'everypay_nag_notice_' . date('W');
        $this->title = pll__(esc_html($this->get_option('everypay_title')));
        $this->description = pll__(esc_html($this->get_option('description')));;
        $this->everypayPublicKey = esc_html($this->get_option('everypayPublicKey'));
        $this->everypaySecretKey = esc_html($this->get_option('everypaySecretKey'));
        $this->everypayMaxInstallments = $this->get_option('everypay_maximum_installments');
        $this->everypay_sandbox = esc_html($this->get_option('everypay_sandbox'));
        $this->errors = array();

        $this->fee = 0;

        if (!defined("EVERYPAY_SANDBOX")) {
            define("EVERYPAY_SANDBOX", ($this->everypay_sandbox == 'yes' ? true : false));
        }

        if (EVERYPAY_SANDBOX) {
            WC_Everypay_Api::setTestMode();
        }

        // The hooks
        add_filter('woocommerce_available_payment_gateways', array($this, 'disable_everypay_if_keys_not_set'));
        add_filter('query_vars', array($this, 'add_everypay_var'));
        add_action('wp_enqueue_scripts', array($this, 'add_everypay_js'));

        add_action('admin_init', array($this, 'nag_everypay'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'load_everypay_admin'));
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
    }

    /**
     *  Remove payment gateway if keys are not set
     */
    public function disable_everypay_if_keys_not_set($available_gateways)
    {
        if (isset($available_gateways['everypay']) && empty($this->everypaySecretKey) || empty($this->everypayPublicKey))
            unset($available_gateways['everypay']);

        return $available_gateways;
    }


    /**
     * Whitelist get param
     *
     * @param array $vars
     */
    public function add_everypay_var($vars)
    {
        $vars[] = "everypayToken";
        return $vars;
    }

    /**
     * Hide notice for this user
     *
     */
    public function nag_everypay()
    {
        global $current_user;
        $user_id = $current_user->ID;

        $evGway = new WC_Everypay_Gateway();
        $nag_name = $evGway->nag_name;
        if (isset($_GET[$nag_name]) && '0' == $_GET[$nag_name]) {
            add_user_meta($user_id, $nag_name, 'true', true);
            if (wp_get_referer()) {
                wp_safe_redirect(wp_get_referer());
            } else {
                wp_safe_redirect(home_url());
            }
        }
    }

    /**
     * Show some notices on the admin
     *
     */
    public function show_admin_notices()
    {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        global $current_user;
        $user_id = $current_user->ID;
        $evGway = new WC_Everypay_Gateway();
        $nag_name = $evGway->nag_name;

        if (get_user_meta($user_id, $nag_name)) {
            return;
        }

        if (empty($this->everypaySecretKey) || empty($this->everypayPublicKey)) {
            echo '<div class="error"><p><strong>Please fill your Everypay keys, in Woocommerce/Settings/Payments</strong><p/></div>';
        }

    }

    /**
     * The scripts on admin tab
     */
    public function load_everypay_admin()
    {
        if (isset($_GET['page']) && $_GET['page'] == 'wc-settings'
            && isset($_GET['tab']) && $_GET['tab'] == 'checkout'
            && isset($_GET['section']) && in_array($_GET['section'], array('wc_everypay_gateway', 'everypay')))
        {
            wp_register_script('everypay_script1', EVERYPAY_JS_URL.'admin/mustache.min.js', array('jquery'), 'ver', true);
            wp_enqueue_script('everypay_script1');

            wp_register_script('everypay_script2', EVERYPAY_JS_URL.'admin/everypay.js', array('jquery'), 'ver', true);
            wp_enqueue_script('everypay_script2');
        }
    }

    /**
     * Enqueue the js files on frontend
     */
    public function add_everypay_js()
    {
        //show only in checkout page
        if (get_the_ID() != get_option("woocommerce_checkout_page_id", 0)) {
            return;
        }

        wp_register_style( 'everypay_styles', EVERYPAY_CSS_URL.'everypay_styles.css' );
        wp_enqueue_style( 'everypay_styles' );

        wp_register_style( 'everypay_modal', EVERYPAY_CSS_URL.'everypay_modal.css' );
        wp_enqueue_style( 'everypay_modal' );

        if (EVERYPAY_SANDBOX)
            wp_register_script('everypay_script', "https://sandbox-js.everypay.gr/v3");
        else
            wp_register_script('everypay_script', "https://js.everypay.gr/v3");

        wp_enqueue_script('everypay_script');

        wp_register_script('everypay_modal', EVERYPAY_JS_URL.'everypay_modal.js', array('jquery'), '1.12', true);
        wp_enqueue_script('everypay_modal');

        wp_register_script('everypay', EVERYPAY_JS_URL.'everypay.js', array('jquery'), '1.12', true);
        wp_enqueue_script('everypay');
    }

    /**
     * Add extra charge to cart totals
     *
     * @param double $totals
     * return double
     */
    public function calculate_order_totals($cart)
    {
        if (!defined('WOOCOMMERCE_CHECKOUT')) {
            return;
        }

        $total = $cart->cart_contents_total + $cart->shipping_total;
        if (!$cart->prices_include_tax) {
            if (count($cart->taxes)) {
                foreach ($cart->taxes as $tax) {
                    $total +=  $tax;
                }
            }
            if (count($cart->shipping_taxes)) {
                foreach ($cart->shipping_taxes as $tax) {
                    $total += $tax;
                }
            }
        }

        $fee_name = pll__('Payment fee');
        $fee_id = 'payment-fee';
        $fees = $cart->get_fees();

        $already_exists = false;
        foreach ($fees as $i => $fee) {
            if ($fee->id == $fee_id) {
                $already_exists = true;
            } else {
                $total += $fee->amount;
            }
        }

        if (WC()->session->chosen_payment_method != 'everypay') {
            return;
        }

        if ($this->get_option('everypay_fee_enabled') !== 'yes') {
            return;
        }

        $fee = floatval($this->get_option('everypay_fee_percent'));
        $c = floatval($this->get_option('everypay_fee_amount'));

        $newtotal = (($total + $c) * 100) / (100 - $fee);

        $fee_amount = $newtotal - $total;

        if (!$already_exists) {
            $cart->add_fee($fee_name, $fee_amount);
        } else {
            foreach ($fees as $i => $fee) {
                if ($fee->id == $fee_id) {
                    $fees[$i]->amount = $fee_amount;
                    break;
                }
            }
        }

        $this->fee = $fee_amount;
    }

    public function admin_options()
    {

        ?>
        <h3><?php pll_e('Everypay Payment Gateway for Woocommerce'); ?></h3>
        <p><?php pll_e('Everypay is a company that provides a way for individuals and businesses to accept payments over the Internet.'); ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            <tr valign="top">
                <th scope="row" class="titledesc" style="padding-top:0">&nbsp;</th>
                <td class="forminp" id="everypay-max_installments-table" style="padding-top:0">
                    <div id="installments"></div>
                    <div id="installment-table" style="display:none">
                        <table class="widefat wc_input_table table" cellspacing="0">
                            <thead>
                            <tr>
                                <th>Από (Ποσό σε &euro;)</th>
                                <th>Eως (Ποσό σε &euro;)</th>
                                <th>Μέγιστος Αρ. Δόσεων</th>
                                <th>
                                    <a class="button-primary" href="#" id="add-installment" style="width:101px;">
                                        <i class="icon icon-plus-sign"></i> <span class="ab-icon"></span>  Προσθήκη
                                    </a>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    <style type="text/css">
                        #everypay-max_installments-table table{border:none}
                        .remove-installment{font-size: 2em; text-decoration: none !important;color:#ee5f5b}
                        #installment-table table{width:600px;background: white;}
                        #everypay-max_installments-table table tr td,
                        #everypay-max_installments-table table tr th{border:none;border-bottom:1px solid #f2f2f2}
                        #everypay-max_installments-table{width:100%;max-width: 801px;background: #fff; padding:16px;}
                        #everypay-max_installments-table table input[type="number"] {width: 99px;}
                    </style>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Form fields for the admin
     *
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => pll__('Enable/Disable'),
                'type' => 'checkbox',
                'label' => pll__('Enable Everypay'),
                'default' => 'yes'
            ),
            'everypay_title' => array(
                'title' => pll__('Title'),
                'type' => 'text',
                'description' => pll__('This controls the title which the user sees during checkout.'),
                'default' => pll__('Pay with Card'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => pll__('Description'),
                'type' => 'textarea',
                'description' => pll__('Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%'),
                'default' => pll__('Pay using your credit or debit card.'),
                'desc_tip' => pll__('Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%'),
            ),
            'everypayPublicKey' => array(
                'title' => pll__('Public Key'),
                'type' => 'text',
                'description' => pll__('This is the Public Key found in API Keys in Account Dashboard.'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Everypay Public Key'
            ),
            'everypaySecretKey' => array(
                'title' => pll__('Secret Key'),
                'type' => 'text',
                'description' => pll__('This is the Secret Key found in API Keys in Account Dashboard.'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Everypay Secret Key'
            ),
            'everypay_sandbox' => array(
                'title' => pll__('Sandbox Mode'),
                'type' => 'checkbox',
                'label' => pll__('Enable sandbox mode'),
                'description' => pll__('If checked its in sandbox mode and if unchecked its in live mode'),
                'desc_tip' => true,
                'default' => 'no',
            ),
            'everypay_tokenization' => array(
                'title' => pll__('Tokenization'),
                'type' => 'checkbox',
                'label' => pll__('Enable Tokenization'),
                'description' => pll__('Allow your customers to save their cards.'),
                'desc_tip' => true,
                'default' => 'no',
            ),
            'everypay_maximum_installments' => array(
                'title' => pll__('Everypay Max Installments'),
                'type' => 'hidden',
                'label' => pll__('Installments'),
                'description' => pll__('Configure the amount of installments offered depending on the amount of the order. Leave emprt if no installments are offered at all'),
                'desc_tip' => pll__('Choose the maximum number for installments offered'),
                'default' => '',
            ),
        );
    }

    function everypay_get_installments($total, $ins)
    {
        $inst = htmlspecialchars_decode($ins);
        if ($inst) {
            $installments = json_decode($inst, true);
            $max_installments = 0;
            foreach ($installments as $i) {
                if($total >= $this->format_the_amount($i['from']) && $total <= $this->format_the_amount($i['to']) && intval($i['max']) > $max_installments)
                    $max_installments = intval($i['max']);
            }
            return $max_installments;
        }
        return 0;
    }


    /**
     * Open everypay iframe
     *
     * @return string
     */
    public function show_everypay_iframe()
    {
        global $woocommerce, $current_user;

        $total = $woocommerce->cart->total;
        $address_1 = get_user_meta( $current_user->ID, 'billing_address_1', true );

        // fix decimals for new woocommerce
        if(gettype($total) != "string"){
            if (round($total, 0) == $total){
                $total = $total * 100;
            }
        } else {
            $total = $this->format_the_amount($total);
        }

        $EVDATA = array(
            'description' => get_bloginfo('name') . ' ' . strip_tags(html_entity_decode(wc_price($total / 100))),
            'amount' => $total,
            'sandbox' => (EVERYPAY_SANDBOX ? 1 : 0),
            'pk' => $this->everypayPublicKey,
            'max_installments' => $this->everypay_get_installments($total, $this->everypayMaxInstallments),
            'billing_address' => $address_1
        );

        // use english if the language is not greek (default)
        if(substr(get_locale(), 0, 2) != "el")
            $EVDATA['locale'] = "en";

        $response_data = array(
            'result' => 'failure',
            'refresh' => 'false',
            'messages' => "<div ><script type=\"text/javascript\">"
                . "EVDATA = " . json_encode($EVDATA) . ";"
                . "load_everypay();</script></div>",
        );

        return json_encode($response_data);
    }


    private function format_the_amount($amount)
    {
        $tmp = intval(preg_replace("/[^0-9]/", '', (string) $amount));

        // check if number had no decimals
        if($tmp == intval($amount)){
            return $tmp * 100;
        }

        return $tmp;
    }

    /*
     *  Process the payment
     *
     * @param int $order_id
     */
    public function process_payment($order_id)
    {
        if (isset($_POST['everypayToken']) && !empty($_POST['everypayToken'])) {
            $token = sanitize_text_field($_POST['everypayToken']);
        } else {
            $token = 0;
        }

        if (!$token) {
            echo $this->show_everypay_iframe();
            exit;
        }

        global $error;

        try {

            $wc_order = new WC_Order($order_id);
            $grand_total = $wc_order->order_total;
            $amount = $this->format_the_amount($grand_total);

            $description = get_bloginfo('name') . ' / '
                . pll__('Order') . ' #' . $wc_order->get_order_number() . ' - '
                . number_format($amount/100, 2, ',', '.') . '€';

            $data = array(
                'description' => $description,
                'amount' => $amount,
                'payee_email' => $wc_order->billing_email,
                'payee_phone' => $wc_order->billing_phone,
                'token' => $token,
                'max_installments' => $this->everypay_get_installments($amount, $this->everypayMaxInstallments),
            );

            // --------------- Enable for debug -------------
            /* $error = var_export($data, true);
              wc_add_notice($error, $notice_type = 'error');
              WC()->session->reload_checkout = true;
              return; */

            WC_Everypay_Api::setApiKey($this->everypaySecretKey);
            $response = WC_Everypay_Api::addPayment($data);

            if (isset($response['body']['error'])) {
                $error = $response['body']['error']['message'];

                $trimmed = trim($this->get_option('everypay_error_message'));
                if (!empty($trimmed)) {
                    $error = $this->get_option('everypay_error_message');
                }

                wc_add_notice($error, $notice_type = 'error');

                WC()->session->reload_checkout = true;
            } else {
                //wc_add_notice('Payment success!');

                $dt = new DateTime("Now");
                $timestamp = $dt->format('Y-m-d H:i:s e');
                $token = $response['body']['token'];

                $wc_order->add_order_note(pll__('Everypay payment completed at-' .
                    $timestamp . '-with Token ID=' . $token));

                $wc_order->payment_complete($token);
                $wc_order->get_order();
                add_post_meta($order_id, 'token', $token);

                WC()->cart->empty_cart();

                $responseData = array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($wc_order)
                );

                return $responseData;
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $trimmed = trim($this->get_option('everypay_error_message'));
            if (!empty($trimmed)) {
                $error = $this->get_option('everypay_error_message');
            }

            wc_add_notice($error, $notice_type = 'error');
            WC()->session->reload_checkout = true;
        }

        return;
    }

    /**
     * Refund
     *
     * @param type $order_id
     * @param type $amount
     * @param type $reason
     * @return boolean
     */
    public function process_refund($order_id, $amount = NULL, $reason = '')
    {
        if (!$amount) {
            return false;
        }

        try {
            $params = array(
                'amount' => preg_replace("/[^0-9]/", '', number_format($amount, 2)),
                'description' => $reason
            );

            $token = get_post_meta( (int) $order_id, 'token', true);

            WC_Everypay_Api::setApiKey($this->everypaySecretKey);
            $refund = WC_Everypay_Api::refundPayment($token, $params);

            if (!isset($refund['body']['error'])) {
                $dt = new DateTime("Now");
                $timestamp = $dt->format('Y-m-d H:i:s e');
                $refToken = $refund['body']['token'];

                $wc_order = new WC_Order($order_id);
                $wc_order->add_order_note(pll__('Everypay Refund completed at-' .
                    $timestamp . '-with Refund Token=' . $refToken));

                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}