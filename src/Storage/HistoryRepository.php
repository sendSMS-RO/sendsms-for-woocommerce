<?php
/**
 * Read/write access to the {prefix}rosendsms_history table.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Storage;

use Rosendsms\ForWooCommerce\Install;

defined( 'ABSPATH' ) || exit;

/**
 * History repository.
 *
 * Schema (matches v1.x exactly):
 *   id, phone, status, message, details, content, type, sent_on
 */
final class HistoryRepository {

	/**
	 * Insert a single row.
	 *
	 * @param array $row Associative array with keys phone, status, message, details, content, type.
	 *                   sent_on is filled with `current_time('mysql')` automatically.
	 * @return int Inserted row id, or 0 on failure.
	 */
	public function record( array $row ): int {
		global $wpdb;

		$defaults = array(
			'phone'   => '',
			'status'  => '',
			'message' => '',
			'details' => '',
			'content' => '',
			'type'    => '',
		);
		$row      = array_merge( $defaults, $row );
		$row['sent_on'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin's own history table; no cache layer.
		$inserted = $wpdb->insert(
			Install::history_table(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Fetch rows for the admin History list table.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type string $search   Optional. Substring to match across all text columns.
	 *     @type string $orderby  Optional. Column name. Caller MUST whitelist this.
	 *     @type string $order    Optional. ASC|DESC.
	 *     @type int    $per_page Default 10.
	 *     @type int    $offset   Default 0.
	 * }
	 * @return array<int,array<string,string>>
	 */
	public function query( array $args ): array {
		global $wpdb;

		$search   = (string) ( $args['search']  ?? '' );
		$orderby  = (string) ( $args['orderby'] ?? 'id' );
		$order    = strtoupper( (string) ( $args['order'] ?? 'DESC' ) );
		$per_page = max( 1, (int) ( $args['per_page'] ?? 10 ) );
		$offset   = max( 0, (int) ( $args['offset']   ?? 0 ) );

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}
		if ( ! in_array( $orderby, self::sortable_columns(), true ) ) {
			$orderby = 'id';
		}

		$where_args = array();
		$where_sql  = ' WHERE 1 = 1';
		if ( '' !== $search ) {
			$like        = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql  .= ' AND (phone LIKE %s OR message LIKE %s OR content LIKE %s OR `type` LIKE %s OR details LIKE %s OR sent_on LIKE %s)';
			$where_args  = array( $like, $like, $like, $like, $like, $like );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $orderby/$order are whitelisted; table name uses $wpdb->prefix; values are placeholdered.
		$sql = "SELECT id, phone, status, message, details, content, `type`, sent_on
		        FROM " . Install::history_table() . $where_sql . "
		        ORDER BY `$orderby` $order
		        LIMIT %d OFFSET %d";

		$prepared = $wpdb->prepare( $sql, array_merge( $where_args, array( $per_page, $offset ) ) );
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count rows matching the same search expression.
	 *
	 * @param string $search Optional substring.
	 * @return int
	 */
	public function count( string $search = '' ): int {
		global $wpdb;

		$where_args = array();
		$where_sql  = ' WHERE 1 = 1';
		if ( '' !== $search ) {
			$like        = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql  .= ' AND (phone LIKE %s OR message LIKE %s OR content LIKE %s OR `type` LIKE %s OR details LIKE %s OR sent_on LIKE %s)';
			$where_args  = array( $like, $like, $like, $like, $like, $like );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name uses $wpdb->prefix; values are placeholdered.
		$sql = 'SELECT COUNT(id) FROM ' . Install::history_table() . $where_sql;
		$value = empty( $where_args )
			? $wpdb->get_var( $sql )
			: $wpdb->get_var( $wpdb->prepare( $sql, $where_args ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

		return (int) $value;
	}

	/**
	 * Whitelisted sortable columns for {@see query()}.
	 *
	 * @return string[]
	 */
	public static function sortable_columns(): array {
		return array( 'id', 'phone', 'status', 'message', 'details', 'content', 'type', 'sent_on' );
	}
}
