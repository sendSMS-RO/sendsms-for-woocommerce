=== SendSMS for WooCommerce ===
Contributors: neamtua, catalinsendsms
Tags: sms, woocommerce, sendsms, notifications, order
Requires at least: 4.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send SMS notifications to your customers when their WooCommerce order status changes, run SMS campaigns, and send single SMS from any order.

== Description ==
**SendSMS for WooCommerce** connects your WooCommerce store to the sendsms.ro SMS gateway so customers get a text message at every step of their order — placed, paid, shipped, completed, refunded, or any other status you choose.

Why SMS? It has a ~95% open rate, and most messages are read within five seconds of arrival. For order updates, that's hard to beat.

Features:

* Per-status message templates with variables: `{billing_first_name}`, `{billing_last_name}`, `{shipping_first_name}`, `{shipping_last_name}`, `{order_number}`, `{order_date}`, `{order_total}`.
* Campaign sender with date / county / product filters that pulls phone numbers from past orders.
* "Send a test SMS" page for verifying templates against any phone number.
* Per-order "Send SMS" sidebar metabox for one-off messages, with the SMS body recorded as an order note.
* SMS history table with full searching and sorting.
* Compatible with WooCommerce High-Performance Order Storage (HPOS).
* Optional unsubscribe-link (GDPR) and short-URL flags per status.
* Customer opt-out checkbox available at checkout.

This plugin requires a [sendsms.ro](https://www.sendsms.ro/ro/) account. Sign-up is free; SMS pricing is per message and depends on the destination country.

== Installation ==
1. Install **WooCommerce** if you don't already have it.
2. Upload the `sendsms-for-woocommerce` folder to `/wp-content/plugins/`, or install the plugin from the WordPress.org directory.
3. Activate the plugin under **Plugins** in the WordPress admin.
4. Go to **SendSMS → Configuration** and enter your sendsms.ro username and API key.

== Frequently Asked Questions ==

= Do I need an account with sendsms.ro? =
Yes. Sign up for free at https://www.sendsms.ro/ro/ and create an API key.

= Does this plugin work with WooCommerce HPOS (High-Performance Order Storage)? =
Yes. The plugin reads and writes order data through the WooCommerce order API so it works regardless of whether your store uses legacy CPT storage or HPOS.

= What PHP versions are supported? =
PHP 7.4 through PHP 8.3+. Tested on PHP 7.4 and PHP 8.3 against WordPress 7.0 and WooCommerce 10.7.

= Can my customers opt out of SMS notifications? =
Yes. Enable the "Opt-out in cart" setting under Configuration; a checkbox appears at checkout. Customers who tick it never receive automated SMS for that order.

== Screenshots ==
1. Plugin landing page
2. Configuration page with status templates
3. SMS history
4. Campaign sender
5. Send a test SMS
6. Send a single SMS from inside an order

== Changelog ==
= 1.0.0 =
Initial release on WordPress.org. Previously distributed via [sendSMS-RO/sendsms-woocommerce-5.5.1](https://github.com/sendSMS-RO/sendsms-woocommerce-5.5.1) (now archived). This release corresponds to v1.4.3 of the legacy distribution and brings:

* HPOS compatibility (declared via `FeaturesUtil::declare_compatibility`).
* Order-metabox single-send registered on both legacy and HPOS Orders screens.
* Security hardening: AJAX nonces + capability checks, CSRF-protected test-send form, settings password no longer rendered back into HTML, History page stored-XSS escaping, HTTPS balance check.
* In-memory campaign CSV (no `batches/` filesystem write).
* Full WordPress.org Plugin Check compliance pass.
