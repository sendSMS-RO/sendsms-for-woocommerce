<?php
/**
 * Activation hook, history-table schema management, and one-shot migration
 * of older-prefix data into the current `rosendsms_*` names.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce;

use Rosendsms\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates / migrates the {prefix}rosendsms_history table and copies any
 * older-prefix options + order meta over to the current names.
 *
 * Two earlier name schemes are migrated:
 *  - v1.x:    wc_sendsms_plugin_options / wcsendsms_history / wc_sendsms_optout / ...
 *  - v2.0.2:  sendsmsro_options / sendsmsro_history / sendsmsro_optout / ...
 *
 * Both schemes are migrated to the current rosendsms_ names. The migration is
 * gated by the schema-version option and safe to re-run.
 */
final class Install {

	/**
	 * Schema version stored in the `rosendsms_db_version` option.
	 *
	 * Bump this whenever a migration step is added or the table schema changes.
	 */
	const SCHEMA_VERSION = '2.0.3';

	const VERSION_OPTION = 'rosendsms_db_version';

	const NEW_TABLE_SUFFIX = 'rosendsms_history';
	const NEW_META_KEY     = 'rosendsms_optout';

	/**
	 * Migration sources, oldest first. Each entry describes one previous
	 * naming scheme that we know how to migrate to the current rosendsms_*
	 * names.
	 *
	 * @return array<int, array{
	 *     options: array<string,string>,
	 *     table:   string,
	 *     meta:    string,
	 *     version_option: string
	 * }>
	 */
	private static function legacy_schemes(): array {
		return array(
			// v1.x (the original legacy plugin).
			array(
				'options'        => array(
					'wc_sendsms_plugin_options'    => 'rosendsms_options',
					'wc-sendsms-default-price'     => 'rosendsms_default_price',
					'wc-sendsms-default-price-time' => 'rosendsms_default_price_time',
				),
				'version_option' => 'wc_sendsms_db_version',
				'table'          => 'wcsendsms_history',
				'meta'           => 'wc_sendsms_optout',
			),
			// v2.0.2 (the previous WP.org-rejected build).
			array(
				'options'        => array(
					'sendsmsro_options'              => 'rosendsms_options',
					'sendsmsro_default_price'        => 'rosendsms_default_price',
					'sendsmsro_default_price_time'   => 'rosendsms_default_price_time',
				),
				'version_option' => 'sendsmsro_db_version',
				'table'          => 'sendsmsro_history',
				'meta'           => 'sendsmsro_optout',
			),
		);
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::migrate_legacy_schemes();
		self::install_table();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Re-runs the install routine when the stored schema version doesn't match.
	 * Called from Plugin::boot() so an upgrade-via-zip flow gets the same migration.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		self::migrate_legacy_schemes();
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
	 * Apply every known legacy → current migration. Each step only writes when
	 * the target name is still empty / absent, so re-runs are no-ops.
	 *
	 * @return void
	 */
	private static function migrate_legacy_schemes(): void {
		foreach ( self::legacy_schemes() as $scheme ) {
			self::migrate_one_scheme( $scheme );
		}
	}

	/**
	 * Migrate a single naming scheme.
	 *
	 * @param array $scheme {
	 *     @type array<string,string> $options        Old option key → new option key.
	 *     @type string               $version_option Old schema-version option.
	 *     @type string               $table          Old history table suffix (without $wpdb->prefix).
	 *     @type string               $meta           Old order-meta key.
	 * }
	 * @return void
	 */
	private static function migrate_one_scheme( array $scheme ): void {
		global $wpdb;

		// Options.
		foreach ( $scheme['options'] as $old => $new ) {
			$legacy = get_option( $old );
			if ( false !== $legacy && false === get_option( $new, false ) ) {
				update_option( $new, $legacy );
			}
		}

		// Schema-version pointer.
		$legacy_version = get_option( $scheme['version_option'] );
		if ( false !== $legacy_version && false === get_option( self::VERSION_OPTION, false ) ) {
			update_option( self::VERSION_OPTION, $legacy_version );
		}

		// History table — rename if the legacy table exists and the new one doesn't.
		$legacy_table = $wpdb->prefix . $scheme['table'];
		$new_table    = $wpdb->prefix . self::NEW_TABLE_SUFFIX;
		if ( $legacy_table === $new_table ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is $wpdb->prefix plus a literal.
		$legacy_exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) ) === $legacy_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is $wpdb->prefix plus a literal.
		$new_exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) ) === $new_table;
		if ( $legacy_exists && ! $new_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table identifier cannot be parameterised; both names are built from $wpdb->prefix plus class constants.
			$wpdb->query( "RENAME TABLE `$legacy_table` TO `$new_table`" );
		}

		// Order meta — relabel both CPT (wp_postmeta) and HPOS (wp_wc_orders_meta) entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared statement.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				self::NEW_META_KEY,
				$scheme['meta']
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
				$scheme['meta']
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is already a prepared statement above.
			$wpdb->query( $sql );
		}
	}
}
