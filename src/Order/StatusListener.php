<?php
/**
 * Listens for woocommerce_order_status_changed and sends the customer SMS.
 *
 * @package SendSMS\ForWooCommerce
 */

namespace SendSMS\ForWooCommerce\Order;

use SendSMS\ForWooCommerce\Api\Client;
use SendSMS\ForWooCommerce\Storage\Settings;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Fires customer SMS notifications on every WooCommerce order status change
 * for which a template is configured + enabled.
 */
final class StatusListener {

	/**
	 * Settings reader.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * API client (and shared history repo).
	 *
	 * @var Client
	 */
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
	 * Register the WP hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
	}

	/**
	 * Hook callback.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $from       Previous status (without wc- prefix).
	 * @param string   $to         New status (without wc- prefix).
	 * @param WC_Order $order_obj  The order object (optional in older WC; we re-fetch defensively).
	 * @return void
	 */
	public function on_status_changed( int $order_id, string $from, string $to, $order_obj = null ): void {
		$order = $order_obj instanceof WC_Order ? $order_obj : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Customer opt-out short-circuit.
		if ( $order->get_meta( 'wc_sendsms_optout' ) ) {
			return;
		}

		if ( ! $this->settings->has_credentials() ) {
			return;
		}

		$wc_status = 'wc-' . $to;
		if ( ! $this->settings->is_status_enabled( $wc_status ) ) {
			return;
		}
		$template = $this->settings->template_for_status( $wc_status );
		if ( '' === $template ) {
			return;
		}

		$context = array(
			'type'   => 'order',
			'status' => $wc_status,
		);

		/**
		 * Filter: allow third-party code to suppress a status-change SMS.
		 *
		 * @param bool     $should_send Default true.
		 * @param WC_Order $order
		 * @param array    $context     Includes 'type' (order|new order|test|single order) and 'status'.
		 */
		if ( ! apply_filters( 'sendsms_for_woocommerce_should_send', true, $order, $context ) ) {
			return;
		}

		$message = PlaceholderReplacer::render( $template, $order );
		/**
		 * Filter: tweak the rendered SMS body before sending.
		 *
		 * @param string   $message
		 * @param WC_Order $order
		 * @param array    $context
		 */
		$message = (string) apply_filters( 'sendsms_for_woocommerce_message', $message, $order, $context );

		$phone = $this->resolve_phone( $order, $context );
		if ( '' === $phone ) {
			return;
		}

		$response = $this->api->send_message(
			$this->settings->username(),
			$this->settings->password(),
			$this->settings->from(),
			$phone,
			$message,
			$this->settings->uses_short_url( $wc_status ),
			$this->settings->appends_unsubscribe_link( $wc_status )
		);

		$this->api->history()->record(
			array(
				'phone'   => $phone,
				'status'  => (string) $response->status_code,
				'message' => $response->message,
				'details' => $response->details_as_string(),
				'content' => $message,
				'type'    => 'order',
			)
		);

		$this->api->refresh_price_cache_if_due(
			$this->settings->username(),
			$this->settings->password(),
			$phone
		);

		/**
		 * Action: fired after every successful or failed SMS send.
		 *
		 * @param array    $data    {phone, message, type, response}
		 * @param WC_Order $order
		 */
		do_action(
			'sendsms_for_woocommerce_after_send',
			array(
				'phone'    => $phone,
				'message'  => $message,
				'type'     => 'order',
				'response' => $response,
			),
			$order
		);
	}

	/**
	 * Choose the phone number to send to.
	 *
	 * Simulation mode short-circuits to the configured simulation number; otherwise the order's billing phone is used.
	 *
	 * @param WC_Order $order   The order.
	 * @param array    $context Context array (status, type).
	 * @return string Empty if no number is available.
	 */
	private function resolve_phone( WC_Order $order, array $context ): string {
		if ( $this->settings->simulation_enabled() ) {
			$raw = $this->settings->simulation_number();
		} else {
			$raw = (string) $order->get_billing_phone();
		}
		$phone = PhoneNumber::normalize( $raw, $this->settings->country_code() );

		/**
		 * Filter: re-target the recipient phone before sending.
		 *
		 * @param string   $phone   Normalised phone (digits only, with optional country prefix).
		 * @param WC_Order $order
		 * @param array    $context
		 */
		return (string) apply_filters( 'sendsms_for_woocommerce_recipient_phone', $phone, $order, $context );
	}
}
