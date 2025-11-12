<?php

if (!defined('ABSPATH'))
	exit;


class WC_Everypay_Gateway extends WC_Payment_Gateway
{

	/**
	 * Everypay public key
	 * @var string
	 */
	private $everypayPublicKey;

	/**
	 * Everypay secret key
	 * @var string
	 */
	private $everypaySecretKey;

	/**
	 * Sandbox mode status
	 * @var string
	 */
	private $everypay_sandbox;

	/**
	 * @var string
	 */
	private $max_installments;

	private $tokenization_status;

	/**
	 * Everypay helpers
	 * @var object
	 */
	private $helpers;

	/**
	 * Everypay Renderer
	 * @var object
	 */
	private $renderer;

	/**
	 * @var string
	 */
	private $locale;

	/**
	 * IRIS integration toggle.
	 *
	 * @var bool
	 */
	private $iris_enabled = false;

	/**
	 * @var string
	 */
	private $iris_merchant_name = '';

	/**
	 * @var string
	 */
	private $iris_callback_url = '';

	/**
	 * @var string
	 */
	private $iris_country = '';

	/**
	 * @var string
	 */
	private $iris_md = '';

	public function __construct()
	{
		$this->id = 'everypay';
		$this->icon = apply_filters('woocommerce_everypay_icon', EVERYPAY_IMAGES_URL . '/everypay.png');
		$this->has_fields = true;
		$this->method_title = 'Everypay';
		$this->method_description = 'Everypay is a company that provides a way for individuals and businesses to accept payments over the Internet.';
		$this->init_form_fields();
		$this->init_settings();
		$this->supports = array('products', 'refunds');
		$this->title = esc_html($this->get_option('everypay_title'));
		$this->description = esc_html($this->get_option('description'));

		$this->everypayPublicKey = esc_html($this->get_option('everypayPublicKey'));
		$this->everypaySecretKey = esc_html($this->get_option('everypaySecretKey'));
		$this->max_installments = $this->get_option('everypay_maximum_installments');
		$this->tokenization_status = $this->get_option('everypay_tokenization');
		$this->everypay_sandbox = $this->get_option('everypay_sandbox');
		$this->iris_enabled = $this->get_option('everypay_iris_enabled') === 'yes';
		$this->iris_merchant_name = sanitize_text_field($this->get_option('everypay_iris_merchant_name'));
		$this->iris_callback_url = esc_url_raw(home_url('/everypay-iris-callback'));
		$this->iris_country = 'GR';

		if ($this->iris_enabled) {
			$this->prepare_iris_reference();
		}

		$this->helpers = new WC_Everypay_Helpers();
		$this->renderer = new WC_Everypay_Renderer($this->helpers, $this->everypayPublicKey, $this->tokenization_status);

		$isGooglePayEnabled = $this->get_option('everypay_googlepay_enabled');
		$googlePayCountryCode = $this->get_option('everypay_googlepay_country_code');
		$googlePayMerchantName = $this->get_option('everypay_googlepay_merchant_name');
		$googlePayMerchantUrl = $this->get_option('everypay_googlepay_merchant_url');
		$googlePayAllowedCardNetworks = $this->get_option('everypay_googlepay_allowed_card_networks');
		$googlePayAllowedAuthMethods = $this->get_option('everypay_googlepay_allowed_auth_methods');
		$googlePayButtonColor = $this->get_option('everypay_googlepay_button_color');

		if ($isGooglePayEnabled == 'yes') {
			$this->renderer->setGooglePay(
				$googlePayCountryCode,
				$googlePayMerchantName,
				$googlePayMerchantUrl,
				$googlePayAllowedCardNetworks,
				$googlePayAllowedAuthMethods,
				$googlePayButtonColor
			);
		}

		$isApplePayEnabled = $this->get_option('everypay_applepay_enabled');
		$applePayCountryCode = $this->get_option('everypay_applepay_country_code');
		$applePayMerchantName = $this->get_option('everypay_applepay_merchant_name');
		$applePayMerchantUrl = $this->get_option('everypay_applepay_merchant_url');
		$applePayAllowedCardNetworks = $this->get_option('everypay_applepay_allowed_card_networks');
		$applePayButtonColor = $this->get_option('everypay_applepay_button_color');

		if ($isApplePayEnabled == 'yes') {
			$this->renderer->setApplePay(
				$applePayCountryCode,
				$applePayMerchantName,
				$applePayMerchantUrl,
				$applePayAllowedCardNetworks,
				$applePayButtonColor
			);
		}

		if ($this->iris_enabled) {
			$this->renderer->setIrisConfiguration(array(
				'merchant_name' => $this->iris_merchant_name,
				'callback_url' => $this->iris_callback_url,
				'country' => $this->iris_country ?: 'GR',
				'md' => $this->iris_md,
			));
		}

		if (!defined("EVERYPAY_SANDBOX")) {
			define("EVERYPAY_SANDBOX", $this->everypay_sandbox == 'yes');
		}

		if (EVERYPAY_SANDBOX) {
			WC_Everypay_Api::setTestMode();
		}

		add_filter('woocommerce_available_payment_gateways', array($this, 'disable_everypay_if_keys_not_set'));
		add_filter('query_vars', array($this, 'add_everypay_var'));
		add_action('wp_enqueue_scripts', array($this, 'add_everypay_js'));

		if (is_admin()) {
			new WC_Everypay_Admin($this->everypayPublicKey, $this->everypaySecretKey);
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		}
	}


	private function create_payload($wc_order, $token)
	{
		$amount = $this->helpers->format_amount($wc_order->get_total());
		$billing_email = $wc_order->get_billing_email();
		$billing_phone = $wc_order->get_billing_phone();
		$description = get_bloginfo('name') . ' / '
			. 'Order' . ' #' . $wc_order->get_order_number() . ' - '
			. number_format($amount / 100, 2, ',', '.') . '€';

		if (
			(!$billing_email && !$billing_phone)
			|| !$description
			|| !$token
		) {
			throw new Exception('create_payload: invalid variable');
		}

		$installments = $this->helpers->calculate_installments(
			$amount,
			$this->max_installments
		);

		$payload = array(
			'description' => $description,
			'amount' => $amount,
			'payee_email' => $billing_email,
			'payee_phone' => $billing_phone,
			'max_installments' => $installments,
			'token' => $token,
		);

		if ($this->is_iris_token($token)) {
			unset($payload['token']);
			$payload['source_token'] = $token;
			$payload['payment_method'] = 'iris';
		}

		return $payload;
	}

	private function prepare_iris_reference(): void
	{
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}

		if (!function_exists('WC') || !WC()->session) {
			return;
		}

		$reference = WC()->session->get('everypay_iris_md');
		if (empty($reference)) {
			if (function_exists('wp_generate_uuid4')) {
				$reference = wp_generate_uuid4();
			} else {
				$reference = uniqid('iris_', true);
			}
			WC()->session->set('everypay_iris_md', $reference);
		}

		$this->iris_md = $reference;
	}

	private function is_iris_token($token)
	{
		return is_string($token) && strpos($token, 'src_') === 0;
	}

	/*
     *  Process the payment
     *
     * @param int $order_id
     */
	public function process_payment($order_id)
	{
		try {
			if (isset($_POST['delete_card']) && is_user_logged_in() && $this->tokenization_status == 'yes') {
				$user_id = get_current_user_id();
				(new WC_Everypay_Tokenization())->delete_card($_POST['delete_card'], $user_id);
				return array(
					'result' => 'success',
					'messages' => '<div class=""></div>'
				);
			}
			$wc_order = new WC_Order($order_id);

			if ($this->is_iris_enabled() && !empty($this->iris_md) && !$wc_order->get_meta('everypay_iris_md')) {
				$wc_order->update_meta_data('everypay_iris_md', $this->iris_md);
				$wc_order->save();
			}

			if (!isset($_POST['everypayToken']) || empty($_POST['everypayToken'])) {
				$this->renderer->render_iframe(WC()->cart->total, $this->max_installments);
				exit;
			}

			$token = sanitize_text_field($_POST['everypayToken']);
			unset($_POST['everypayToken']);
			WC_Everypay_Api::setApiKey($this->everypaySecretKey);
			$is_iris_payment = $this->is_iris_token($token);

			if ($is_iris_payment) {
				$wc_order->update_meta_data('everypay_source_token', $token);
				if (!empty($this->iris_md)) {
					$wc_order->update_meta_data('everypay_iris_md', $this->iris_md);
				}
				$wc_order->save();
			}

			$payload = $this->create_payload($wc_order, $token);

			if (!$is_iris_payment && is_user_logged_in() && (isset($_POST['everypay_save_card']) || isset($_POST['tokenized-card'])) && $this->tokenization_status == 'yes') {
				(new WC_Everypay_Repository())->save_logs('tokenization_payment', implode(" ", $payload));
				$user_id = $wc_order->get_user_id();
				$everypay_tokenization = new WC_Everypay_Tokenization();
				$response = $everypay_tokenization->process_tokenized_payment($user_id, $payload);
			} else {
				(new WC_Everypay_Repository())->save_logs($is_iris_payment ? 'iris_payment' : 'payment', implode(" ", $payload));
				$response = WC_Everypay_Api::addPayment($payload);
			}
			$payment_token = '';
			if (isset($response['body']['token'])) {
				$payment_token = $response['body']['token'];
			} elseif (isset($response['body']['payment']['token'])) {
				$payment_token = $response['body']['payment']['token'];
			}

			if (empty($payment_token)) {
				throw new Exception('Everypay response did not include payment token.');
			}

			return $this->complete_order($wc_order, $payment_token, $is_iris_payment ? 'iris' : 'card');
		} catch (Exception $e) {

			$error = 'An error occurred. Please try again.';
			wc_add_notice(esc_html($error), 'error');
			$response_data = array(
				'result' => 'failure',
				'reload' => true,
				'refresh' => true
			);
			echo json_encode($response_data);
			if ($order_id) {
				wp_delete_post($order_id, true);
			}
			(new WC_Everypay_Repository())->save_logs('error', $e->getMessage());
			exit;
		}
	}

	private function complete_order($wc_order, $token, $method = 'card')
	{
		$dt = new DateTime("Now");
		$timestamp = $dt->format('Y-m-d H:i:s e');

		$method_label = $method === 'iris' ? 'IRIS' : 'Card';
		$wc_order->add_order_note('Everypay ' . $method_label . ' payment completed at-' . $timestamp);

		$wc_order->update_meta_data('everypay_payment_token', $token);
		$wc_order->update_meta_data('everypay_payment_method', $method);
		if ($method === 'iris') {
			$wc_order->set_payment_method_title(__('IRIS Bank Payment', 'everypay'));
		}
		$wc_order->payment_complete();

		if (WC()->cart) {
			WC()->cart->empty_cart();
		}

		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url($wc_order)
		);
	}

	public function payment_fields()
	{
		echo $this->description;

		if ($this->tokenization_status != "yes" || !is_user_logged_in()) {
			return;
		}
		$repository = new WC_Everypay_Repository();
		$user_id = get_current_user_id();
		$customer_cards = $repository->get_customer_cards($user_id);

		wp_enqueue_script('tokenization_js', EVERYPAY_JS_URL . 'tokenization.js', array('jquery'), false, true);

		echo '<div>';
		if (!empty($customer_cards)) {
			$this->renderer->render_cards($customer_cards);
		}
		echo '</div>';
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

			$wc_order = new WC_Order($order_id);
			$token = $wc_order->get_meta('everypay_payment_token');

			WC_Everypay_Api::setApiKey($this->everypaySecretKey);
			$refund = WC_Everypay_Api::refundPayment($token, $params);

			$dt = new DateTime("Now");
			$timestamp = $dt->format('Y-m-d H:i:s e');
			$refToken = $refund['body']['token'];

			$wc_order->add_order_note('Everypay Refund completed at-' . $timestamp . '-with Refund Token=' . $refToken);

			return true;
		} catch (\Exception $e) {
			return false;
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

		wp_register_style('everypay_styles', EVERYPAY_CSS_URL . 'everypay_styles.css', [], EVERYPAY_PLUGIN_VERSION);
		wp_enqueue_style('everypay_styles');

		wp_register_style('everypay_modal', EVERYPAY_CSS_URL . 'everypay_modal.css', [], EVERYPAY_PLUGIN_VERSION);
		wp_enqueue_style('everypay_modal');

		if (EVERYPAY_SANDBOX) {
			wp_register_script('everypay_script', "https://sandbox-js.everypay.gr/v3");
		} else {
			wp_register_script('everypay_script', "https://js.everypay.gr/v3");
		}

		wp_enqueue_script('everypay_script');

		wp_register_script('everypay_helpers', EVERYPAY_JS_URL . 'helpers.js', [], EVERYPAY_PLUGIN_VERSION);
		wp_enqueue_script('everypay_helpers');

		wp_register_script('everypay_modal', EVERYPAY_JS_URL . 'everypay_modal.js', array(), EVERYPAY_PLUGIN_VERSION, true);
		wp_enqueue_script('everypay_modal');

		wp_register_script('everypay', EVERYPAY_JS_URL . 'everypay.js', array(), EVERYPAY_PLUGIN_VERSION, true);
		wp_enqueue_script('everypay');
	}

	private function handle_payment_error($errorMessage)
	{
		$error = 'An error occurred. Please try again.';
		wc_add_notice($error, $notice_type = 'error');
		(new WC_Everypay_Repository())->save_logs('error', $errorMessage);
	}


	/**
	 * Require admin settings fields
	 */
	public function init_form_fields()
	{
		$this->form_fields = require plugin_dir_path(__FILE__) . '../admin/wc-admin-form-fields.php';
	}


	public function admin_options()
	{
		?>
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
                                        <i class="icon icon-plus-sign"></i> <span class="ab-icon"></span> Προσθήκη
                                    </a>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
		<?php
	}

	/**
	 * Whitelist get param
	 * @param array $vars
	 * @return array
	 */
	public function add_everypay_var($vars)
	{
		$vars[] = "everypayToken";
		return $vars;
	}


	/**
	 *  Remove payment gateway if keys are not set
	 *
	 * @param $available_gateways
	 *
	 * @return mixed
	 */
	public function disable_everypay_if_keys_not_set($available_gateways)
	{
		if (isset($available_gateways['everypay']) && empty($this->everypaySecretKey) || empty($this->everypayPublicKey))
			unset($available_gateways['everypay']);

		return $available_gateways;
	}

	public function register_apple_pay_merchant_domain(string $domain)
	{
		if (empty($domain)) {
			return false;
		}

		try {
			WC_Everypay_Api::setApiKey($this->everypaySecretKey);
			WC_Everypay_Api::registerApplePayMerchantDomain($domain);

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function is_iris_enabled(): bool
	{
		return (bool) $this->iris_enabled;
	}

	public function get_iris_merchant_name(): string
	{
		return $this->iris_merchant_name;
	}

	public function get_iris_callback_url(): string
	{
		return $this->iris_callback_url;
	}

	public function get_iris_country(): string
	{
		return $this->iris_country ?: 'GR';
	}

	public function get_secret_key(): string
	{
		return $this->everypaySecretKey;
	}

	public function get_public_key(): string
	{
		return $this->everypayPublicKey;
	}

	public function get_iris_md_reference(): string
	{
		if (empty($this->iris_md) && $this->iris_enabled) {
			$this->prepare_iris_reference();
		}
		return $this->iris_md;
	}
}
