<?php

return [
	'enabled' => [
		'title' => 'Enable/Disable',
		'type' => 'checkbox',
		'label' => 'Enable Everypay',
		'default' => 'yes'
	],
	'everypay_title' => [
		'title' => 'Title',
		'type' => 'text',
		'description' => 'This controls the title which the user sees during checkout.',
		'default' => 'Pay with Card',
		'desc_tip' => true,
	],
	'description' => [
		'title' => 'Description',
		'type' => 'textarea',
		'description' => 'Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%',
		'default' => 'Pay using your credit or debit card.',
		'desc_tip' => 'Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%',
	],
	'everypayPublicKey' => [
		'title' => 'Public Key',
		'type' => 'text',
		'description' => 'This is the Public Key found in API Keys in Account Dashboard.',
		'default' => '',
		'desc_tip' => true,
		'placeholder' => 'Everypay Public Key'
	],
	'everypaySecretKey' => [
		'title' => 'Secret Key',
		'type' => 'text',
		'description' => 'This is the Secret Key found in API Keys in Account Dashboard.',
		'default' => '',
		'desc_tip' => true,
		'placeholder' => 'Everypay Secret Key'
	],
	'everypay_sandbox' => [
		'title' => 'Sandbox Mode',
		'type' => 'checkbox',
		'label' => 'Enable sandbox mode',
		'description' => 'If checked its in sandbox mode and if unchecked its in live mode',
		'desc_tip' => true,
		'default' => 'no',
	],
	'everypay_tokenization' => [
		'title' => 'Everypay Tokenization',
		'type' => 'checkbox',
		'label' => 'Enable Tokenization',
		'description' => 'Allow your customers to save their cards.',
		'desc_tip' => true,
		'default' => false,
	],
	'everypay_googlepay_enabled' => [
		'title' => 'Google Pay',
		'type' => 'checkbox',
		'label' => 'Enable Google Pay',
		'default' => false,
	],
	'everypay_googlepay_warning' => [
		'type' => 'title',
		'description' => '<div id="googlepay-warning" style="color: orange; font-weight: bold;">
			⚠️ Please contact Support to Enable/Disable Google Pay Payments Processing.
		</div>',
	],
	'everypay_googlepay_country_code' => [
		'title' => 'Country Code',
		'type' => 'text',
		'default' => WC()->countries->get_base_country(),
		'desc_tip' => 'Billing country for Google Pay',
		'description' => 'Billing country for Google Pay',
	],
	'everypay_googlepay_merchant_name' => [
		'title' => 'Merchant Name',
		'type' => 'text',
		'default' => 'MerchantName',
		'desc_tip' => 'The name to be displayed on the payform modal',
		'description' => 'The name to be displayed on the payform modal',
		'wrapper_class' => 'hide-if-googlepay-disabled',
	],
	'everypay_googlepay_merchant_url' => [
		'title' => 'Merchant URL',
		'type' => 'text',
		'default' => home_url(),
		'description' => 'Your store URL (e.g. https://my-store.com)',
		'desc_tip' => 'Your store URL (e.g. https://my-store.com)',
	],
	'everypay_googlepay_allowed_card_networks' => [
		'title' => 'Allowed Card Networks',
		'type' => 'text',
		'default' => 'VISA,MASTERCARD',
		'desc_tip' => 'Comma-separated (e.g. VISA,MASTERCARD)',
		'description' => 'Comma-separated (e.g. VISA,MASTERCARD)',
	],
	'everypay_googlepay_allowed_auth_methods' => [
		'title' => 'Allowed Auth Methods',
		'type' => 'text',
		'default' => 'CRYPTOGRAM_3DS,PAN_ONLY',
		'desc_tip' => 'Comma-separated (e.g. CRYPTOGRAM_3DS,PAN_ONLY)',
		'description' => 'Comma-separated (e.g. CRYPTOGRAM_3DS,PAN_ONLY)',
	],
	'everypay_googlepay_button_color' => [
		'title' => 'Button Color',
		'type' => 'text',
		'default' => 'black',
		'desc_tip' => 'Available options are black or white',
		'description' => 'Available options are black or white',
	],
	'everypay_applepay_enabled' => [
		'title' => 'Apple Pay',
		'type' => 'checkbox',
		'label' => 'Enable Apple Pay',
		'default' => false,
	],
	'everypay_applepay_warning' => [
		'type' => 'title',
		'description' => '<div id="applepay-warning" style="color: orange; font-weight: bold;">
			⚠️ Please contact Support to Enable/Disable Apple Pay Payments Processing.
		</div>',
	],
	'everypay_applepay_country_code' => [
		'title' => 'Country Code',
		'type' => 'text',
		'default' => WC()->countries->get_base_country(),
		'desc_tip' => 'Billing country for Apple Pay',
		'description' => 'Billing country for Apple Pay',
	],
	'everypay_applepay_merchant_name' => [
		'title' => 'Merchant Name',
		'type' => 'text',
		'default' => 'MerchantName',
		'desc_tip' => 'The name to be displayed on the payform modal',
		'description' => 'The name to be displayed on the payform modal',
		'wrapper_class' => 'hide-if-googlepay-disabled',
	],
	'everypay_applepay_merchant_url' => [
		'title' => 'Merchant URL',
		'type' => 'text',
		'default' => home_url(),
		'description' => 'Your store URL (e.g. https://my-store.com)',
		'desc_tip' => 'Your store URL (e.g. https://my-store.com)',
	],
	'everypay_applepay_allowed_card_networks' => [
		'title' => 'Allowed Card Networks',
		'type' => 'text',
		'default' => 'VISA,MASTERCARD',
		'desc_tip' => 'Comma-separated (e.g. VISA,MASTERCARD)',
		'description' => 'Comma-separated (e.g. VISA,MASTERCARD)',
	],
	'everypay_applepay_button_color' => [
		'title' => 'Button Color',
		'type' => 'text',
		'default' => 'black',
		'desc_tip' => 'Available options are black or white',
		'description' => 'Available options are black or white',
	],
	'everypay_maximum_installments' => [
		'title' => 'Everypay Installments',
		'type' => 'hidden',
		'label' => 'Installments',
		'description' => 'Configure the amount of installments offered depending on the amount of the order. Leave empty if no installments are offered at all',
		'desc_tip' => 'Choose the maximum number for installments offered',
		'default' => ''
	],
];