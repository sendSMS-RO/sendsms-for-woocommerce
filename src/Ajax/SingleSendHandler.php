<?php
/**
 * Single-send AJAX handler (order edit metabox).
 *
 * @package SendSMS\ForWooCommerce
 */

namespace SendSMS\ForWooCommerce\Ajax;

use SendSMS\ForWooCommerce\Api\Client;
use SendSMS\ForWooCommerce\Order\PhoneNumber;
use SendSMS\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the AJAX call fired by the order-edit metabox.
 *
 * Action: `wp_ajax_wc_sendsms_single` (action name preserved from v1.x).
 *
 * Nonce: `wc_sendsms_send_single` (request field `security`).
 * Capability: `manage_woocommerce` (shop managers + administrators).
 */
final class SingleSendHandler {

	const ACTION       = 'wc_sendsms_single';
	const NONCE_ACTION = 'wc_sendsms_send_single';

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
	 * @return void
	 */
	public function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'security' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( '', '', 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer above.
		$order_id     = isset( $_POST['order'] )   ? absint( $_POST['order'] )                                                    : 0;
		$raw_phone    = isset( $_POST['phone'] )   ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )                         : '';
		$content      = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) )                   : '';
		$short        = isset( $_POST['short'] )   ? filter_var( sanitize_text_field( wp_unslash( $_POST['short'] ) ), FILTER_VALIDATE_BOOLEAN ) : false;
		$gdpr         = isset( $_POST['gdpr'] )    ? filter_var( sanitize_text_field( wp_unslash( $_POST['gdpr'] ) ),  FILTER_VALIDATE_BOOLEAN ) : false;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 0 === $order_id || '' === $raw_phone || '' === $content ) {
			echo esc_html__( 'You must complete the message and a phone number.', 'sendsms-for-woocommerce' );
			wp_die();
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			echo esc_html__( 'Invalid order.', 'sendsms-for-woocommerce' );
			wp_die();
		}
		if ( ! $this->settings->has_credentials() ) {
			echo esc_html__( 'The plugin is not configured. Please set your sendsms.ro credentials.', 'sendsms-for-woocommerce' );
			wp_die();
		}

		$phone = PhoneNumber::normalize( $raw_phone, $this->settings->country_code() );
		if ( '' === $phone ) {
			echo esc_html__( 'The validated phone number is empty.', 'sendsms-for-woocommerce' );
			wp_die();
		}

		$response = $this->api->send_message(
			$this->settings->username(),
			$this->settings->password(),
			$this->settings->from(),
			$phone,
			$content,
			$short,
			$gdpr
		);

		$this->api->history()->record(
			array(
				'phone'   => $phone,
				'status'  => (string) $response->status_code,
				'message' => $response->message,
				'details' => $response->details_as_string(),
				'content' => $content,
				'type'    => 'single order',
			)
		);
		$this->api->refresh_price_cache_if_due( $this->settings->username(), $this->settings->password(), $phone );

		$order->add_order_note(
			sprintf(
				/* translators: 1: phone number, 2: message body. */
				__( 'SMS message sent to %1$s: %2$s', 'sendsms-for-woocommerce' ),
				$phone,
				$content
			)
		);

		echo esc_html__( 'The message was sent.', 'sendsms-for-woocommerce' );
		wp_die();
	}
}
