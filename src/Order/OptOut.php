<?php
/**
 * Checkout opt-out checkbox + order meta.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Order;

use Rosendsms\ForWooCommerce\Plugin;
use Rosendsms\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the "I don't want SMS" checkbox at checkout and persists the choice
 * to order meta as `rosendsms_optout` (the legacy v1.x `wc_sendsms_optout`
 * meta is renamed on activation by {@see Install::migrate_from_v1()}).
 */
final class OptOut {

	/**
	 * Order meta key. Read by {@see StatusListener::on_status_changed()}.
	 */
	const META_KEY = 'rosendsms_optout';

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

		echo '<div class="rosendsms-optout-wrap">';
		woocommerce_form_field(
			'rosendsms_optout',
			array(
				'type'  => 'checkbox',
				'class' => array( 'input-checkbox', 'form-row-wide' ),
				'label' => __( 'I do not want to receive SMS notifications about this order.', 'sendsms-for-woocommerce' ),
			),
			$checkout->get_value( 'rosendsms_optout' )
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
		if ( ! isset( $_POST['rosendsms_optout'] ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$order->update_meta_data( self::META_KEY, (string) $_POST['rosendsms_optout'] ? 1 : 0 );
		$order->save();
	}
}
