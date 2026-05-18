<?php
/**
 * Plugin Name:       SendSMS for WooCommerce
 * Plugin URI:        https://www.sendsms.ro/ro/ecommerce/plugin-woocommerce/
 * Description:       Send SMS notifications to your customers on every WooCommerce order status change. Per-status templates, campaign sender, single-order SMS, full history.
 * Version:           2.0.1
 * Requires at least: 4.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            sendSMS
 * Author URI:        https://www.sendsms.ro/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sendsms-for-woocommerce
 * Domain Path:       /languages
 *
 * @package SendSMS\ForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

// Plugin metadata constants.
define( 'SENDSMS_FWC_VERSION', '2.0.1' );
define( 'SENDSMS_FWC_FILE', __FILE__ );
define( 'SENDSMS_FWC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SENDSMS_FWC_URL', plugin_dir_url( __FILE__ ) );
define( 'SENDSMS_FWC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 autoloader for SendSMS\ForWooCommerce\* under /src.
 *
 * @param string $class Fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'SendSMS\\ForWooCommerce\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = SENDSMS_FWC_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Declare HPOS (custom_order_tables) compatibility before WooCommerce initialises.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SENDSMS_FWC_FILE, true );
		}
	}
);

// Activation: create the history table and store the schema version.
register_activation_hook( SENDSMS_FWC_FILE, array( \SendSMS\ForWooCommerce\Install::class, 'activate' ) );

/**
 * Boot the plugin once all plugins are loaded (WooCommerce included).
 */
add_action(
	'plugins_loaded',
	static function () {
		// WooCommerce is mandatory. Without it, surface an admin notice and bail.
		if ( ! \SendSMS\ForWooCommerce\Plugin::woocommerce_is_active() ) {
			\SendSMS\ForWooCommerce\Plugin::add_missing_wc_notice();
			return;
		}
		\SendSMS\ForWooCommerce\Plugin::instance()->boot();
	}
);
