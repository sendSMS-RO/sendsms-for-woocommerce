<?php
/**
 * Activation hook, history-table schema management, and one-shot migration
 * of v1.x data into the new prefixed names.
 *
 * @package Sendsmsro\ForWooCommerce
 */

namespace Sendsmsro\ForWooCommerce;

use Sendsmsro\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates / migrates the {prefix}sendsmsro_history table and copies legacy
 * v1.x options + order meta to the new namespace-safe names.
 *
 * v1.x stored:
 *   - option   wc_sendsms_plugin_options
 *   - option   wc_sendsms_db_version
 *   - option   wc-sendsms-default-price
 *   - option   wc-sendsms-default-price-time
 *   - table    {prefix}wcsendsms_history
 *   - meta_key wc_sendsms_optout (order meta)
 *
 * v2.0.2+ uses the corresponding sendsmsro_* names. The migration is one-shot
 * (gated by the schema-version option) and safe to re-run.
 */
final class Install {

	/**
	 * Schema version stored in the `sendsmsro_db_version` option.
	 *
	 * Bump this whenever a migration step is added or the table schema changes.
	 */
	const SCHEMA_VERSION = '2.0.2';

	const VERSION_OPTION        = 'sendsmsro_db_version';
	const LEGACY_VERSION_OPTION = 'wc_sendsms_db_version';

	const LEGACY_TABLE_SUFFIX = 'wcsendsms_history';
	const NEW_TABLE_SUFFIX    = 'sendsmsro_history';

	const LEGACY_META_KEY = 'wc_sendsms_optout';
	const NEW_META_KEY    = 'sendsmsro_optout';

	/**
	 * Activation hook. Migrates v1.x data, creates the history table, stamps the schema version.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::migrate_from_v1();
		self::install_table();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Re-runs the install routine when the stored schema version doesn't match.
	 * Hooked from Plugin::boot() so an upgrade-via-zip flow gets the same migration.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		self::migrate_from_v1();
		self::install_table();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * The fully-qualified history table name. Subsystems read this instead of hard-coding the suffix.
	 *
	 * @return string
	 */
	public static function history_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::NEW_TABLE_SUFFIX;
	}

	/**
	 * Create or upgrade the history table with `dbDelta`.
	 *
	 * @return void
	 */
	private static function install_table(): void {
		global $wpdb;

		$table_name      = self::history_table();
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
	 * Copy v1.x data into the new namespace-safe names. Safe to run multiple times;
	 * never overwrites an already-migrated value.
	 *
	 * @return void
	 */
	private static function migrate_from_v1(): void {
		global $wpdb;

		// 1. Settings option.
		$legacy_options = get_option( 'wc_sendsms_plugin_options' );
		if ( is_array( $legacy_options ) && false === get_option( Settings::OPTION_KEY, false ) ) {
			update_option( Settings::OPTION_KEY, $legacy_options );
		}

		// 2. Price-cache options.
		$legacy_price = get_option( 'wc-sendsms-default-price' );
		if ( false !== $legacy_price && false === get_option( 'sendsmsro_default_price', false ) ) {
			update_option( 'sendsmsro_default_price', $legacy_price );
		}
		$legacy_price_time = get_option( 'wc-sendsms-default-price-time' );
		if ( false !== $legacy_price_time && false === get_option( 'sendsmsro_default_price_time', false ) ) {
			update_option( 'sendsmsro_default_price_time', $legacy_price_time );
		}

		// 3. db_version pointer.
		$legacy_version = get_option( self::LEGACY_VERSION_OPTION );
		if ( false !== $legacy_version && false === get_option( self::VERSION_OPTION, false ) ) {
			update_option( self::VERSION_OPTION, $legacy_version );
		}

		// 4. History table — rename if the legacy table exists and the new one doesn't.
		$legacy_table = $wpdb->prefix . self::LEGACY_TABLE_SUFFIX;
		$new_table    = $wpdb->prefix . self::NEW_TABLE_SUFFIX;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$legacy_exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) ) === $legacy_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$new_exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) ) === $new_table;
		if ( $legacy_exists && ! $new_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table identifier cannot be parameterised; both names are built from $wpdb->prefix plus class constants.
			$wpdb->query( "RENAME TABLE `$legacy_table` TO `$new_table`" );
		}

		// 5. Order meta — relabel both CPT (wp_postmeta) and HPOS (wp_wc_orders_meta) entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				self::NEW_META_KEY,
				self::LEGACY_META_KEY
			)
		);

		$hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is $wpdb->prefix plus a literal.
		$hpos_exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_meta_table ) ) === $hpos_meta_table;
		if ( $hpos_exists ) {
			$sql = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table identifier cannot be parameterised; built from $wpdb->prefix + a literal verified above.
				"UPDATE `$hpos_meta_table` SET meta_key = %s WHERE meta_key = %s",
				self::NEW_META_KEY,
				self::LEGACY_META_KEY
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is already a prepared statement above.
			$wpdb->query( $sql );
		}
	}
}
