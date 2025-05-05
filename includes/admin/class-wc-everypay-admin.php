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
		if (!current_user_can( 'manage_woocommerce' )) {
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
            && isset($_GET['section']) && in_array($_GET['section'], array('wc_everypay_gateway', 'everypay')))
        {
            wp_register_style('everypay_admin_css', EVERYPAY_CSS_URL . 'admin/everypay_admin.css', [], '1.0.0');
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
		    && isset($_GET['section']) && in_array($_GET['section'], array('wc_everypay_gateway', 'everypay')))
		{
			wp_register_script('everypay_script1', EVERYPAY_JS_URL.'admin/mustache.min.js', array('jquery'), 'ver', true);
			wp_enqueue_script('everypay_script1');

			wp_register_script('everypay_script2', EVERYPAY_JS_URL.'admin/everypay.js', array('jquery'), 'ver', true);
			wp_enqueue_script('everypay_script2');

            add_action('admin_footer', [$this, 'googlepay_toggle_script']);
		}
	}

    public function googlepay_toggle_script()
    {
        ?>
        <script>
            jQuery(function($) {
                function toggleGooglePayFields() {
                    const enabled = $('#woocommerce_everypay_everypay_googlepay_enabled').is(':checked');
                    console.log('Google Pay enabled: ' + enabled);
                    $('#googlepay-warning').toggle(enabled); // direct div toggle
                    $('#woocommerce_everypay_everypay_googlepay_country_code').closest('tr').toggle(enabled);
                    $('#woocommerce_everypay_everypay_googlepay_merchant_name').closest('tr').toggle(enabled);
                    $('#woocommerce_everypay_everypay_googlepay_allowed_card_networks').closest('tr').toggle(enabled);
                    $('#woocommerce_everypay_everypay_googlepay_merchant_url').closest('tr').toggle(enabled);
                    $('#woocommerce_everypay_everypay_googlepay_allowed_auth_methods').closest('tr').toggle(enabled);
                }

                toggleGooglePayFields();
                $('#woocommerce_everypay_everypay_googlepay_enabled').on('change', toggleGooglePayFields);
            });
        </script>
        <?php
    }
}
