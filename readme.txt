=== SendSMS for WooCommerce ===
Contributors: sendsms, neamtua, catalinsendsms
Tags: sms, woocommerce, sendsms, notifications, order
Requires at least: 4.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.2
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

== External services ==

This plugin connects to the **sendsms.ro** SMS gateway — a third-party service operated by SC sendSMS Solutions SRL — to deliver text messages to your customers. Using the plugin requires an active sendsms.ro account.

What the service is used for:

* Sending the configured order-status SMS to each order's billing phone number.
* Sending the optional owner-notification SMS to a phone number you configure.
* Sending the test SMS triggered from the **SendSMS → Send a test** page.
* Sending bulk campaign SMS triggered from the **SendSMS → Campaign** page.
* Reading your account balance to display it on the **Configuration** page.
* Looking up the per-SMS price for a route (used in the campaign "Estimate the price" feature). Cached locally for 24 hours.

What data is sent, and when:

* On every outbound SMS: your sendsms.ro **username** and **API key/password**, the configured **sender label**, the **recipient phone number** (typically the order's billing phone or an admin-supplied number for tests/campaigns), and the **message body** (which may contain placeholder substitutions from the order such as first/last name and order number/date/total). For campaign sends, the recipients + message are POSTed as a CSV body.
* On every balance/price-lookup request: your **username**, **API key/password**, and (for the price lookup) the **destination phone number**.
* No data is sent until you have entered credentials and either an order transition occurs, you press a send button manually, or you open the Configuration page (which checks the account balance once per page load).

Service endpoints used: `https://api.sendsms.ro/json` (HTTPS).

Third-party terms of service and privacy:

* Terms and conditions: https://www.sendsms.ro/en/terms-and-conditions/
* GDPR / privacy: https://www.sendsms.ro/en/gdpr/
* ISO 27001 certification: https://www.sendsms.ro/en/iso-27001-certified/

== Upgrade Notice ==
= 2.0.2 =
Renames all stored option keys, the history table, the order opt-out meta key, AJAX actions, and the PHP namespace to a `sendsmsro_` prefix. Existing settings and SMS history are migrated automatically on activation.

= 2.0.1 =
Polish pass after the 2.0.0 rewrite. Fixes a settings-page bug where saving one tab wiped the values stored on other tabs.

= 2.0.0 =
Full rewrite under the SendSMS\\ForWooCommerce namespace. Settings and SMS history carry over automatically. WordPress.org launch.

== Changelog ==
= 2.0.2 =
* All stored names are now `sendsmsro_` / `Sendsmsro\\`-prefixed to satisfy WordPress.org Plugin Directory naming conventions. PHP namespace is `Sendsmsro\\ForWooCommerce`; the settings option is `sendsmsro_options`; the history table is `{prefix}sendsmsro_history`; the order opt-out meta key is `sendsmsro_optout`; the AJAX actions are `wp_ajax_sendsmsro_campaign` and `wp_ajax_sendsmsro_single`; extensibility hooks are now `sendsmsro_should_send`, `sendsmsro_message`, `sendsmsro_recipient_phone`, `sendsmsro_after_send`.
* Added an activation-time migration that copies the v1.x / v2.0.x stored data (settings option, price cache, history table, order opt-out meta) into the new names. Upgrading from a previous install carries over every setting and SMS-history row automatically.
* `register_setting()` sanitize callback now applies per-field sanitization (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_key`, and recursive sanitization for the per-status template/flag arrays).
* `readme.txt` now includes an `== External services ==` section disclosing every sendsms.ro endpoint used, the data sent, and links to the gateway's terms / privacy / ISO certification.
* Added `sendsms` (the plugin's WordPress.org owner account) to the Contributors line.

= 2.0.1 =
* Settings page: fixed a bug where saving any tab wiped the values stored on the other tabs. Each tab now merges into the existing option instead of overwriting it.
* Order metabox: the Phone field is now pre-filled with the order's billing phone, and is no longer cleared after a successful send so follow-up messages don't require retyping the number.
* Settings page: added cell padding on the per-status template table so column headers and labels are no longer flush against the edges.

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
