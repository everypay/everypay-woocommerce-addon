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
	private  $everypay_sandbox;

	/**
	 * @var string
	 */
	private $max_installments;

	private  $tokenization_status;

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

    private $isGooglePayEnabled;

    private $googlePayCountryCode;

    private $googlePayMerchantName;

    private $googlePayMerchantUrl;

    private $googlePayAllowedCardNetworks;

    private $googlePayAllowedAuthMethods;

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

        $this->isGooglePayEnabled = $this->get_option('everypay_googlepay_enabled');
        $this->googlePayCountryCode = $this->get_option('everypay_googlepay_country_code');
        $this->googlePayMerchantName = $this->get_option('everypay_googlepay_merchant_name');
        $this->googlePayMerchantUrl = $this->get_option('everypay_googlepay_merchant_url');
        $this->googlePayAllowedCardNetworks = $this->get_option('everypay_googlepay_allowed_card_networks');
        $this->googlePayAllowedAuthMethods = $this->get_option('everypay_googlepay_allowed_auth_methods');

        $this->helpers = new WC_Everypay_Helpers();
        $this->renderer = new WC_Everypay_Renderer($this->helpers, $this->everypayPublicKey, $this->tokenization_status);

        if ($this->isGooglePayEnabled == 'yes') {
            $this->renderer->setGooglePay(
                $this->googlePayCountryCode,
                $this->googlePayMerchantName,
                $this->googlePayMerchantUrl,
                $this->googlePayAllowedCardNetworks,
                $this->googlePayAllowedAuthMethods
            );
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

		return array(
			'description' => $description,
			'amount' => $amount,
			'payee_email' => $billing_email,
			'payee_phone' => $billing_phone,
			'token' => $token,
			'max_installments' => $installments,
		);
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
					'result'   => 'success',
					'messages' => '<div class=""></div>'
				);
			}
			if (!isset($_POST['everypayToken']) || empty($_POST['everypayToken'])) {
				$this->renderer->render_iframe(WC()->cart->total, $this->max_installments);
				exit;
			}
			$token = sanitize_text_field($_POST['everypayToken']);
			unset($_POST['everypayToken']);
			$wc_order = new WC_Order($order_id);
			WC_Everypay_Api::setApiKey($this->everypaySecretKey);
			$payload = $this->create_payload($wc_order, $token);

			if (is_user_logged_in() && (isset($_POST['everypay_save_card']) || isset($_POST['tokenized-card'])) && $this->tokenization_status == 'yes') {
				(new WC_Everypay_Repository())->save_logs('tokenization_payment', implode(" ", $payload));
				$user_id = $wc_order->get_user_id();
				$everypay_tokenization = new WC_Everypay_Tokenization();
				$response = $everypay_tokenization->process_tokenized_payment($user_id, $payload);
			} else {
				(new WC_Everypay_Repository())->save_logs('payment', implode(" ", $payload));
				$response = WC_Everypay_Api::addPayment($payload);
			}
			return $this->complete_order($wc_order, $response['body']['token']);
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

	private function complete_order($wc_order, $token)
	{
		$dt = new DateTime("Now");
		$timestamp = $dt->format('Y-m-d H:i:s e');

		$wc_order->add_order_note('Everypay payment completed at-' . $timestamp);
        $wc_order->update_meta_data('everypay_payment_token', $token);
        $wc_order->payment_complete();
		$wc_order->get_order();
		WC()->cart->empty_cart();

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

		wp_register_style('everypay_styles', EVERYPAY_CSS_URL . 'everypay_styles.css');
		wp_enqueue_style('everypay_styles');

		wp_register_style('everypay_modal', EVERYPAY_CSS_URL . 'everypay_modal.css');
		wp_enqueue_style('everypay_modal');

		if (EVERYPAY_SANDBOX) {
			wp_register_script('everypay_script', "https://sandbox-js.everypay.gr/v3");
		} else {
			wp_register_script('everypay_script', "https://js.everypay.gr/v3");
		}

		wp_enqueue_script('everypay_script');

		wp_register_script('everypay_helpers', EVERYPAY_JS_URL . 'helpers.js');
		wp_enqueue_script('everypay_helpers');

		wp_register_script('everypay_modal', EVERYPAY_JS_URL . 'everypay_modal.js', array(), false, true);
		wp_enqueue_script('everypay_modal');

		wp_register_script('everypay', EVERYPAY_JS_URL . 'everypay.js', array(), false, true);
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
											<i class="icon icon-plus-sign"></i> <span class="ab-icon"></span>  Προσθήκη
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
}
