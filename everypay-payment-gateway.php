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

function everypay_recalculate_serialized_string_lengths($value)
{
	if (!is_string($value) || $value === '') {
		return $value;
	}

	return preg_replace_callback('/s:\d+:"(.*?)";/s', function ($matches) {
		return 's:' . strlen($matches[1]) . ':"' . $matches[1] . '";';
	}, $value);
}

function everypay_get_gateway_settings()
{
	$settings = get_option('woocommerce_everypay_settings', null);
	if (is_array($settings)) {
		return $settings;
	}

	global $wpdb;
	$option_name = 'woocommerce_everypay_settings';
	$raw_settings = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$option_name
		)
	);

	if (!is_string($raw_settings) || $raw_settings === '') {
		return array();
	}

	$repaired_settings = @unserialize(everypay_recalculate_serialized_string_lengths($raw_settings));
	if (!is_array($repaired_settings)) {
		return array();
	}

	update_option($option_name, $repaired_settings, false);

	return $repaired_settings;
}

function everypay_maybe_repair_gateway_settings_option()
{
	everypay_get_gateway_settings();
}

function debug($message, ...$params)
{
    static $stdout;

    if ($stdout === null) {
        $stdout = fopen('php://stdout', 'w');
    }

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = $trace[1] ?? [];

    // $file = $caller['file'] ?? 'n/a';
    $line = $caller['line'] ?? 'n/a';
    $class = $caller['class'] ?? '';
    $type = $caller['type'] ?? '';
    $function = $caller['function'] ?? '';

    $yellow = "\033[33m";
    $reset = "\033[0m";

    $location = sprintf(
        "[{$yellow}DEBUG@%s%s%s():%s{$reset}] ",
        $class,
        $type,
        $function,
        $line
    );

    if (count($params) === 1 && is_array($params[0])) {
        $params = $params[0];
    } else if (count($params) === 0 && is_array($message)) {
        $params = [$message];
        $message = null;
    }

    if ($message === null) {
        $message = '';
    }

    fwrite($stdout, $location . $message . PHP_EOL);

    foreach ($params as $param) {
        fwrite($stdout, var_export($param, true) . PHP_EOL);
    }
}

function everypay_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Everypay requires WooCommerce to be installed and active. You can download %s here.'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong><p/></div>';
}

function everypay_init()
{
	everypay_maybe_repair_gateway_settings_option();

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
add_action('before_woocommerce_init', 'everypay_declare_blocks_compatibility');
add_action('woocommerce_blocks_payment_method_type_registration', 'everypay_register_blocks_support');

add_action('wp_ajax_register_apple_pay_merchant_domain', 'register_apple_pay_merchant_domain');
add_action('wp_ajax_everypay_create_iris_session', 'everypay_create_iris_session');
add_action('wp_ajax_nopriv_everypay_create_iris_session', 'everypay_create_iris_session');
add_action('wp_ajax_everypay_iris_callback', 'everypay_handle_iris_callback_request');
add_action('wp_ajax_nopriv_everypay_iris_callback', 'everypay_handle_iris_callback_request');
add_action('parse_request', 'everypay_maybe_handle_iris_callback_route');
add_action('parse_request', 'everypay_maybe_handle_iris_webhook_route');
add_action('woocommerce_before_checkout_form', 'everypay_print_iris_error_notice', 5);

function everypay_declare_blocks_compatibility()
{
	if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
		'cart_checkout_blocks',
		__FILE__,
		true
	);
}

function everypay_register_blocks_support($payment_method_registry)
{
	if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		return;
	}

	require_once dirname( __FILE__ ) . '/includes/class-wc-everypay-blocks-support.php';

	if (!class_exists('WC_Everypay_Blocks_Support')) {
		return;
	}

	$payment_method_registry->register(new WC_Everypay_Blocks_Support());
}

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

function everypay_send_iris_json_response(bool $success, array $data = array(), int $status_code = 200)
{
	status_header($status_code);
	header('Content-Type: application/json; charset=utf-8');
	echo wp_json_encode(array_merge(array('success' => $success), $data));
	return;
}

function everypay_get_iris_callback_request_path(): string
{
	if (!class_exists('WC_Everypay_Gateway')) {
		return '/everypay-iris-callback';
	}

	$callback_url = WC_Everypay_Gateway::get_iris_callback_endpoint_url();
	$path = wp_parse_url($callback_url, PHP_URL_PATH);

	if (!is_string($path) || $path === '') {
		return '/everypay-iris-callback';
	}

	return untrailingslashit($path);
}

function everypay_get_iris_webhook_request_path(): string
{
	if (!class_exists('WC_Everypay_Gateway')) {
		return '/everypay-iris-webhook';
	}

	$webhook_url = WC_Everypay_Gateway::get_iris_webhook_endpoint_url();
	$path = wp_parse_url($webhook_url, PHP_URL_PATH);

	if (!is_string($path) || $path === '') {
		return '/everypay-iris-webhook';
	}

	return untrailingslashit($path);
}

function everypay_maybe_handle_iris_callback_route()
{
	if (empty($_SERVER['REQUEST_URI'])) {
		return;
	}

	$request_path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
	if (!is_string($request_path) || $request_path === '') {
		return;
	}

	if (untrailingslashit($request_path) !== everypay_get_iris_callback_request_path()) {
		return;
	}

	everypay_handle_iris_callback_request(true);
	exit;
}

function everypay_maybe_handle_iris_webhook_route()
{
	if (empty($_SERVER['REQUEST_URI'])) {
		return;
	}

	$request_path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH);
	if (!is_string($request_path) || $request_path === '') {
		return;
	}

	if (untrailingslashit($request_path) !== everypay_get_iris_webhook_request_path()) {
		return;
	}

	everypay_handle_iris_callback_request(false);
	exit;
}

function everypay_is_iris_enabled_in_settings(): bool
{
	$settings = everypay_get_gateway_settings();
	return ($settings['everypay_iris_enabled'] ?? 'no') === 'yes';
}

function everypay_is_sandbox_mode_enabled(): bool
{
	$settings = everypay_get_gateway_settings();
	return ($settings['everypay_sandbox'] ?? 'no') === 'yes';
}

function everypay_get_iris_reference(): string
{
	if (!function_exists('WC') || !WC()->session) {
		return '';
	}

	$reference = WC()->session->get('everypay_iris_md');
	if (empty($reference)) {
		$reference = function_exists('wp_generate_uuid4')
			? wp_generate_uuid4()
			: uniqid('iris_', true);
		WC()->session->set('everypay_iris_md', $reference);
	}

	return (string) $reference;
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


function everypay_handle_iris_callback_request(bool $redirect_to_order_received = true)
{
	nocache_headers();

	if ('GET' === $_SERVER['REQUEST_METHOD']) {
		$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
		$md = isset($_GET['md']) ? sanitize_text_field(wp_unslash($_GET['md'])) : '';

		$order = everypay_find_order_by_iris_reference($token, $md);

		if ($order instanceof WC_Order) {
			$redirect_url = $order->get_checkout_order_received_url();
			if ($redirect_to_order_received && !empty($redirect_url)) {
				wp_safe_redirect($redirect_url, 303);
				exit;
			}
		}

		status_header(200);
		header('Content-Type: application/json; charset=utf-8');
		echo wp_json_encode(['success' => true]);
		return;
	}

	if ('POST' !== $_SERVER['REQUEST_METHOD']) {
		everypay_send_iris_json_response(false, ['message' => 'Method Not Allowed'], 405);
		return;
	}

	$post_data = array();

	foreach (wp_unslash($_POST) as $key => $value) {
		$post_data[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
	}

	$hash_raw = isset($post_data['hash']) ? $post_data['hash'] : '';

	if (empty($hash_raw)) {
		everypay_send_iris_json_response(false, ['message' => 'Missing hash.'], 400);
		return;
	}

	$gateway_settings = everypay_get_gateway_settings();
	$secret_key = isset($gateway_settings['everypaySecretKey'])
		? sanitize_text_field($gateway_settings['everypaySecretKey'])
		: '';

	if (empty($secret_key)) {
		everypay_send_iris_json_response(false, ['message' => 'EveryPay secret key is not configured.'], 500);
		return;
	}

	$decoded_hash = base64_decode($hash_raw, true);
	if ($decoded_hash === false || strpos($decoded_hash, '|') === false) {
		everypay_send_iris_json_response(false, ['message' => 'Invalid hash payload.'], 400);
		return;
	}

	list($provided_hash, $payload_json) = explode('|', $decoded_hash, 2);

	$calculated_hash = hash_hmac('sha256', $payload_json, $secret_key);

	if (!hash_equals($provided_hash, $calculated_hash)) {
		everypay_send_iris_json_response(false, ['message' => 'Hash verification failed.'], 400);
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
					if (everypay_is_sandbox_mode_enabled()) {
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
				if ($redirect_to_order_received && !empty($redirect_url)) {
					wp_safe_redirect($redirect_url, 303);
					exit;
				}

				everypay_send_iris_json_response(true, ['order_id' => $order->get_id()]);
				return;
			}
		}
	}

	if (!$has_error && (!isset($order) || !$order instanceof WC_Order)) {
		$message = __('IRIS transaction could not be matched to an order. Please contact support or try again.', 'everypay');
		$message = sanitize_text_field($message);
		everypay_send_iris_json_response(false, ['message' => $message], 404);
		return;
	}

	if ($has_error) {
		$message = $error_message ?: __('IRIS payment failed. Please try another payment method.', 'everypay');
		$message = sanitize_text_field($message);
		everypay_send_iris_json_response(false, ['message' => $message], 422);
		return;
	}

	everypay_send_iris_json_response(true);
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

		$gateway_settings = everypay_get_gateway_settings();

		if (!everypay_is_iris_enabled_in_settings()) {
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

		$callback_url = WC_Everypay_Gateway::get_iris_callback_endpoint_url();
		$webhook_url = WC_Everypay_Gateway::get_iris_webhook_endpoint_url();
		if (empty($callback_url)) {
			wp_send_json_error(['message' => 'IRIS session failed: callback URL is missing.']);
			wp_die();
		}
		if (empty($webhook_url)) {
			wp_send_json_error(['message' => 'IRIS session failed: webhook URL is missing.']);
			wp_die();
		}

		$country = !empty($gateway_settings['everypay_iris_country'])
			? strtoupper(sanitize_text_field($gateway_settings['everypay_iris_country']))
			: 'GR';
		if (empty($country)) {
			$country = WC()->countries->get_base_country();
		}

		$params = array(
			'amount' => $amount,
			'currency' => $currency,
			'country' => $country,
			'callback_url' => $callback_url,
			'webhook_url' => $webhook_url,
		);

		$md_reference = everypay_get_iris_reference();

		if (!empty($md_reference)) {
			$params['md'] = $md_reference;
		}

		if (!empty($_POST['uuid'])) {
			$params['uuid'] = sanitize_text_field(wp_unslash($_POST['uuid']));
		}

		if (!empty($_POST['md'])) {
			$params['md'] = sanitize_text_field(wp_unslash($_POST['md']));
		}

		$secret_key = isset($gateway_settings['everypaySecretKey'])
			? sanitize_text_field($gateway_settings['everypaySecretKey'])
			: '';
		if (empty($secret_key)) {
			wp_send_json_error(['message' => 'IRIS session failed: secret key is missing.']);
			wp_die();
		}

		WC_Everypay_Api::setApiKey($secret_key);
		if (everypay_is_sandbox_mode_enabled()) {
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
}

function uninstall() {
	require_once plugin_dir_path(__FILE__) . "includes/class-wc-everypay-repository.php";
	$repository = new WC_Everypay_Repository();
	$repository->drop_tokenization_table();
	$repository->drop_logging_table();
}

register_activation_hook( __FILE__, 'install' );
register_deactivation_hook( __FILE__, 'uninstall' );
