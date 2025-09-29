=== Ari10 Pay Payment Gateway for WooCommerce ===
Contributors: monedapay
Tags: woocommerce, cryptocurrency, crypto, payment gateway, bitcoin
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept cryptocurrency payments in WooCommerce with Ari10 Pay. Secure, simple checkout with full multisite support.

== Description ==
Ari10 Pay Payment Gateway for Woocommerce adds a cryptocurrency payment method to WooCommerce. It connects your store to the Ari10 platform to create payment links, verify transactions, and automatically update order statuses via webhooks.

Key features:
- WooCommerce payment gateway with admin settings
- Sandbox and Production environments
- Ari10 Pay API integration for payments and status updates
- Webhook signature verification (HMAC)
- Debug logging (WooCommerce Logs)
- Multisite compatible (per-site settings, clean deactivation)
- REST API endpoints for order info and status updates

Requirements:
- WordPress 5.8+
- WooCommerce 6.0+
- PHP 8.2+
- MonedaPay account (API credentials)

Note: WooCommerce requires at least version 6.0.

== Installation ==
1. Install and activate WooCommerce.
2. Upload the plugin to the /wp-content/plugins/ directory or install via Plugins > Add New.
3. Activate “Ari10 Pay Payment Gateway for WooCommerce”. 
4. Go to WooCommerce > Settings > Payments > Ari10 Pay Payment Gateway for Woocommerce and enter your API credentials.
5. Choose an Environment (Sandbox or Production) and save changes.

== Frequently Asked Questions ==
= Where do I find my API credentials? =
Log in to your Ari10 dashboard to get your Merchant ID, Shop ID, and Encryption Key.

= Does this plugin support sandbox (test) mode? =
Yes. Select “Sandbox” in the plugin settings to process test payments.

= How do I enable logging? =
Enable “Debug logging” in the gateway settings. Logs are available in WooCommerce > Status > Logs.

= Do I need to configure a webhook? =
The plugin exposes an endpoint and validates HMAC signatures. Follow the Ari10 Pay documentation to set the webhook URL to your site.

= Is WordPress Multisite supported? =
Yes. You can activate network-wide or per-site. Each site has its own settings, and the plugin cleans up properly on deactivation.

== Screenshots ==
1. Ari10 Pay gateway settings in WooCommerce.
2. Checkout with Ari10 Pay selected.

== Changelog ==
= 1.0.4 =
- Initial public release for WordPress.org
- WooCommerce gateway with Ari10 Pay API integration
- Webhook handler and REST endpoints
- Multisite support and debug logging

== Upgrade Notice ==
= 1.0.4 =
Initial release. Update to start accepting cryptocurrency payments via Ari10 Pay.

== Support ==
For help and documentation, visit https://monedapay.com

== License ==
This plugin is licensed under the GPL v2 or later.
