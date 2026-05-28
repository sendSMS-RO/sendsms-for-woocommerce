<?php
/**
 * Sends an SMS to the shop owner on every new order, if enabled.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Order;

use Rosendsms\ForWooCommerce\Api\Client;
use Rosendsms\ForWooCommerce\Storage\Settings;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Owner notifications: hooks `woocommerce_new_order`.
 */
final class NewOrderListener {

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Client
	 */
	private $api;

	/**
	 * @param Settings $settings
	 * @param Client   $api
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
		add_action( 'woocommerce_new_order', array( $this, 'on_new_order' ), 10, 2 );
	}

	/**
	 * Hook callback.
	 *
	 * @param int      $order_id  New order ID.
	 * @param WC_Order $order_obj Order object (older WC may not pass this).
	 * @return void
	 */
	public function on_new_order( int $order_id, $order_obj = null ): void {
		if ( ! $this->settings->owner_notification_enabled() ) {
			return;
		}
		if ( ! $this->settings->has_credentials() ) {
			return;
		}

		$order = $order_obj instanceof WC_Order ? $order_obj : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$context = array( 'type' => 'new order' );

		if ( ! apply_filters( 'rosendsms_should_send', true, $order, $context ) ) {
			return;
		}

		$message = PlaceholderReplacer::render( $this->settings->owner_template(), $order );
		$message = (string) apply_filters( 'rosendsms_message', $message, $order, $context );

		$phone = PhoneNumber::normalize( $this->settings->owner_phone(), $this->settings->country_code() );
		$phone = (string) apply_filters( 'rosendsms_recipient_phone', $phone, $order, $context );
		if ( '' === $phone ) {
			return;
		}

		$response = $this->api->send_message(
			$this->settings->username(),
			$this->settings->password(),
			$this->settings->from(),
			$phone,
			$message,
			$this->settings->owner_uses_short_url(),
			$this->settings->owner_appends_unsubscribe_link()
		);

		$this->api->history()->record(
			array(
				'phone'   => $phone,
				'status'  => (string) $response->status_code,
				'message' => $response->message,
				'details' => $response->details_as_string(),
				'content' => $message,
				'type'    => 'new order',
			)
		);

		$this->api->refresh_price_cache_if_due(
			$this->settings->username(),
			$this->settings->password(),
			$phone
		);

		do_action(
			'rosendsms_after_send',
			array(
				'phone'    => $phone,
				'message'  => $message,
				'type'     => 'new order',
				'response' => $response,
			),
			$order
		);
	}
}
