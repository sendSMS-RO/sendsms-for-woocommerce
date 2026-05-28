<?php
/**
 * "SendSMS" top-level landing page.
 *
 * @package Sendsmsro\ForWooCommerce
 */

namespace Sendsmsro\ForWooCommerce\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Static informational landing page.
 */
final class MainPage {

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap sendsmsro-page">
			<h1><?php esc_html_e( 'SendSMS for WooCommerce', 'sendsms-for-woocommerce' ); ?></h1>

			<p><?php esc_html_e( 'To use the plugin, enter your sendsms.ro credentials on the Configuration page.', 'sendsms-for-woocommerce' ); ?></p>
			<p>
				<?php esc_html_e( "Don't have a sendSMS account?", 'sendsms-for-woocommerce' ); ?>
				<a href="https://www.sendsms.ro/ro" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sign up for free here', 'sendsms-for-woocommerce' ); ?></a>.
			</p>

			<h2><?php esc_html_e( 'How status notifications work', 'sendsms-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'On the Configuration page, under Customer notifications, you will find one template per WooCommerce order status. Fill in only the statuses you want to notify on; leave the rest empty. Each enabled template fires when an order transitions into that status.', 'sendsms-for-woocommerce' ); ?></p>
			<p>
				<?php esc_html_e( 'Example: to notify customers when their order is completed, enable the Completed template and write something like:', 'sendsms-for-woocommerce' ); ?>
				<br />
				<strong><?php esc_html_e( 'Hi {billing_first_name}, your order {order_number} has been completed. Thank you!', 'sendsms-for-woocommerce' ); ?></strong>
			</p>

			<h3><?php esc_html_e( 'Available placeholders', 'sendsms-for-woocommerce' ); ?></h3>
			<ul style="list-style: disc; padding-left: 1.5em;">
				<li><code>{billing_first_name}</code>, <code>{billing_last_name}</code></li>
				<li><code>{shipping_first_name}</code>, <code>{shipping_last_name}</code></li>
				<li><code>{order_number}</code>, <code>{order_date}</code>, <code>{order_total}</code></li>
			</ul>

			<p style="text-align: center; margin-top: 2em;">
				<a href="https://www.sendsms.ro/" target="_blank" rel="noopener noreferrer">
					<img src="<?php echo esc_url( SENDSMSRO_URL . 'images/sendsms_logo.png' ); ?>" alt="sendSMS" />
				</a>
			</p>
		</div>
		<?php
	}
}
