<?php

return array(
	'enabled' => array(
		'title' => 'Enable/Disable',
		'type' => 'checkbox',
		'label' => 'Enable Everypay',
		'default' => 'yes'
	),
	'everypay_title' => array(
		'title' => 'Title',
		'type' => 'text',
		'description' => 'This controls the title which the user sees during checkout.',
		'default' => 'Pay with Card',
		'desc_tip' => true,
	),
	'description' => array(
		'title' => 'Description',
		'type' => 'textarea',
		'description' => 'Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%',
		'default' => 'Pay using your credit or debit card.',
		'desc_tip' => 'Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%',
	),
	'everypayPublicKey' => array(
		'title' => 'Public Key',
		'type' => 'text',
		'description' => 'This is the Public Key found in API Keys in Account Dashboard.',
		'default' => '',
		'desc_tip' => true,
		'placeholder' => 'Everypay Public Key'
	),
	'everypaySecretKey' => array(
		'title' => 'Secret Key',
		'type' => 'text',
		'description' => 'This is the Secret Key found in API Keys in Account Dashboard.',
		'default' => '',
		'desc_tip' => true,
		'placeholder' => 'Everypay Secret Key'
	),
	'everypay_sandbox' => array(
		'title' => 'Sandbox Mode',
		'type' => 'checkbox',
		'label' => 'Enable sandbox mode',
		'description' => 'If checked its in sandbox mode and if unchecked its in live mode',
		'desc_tip' => true,
		'default' => 'no',
	),
	'everypay_tokenization' => array(
		'title' => 'Everypay Tokenization',
		'type' => 'checkbox',
		'label' => 'Enable Tokenization',
		'description' => 'Allow your customers to save their cards.',
		'desc_tip' => true,
		'default' => false,
	),
	'everypay_maximum_installments' => array(
		'title' => 'Everypay Installments',
		'type' => 'hidden',
		'label' => 'Installments',
		'description' => 'Configure the amount of installments offered depending on the amount of the order. Leave empty if no installments are offered at all',
		'desc_tip' => 'Choose the maximum number for installments offered',
		'default' => ''
	),

);