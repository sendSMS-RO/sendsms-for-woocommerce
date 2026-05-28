<?php
/**
 * "Send a test SMS" admin page.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Admin\Pages;

use Rosendsms\ForWooCommerce\Admin\Menu;
use Rosendsms\ForWooCommerce\Api\Client;
use Rosendsms\ForWooCommerce\Order\PhoneNumber;
use Rosendsms\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Test-send page. Sends a single SMS to any number from a self-submitted form.
 */
final class TestPage {

	const NONCE_ACTION = 'rosendsms_test_send';

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
	 * Render page (and process form submission inline).
	 *
	 * @return void
	 */
	public function render(): void {
		$this->maybe_handle_submission();
		?>
		<div class="wrap rosendsms-page">
			<h1><?php esc_html_e( 'SendSMS — Send a test', 'sendsms-for-woocommerce' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="rosendsms-test-phone"><?php esc_html_e( 'Phone number', 'sendsms-for-woocommerce' ); ?></label>
							</th>
							<td>
								<input id="rosendsms-test-phone" type="text" name="rosendsms_test_phone" style="width: 400px;" required />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Short URL?', 'sendsms-for-woocommerce' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="rosendsms_test_short_url" value="1" />
									<?php esc_html_e( 'Replace http(s) links in the message with a short URL.', 'sendsms-for-woocommerce' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Add unsubscribe link?', 'sendsms-for-woocommerce' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="rosendsms_test_gdpr" value="1" />
									<?php esc_html_e( 'Append a one-click unsubscribe link. Use {gdpr} in the message to place it explicitly; otherwise it is appended at the end.', 'sendsms-for-woocommerce' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="rosendsms-test-message"><?php esc_html_e( 'Message', 'sendsms-for-woocommerce' ); ?></label>
							</th>
							<td>
								<textarea id="rosendsms-test-message" name="rosendsms_test_message" class="rosendsms-content" style="width: 400px; height: 100px;" required></textarea>
								<p class="description rosendsms-length-counter"><?php esc_html_e( 'The field is empty.', 'sendsms-for-woocommerce' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<p style="clear: both;">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Send the message', 'sendsms-for-woocommerce' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Process the form submission if it just arrived.
	 *
	 * @return void
	 */
	private function maybe_handle_submission(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'post' !== $method ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( self::NONCE_ACTION );

		$raw_phone   = isset( $_POST['rosendsms_test_phone'] )   ? sanitize_text_field( wp_unslash( $_POST['rosendsms_test_phone'] ) )   : '';
		$raw_message = isset( $_POST['rosendsms_test_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rosendsms_test_message'] ) ) : '';
		$short       = isset( $_POST['rosendsms_test_short_url'] );
		$gdpr        = isset( $_POST['rosendsms_test_gdpr'] );

		if ( '' === $raw_phone ) {
			$this->notice( 'error', __( 'You have not entered a phone number.', 'sendsms-for-woocommerce' ) );
			return;
		}
		if ( '' === $raw_message ) {
			$this->notice( 'error', __( 'You have not entered a message.', 'sendsms-for-woocommerce' ) );
			return;
		}
		if ( ! $this->settings->has_credentials() || '' === $this->settings->from() ) {
			$this->notice( 'error', __( 'The plugin is not fully configured. Please complete the Configuration page.', 'sendsms-for-woocommerce' ) );
			return;
		}

		$phone = PhoneNumber::normalize( $raw_phone, $this->settings->country_code() );
		if ( '' === $phone ) {
			$this->notice( 'error', __( 'The validated phone number is empty.', 'sendsms-for-woocommerce' ) );
			return;
		}

		$response = $this->api->send_message(
			$this->settings->username(),
			$this->settings->password(),
			$this->settings->from(),
			$phone,
			$raw_message,
			$short,
			$gdpr
		);

		$this->api->history()->record(
			array(
				'phone'   => $phone,
				'status'  => (string) $response->status_code,
				'message' => $response->message,
				'details' => $response->details_as_string(),
				'content' => $raw_message,
				'type'    => 'test',
			)
		);

		if ( $response->success ) {
			$this->notice( 'success', __( 'The message was sent.', 'sendsms-for-woocommerce' ) );
		} else {
			$this->notice(
				'error',
				sprintf(
					/* translators: %s: error message from the gateway. */
					__( 'The gateway rejected the send: %s', 'sendsms-for-woocommerce' ),
					$response->message
				)
			);
		}
	}

	/**
	 * Echo an admin notice.
	 *
	 * @param string $type    "success"|"error"|"warning"|"info".
	 * @param string $message Localised, plain-text message.
	 * @return void
	 */
	private function notice( string $type, string $message ): void {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
