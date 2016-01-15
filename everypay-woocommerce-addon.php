<?php
/**
 * Plugin Name: Everypay WooCommerce Addon
 * Plugin URI: https://wordpress.org/plugins/everypay-woocommerce-addon/
 * Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Everypay.
 * Version: 1.0.2
 * Author: Everypay S.A.
 * Author URI: https://everypay.gr
 * License: GPL2
 */
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

function everypay_init()
{

    if (!class_exists('Everypay')) {
        include(plugin_dir_path(__FILE__) . "lib/Everypay.php");
    }

    function add_everypay_gateway_class($methods)
    {
        $methods[] = 'WC_Everypay_Gateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_everypay_gateway_class');

    /**
     * Show some notices on the admin
     *
     * @param array $messages
     */
    function show_everypay_notices($messages = array())
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
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
    add_action('admin_notices', 'show_everypay_notices');

    /**
     * Hide notice for this user
     *
     */
    function nag_everypay()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
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
    add_action('admin_init', 'nag_everypay');

    function add_everypay_var($vars)
    {
        $vars[] = "everypayToken";
        return $vars;
    }
    add_filter('query_vars', 'add_everypay_var');

    /**
     * Enqueue the js and css scripts if checkout page
     *
     * @return type
     */
    function add_everypay_js()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        //show only in checkout page
        if (get_the_ID() != get_option("woocommerce_checkout_page_id", 0)) {
            return;
        }

        wp_register_script('everypay_script', "https://button.everypay.gr/js/button.js");
        wp_enqueue_script('everypay_script');

        wp_register_script('everypay', plugins_url('js/everypay.js', __FILE__), array('jquery'), '1.1', true);
        wp_enqueue_script('everypay');
    }
    add_action('wp_enqueue_scripts', 'add_everypay_js');

    /**
     * Decide wether the plugin is ready to accept payments
     * according to the settings
     *
     */
    function everypay_payment_gateway_disable($available_gateways)
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        global $woocommerce;
        $evGway = new WC_Everypay_Gateway();

        if (isset($available_gateways['everypay']) && $evGway->has_issues()) {
            unset($available_gateways['everypay']);
        }

        return $available_gateways;
    }
    add_filter('woocommerce_available_payment_gateways', 'everypay_payment_gateway_disable');

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
                $this->icon = apply_filters('woocommerce_everypay_icon', plugins_url('images/credit-card.gif', __FILE__));
                $this->has_fields = true;
                $this->method_title = 'Everypay Cards Settings';
                $this->init_form_fields();
                $this->init_settings();

                $this->supports = array('products', 'refunds');
                $this->nag_name = 'everypay_nag_notice_' . date('W');
                $this->title = $this->get_option('everypay_title');
                $this->everypayPublicKey = $this->get_option('everypayPublicKey');
                $this->everypaySecretKey = $this->get_option('everypaySecretKey');
                $this->everypayMaxInstallments = $this->get_option('everypay_maxinstallments');
                $this->everypay_storecurrency = $this->get_option('everypay_storecurrency');
                $this->everypay_sandbox = $this->get_option('everypay_sandbox');
                $this->errors = array();

                if (!defined("EVERYPAY_SANDBOX")) {
                    define("EVERYPAY_SANDBOX", ($this->everypay_sandbox == 'yes' ? true : false));
                }

                if (EVERYPAY_SANDBOX) {
                    Everypay::setTestMode();
                }

                if (is_admin()) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                }
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
            /*
             * Config the admin options
             *
             */

            public function admin_options()
            {

                ?>
                <h3><?php _e('Everypay addon for Woocommerce', 'woocommerce'); ?></h3>
                <p><?php _e('Everypay is a company that provides a way for individuals and businesses to accept payments over the Internet.', 'woocommerce'); ?></p>
                <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
                </table>
                <?php
            }

            /**
             * Form fields
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
                    'everypay_storecurrency' => array(
                        'title' => __('Fund Receiving Currency'),
                        'type' => 'select',
                        'class' => 'select',
                        'css' => 'width: 350px;',
                        'desc_tip' => __('Select the currency in which you like to receive payment the currency that has (*) is unsupported on  American Express Cards.This is independent of store base currency so please update your cart price accordingly.', 'woocommerce'),
                        'options' => array('USD' => ' United States Dollar', 'EUR' => 'Euro'),
                        'description' => "<span style='color:red;'>Select the currency in which you like to receive payment the currency that has (*) is unsupported on  American Express Cards.This is independent of store base currency so please update your cart price accordingly.</span>",
                        'default' => 'EUR',
                    ),
                    'everypay_sandbox' => array(
                        'title' => __('Everypay Sandbox', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Sandbox mode (test)? ', 'woocommerce'),
                        'description' => __('If checked its in sanbox mode and if unchecked its in live mode', 'woocommerce'),
                        'desc_tip' => true,
                        'default' => 'no',
                    ),
                    'everypay_maxinstallments' => array(
                        'title' => __('Everypay Max Installments', 'woocommerce'),
                        'type' => 'number',
                        'max' => 12,
                        'min' => 0,
                        'label' => __('Installments', 'woocommerce'),
                        'description' => __('Choose the maximum number for installments offered', 'woocommerce'),
                        'desc_tip' => true,
                        'default' => '0',
                    ),/*
                      'everypay_cardtypes' => array(
                      'title' => __('Accepted Cards', 'woocommerce'),
                      'type' => 'multiselect',
                      'class' => 'chosen_select',
                      'css' => 'width: 350px;',
                      'desc_tip' => __('Select the card types to accept.', 'woocommerce'),
                      'options' => array(
                      'mastercard' => 'MasterCard',
                      'visa' => 'Visa',
                      'discover' => 'Discover',
                      'amex' => 'American Express',
                      'jcb' => 'JCB',
                      'dinersclub' => 'Dinners Club',
                      ),
                      'default' => array('mastercard', 'visa'),
                      ), */
                );
            }
            /* Get Card Types */

            function get_card_type($number)
            {
                $number = preg_replace('/[^\d]/', '', $number);
                if (preg_match('/^3[47][0-9]{13}$/', $number)) {
                    return 'amex';
                } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
                    return 'dinersclub';
                } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
                    return 'discover';
                } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
                    return 'jcb';
                } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
                    return 'mastercard';
                } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
                    return 'visa';
                } else {
                    return 'unknown';
                }
            }

            //Function to check IP
            function get_client_ip()
            {
                $ipaddress = '';
                if (getenv('HTTP_CLIENT_IP'))
                    $ipaddress = getenv('HTTP_CLIENT_IP');
                else if (getenv('HTTP_X_FORWARDED_FOR'))
                    $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
                else if (getenv('HTTP_X_FORWARDED'))
                    $ipaddress = getenv('HTTP_X_FORWARDED');
                else if (getenv('HTTP_FORWARDED_FOR'))
                    $ipaddress = getenv('HTTP_FORWARDED_FOR');
                else if (getenv('HTTP_FORWARDED'))
                    $ipaddress = getenv('HTTP_FORWARDED');
                else if (getenv('REMOTE_ADDR'))
                    $ipaddress = getenv('REMOTE_ADDR');
                else
                    $ipaddress = '0.0.0.0';
                return $ipaddress;
            }

            public function payment_fields()
            {
                global $woocommerce;

                $total = $woocommerce->cart->total;

                $description = get_bloginfo('name') . ' ' . strip_tags(html_entity_decode(wc_price($total)));
                $total = preg_replace('#[^\d.]#', '', $total * 100);
                $locale = 'el'; //explode('_', get_locale())[0];

                ?>
                <style>
                    .payment_box.payment_method_everypay{text-align: center; display: none !important}
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
                    'amount' => preg_replace('#[^\d.]#', '', $total * 100),
                    'locale' => 'el',
                    'sandbox' => (EVERYPAY_SANDBOX ? 1 : 0),
                    'callback' => "handleCallback",
                    'key' => $this->everypayPublicKey,
                    'max_installments' => $this->everypayMaxInstallments,
                );

                $responsedata = array(
                    'result' => 'failure',
                    'refresh' => 'true',
                    'messages' => "<div ><script type=\"text/javascript\">"
                    . "EVERYPAY_OPC_BUTTON = " . json_encode($EVDATA) . ";"
                    . "load_everypay();</script></div>",
                );
                return json_encode($responsedata);
            }

            /**
             * Used to proccess the payment
             *
             * @global type $error
             * @global type $woocommerce
             * @param int $order_id
             * @return
             *
             */
            public function process_payment($order_id)
            {

                //give command to open the modal box
                $token = get_query_var('everypayToken', 0);
                if (!$token) {
                    echo $this->show_button();
                    exit;
                }

                //continue to payment
                global $error, $current_user, $woocommerce;

                try {

                    $wc_order = new WC_Order($order_id);
                    $grand_total = $wc_order->order_total;
                    $amount = $grand_total * 100;

                    $total = $woocommerce->cart->total;

                    $description = get_bloginfo('name') . ' ' . strip_tags(html_entity_decode(wc_price($total)));
                    $total = preg_replace("/[^0-9]/", '', $total * 100);
                    $locale = explode('_', get_locale())[0];

                    $data = array(
                        'description' => get_bloginfo('name') . ' '
                        . 'Order #' . $wc_order->get_order_number() . ' - '
                        . strip_tags(html_entity_decode(wc_price($total / 100))),
                        'amount' => $total,
                        'payee_email' => $wc_order->billing_email,
                        'payee_phone' => $wc_order->billing_phone,
                        'token' => get_query_var('everypayToken', 0),
                        'max_installments' => $this->everypayMaxInstallments
                    );

                    Everypay::setApiKey($this->everypaySecretKey);
                    $response = Everypay::addPayment($data);

                    if (isset($response['body']['error'])) {
                        $error = $response['body']['error']['message'];
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
                        'amount' => preg_replace("/[^0-9]/", '', $amount),
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
}
add_action('plugins_loaded', 'everypay_init');
