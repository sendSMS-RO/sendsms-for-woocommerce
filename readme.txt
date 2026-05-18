=== SendSMS for WooCommerce ===
Contributors: neamtua, catalinsendsms
Tags: sms, woocommerce, sendsms, notifications, order
Requires at least: 4.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
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

== Upgrade Notice ==
= 2.0.0 =
Full rewrite under the SendSMS\\ForWooCommerce namespace. Settings and SMS history carry over automatically. WordPress.org launch.

== Changelog ==
= 2.0.0 =
Full architectural rewrite. The plugin now follows modern WordPress conventions while preserving every existing setting and the SMS history database table — upgrading from 1.x is transparent.

* Code reorganised into a PSR-4 namespace tree (`SendSMS\\ForWooCommerce\\…`) with one class per responsibility (API client, settings reader, order listeners, admin pages, AJAX handlers).
* Settings page redesigned with three tabs: **Account**, **Customer notifications**, **Owner notification**. The per-status template list is now a single table.
* Admin scripts moved to `assets/js/` and enqueued only on the relevant pages.
* New extensibility hooks for third-party developers: `sendsms_for_woocommerce_should_send`, `sendsms_for_woocommerce_message`, `sendsms_for_woocommerce_recipient_phone`, and `sendsms_for_woocommerce_after_send`.
* Continued from the 1.4.3 security baseline (legacy repository now archived):
  * Single-send AJAX endpoint guarded with nonce + `manage_woocommerce` capability.
  * Test-send form CSRF-protected.
  * Stored password never rendered back into the settings form.
  * Stored-XSS escaping on every column of the History page.
  * HPOS-aware throughout (declared via `FeaturesUtil::declare_compatibility`).
  * In-memory CSV for campaign sends (no `batches/` filesystem write).
* Minimum PHP raised to 7.4; verified on PHP 7.4 and PHP 8.3.

= 1.0.0 =
Internal mirror of the legacy 1.4.3 release. Not distributed via WordPress.org.
