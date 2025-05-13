<?php
/**
 * Plugin Name: EveryPay Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/everypay-woocommerce-addon/
 * Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Everypay.
 * Version: 3.7
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
            define('EVERYPAY_PLUGIN_VERSION', '3.7');
            define('EVERYPAY_IMAGES_URL', plugins_url('images/', __FILE__));
            define('EVERYPAY_JS_URL', plugins_url('assets/js/', __FILE__));
            define('EVERYPAY_CSS_URL', plugins_url('assets/css/', __FILE__));

	        require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-helpers.php";
            require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-api.php";
	        require_once dirname( __FILE__ ) . '/includes/class-wc-everypay-renderer.php';
	        require_once dirname( __FILE__ ) . '/includes/admin/class-wc-everypay-admin.php';
	        require_once dirname( __FILE__ ) . '/includes/class-wc-everypay-repository.php';
	        require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-everypay-gateway.php';
            require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-everypay-tokenization.php';

        }


    }

    new WC_Everypay();
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );


add_action('plugins_loaded', 'everypay_init');

add_action('wp_ajax_register_apple_pay_merchant_domain', 'register_apple_pay_merchant_domain');

function register_apple_pay_merchant_domain()
{
	if (!isset( $_POST['_nonce'] ) || ! wp_verify_nonce( $_POST['_nonce'], 'everypay_register_domain_nonce' ) ) {
		wp_send_json_error( [ 'message' => 'Invalid nonce.' ] );
		wp_die();
	}

	$merchant_domain = isset( $_POST['merchantDomain'] ) ? sanitize_url( $_POST['merchantDomain'] ) : '';
	if (empty($merchant_domain)) {
		wp_send_json_error( [ 'message' => 'Merchant domain is required.' ] );
		wp_die();
	}

	$parsed_merchant_domain = parse_url($merchant_domain);
	if (!isset($parsed_merchant_domain['host'])) {
		wp_send_json_error( [ 'message' => 'Merchant domain is invalid.' ] );
		wp_die();
	}

	$merchant_domain = $parsed_merchant_domain['host'];

	$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
	if (!isset($payment_gateways['everypay'])) {
		wp_send_json_error( [ 'message' => 'Payment gateway not found.' ] );
		wp_die();
	}

	$success = $payment_gateways['everypay']->register_apple_pay_merchant_domain($merchant_domain);

	if ($success) {
		$message = EVERYPAY_SANDBOX
			? 'Domain registered successfully in sandbox.'
			: 'Domain registered successfully in production.';
		wp_send_json_success(['message' => $message]);
	} else {
		$message = EVERYPAY_SANDBOX
			? 'Failed to register the domain in sandbox.'
			: 'Failed to register the domain in production.';
		wp_send_json_error(['message' => $message]);
	}

	wp_die();
}

function install() {
    require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-repository.php";
    $repository = new WC_Everypay_Repository();
	$repository->create_tokenization_table();
	$repository->create_logging_table();
}

function uninstall() {
	require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-repository.php";
	$repository = new WC_Everypay_Repository();
	$repository->drop_tokenization_table();
	$repository->drop_logging_table();
}

register_activation_hook( __FILE__, 'install' );
register_deactivation_hook( __FILE__, 'uninstall' );