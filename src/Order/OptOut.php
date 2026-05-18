<?php
/**
 * Checkout opt-out checkbox + order meta.
 *
 * @package SendSMS\ForWooCommerce
 */

namespace SendSMS\ForWooCommerce\Order;

use SendSMS\ForWooCommerce\Plugin;
use SendSMS\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "I don't want SMS" checkbox at checkout and persists the choice
 * to order meta as `wc_sendsms_optout` (preserved from v1.x).
 */
final class OptOut {

	/**
	 * Order meta key. Read by {@see StatusListener::on_status_changed()}.
	 */
	const META_KEY = 'wc_sendsms_optout';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_checkbox' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_choice' ) );
	}

	/**
	 * Render the checkbox below the order-notes textarea at checkout.
	 *
	 * @param \WC_Checkout $checkout Checkout instance.
	 * @return void
	 */
	public function render_checkbox( $checkout ): void {
		$settings = Plugin::instance()->settings();
		if ( ! $settings instanceof Settings ) {
			return;
		}
		if ( ! $settings->checkout_optout_enabled() ) {
			return;
		}

		echo '<div class="sendsms-fwc-optout-wrap">';
		woocommerce_form_field(
			'wc_sendsms_optout',
			array(
				'type'  => 'checkbox',
				'class' => array( 'input-checkbox', 'form-row-wide' ),
				'label' => __( 'I do not want to receive SMS notifications about this order.', 'sendsms-for-woocommerce' ),
			),
			$checkout->get_value( 'wc_sendsms_optout' )
		);
		echo '</div><div style="clear: both">&nbsp;</div>';
	}

	/**
	 * Persist the opt-out flag onto the order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function save_choice( int $order_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WooCommerce checkout.
		if ( ! isset( $_POST['wc_sendsms_optout'] ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$order->update_meta_data( self::META_KEY, (string) $_POST['wc_sendsms_optout'] ? 1 : 0 );
		$order->save();
	}
}
