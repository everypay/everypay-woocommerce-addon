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
	}


	/**
	 * Show some notices on the admin
	 */
	public function show_admin_notices()
	{
		if (!current_user_can( 'manage_woocommerce' )) {
			return;
		}

		if (empty($this->secret_key) || empty($this->public_key)) {
			echo '<div class="error notice"><p><strong>Please fill your Everypay keys, in Woocommerce/Settings/Payments</strong><p/></div>';
		}

	}

	/**
	 * The scripts on admin tab
	 */
	public function load_admin_js()
	{
		if (isset($_GET['page']) && $_GET['page'] == 'wc-settings'
		    && isset($_GET['tab']) && $_GET['tab'] == 'checkout'
		    && isset($_GET['section']) && in_array($_GET['section'], array('wc_everypay_gateway', 'everypay')))
		{
			wp_register_script('everypay_script1', EVERYPAY_JS_URL.'admin/mustache.min.js', array('jquery'), 'ver', true);
			wp_enqueue_script('everypay_script1');

			wp_register_script('everypay_script2', EVERYPAY_JS_URL.'admin/everypay.js', array('jquery'), 'ver', true);
			wp_enqueue_script('everypay_script2');
		}
	}

}
