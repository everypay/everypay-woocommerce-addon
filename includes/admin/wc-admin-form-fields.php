<?php

return array(
	'enabled' => array(
		'title' => pll__('Enable/Disable'),
		'type' => 'checkbox',
		'label' => pll__('Enable Everypay'),
		'default' => 'yes'
	),
	'everypay_title' => array(
		'title' => pll__('Title'),
		'type' => 'text',
		'description' => pll__('This controls the title which the user sees during checkout.'),
		'default' => pll__('Pay with Card'),
		'desc_tip' => true,
	),
	'description' => array(
		'title' => pll__('Description'),
		'type' => 'textarea',
		'description' => pll__('Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%'),
		'default' => pll__('Pay using your credit or debit card.'),
		'desc_tip' => pll__('Payment method description that the customer will see on your website. If there is an extra fee, you can display it by using the keyword %AMOUNT% in your text. For eg. There will be an extra charge of %AMOUNT%'),
	),
	'everypayPublicKey' => array(
		'title' => pll__('Public Key'),
		'type' => 'text',
		'description' => pll__('This is the Public Key found in API Keys in Account Dashboard.'),
		'default' => '',
		'desc_tip' => true,
		'placeholder' => 'Everypay Public Key'
	),
	'everypaySecretKey' => array(
		'title' => pll__('Secret Key'),
		'type' => 'text',
		'description' => pll__('This is the Secret Key found in API Keys in Account Dashboard.'),
		'default' => '',
		'desc_tip' => true,
		'placeholder' => 'Everypay Secret Key'
	),
	'everypay_sandbox' => array(
		'title' => pll__('Sandbox Mode'),
		'type' => 'checkbox',
		'label' => pll__('Enable sandbox mode'),
		'description' => pll__('If checked its in sandbox mode and if unchecked its in live mode'),
		'desc_tip' => true,
		'default' => 'no',
	),
	'everypay_tokenization' => array(
		'title' => pll__('Everypay Tokenization'),
		'type' => 'checkbox',
		'label' => pll__('Enable Tokenization'),
		'description' => pll__('Allow your customers to save their cards.'),
		'desc_tip' => true,
		'default' => false,
	),
	'everypay_maximum_installments' => array(
		'title' => pll__('Everypay Installments'),
		'type' => 'hidden',
		'label' => pll__('Installments'),
		'description' => pll__('Configure the amount of installments offered depending on the amount of the order. Leave empty if no installments are offered at all'),
		'desc_tip' => pll__('Choose the maximum number for installments offered'),
		'default' => ''
	),

);