<?php
/**
 * WP_List_Table for the SMS history.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Admin;

use Rosendsms\ForWooCommerce\Storage\HistoryRepository;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

/**
 * History list table.
 *
 * All output is run through `esc_html()` to defend against stored XSS coming
 * out of attacker-influenced columns (e.g. SMS body containing customer names).
 */
final class HistoryTable extends WP_List_Table {

	/**
	 * @var HistoryRepository
	 */
	private $history;

	/**
	 * @param HistoryRepository $history Shared history repository.
	 */
	public function __construct( HistoryRepository $history ) {
		parent::__construct(
			array(
				'singular' => 'sms',
				'plural'   => 'sms',
				'ajax'     => false,
			)
		);
		$this->history = $history;
	}

	/**
	 * Column definitions.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'id'      => __( 'ID', 'sendsms-for-woocommerce' ),
			'phone'   => __( 'Phone', 'sendsms-for-woocommerce' ),
			'status'  => __( 'Status', 'sendsms-for-woocommerce' ),
			'message' => __( 'Answer', 'sendsms-for-woocommerce' ),
			'details' => __( 'Details', 'sendsms-for-woocommerce' ),
			'content' => __( 'Content', 'sendsms-for-woocommerce' ),
			'type'    => __( 'Type', 'sendsms-for-woocommerce' ),
			'sent_on' => __( 'Date', 'sendsms-for-woocommerce' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array{0:string,1:bool}>
	 */
	public function get_sortable_columns(): array {
		return array(
			'id'      => array( 'id', false ),
			'phone'   => array( 'phone', false ),
			'status'  => array( 'status', false ),
			'message' => array( 'message', false ),
			'details' => array( 'details', false ),
			'content' => array( 'content', false ),
			'type'    => array( 'type', false ),
			'sent_on' => array( 'sent_on', true ),
		);
	}

	/**
	 * Default column renderer. Every value is escaped.
	 *
	 * @param array<string,mixed> $item        Row data.
	 * @param string              $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Populate the table from the repository.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table search/sort.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table search/sort.
		$orderby_raw = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'id';
		$orderby     = in_array( $orderby_raw, HistoryRepository::sortable_columns(), true ) ? $orderby_raw : 'id';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table search/sort.
		$order_raw = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';
		$order     = in_array( strtoupper( $order_raw ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $order_raw ) : 'DESC';

		$current_page = (int) $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$total_items = $this->history->count( $search );
		$this->items = $this->history->query(
			array(
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'offset'   => $offset,
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}
