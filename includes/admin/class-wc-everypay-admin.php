<?php

class WC_Everypay_Admin
{

	private $public_key;
	private $secret_key;

	public function __construct($public_key, $secret_key)
	{
		if (!is_admin()) {
			return;
		}
		$this->public_key = $public_key;
		$this->secret_key = $secret_key;
		add_action('admin_enqueue_scripts', array($this, 'load_admin_js'));
		add_action('admin_notices', array($this, 'show_admin_notices'));
		add_action('admin_enqueue_scripts', array($this, 'load_admin_css'));
	}

	/**
	 * Show some notices on the admin
	 */
	public function show_admin_notices()
	{
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		if (empty($this->secret_key) || empty($this->public_key)) {
			echo '<div class="error notice"><p><strong>Please fill your Everypay keys, in Woocommerce/Settings/Payments</strong><p/></div>';
		}

	}

	public function load_admin_css()
	{
		if (isset($_GET['page']) && $_GET['page'] == 'wc-settings'
			&& isset($_GET['tab']) && $_GET['tab'] == 'checkout'
			&& isset($_GET['section']) && in_array($_GET['section'], array('wc_everypay_gateway', 'everypay'))) {
			wp_register_style('everypay_admin_css', EVERYPAY_CSS_URL . 'admin/everypay_admin.css', [], EVERYPAY_PLUGIN_VERSION);
			wp_enqueue_style('everypay_admin_css');
		}
	}

	/**
	 * The scripts on admin tab
	 */
	public function load_admin_js()
	{
		if (isset($_GET['page']) && $_GET['page'] == 'wc-settings'
			&& isset($_GET['tab']) && $_GET['tab'] == 'checkout'
			&& isset($_GET['section']) && in_array($_GET['section'], array('wc_everypay_gateway', 'everypay'))) {
			wp_register_script('everypay_script1', EVERYPAY_JS_URL . 'admin/mustache.min.js', array('jquery'), EVERYPAY_PLUGIN_VERSION, true);
			wp_enqueue_script('everypay_script1');

			wp_register_script('everypay_script2', EVERYPAY_JS_URL . 'admin/everypay.js', array('jquery'), EVERYPAY_PLUGIN_VERSION, true);
			wp_enqueue_script('everypay_script2');

			wp_localize_script('everypay_script2', 'everypay_ajax_object', [
				'ajax_url'   => admin_url('admin-ajax.php'),
				'nonce'      => wp_create_nonce('everypay_register_domain_nonce'),
				'spinner_url' => admin_url('images/spinner.gif')
			]);
		}
	}
}
