<?php
/**
 * Plugin Name: EveryPay Payment Gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/everypay-woocommerce-addon/
 * Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Everypay.
 * Version: 3.8
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
            define('EVERYPAY_PLUGIN_VERSION', '3.8');
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
add_action('wp_ajax_everypay_create_iris_session', 'everypay_create_iris_session');
add_action('wp_ajax_nopriv_everypay_create_iris_session', 'everypay_create_iris_session');
add_action('init', 'everypay_register_rewrite_rules');
add_filter('query_vars', 'everypay_add_query_vars');
add_action('template_redirect', 'everypay_maybe_handle_iris_callback');
add_action('woocommerce_before_checkout_form', 'everypay_print_iris_error_notice', 5);

function everypay_set_iris_error_notice($message)
{
	if (function_exists('WC') && WC()->session) {
		WC()->session->set('everypay_iris_error', $message);
	}
}

function everypay_clear_iris_error_notice()
{
	if (function_exists('WC') && WC()->session) {
		WC()->session->set('everypay_iris_error', null);
	}
}

function everypay_print_iris_error_notice()
{
	if (!function_exists('wc_print_notice')) {
		return;
	}

	$message = '';

	if (function_exists('WC') && WC()->session) {
		$session_message = WC()->session->get('everypay_iris_error');
		if (!empty($session_message)) {
			$message = $session_message;
			everypay_clear_iris_error_notice();
		}
	}

	if (!$message && isset($_GET['everypay-iris-error'])) {
		$message = sanitize_text_field(wp_unslash($_GET['everypay-iris-error']));
	}

	if (!empty($message)) {
		wc_print_notice(esc_html($message), 'error');
	}
}

function everypay_find_order_by_iris_reference(string $token = '', string $md = '')
{
	if (!function_exists('wc_get_orders')) {
		return null;
	}

	$statuses = array_keys(wc_get_order_statuses());

	if ($token) {
		$orders = wc_get_orders(array(
			'limit' => 1,
			'meta_key' => 'everypay_source_token',
			'meta_value' => $token,
			'post_status' => $statuses,
		));

		if (!empty($orders)) {
			return $orders[0];
		}

		$orders = wc_get_orders(array(
			'limit' => 1,
			'meta_key' => 'everypay_payment_token',
			'meta_value' => $token,
			'post_status' => $statuses,
		));

		if (!empty($orders)) {
			return $orders[0];
		}
	}

	if ($md) {
		$orders = wc_get_orders(array(
			'limit' => 1,
			'meta_key' => 'everypay_iris_md',
			'meta_value' => $md,
			'post_status' => $statuses,
		));

		if (!empty($orders)) {
			return $orders[0];
		}
	}

	return null;
}

function register_apple_pay_merchant_domain()
{
	if (!isset( $_POST['_nonce'] ) || !wp_verify_nonce( $_POST['_nonce'], 'everypay_register_domain_nonce' )) {
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

function everypay_register_rewrite_rules()
{
	add_rewrite_rule('^everypay-iris-callback/?$', 'index.php?everypay_iris_callback=1', 'top');
}

function everypay_add_query_vars($vars)
{
	$vars[] = 'everypay_iris_callback';
	return $vars;
}

function everypay_maybe_handle_iris_callback()
{
	if (!get_query_var('everypay_iris_callback')) {
		return;
	}

	everypay_handle_iris_callback_request();
	exit;
}

function everypay_handle_iris_callback_request()
{
	nocache_headers();

	if ('GET' === $_SERVER['REQUEST_METHOD']) {
		$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
		$md = isset($_GET['md']) ? sanitize_text_field(wp_unslash($_GET['md'])) : '';

		$order = everypay_find_order_by_iris_reference($token, $md);

		if ($order instanceof WC_Order) {
			$redirect_url = $order->get_checkout_order_received_url();
			if (!empty($redirect_url)) {
				wp_safe_redirect($redirect_url);
				exit;
			}
		}

		status_header(200);
		header('Content-Type: application/json; charset=utf-8');
		echo wp_json_encode(['success' => true]);
		return;
	}

	if ('POST' !== $_SERVER['REQUEST_METHOD']) {
		header('Content-Type: application/json; charset=utf-8');
		status_header(405);
		echo wp_json_encode(['success' => false, 'message' => 'Method Not Allowed']);
		return;
	}

	$post_data = array();

	foreach (wp_unslash($_POST) as $key => $value) {
		$post_data[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
	}

	$hash_raw = isset($post_data['hash']) ? $post_data['hash'] : '';

	if (empty($hash_raw)) {
		status_header(400);
		echo wp_json_encode(['success' => false, 'message' => 'Missing hash.']);
		return;
	}

	$secret_key = '';
	$gateway_instance = null;
	if (function_exists('WC')) {
		$gateways_handler = WC()->payment_gateways();
		$available_gateways = $gateways_handler ? $gateways_handler->payment_gateways() : array();
		if (isset($available_gateways['everypay']) && $available_gateways['everypay'] instanceof WC_Everypay_Gateway) {
			/** @var WC_Everypay_Gateway $gateway_instance */
			$gateway_instance = $available_gateways['everypay'];
			$secret_key = $gateway_instance->get_secret_key();
		}
	}

	if (empty($secret_key)) {
		$gateway_settings = get_option('woocommerce_everypay_settings', array());
		if (isset($gateway_settings['everypaySecretKey'])) {
			$secret_key = $gateway_settings['everypaySecretKey'];
		}
	}

	if (empty($secret_key)) {
		status_header(500);
		header('Content-Type: application/json; charset=utf-8');
		echo wp_json_encode(['success' => false, 'message' => 'EveryPay secret key is not configured.']);
		return;
	}

	$decoded_hash = base64_decode($hash_raw, true);
	if ($decoded_hash === false || strpos($decoded_hash, '|') === false) {
		status_header(400);
		header('Content-Type: application/json; charset=utf-8');
		echo wp_json_encode(['success' => false, 'message' => 'Invalid hash payload.']);
		return;
	}

	list($provided_hash, $payload_json) = explode('|', $decoded_hash, 2);

	$calculated_hash = hash_hmac('sha256', $payload_json, $secret_key);

	if (!hash_equals($provided_hash, $calculated_hash)) {
		status_header(400);
		header('Content-Type: application/json; charset=utf-8');
		echo wp_json_encode(['success' => false, 'message' => 'Hash verification failed.']);
		return;
	}

	$payload_array = json_decode($payload_json, true);
	if (!is_array($payload_array)) {
		$payload_array = array();
	}

	$data_to_store = array(
		'payload' => $payload_array,
		'post' => $post_data,
		'verified' => true,
	);

	try {
		if (!class_exists('WC_Everypay_Repository')) {
			require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-repository.php";
		}
		$repository = new WC_Everypay_Repository();
		$repository->save_logs('iris_callback', wp_json_encode($data_to_store));
	} catch (Exception $e) {
		// Failing to log should not block the callback.
	}

	$token = isset($payload_array['token']) ? sanitize_text_field($payload_array['token']) : (isset($post_data['token']) ? sanitize_text_field($post_data['token']) : '');
	$md = isset($payload_array['md']) ? sanitize_text_field($payload_array['md']) : (isset($post_data['md']) ? sanitize_text_field($post_data['md']) : '');
	$error_status = isset($payload_array['error_status']) ? sanitize_text_field($payload_array['error_status']) : (isset($post_data['error_status']) ? sanitize_text_field($post_data['error_status']) : '');
	$error_message = isset($payload_array['error_message']) ? sanitize_text_field($payload_array['error_message']) : (isset($post_data['error_message']) ? sanitize_text_field($post_data['error_message']) : '');
	$has_error = !empty($error_status) || (!empty($error_message) && empty($token));
	$order = null;

	$payment_token_created = '';

	if ($token && function_exists('wc_get_orders')) {
		$order = everypay_find_order_by_iris_reference($token, $md);

		if ($order instanceof WC_Order) {
			if ($md && !$order->get_meta('everypay_iris_md')) {
				$order->update_meta_data('everypay_iris_md', $md);
			}
			if ($token && !$order->get_meta('everypay_source_token')) {
				$order->update_meta_data('everypay_source_token', $token);
			}
			if (!$has_error && !$order->is_paid()) {
				try {
					if (!class_exists('WC_Everypay_Helpers')) {
						require_once plugin_dir_path(__FILE__) . 'includes/class-wc-everypay-helpers.php';
					}

					$helpers = new WC_Everypay_Helpers();
					$amount = $helpers->format_amount($order->get_total());

					if (empty($amount)) {
						throw new Exception('IRIS payment failed: invalid order amount.');
					}

					$store_name = sanitize_text_field(get_bloginfo('name'));
					if (defined('EVERYPAY_SANDBOX') && EVERYPAY_SANDBOX) {
						if (strpos($store_name, '.') !== false) {
							$store_name = substr($store_name, 0, strpos($store_name, '.'));
						}
						if (empty($store_name)) {
							$store_name = 'shop';
						}
					}
					if (empty($store_name)) {
						$store_name = 'Shop';
					}

					$description = $store_name . ' / ' . 'Order' . ' #' . $order->get_order_number() . ' - ' . number_format($amount / 100, 2, ',', '.') . '€';
					if (empty($description)) {
						throw new Exception('IRIS payment failed: missing payment description.');
					}

					$payload = array(
						'amount' => $amount,
						'description' => $description,
						'token' => $token,
					);

					$billing_email = $order->get_billing_email();
					if (!empty($billing_email)) {
						$payload['payee_email'] = sanitize_text_field($billing_email);
					}

					$billing_phone = $order->get_billing_phone();
					if (!empty($billing_phone)) {
						$payload['payee_phone'] = preg_replace('/[^0-9+]/', '', $billing_phone);
					}

					$order_currency = $order->get_currency();
					if (!empty($order_currency)) {
						$payload['currency'] = strtoupper(sanitize_text_field($order_currency));
					}

					$billing_country = $order->get_billing_country();
					if (!empty($billing_country)) {
						$payload['country'] = strtoupper(sanitize_text_field($billing_country));
					} elseif (function_exists('WC') && WC()->countries) {
						$payload['country'] = strtoupper(sanitize_text_field(WC()->countries->get_base_country()));
					}

					if (!class_exists('WC_Everypay_Api')) {
						require_once plugin_dir_path(__FILE__) . 'includes/class-wc-everypay-api.php';
					}
					WC_Everypay_Api::setApiKey($secret_key);
					if (defined('EVERYPAY_SANDBOX') && EVERYPAY_SANDBOX) {
						WC_Everypay_Api::setTestMode();
					}

					$payment_response = WC_Everypay_Api::addPayment($payload);

					try {
						if (!class_exists('WC_Everypay_Repository')) {
							require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-repository.php";
						}
						(new WC_Everypay_Repository())->save_logs(
							'iris_payment',
							wp_json_encode(
								array(
									'request' => $payload,
									'status' => $payment_response['status'] ?? '',
								)
							)
						);
					} catch (Exception $log_exception) {
						// Logging failure should not block the payment.
					}

					if (isset($payment_response['body']['token'])) {
						$payment_token_created = $payment_response['body']['token'];
					} elseif (isset($payment_response['body']['payment']['token'])) {
						$payment_token_created = $payment_response['body']['payment']['token'];
					}

					if (empty($payment_token_created)) {
						throw new Exception('IRIS payment failed. Please try another payment method.');
					}

					$order->update_meta_data('everypay_payment_method', 'iris');
					$order->update_meta_data('everypay_payment_token', $payment_token_created);
				} catch (Exception $payment_exception) {
					$has_error = true;
					$error_message = $payment_exception->getMessage();

					try {
						if (!class_exists('WC_Everypay_Repository')) {
							require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-repository.php";
						}
						(new WC_Everypay_Repository())->save_logs('iris_payment_error', $payment_exception->getMessage());
					} catch (Exception $log_exception) {
						// Ignore logging errors.
					}
				}
			}

			if ($has_error) {
				$order->update_status('failed', 'Everypay IRIS callback error: ' . $error_message);
			} else {
				if (!$order->is_paid()) {
					$existing_payment_token = !empty($payment_token_created) ? $payment_token_created : $order->get_meta('everypay_payment_token');
					if (!empty($existing_payment_token) && strpos($existing_payment_token, 'src_') !== 0) {
						$order->payment_complete($existing_payment_token);
					} else {
						$has_error = true;
						if (empty($error_message)) {
							$error_message = __('IRIS payment failed. Please try another payment method.', 'everypay');
						}
						$order->update_status('failed', 'Everypay IRIS callback error: ' . $error_message);
					}
				}

				if (!$has_error) {
					$order->add_order_note('Everypay IRIS callback received.');
					$order->set_payment_method_title(__('IRIS Bank Payment', 'everypay'));
					$order->update_meta_data('everypay_payment_method', 'iris');
				}
			}
			$order->save();

			if (!$has_error) {
				everypay_clear_iris_error_notice();
				$redirect_url = $order->get_checkout_order_received_url();
				if (!empty($redirect_url)) {
					wp_safe_redirect($redirect_url, 303);
					exit;
				}
			}
		}
	}

	if (!$has_error && (!isset($order) || !$order instanceof WC_Order)) {
	$message = __('IRIS transaction could not be matched to an order. Please contact support or try again.', 'everypay');
	$message = sanitize_text_field($message);
	if (function_exists('wc_add_notice')) {
		wc_add_notice(esc_html($message), 'error');
	}
	everypay_set_iris_error_notice($message);
	$redirect_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
	$redirect_url = add_query_arg('everypay-iris-error', rawurlencode($message), $redirect_url);
		wp_safe_redirect($redirect_url, 303);
		exit;
	}

	if ($has_error) {
	$message = $error_message ?: __('IRIS payment failed. Please try another payment method.', 'everypay');
	$message = sanitize_text_field($message);
	$redirect_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
	if (function_exists('wc_add_notice')) {
		wc_add_notice(esc_html($message), 'error');
	}
	everypay_set_iris_error_notice($message);
	$redirect_url = add_query_arg('everypay-iris-error', rawurlencode($message), $redirect_url);
		wp_safe_redirect($redirect_url, 303);
		exit;
	}

	status_header(200);
	header('Content-Type: application/json; charset=utf-8');
	echo wp_json_encode(['success' => true]);
}
function everypay_create_iris_session()
{
	try {
		if (!isset($_POST['_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_nonce'])), 'everypay_create_iris_session')) {
			wp_send_json_error(['message' => 'Invalid nonce.']);
			wp_die();
		}

		if (!function_exists('WC')) {
			wp_send_json_error(['message' => 'WooCommerce is required.']);
			wp_die();
		}

		$gateways_handler = WC()->payment_gateways();
		$available_gateways = $gateways_handler ? $gateways_handler->payment_gateways() : array();

		if (!isset($available_gateways['everypay']) || !$available_gateways['everypay'] instanceof WC_Everypay_Gateway) {
			wp_send_json_error(['message' => 'Payment gateway not found.']);
			wp_die();
		}

		/** @var WC_Everypay_Gateway $gateway */
		$gateway = $available_gateways['everypay'];

		if (!$gateway->is_iris_enabled()) {
			wp_send_json_error(['message' => 'IRIS is not enabled.']);
			wp_die();
		}

		$helpers = new WC_Everypay_Helpers();
		$cart_total = WC()->cart ? WC()->cart->total : 0;
		$amount = $helpers->format_amount($cart_total);

		if (empty($amount) && !empty($_POST['amount'])) {
			$amount = intval(sanitize_text_field(wp_unslash($_POST['amount'])));
		}

		if (empty($amount)) {
			wp_send_json_error(['message' => 'Unable to determine order total for IRIS session.']);
			wp_die();
		}

		$currency = isset($_POST['currency'])
			? strtoupper(sanitize_text_field(wp_unslash($_POST['currency'])))
			: get_woocommerce_currency();

		$callback_url = $gateway->get_iris_callback_url();
		if (empty($callback_url)) {
			wp_send_json_error(['message' => 'IRIS session failed: callback URL is missing.']);
			wp_die();
		}

		$country = $gateway->get_iris_country();
		if (empty($country)) {
			$country = WC()->countries->get_base_country();
		}

		$params = array(
			'amount' => $amount,
			'currency' => $currency,
			'country' => $country,
			'callback_url' => $callback_url,
		);

		$md_reference = method_exists($gateway, 'get_iris_md_reference') ? $gateway->get_iris_md_reference() : '';

		if (!empty($md_reference)) {
			$params['md'] = $md_reference;
		}

		if (!empty($_POST['uuid'])) {
			$params['uuid'] = sanitize_text_field(wp_unslash($_POST['uuid']));
		}

		if (!empty($_POST['md'])) {
			$params['md'] = sanitize_text_field(wp_unslash($_POST['md']));
		}

		$secret_key = $gateway->get_secret_key();
		if (empty($secret_key)) {
			wp_send_json_error(['message' => 'IRIS session failed: secret key is missing.']);
			wp_die();
		}

		WC_Everypay_Api::setApiKey($secret_key);
		if (defined('EVERYPAY_SANDBOX') && EVERYPAY_SANDBOX) {
			WC_Everypay_Api::setTestMode();
		}

		$response = WC_Everypay_Api::createIrisSession($params);

		(new WC_Everypay_Repository())->save_logs(
			'iris_session',
			wp_json_encode(
				array(
					'request' => $params,
					'status' => $response['status'] ?? '',
				)
			)
		);

		if (!isset($response['status']) || $response['status'] < 200 || $response['status'] >= 300) {
			$message = isset($response['body']['error']['message']) ? $response['body']['error']['message'] : 'IRIS session failed.';
			wp_send_json_error(['message' => $message]);
			wp_die();
		}

		$data = array(
			'signature' => $response['body']['signature'] ?? '',
			'uuid' => $response['body']['uuid'] ?? ($params['uuid'] ?? ''),
		);

		wp_send_json_success($data);
	} catch (Exception $exception) {
		(new WC_Everypay_Repository())->save_logs('iris_session_error', $exception->getMessage());
		wp_send_json_error(['message' => 'IRIS session error. ' . $exception->getMessage()]);
	} finally {
		wp_die();
	}
}

function install() {
    require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-repository.php";
    $repository = new WC_Everypay_Repository();
	$repository->create_tokenization_table();
	$repository->create_logging_table();
	everypay_register_rewrite_rules();
	flush_rewrite_rules();
}

function uninstall() {
	require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-repository.php";
	$repository = new WC_Everypay_Repository();
	$repository->drop_tokenization_table();
	$repository->drop_logging_table();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'install' );
register_deactivation_hook( __FILE__, 'uninstall' );
