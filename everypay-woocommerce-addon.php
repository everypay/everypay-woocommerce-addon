<?php
/**
 * Plugin Name: EveryPay Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/everypay-woocommerce-addon/
 * Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Everypay.
 * Version: 2
 * Author: Everypay S.A.
 * Author URI: https://everypay.gr
 * License: GPL2
 */

if (!defined('ABSPATH'))
    exit;


function everypay_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Everypay requires WooCommerce to be installed and active. You can download %s here.'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong><p/></div>';
}

function everypay_init()
{

    if ( ! class_exists( 'WooCommerce' ) || ! class_exists('WC_Payment_Gateway')) {
        add_action( 'admin_notices', 'everypay_woocommerce_missing_notice' );
        return;
    }


    class WC_Everypay {


        public function __construct() {

            $this->init();
            add_filter('woocommerce_payment_gateways', array($this, 'add_everypay_gateway'));
        }

        /**
         * Add Everypay to payment methods
         * @param array $methods
         */
        public function add_everypay_gateway($methods)
        {
            $methods[] = 'WC_Everypay_Gateway';
            return $methods;
        }

        public function init() {
            define('EVERYPAY_IMAGES_URL', plugins_url('images/', __FILE__));
            define('EVERYPAY_JS_URL', plugins_url('assets/js/', __FILE__));
            define('EVERYPAY_CSS_URL', plugins_url('assets/css/', __FILE__));

            require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-global-checks.php";
            require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-api.php";
            require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-everypay-gateway.php';

        }


    }

    new WC_Everypay();
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

if(!is_plugin_active( 'theme-translation-for-polylang/polylang-theme-translation.php' ) ) {
	function pll__($string){
		return __($string, 'woocommerce');
	}
	function pll_e($string){
		echo __($string, 'woocommerce');
	}
}

add_action('plugins_loaded', 'everypay_init');
