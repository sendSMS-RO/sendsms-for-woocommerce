<?php
/**
 * "Send SMS" metabox on the order edit screen.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Admin;

use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Side metabox on the shop order edit screen with a single-send form.
 *
 * Registered on both the legacy shop_order CPT screen and the modern HPOS
 * `wc-orders` screen so it appears on every WooCommerce install regardless of
 * the order storage backend.
 *
 * The form submits via AJAX → {@see \Rosendsms\ForWooCommerce\Ajax\SingleSendHandler}.
 */
final class OrderMetabox {

	const METABOX_ID = 'rosendsms_order_metabox';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
	}

	/**
	 * Register the metabox on whichever order screen is active.
	 *
	 * @return void
	 */
	public function add_metabox(): void {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$hpos_screen = wc_get_page_screen_id( 'shop-order' );
			if ( $hpos_screen && 'shop_order' !== $hpos_screen ) {
				$screens[] = $hpos_screen;
			}
		}
		add_meta_box(
			self::METABOX_ID,
			__( 'Send SMS', 'sendsms-for-woocommerce' ),
			array( $this, 'render' ),
			$screens,
			'side',
			'high'
		);
	}

	/**
	 * Metabox renderer.
	 *
	 * @param WP_Post|WC_Order $post_or_order Either a WP_Post (legacy) or a WC_Order (HPOS).
	 * @return void
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( (int) ( $post_or_order->ID ?? 0 ) );
		if ( ! $order ) {
			return;
		}
		$order_id      = $order->get_id();
		$billing_phone = (string) $order->get_billing_phone();
		?>
		<div class="rosendsms-metabox">
			<input type="hidden" id="rosendsms-order-id" value="<?php echo esc_attr( (string) $order_id ); ?>" />

			<p>
				<label for="rosendsms-mb-phone"><?php esc_html_e( 'Phone', 'sendsms-for-woocommerce' ); ?></label><br />
				<input type="text" id="rosendsms-mb-phone" style="width: 100%;" value="<?php echo esc_attr( $billing_phone ); ?>" />
			</p>

			<p>
				<label><input type="checkbox" id="rosendsms-mb-short" /> <?php esc_html_e( 'Short URL', 'sendsms-for-woocommerce' ); ?></label>
			</p>

			<p>
				<label><input type="checkbox" id="rosendsms-mb-gdpr" /> <?php esc_html_e( 'Append unsubscribe link', 'sendsms-for-woocommerce' ); ?></label>
			</p>

			<p>
				<label for="rosendsms-mb-content"><?php esc_html_e( 'Message', 'sendsms-for-woocommerce' ); ?></label><br />
				<textarea id="rosendsms-mb-content" class="rosendsms-content" style="width: 100%; height: 100px;"></textarea>
				<small class="description rosendsms-length-counter"><?php esc_html_e( 'The field is empty.', 'sendsms-for-woocommerce' ); ?></small>
			</p>

			<p>
				<button type="button" class="button button-primary" id="rosendsms-mb-send"><?php esc_html_e( 'Send the message', 'sendsms-for-woocommerce' ); ?></button>
			</p>
		</div>
		<?php
	}
}
