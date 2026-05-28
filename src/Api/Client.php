<?php
/**
 * sendsms.ro JSON API client.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Api;

use Rosendsms\ForWooCommerce\Storage\HistoryRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Thin HTTP wrapper over https://api.sendsms.ro/json.
 *
 * The URL shapes here are FIXED by the upstream API and must not change without
 * coordinating with sendsms.ro. All endpoints accept credentials as URL query
 * parameters; this is required by the upstream and is not something this plugin
 * can move to POST bodies or Authorization headers.
 *
 * Callers are responsible for recording outcomes to {@see HistoryRepository}.
 */
final class Client {

	/**
	 * Base URL of the upstream API.
	 */
	const BASE_URL = 'https://api.sendsms.ro/json';

	/**
	 * Cache option for the per-SMS price.
	 */
	const PRICE_OPTION = 'rosendsms_default_price';

	/**
	 * When the cached price should be refreshed next.
	 */
	const PRICE_TIME_OPTION = 'rosendsms_default_price_time';

	/**
	 * History repository. Kept for callers that want the same instance.
	 *
	 * @var HistoryRepository
	 */
	private $history;

	/**
	 * @param HistoryRepository $history Shared history repository.
	 */
	public function __construct( HistoryRepository $history ) {
		$this->history = $history;
	}

	/**
	 * History repository accessor (callers record their own rows).
	 *
	 * @return HistoryRepository
	 */
	public function history(): HistoryRepository {
		return $this->history;
	}

	// ------------------------------------------------------------------------
	// Endpoints.
	// ------------------------------------------------------------------------

	/**
	 * GET user_get_balance.
	 *
	 * @param string $username sendsms.ro username.
	 * @param string $password sendsms.ro password/API key.
	 * @return Response
	 */
	public function get_balance( string $username, string $password ): Response {
		$url = self::BASE_URL . '?' . self::build_query(
			array(
				'action'   => 'user_get_balance',
				'username' => $username,
				'password' => $password,
			)
		);
		return self::decode( wp_remote_get( $url ) );
	}

	/**
	 * GET message_send (or message_send_gdpr when append_unsubscribe is true).
	 *
	 * @param string $username             sendsms.ro username.
	 * @param string $password             sendsms.ro password/API key.
	 * @param string $from                 Sender label.
	 * @param string $phone                Destination phone (E.164 digits, no '+').
	 * @param string $text                 Message body.
	 * @param bool   $short                Pass true to short-URL-fy http(s) links.
	 * @param bool   $append_unsubscribe   Append the GDPR confirmation link.
	 * @return Response
	 */
	public function send_message(
		string $username,
		string $password,
		string $from,
		string $phone,
		string $text,
		bool $short = false,
		bool $append_unsubscribe = false
	): Response {
		$action = $append_unsubscribe ? 'message_send_gdpr' : 'message_send';
		$url    = self::BASE_URL . '?' . self::build_query(
			array(
				'action'   => $action,
				'username' => $username,
				'password' => $password,
				'from'     => $from,
				'to'       => trim( $phone ),
				'text'     => $text,
				'short'    => $short ? 'true' : 'false',
			)
		);

		// Preserve the legacy "url: <site_url>" header. The upstream API treats this as identifying metadata.
		$args = array(
			'headers' => array( 'url' => get_site_url() ),
		);

		return self::decode( wp_remote_get( $url, $args ) );
	}

	/**
	 * GET route_check_price.
	 *
	 * @param string $username sendsms.ro username.
	 * @param string $password sendsms.ro password/API key.
	 * @param string $phone    Destination phone.
	 * @return Response
	 */
	public function route_check_price( string $username, string $password, string $phone ): Response {
		$url = self::BASE_URL . '?' . self::build_query(
			array(
				'action'   => 'route_check_price',
				'username' => $username,
				'password' => $password,
				'to'       => $phone,
			)
		);
		$args = array(
			'headers' => array( 'url' => get_site_url() ),
		);
		return self::decode( wp_remote_get( $url, $args ) );
	}

	/**
	 * Refresh the cached default per-SMS price if the cache has expired.
	 *
	 * Cached for 24h, behaviour preserved from v1.x.
	 *
	 * @param string $username sendsms.ro username.
	 * @param string $password sendsms.ro password/API key.
	 * @param string $phone    Destination phone to price.
	 * @return void
	 */
	public function refresh_price_cache_if_due( string $username, string $password, string $phone ): void {
		$expires = (string) get_option( self::PRICE_TIME_OPTION, '' );
		if ( '' !== $expires && $expires >= gmdate( 'Y-m-d H:i:s' ) ) {
			return;
		}
		$response = $this->route_check_price( $username, $password, $phone );
		if ( is_array( $response->details ) && isset( $response->details['status'], $response->details['cost'] ) && 64 === (int) $response->details['status'] ) {
			update_option( self::PRICE_OPTION, $response->details['cost'] );
			update_option( self::PRICE_TIME_OPTION, gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ) );
		}
	}

	/**
	 * POST batch_create with an inline CSV body.
	 *
	 * @param string $username   sendsms.ro username.
	 * @param string $password   sendsms.ro password/API key.
	 * @param string $start_time MySQL-format datetime to begin sending.
	 * @param string $name       Human-readable batch name.
	 * @param string $csv_body   The CSV payload (header row: message,to,from).
	 * @return Response
	 */
	public function create_batch( string $username, string $password, string $start_time, string $name, string $csv_body ): Response {
		$url = self::BASE_URL . '?' . self::build_query(
			array(
				'action'     => 'batch_create',
				'username'   => $username,
				'password'   => $password,
				'start_time' => $start_time,
				'name'       => $name,
			)
		);

		$args = array(
			'body' => array( 'data' => $csv_body ),
		);

		return self::decode( wp_remote_post( $url, $args ) );
	}

	// ------------------------------------------------------------------------
	// Helpers.
	// ------------------------------------------------------------------------

	/**
	 * Build an URL-encoded query string in a fixed key order so tests are stable.
	 *
	 * @param array $params Param array.
	 * @return string
	 */
	private static function build_query( array $params ): string {
		// http_build_query handles urlencoding of values; preserves key order.
		return http_build_query( $params, '', '&', PHP_QUERY_RFC1738 );
	}

	/**
	 * Decode a wp_remote_* response into our DTO.
	 *
	 * @param array|\WP_Error $http Result of `wp_remote_get`/`wp_remote_post`.
	 * @return Response
	 */
	private static function decode( $http ): Response {
		if ( is_wp_error( $http ) ) {
			return new Response( null );
		}
		$body = wp_remote_retrieve_body( $http );
		if ( '' === $body ) {
			return new Response( null );
		}
		$decoded = json_decode( $body, true );
		return new Response( is_array( $decoded ) ? $decoded : null );
	}
}
