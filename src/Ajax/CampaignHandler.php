<?php
/**
 * Campaign send AJAX handler.
 *
 * @package SendSMS\ForWooCommerce
 */

namespace SendSMS\ForWooCommerce\Ajax;

use SendSMS\ForWooCommerce\Api\Client;
use SendSMS\ForWooCommerce\Order\Query;
use SendSMS\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the AJAX call fired by the Campaign page "Send the message" button.
 *
 * Action: `wp_ajax_wc_sendsms_campaign` (action name preserved from v1.x).
 *
 * Nonce: `wc_sendsms_send_campaign` (request field `security`).
 * Capability: `manage_options`.
 */
final class CampaignHandler {

	const ACTION       = 'wc_sendsms_campaign';
	const NONCE_ACTION = 'wc_sendsms_send_campaign';

	/** @var Settings */
	private $settings;

	/** @var Client */
	private $api;

	/**
	 * @param Settings $settings Settings reader.
	 * @param Client   $api      API client.
	 */
	public function __construct( Settings $settings, Client $api ) {
		$this->settings = $settings;
		$this->api      = $api;
	}

	/**
	 * Register the AJAX action.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handle the request.
	 *
	 * Echoes a plain-text result string and terminates via wp_die().
	 *
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', '', 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer above already verified the nonce.
		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
		if ( '' === $content ) {
			echo esc_html__( 'You must complete the message first.', 'sendsms-for-woocommerce' );
			wp_die();
		}

		$all       = isset( $_POST['all'] ) && 'true' === $_POST['all'];
		$filtering = isset( $_POST['filtering'] ) && 'true' === $_POST['filtering'];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$phones = $this->resolve_phone_list( $all, $filtering );
		if ( empty( $phones ) ) {
			echo esc_html__( 'You must choose at least one phone number.', 'sendsms-for-woocommerce' );
			wp_die();
		}

		if ( ! $this->settings->has_credentials() ) {
			echo esc_html__( 'The plugin is not configured. Please set your sendsms.ro credentials.', 'sendsms-for-woocommerce' );
			wp_die();
		}

		$csv  = $this->build_csv( $content, $this->settings->from(), $phones );
		$name = 'Wordpress - ' . get_site_url() . ' - ' . uniqid( '', false );

		$response = $this->api->create_batch(
			$this->settings->username(),
			$this->settings->password(),
			current_time( 'mysql' ),
			$name,
			$csv
		);

		if ( ! $response->success ) {
			echo wp_kses_post( wp_json_encode( $response->raw ) );
			wp_die();
		}

		$this->api->history()->record(
			array(
				'phone'   => esc_html__( 'Go to hub.sendsms.ro', 'sendsms-for-woocommerce' ),
				'status'  => (string) $response->status_code,
				'message' => $response->message,
				'details' => $response->details_as_string(),
				'content' => sprintf(
					/* translators: %s: batch name on the upstream gateway. */
					esc_html__( 'Campaign created. Batch name: %s', 'sendsms-for-woocommerce' ),
					$name
				),
				'type'    => 'Batch Campaign',
			)
		);

		echo esc_html__( 'Success', 'sendsms-for-woocommerce' );
		wp_die();
	}

	/**
	 * Resolve the phone list to send to.
	 *
	 * @param bool $send_to_all Whether to send to every phone matching the filter (true) or only the explicit selection (false).
	 * @param bool $filtering   Whether a filter is active.
	 * @return string[]
	 */
	private function resolve_phone_list( bool $send_to_all, bool $filtering ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- handle() already verified the nonce.
		if ( $send_to_all ) {
			$orders = $filtering ? $this->orders_for_filter() : Query::completed_orders();
			return Query::unique_phones( $orders, $this->settings->country_code() );
		}

		$raw = isset( $_POST['phones'] ) ? sanitize_text_field( wp_unslash( $_POST['phones'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' === $raw ) {
			return array();
		}
		return array_values( array_unique( array_filter( explode( '|', $raw ) ) ) );
	}

	/**
	 * Pull filter values from $_POST and run the order query.
	 *
	 * @return \WC_Order[]
	 */
	private function orders_for_filter(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- handle() already verified the nonce.
		$period_start = isset( $_POST['perioada_start'] ) ? sanitize_text_field( wp_unslash( $_POST['perioada_start'] ) ) : '';
		$period_end   = isset( $_POST['perioada_final'] ) ? sanitize_text_field( wp_unslash( $_POST['perioada_final'] ) ) : '';
		$min_amount   = isset( $_POST['suma'] )           ? sanitize_text_field( wp_unslash( $_POST['suma'] ) )           : '';
		$state_keys   = isset( $_POST['judete'] )         ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['judete'] ) )  : array();
		$product_keys = isset( $_POST['produse'] )        ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['produse'] ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return Query::filtered_completed_orders( $period_start, $period_end, $min_amount, $state_keys, $product_keys );
	}

	/**
	 * Build the RFC-4180 CSV body for batch_create.
	 *
	 * @param string   $message Outbound message body.
	 * @param string   $from    Sender label.
	 * @param string[] $phones  Recipients.
	 * @return string
	 */
	private function build_csv( string $message, string $from, array $phones ): string {
		$quote = static function ( $cell ) {
			return '"' . str_replace( '"', '""', (string) $cell ) . '"';
		};
		$data  = $quote( 'message' ) . ',' . $quote( 'to' ) . ',' . $quote( 'from' ) . "\r\n";
		foreach ( $phones as $phone ) {
			$data .= $quote( $message ) . ',' . $quote( $phone ) . ',' . $quote( $from ) . "\r\n";
		}
		return $data;
	}
}
