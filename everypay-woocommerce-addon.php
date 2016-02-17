<?php
/**
 * Plugin Name: Everypay WooCommerce Addon
 * Plugin URI: https://wordpress.org/plugins/everypay-woocommerce-addon/
 * Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Everypay.
 * Version: 1.3
 * Author: Everypay S.A.
 * Author URI: https://everypay.gr
 * License: GPL2
 */
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

function everypay_init()
{
    global $woocommerce;
    if (!isset($woocommerce)) {
        return;
    }

    if (!class_exists('Everypay')) {
        include(plugin_dir_path(__FILE__) . "lib/Everypay.php");
    }



    if (class_exists('WC_Payment_Gateway')) {

        class WC_Everypay_Gateway extends WC_Payment_Gateway
        {

            /**
             * The Public key
             *
             * @var type String
             */
            private $everypayPublicKey;

            /**
             * The secret key
             *
             * @var type String
             */
            private $everypaySecretKey;

            public function __construct()
            {
                $this->id = 'everypay';
                $this->icon = apply_filters('woocommerce_everypay_icon', plugins_url('images/pay-via-everypay.png', __FILE__));
                $this->has_fields = true;
                $this->method_title = 'Everypay Cards Settings';
                $this->init_form_fields();
                $this->init_settings();

                $this->supports = array('products', 'refunds');
                $this->nag_name = 'everypay_nag_notice_' . date('W');
                $this->title = $this->get_option('everypay_title');
                $this->description = $this->get_option('description');
                $this->everypayPublicKey = $this->get_option('everypayPublicKey');
                $this->everypaySecretKey = $this->get_option('everypaySecretKey');
                $this->everypayMaxInstallments = $this->get_option('everypay_maximum_installments');
                //$this->everypay_storecurrency = $this->get_option('everypay_storecurrency');
                $this->everypay_sandbox = $this->get_option('everypay_sandbox');
                $this->errors = array();

                $this->fee = 0;

                if (!defined("EVERYPAY_SANDBOX")) {
                    define("EVERYPAY_SANDBOX", ($this->everypay_sandbox == 'yes' ? true : false));
                }

                if (EVERYPAY_SANDBOX) {
                    Everypay::setTestMode();
                }

                // The hooks
                add_filter('woocommerce_available_payment_gateways', array($this, 'everypay_payment_gateway_disable'));
                add_filter('woocommerce_payment_gateways', array($this, 'add_everypay_gateway_class'));
                add_filter('query_vars', array($this, 'add_everypay_var'));
                add_action('wp_enqueue_scripts', array($this, 'add_everypay_js'));
                add_action('woocommerce_cart_calculate_fees', array($this, 'calculate_order_totals'));

                add_action('admin_init', array($this, 'nag_everypay'));
                add_action('admin_notices', array($this, 'show_everypay_notices'));
                add_action('admin_enqueue_scripts', array($this, 'load_everypay_admin'));
                if (is_admin()) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                }
            }

            /**
             * Decide wether the plugin is ready to accept payments
             * according to the settings
             *
             */
            public function everypay_payment_gateway_disable($available_gateways)
            {
                global $woocommerce;
                $evGway = new WC_Everypay_Gateway();

                if (isset($available_gateways['everypay']) && $evGway->has_issues()) {
                    unset($available_gateways['everypay']);
                }

                return $available_gateways;
            }

            /**
             * Add Everypay payment method
             * 
             * @param array $methods
             */
            public function add_everypay_gateway_class($methods)
            {
                $methods[] = 'WC_Everypay_Gateway';
                return $methods;
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
             * @param array $messages
             */
            public function show_everypay_notices($messages = array())
            {
                global $current_user;
                $user_id = $current_user->ID;
                $evGway = new WC_Everypay_Gateway();
                $nag_name = $evGway->nag_name;

                if (get_user_meta($user_id, $nag_name)) {
                    return;
                }
                $messages = $evGway->has_issues();

                if (!$messages) {
                    return;
                }

                $messages = array_merge(['Everypay plugin (Pay with card) status is off because: <br />'], $messages);
                $messages = implode('<br />', $messages);

                /* Check that the user hasn't already clicked to ignore the message */
                $messages = '<p>' . $messages . '</p>' .
                    sprintf(__('<a href="%1$s">OK. Hide This Notice</a>'), '?' . $nag_name . '=0');

                echo "<div class=\"update-nag\">$messages</div>";
            }

            /**
             * The scripts on admin tab
             */
            public function load_everypay_admin()
            {
                //page=wc-settings&tab=checkout&section=wc_everypay_gateway
                if (isset($_GET['page']) && $_GET['page'] == 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] == 'checkout' && isset($_GET['section']) && $_GET['section'] == 'wc_everypay_gateway') {
                    wp_register_script('everypay_script1', plugins_url('js/admin/mustache.min.js', __FILE__), array('jquery'), 'ver', true);
                    wp_enqueue_script('everypay_script1');

                    wp_register_script('everypay_script2', plugins_url('js/admin/everypay.js', __FILE__), array('jquery'), 'ver', true);
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

                wp_register_script('everypay_script', "https://button.everypay.gr/js/button.js");
                wp_enqueue_script('everypay_script');

                wp_register_script('everypay', plugins_url('js/everypay.js', __FILE__), array('jquery'), '1.1', true);
                wp_enqueue_script('everypay');
            }

            /**
             * Check wether the plugin is available for usage
             *
             *
             * @return boolean
             */
            public function has_issues()
            {
                $messages = array();

                if (!function_exists('curl_init')) {
                    $messages[] = 'Everypay plugin needs the CURL PHP extension.';
                }

                if (!function_exists('json_decode')) {
                    $messages[] = ' Everypay plugin needs the JSON PHP extension.';
                }

                if (empty($this->everypaySecretKey) || empty($this->everypayPublicKey)
                ) {
                    $messages[] = 'Please fill in your Everypay secret and public keys';
                }

                if (empty($messages)) {
                    return false;
                } else {
                    return $messages;
                }
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
                            $total += $tax;
                        }
                    }
                    if (count($cart->shipping_taxes)) {
                        foreach ($cart->shipping_taxes as $tax) {
                            $total += $tax;
                        }
                    }
                }

                $fee_name = 'Payment fee';
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

                /* if ($already_exists) {
                  return;
                  } */

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
                <h3><?php _e('Everypay addon for Woocommerce', 'woocommerce'); ?></h3>
                <p><?php _e('Everypay is a company that provides a way for individuals and businesses to accept payments over the Internet.', 'woocommerce'); ?></p>
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
                        'title' => __('Enable/Disable', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable Everypay', 'woocommerce'),
                        'default' => 'yes'
                    ),
                    'everypay_title' => array(
                        'title' => __('Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('Pay with Card', 'woocommerce'),
                        'desc_tip' => true,
                    ),
                    'description' => array(
                        'title' => __('Description', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%', 'woocommerce'),
                        'default' => __('Pay using your credit or debit card.', 'woocommerce'),
                        'desc_tip' => __('Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%'),
                    ),
                    'everypayPublicKey' => array(
                        'title' => __('Public Key', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This is the Public Key found in API Keys in Account Dashboard.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                        'placeholder' => 'Everypay Public Key'
                    ),
                    'everypaySecretKey' => array(
                        'title' => __('Secret Key', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This is the Secret Key found in API Keys in Account Dashboard.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                        'placeholder' => 'Everypay Secret Key'
                    ),
                    /* 'everypay_storecurrency' => array(
                      'title' => __('Fund Receiving Currency'),
                      'type' => 'select',
                      'class' => 'select',
                      'css' => 'width: 350px;',
                      'desc_tip' => __('Select the currency in which you like to receive payment the currency that has (*) is unsupported on  American Express Cards.This is independent of store base currency so please update your cart price accordingly.', 'woocommerce'),
                      'options' => array('EUR' => 'Euro'),
                      'description' => "<span style='color:red;'>Select the currency in which you like to receive payment the currency that has (*) is unsupported on  American Express Cards.This is independent of store base currency so please update your cart price accordingly.</span>",
                      'default' => 'EUR',
                      ), */
                    'everypay_sandbox' => array(
                        'title' => __('Everypay Sandbox', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Sandbox mode (test)? ', 'woocommerce'),
                        'description' => __('If checked its in sanbox mode and if unchecked its in live mode', 'woocommerce'),
                        'desc_tip' => true,
                        'default' => 'no',
                    ),
                    'everypay_fee_enabled' => array(
                        'title' => __('Apply Extra fee', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable', 'woocommerce'),
                        'description' => __('Allows the fee to be paid by the customer', 'woocommerce'),
                        'desc_tip' => true,
                        'default' => 'no',
                    ),
                    'everypay_fee_percent' => array(
                        'title' => __('Fee Percentage (%)', 'woocommerce'),
                        'type' => 'number',
                        'class' => 'everypay-fee-percentage',
                        'label' => __('Fee Percentage', 'woocommerce'),
                        'description' => __('Percentage of the fee that is applied from the gateway (Everypay). <br />Type 2.4 if your percentage is 2,4%. Leave 0 if no percentage fee is applied', 'woocommerce'),
                        'desc_tip' => __("Percentage of the fee that is applied from the gateway (Everypay). <br />Type 2.4 if your percentage is 2,4%. Leave 0 if no percentage fee is applied", 'woocommerce'),
                        'default' => '0',
                    ),
                    'everypay_fee_amount' => array(
                        'title' => __('Fee Amount (&euro;)', 'woocommerce'),
                        'type' => 'number',
                        'class' => 'everypay-fee-fixed',
                        'label' => __('Fee fixed amount', 'woocommerce'),
                        'description' => __("Fixed amount of the fee that is applied from the gateway (Everypay). <br />For eg. type 0.20&euro; etc. Leave 0 if no fixed amount fee is applied", 'woocommerce'),
                        'desc_tip' => __("Fixed amount of the fee that is applied from the gateway (Everypay). <br />For eg. type 0.20&euro; etc. Leave 0 if no fixed amount fee is applied", 'woocommerce'),
                        'default' => '0',
                    ),
                    'everypay_error_message' => array(
                        'title' => __('Error message', 'woocommerce'),
                        'type' => 'textarea',
                        'label' => __('Error message', 'woocommerce'),
                        'description' => __('Please type a universal error message to display to the customer. Leave empty to show the default error.', 'woocommerce'),
                        'desc_tip' => __('Please type a universal error message to display to the customer. Leave empty to show the default error.', 'woocommerce'),
                        'default' => '',
                    ),
                    'everypay_maximum_installments' => array(
                        'title' => __('Everypay Max Installments', 'woocommerce'),
                        'type' => 'hidden',
                        'label' => __('Installments', 'woocommerce'),
                        'description' => __('Configure the amount of installments offered depending on the amount of the order. Leave emprt if no installments are offered at all', 'woocommerce'),
                        'desc_tip' => __('Choose the maximum number for installments offered', 'woocommerce'),
                        'default' => '',
                    ),
                );
            }

            function everypay_get_installments($total, $ins)
            {
                $inst = htmlspecialchars_decode($ins);
                if ($inst) {
                    $installments = json_decode($inst, true);
                    $counter = 1;
                    $max = 0;
                    $max_installments = 0;
                    foreach ($installments as $i) {
                        if ($i['to'] > $max) {
                            $max = $i['to'];
                            $max_installments = $i['max'];
                        }

                        if (($counter == (count($installments)) && $total >= $max)) {
                            return $max_installments;
                        }

                        if ($total >= $i['from'] && $total <= $i['to']) {
                            return $i['max'];
                        }
                        $counter++;
                    }
                }
                return false;
            }

            /**
             * The html displayed right after the radio button option
             * 
             * @global type $woocommerce
             */
            public function payment_fields()
            {
                global $woocommerce;
                $amount = '';
                if ($this->description) :
                    $fee_id = 'payment-fee';
                    $fees = $woocommerce->cart->get_fees();
                    foreach ($fees as $i => $fee) {
                        if ($fee->id == $fee_id) {
                            $amount = wc_price($fee->amount);
                            break;
                        }
                    }

                    ?>
                    <p><?php echo str_replace('%AMOUNT%', $amount, $this->description); ?></p>
                <?php endif; ?>
                <style type="text/css">
                    .payment_method_everypay .button-holder{display:none}
                    .payment_box.payment_method_everypay{text-align: center;}
                    .payment_method_everypay img{
                        width: 100%;
                        height: auto;
                        max-height: none !important;
                        max-width: 222px;}
                    </style>
                    <div class="button-holder"></div>
                <?php
            }

            /**
             * Give command to open the button
             * 
             * @return string
             */
            public function show_button()
            {
                global $woocommerce;

                $total = $woocommerce->cart->total;
                $EVDATA = array(
                    'description' => get_bloginfo('name') . ' ' . strip_tags(html_entity_decode(wc_price($total))),
                    'amount' => preg_replace("/[^0-9]/", '', $total * 100),
                    'locale' => 'el',
                    'sandbox' => (EVERYPAY_SANDBOX ? 1 : 0),
                    'callback' => "handleCallback",
                    'key' => $this->everypayPublicKey,
                    'max_installments' => $this->everypay_get_installments($total, $this->everypayMaxInstallments),
                );

                $responsedata = array(
                    'result' => 'failure',
                    'refresh' => 'false',
                    'messages' => "<div ><script type=\"text/javascript\">"
                    . "EVERYPAY_OPC_BUTTON = " . json_encode($EVDATA) . ";"
                    . "load_everypay();</script></div>",
                );
                return json_encode($responsedata);
            }

            /**
             * Used to proccess the payment
             *
             * @param int $order_id
             * @return
             *
             */
            public function process_payment($order_id)
            {
                //give command to open the modal box
                $token = isset($_POST['everypayToken']) ? $_POST['everypayToken'] : 0;
                if (!$token) {
                    echo $this->show_button();
                    exit;
                }

                //continue to payment
                global $error, $current_user, $woocommerce;

                try {

                    $wc_order = new WC_Order($order_id);
                    $grand_total = $wc_order->order_total;
                    $amount = $grand_total;

                    $description = get_bloginfo('name') . ' / '
                        . __('Order') . ' #' . $wc_order->get_order_number() . ' - '
                        . strip_tags(html_entity_decode(wc_price($amount)));
                    $amount = preg_replace("/[^0-9]/", '', number_format($amount, 2));

                    $data = array(
                        'description' => $description,
                        'amount' => $amount,
                        'payee_email' => $wc_order->billing_email,
                        'payee_phone' => $wc_order->billing_phone,
                        'token' => $token,
                        'max_installments' => $this->everypay_get_installments($amount / 100, $this->everypayMaxInstallments),
                    );

                    // --------------- Enable for debug -------------
                    /* $error = var_export($data, true);                        
                      wc_add_notice($error, $notice_type = 'error');
                      WC()->session->reload_checkout = true;
                      return; */

                    Everypay::setApiKey($this->everypaySecretKey);
                    $response = Everypay::addPayment($data);

                    if (isset($response['body']['error'])) {
                        $error = $response['body']['error']['message'];

                        if (!empty(trim($this->get_option('everypay_error_message')))) {
                            $error = $this->get_option('everypay_error_message');
                        }

                        wc_add_notice($error, $notice_type = 'error');
                        WC()->session->reload_checkout = true;
                    } else {
                        //wc_add_notice('Payment success!');

                        $dt = new DateTime("Now");
                        $timestamp = $dt->format('Y-m-d H:i:s e');
                        $token = $response['body']['token'];

                        $wc_order->add_order_note(__('Everypay payment completed at-' .
                                $timestamp . '-with Token ID=' . $token, 'woocommerce'));

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
                    if (!empty(trim($this->get_option('everypay_error_message')))) {
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

                    $token = get_post_meta($order_id, 'token', true);

                    Everypay::setApiKey($this->everypaySecretKey);
                    $refund = Everypay::refundPayment($token, $params);

                    if (!isset($refund['body']['error'])) {
                        $dt = new DateTime("Now");
                        $timestamp = $dt->format('Y-m-d H:i:s e');
                        $refToken = $refund['body']['token'];

                        $wc_order = new WC_Order($order_id);
                        $wc_order->add_order_note(__('Everypay Refund completed at-' .
                                $timestamp . '-with Refund Token=' . $refToken, 'woocommerce'));

                        return true;
                    } else {
                        return false;
                    }
                } catch (\Exception $e) {
                    return false;
                }
            }
        }

    }

    new WC_Everypay_Gateway();
}
add_action('plugins_loaded', 'everypay_init');


