<?php

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
	return;
}

class WC_Everypay_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
		/**
		 * Payment method name used by Blocks.
		 *
		 * @var string
		 */
		protected $name = 'everypay';

		/**
		 * Gateway settings.
		 *
		 * @var array
		 */
	protected $settings = array();

		public function initialize()
		{
			$this->settings = get_option('woocommerce_everypay_settings', array());
		}

		public function is_active()
		{
			return ($this->settings['enabled'] ?? 'no') === 'yes'
				&& !empty($this->settings['everypayPublicKey'])
				&& !empty($this->settings['everypaySecretKey']);
		}

		public function get_payment_method_script_handles()
		{
			$this->register_everypay_scripts();

			wp_register_script(
				'everypay-blocks-integration',
				plugins_url('assets/js/blocks/everypay-blocks.js', dirname(__FILE__)),
				array('everypay', 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'),
				defined('EVERYPAY_PLUGIN_VERSION') ? EVERYPAY_PLUGIN_VERSION : '3.8',
				true
			);

			return array('everypay-blocks-integration');
		}

		public function get_payment_method_data()
		{
			$helpers = new WC_Everypay_Helpers();

			return array(
				'name' => $this->name,
				'title' => $this->settings['everypay_title'] ?? __('Everypay', 'everypay'),
				'description' => $this->settings['description'] ?? '',
				'publicKey' => $this->settings['everypayPublicKey'] ?? '',
				'locale' => $helpers->get_locale(),
				'installmentConfig' => $this->settings['everypay_maximum_installments'] ?? '',
				'woocommerceVersion' => defined('WC_VERSION') ? WC_VERSION : '',
				'supports' => array(
					'features' => array('products'),
					'showSaveOption' => ($this->settings['everypay_tokenization'] ?? 'no') === 'yes' && is_user_logged_in(),
					'showSavedCards' => false,
				),
				'googlePay' => $this->get_google_pay_data(),
				'applePay' => $this->get_apple_pay_data(),
				'iris' => $this->get_iris_data(),
			);
		}

		private function register_everypay_scripts()
		{
			$script_url = ($this->settings['everypay_sandbox'] ?? 'no') === 'yes'
				? 'https://sandbox-js.everypay.gr/v3'
				: 'https://js.everypay.gr/v3';

			if (!wp_script_is('everypay_script', 'registered')) {
				wp_register_script('everypay_script', $script_url, array(), null, true);
			}

			if (!wp_script_is('everypay_helpers', 'registered')) {
				wp_register_script(
					'everypay_helpers',
					plugins_url('assets/js/helpers.js', dirname(__FILE__)),
					array(),
					defined('EVERYPAY_PLUGIN_VERSION') ? EVERYPAY_PLUGIN_VERSION : '3.8',
					true
				);
			}

			if (!wp_script_is('everypay_modal', 'registered')) {
				wp_register_script(
					'everypay_modal',
					plugins_url('assets/js/everypay_modal.js', dirname(__FILE__)),
					array(),
					defined('EVERYPAY_PLUGIN_VERSION') ? EVERYPAY_PLUGIN_VERSION : '3.8',
					true
				);
			}

			if (!wp_script_is('everypay', 'registered')) {
				wp_register_script(
					'everypay',
					plugins_url('assets/js/everypay.js', dirname(__FILE__)),
					array('everypay_script', 'everypay_helpers', 'everypay_modal'),
					defined('EVERYPAY_PLUGIN_VERSION') ? EVERYPAY_PLUGIN_VERSION : '3.8',
					true
				);
			}

			if (!wp_style_is('everypay_styles', 'registered')) {
				wp_register_style(
					'everypay_styles',
					plugins_url('assets/css/everypay_styles.css', dirname(__FILE__)),
					array(),
					defined('EVERYPAY_PLUGIN_VERSION') ? EVERYPAY_PLUGIN_VERSION : '3.8'
				);
			}

			if (!wp_style_is('everypay_modal', 'registered')) {
				wp_register_style(
					'everypay_modal',
					plugins_url('assets/css/everypay_modal.css', dirname(__FILE__)),
					array(),
					defined('EVERYPAY_PLUGIN_VERSION') ? EVERYPAY_PLUGIN_VERSION : '3.8'
				);
			}

			wp_enqueue_style('everypay_styles');
			wp_enqueue_style('everypay_modal');
		}

		private function get_google_pay_data()
		{
			if (($this->settings['everypay_googlepay_enabled'] ?? 'no') !== 'yes') {
				return null;
			}

			return array(
				'countryCode' => $this->settings['everypay_googlepay_country_code'] ?? '',
				'merchantName' => $this->settings['everypay_googlepay_merchant_name'] ?? '',
				'merchantUrl' => $this->settings['everypay_googlepay_merchant_url'] ?? '',
				'allowedCardNetworks' => $this->split_csv($this->settings['everypay_googlepay_allowed_card_networks'] ?? ''),
				'allowedAuthMethods' => $this->split_csv($this->settings['everypay_googlepay_allowed_auth_methods'] ?? ''),
				'buttonColor' => $this->settings['everypay_googlepay_button_color'] ?? '',
			);
		}

		private function get_apple_pay_data()
		{
			if (($this->settings['everypay_applepay_enabled'] ?? 'no') !== 'yes') {
				return null;
			}

			return array(
				'countryCode' => $this->settings['everypay_applepay_country_code'] ?? '',
				'merchantName' => $this->settings['everypay_applepay_merchant_name'] ?? '',
				'merchantUrl' => $this->settings['everypay_applepay_merchant_url'] ?? '',
				'allowedCardNetworks' => $this->split_csv($this->settings['everypay_applepay_allowed_card_networks'] ?? ''),
				'buttonColor' => $this->settings['everypay_applepay_button_color'] ?? '',
			);
		}

		private function get_iris_data()
		{
			if (($this->settings['everypay_iris_enabled'] ?? 'no') !== 'yes') {
				return null;
			}

			return array(
				'merchantName' => sanitize_text_field($this->settings['everypay_iris_merchant_name'] ?? ''),
				'callbackUrl' => esc_url_raw(admin_url('admin-ajax.php?action=everypay_iris_callback')),
				'country' => 'GR',
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('everypay_create_iris_session'),
				'action' => 'everypay_create_iris_session',
				'isSandbox' => ($this->settings['everypay_sandbox'] ?? 'no') === 'yes',
			);
		}

		private function split_csv($value)
		{
			$items = array_filter(array_map('trim', explode(',', (string) $value)));
			return array_values($items);
		}
}
