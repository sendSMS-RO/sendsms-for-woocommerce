<?php
/**
 * Activation hook + idempotent upgrade routine.
 *
 * @package SendSMS\ForWooCommerce
 */

namespace SendSMS\ForWooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and migrates the {prefix}wcsendsms_history table.
 *
 * Table and option names match v1.x exactly so upgrading installs see no
 * settings/history loss.
 */
final class Install {

	/**
	 * Schema version stored in the `wc_sendsms_db_version` option.
	 *
	 * Bump this whenever the table schema changes.
	 */
	const SCHEMA_VERSION = '2.0.0';

	/**
	 * Option name that holds the installed schema version.
	 */
	const VERSION_OPTION = 'wc_sendsms_db_version';

	/**
	 * Activation hook. Fires once when the plugin is activated.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::install_table();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Re-run the install if the schema version doesn't match. Hooked on plugins_loaded.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		self::install_table();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Create/upgrade the history table via dbDelta.
	 *
	 * @return void
	 */
	private static function install_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'wcsendsms_history';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `$table_name` (
			`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			`phone` varchar(255) DEFAULT NULL,
			`status` varchar(255) DEFAULT NULL,
			`message` varchar(255) DEFAULT NULL,
			`details` longtext,
			`content` longtext,
			`type` varchar(255) DEFAULT NULL,
			`sent_on` datetime DEFAULT NULL,
			PRIMARY KEY (`id`)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * The fully-qualified table name. Subsystems use this instead of hard-coding.
	 *
	 * @return string
	 */
	public static function history_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wcsendsms_history';
	}
}
