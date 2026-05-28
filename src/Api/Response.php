<?php
/**
 * Decoded sendsms.ro JSON response.
 *
 * @package Sendsmsro\ForWooCommerce
 */

namespace Sendsmsro\ForWooCommerce\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Thin DTO over a decoded JSON response.
 *
 * The sendsms.ro API returns objects of the shape:
 *  - status:  integer code. >= 0 means success; negative is failure.
 *  - message: short label, usually "Sent" on success.
 *  - details: optional. Sometimes a string, sometimes a nested object
 *             (e.g. route_check_price returns {status, cost}).
 *
 * Treat {@see $raw} as the source of truth; the accessors are conveniences.
 */
final class Response {

	/**
	 * The full decoded JSON body. May be empty if the body did not decode.
	 *
	 * @var array
	 */
	public $raw;

	/**
	 * Status integer from the API. -1 if missing.
	 *
	 * @var int
	 */
	public $status_code;

	/**
	 * Short message string.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Details payload — string or array depending on the endpoint.
	 *
	 * @var mixed
	 */
	public $details;

	/**
	 * Whether the call succeeded (`status >= 0`).
	 *
	 * @var bool
	 */
	public $success;

	/**
	 * Build from a decoded JSON body.
	 *
	 * @param array|null $decoded Decoded JSON body (or null on transport error).
	 */
	public function __construct( $decoded ) {
		$this->raw         = is_array( $decoded ) ? $decoded : array();
		$this->status_code = isset( $this->raw['status'] ) ? (int) $this->raw['status'] : -1;
		$this->message     = isset( $this->raw['message'] ) ? (string) $this->raw['message'] : '';
		$this->details     = $this->raw['details'] ?? null;
		$this->success     = $this->status_code >= 0;
	}

	/**
	 * Render details as a string for the history table's "details" column.
	 *
	 * @return string
	 */
	public function details_as_string(): string {
		if ( null === $this->details ) {
			return '';
		}
		if ( is_scalar( $this->details ) ) {
			return (string) $this->details;
		}
		$json = wp_json_encode( $this->details );
		return false === $json ? '' : $json;
	}
}
