# woocommerce
Woocommerce plugin for wordpress 3.2+

## Description

This plugin acts as an addon for woocommerce to add a payment method for WooCommerce for accepting **Credit Cards Payments** by merchants via **Everypay** directly on checkout page.
This plugin uses Everypay API version  **2015-04-07** to create tokens and charge credit cards. For better visualization of how it looks & works check screenshots tab.


## Features
1. Very Simple Clean Code plugin to add a Everypay payment method to woocommerce
2. No technical skills needed.
3. This plugin bundles with <a href="https://github.com/everypay/everypay-php">Official Everypay® API Libraries</a> Version 1.18.0 to support PHP 5.2 Compatibility.
4. Configurable through the woocommerce checkout admin panel

## Installation

1. Upload 'everypay-woocommerce-addon' folder to the '/wp-content/plugins/' directory
2. Activate 'everypay Woocommerce Addon' from wp plugin lists in admin area
3. Plugin will appear in settings of woocommerce
4. You can set the addon settings from  wocommmerce -> settings -> Checkout -> Everypay Cards Settings
5. You can check for Testing Card No <a href="https://everypay.gr/docs" target="_blank" >Here</a> 
6. Integrated Everypay Libraries

## Frequently Asked Questions

1. You need to have woocoommerce plugin installed to make this plugin work
2. You need to obtain API keys from Everypay <a href="https://dashboard.everypay.gr/">Dashboard</a>
3. This plugin works on test & live api keys.
4. This plugin requires SSL as per <a href="https://everypay.com/docs/ssl">here</a> but can work even without SSL.
5. This plugin does not store Card Details anywhere.
6. This plugin comes packed with Official Everypay Libraries
7. This plugin requires CURL
8. Everypay & PCI compliance requires to use SSL always
9. This plugin Support refunds **(Only in Cents)** in woocommerce interface. On full refund order state changes automatically to refunded(WooCommerce Feature).
10. Upon refunds the items are not restocked automatically
