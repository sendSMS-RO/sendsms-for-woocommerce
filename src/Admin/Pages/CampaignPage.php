<?php
/**
 * "Campaign" admin page: filter past orders and send bulk SMS.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Admin\Pages;

use Rosendsms\ForWooCommerce\Admin\Menu;
use Rosendsms\ForWooCommerce\Order\Query;
use Rosendsms\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Campaign page.
 *
 * Two forms:
 *  1. GET filter form  — narrows the recipient list by period/amount/products/states.
 *  2. POST send button — fires the AJAX handler ({@see CampaignHandler}).
 */
final class CampaignPage {

	const FILTER_NONCE_ACTION = 'rosendsms_send_campaign';

	/** @var Settings */
	private $settings;

	/**
	 * @param Settings $settings Settings reader.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		$products       = Query::product_options();
		$billing_states = Query::billing_state_options();
		$filter         = $this->read_filter_from_get();
		$cc             = $this->settings->country_code();

		// Resolve recipient list for the headline count.
		$orders = $filter['filtering']
			? Query::filtered_completed_orders(
				$filter['period_start'],
				$filter['period_end'],
				$filter['min_amount'],
				$filter['state_keys'],
				$filter['product_keys']
			)
			: Query::completed_orders();
		$phones = Query::unique_phones( $orders, $cc );
		?>
		<div class="wrap rosendsms-page">
			<h1><?php esc_html_e( 'SendSMS — Campaign', 'sendsms-for-woocommerce' ); ?></h1>

			<form method="get" action="">
				<?php wp_nonce_field( self::FILTER_NONCE_ACTION ); ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( Menu::CAMPAIGN_SLUG ); ?>" />
				<input type="hidden" name="filtering" value="true" />
				<div style="display: flex; flex-wrap: wrap; gap: 1.5em;">
					<div style="flex: 1 1 45%; min-width: 280px;">
						<p>
							<label><?php esc_html_e( 'Period', 'sendsms-for-woocommerce' ); ?></label><br />
							<input type="date" name="perioada_start" value="<?php echo esc_attr( $filter['period_start'] ); ?>" />
							—
							<input type="date" name="perioada_final" value="<?php echo esc_attr( $filter['period_end'] ); ?>" />
						</p>
					</div>
					<div style="flex: 1 1 45%; min-width: 280px;">
						<p>
							<label><?php esc_html_e( 'Minimum amount per order', 'sendsms-for-woocommerce' ); ?></label><br />
							<input type="number" name="suma" step="0.01" min="0" value="<?php echo esc_attr( $filter['min_amount'] ); ?>" />
						</p>
					</div>
					<div style="flex: 1 1 45%; min-width: 280px;">
						<p><?php esc_html_e( 'Purchased product (leave empty for all products)', 'sendsms-for-woocommerce' ); ?></p>
						<select name="produse[]" multiple="multiple" class="wc-enhanced-select" data-placeholder="<?php esc_attr_e( 'Select products...', 'sendsms-for-woocommerce' ); ?>" style="width: 100%;">
							<?php
							foreach ( $products as $p ) {
								$value    = 'id_' . $p['id'];
								$selected = in_array( $value, $filter['product_keys'], true );
								printf(
									'<option value="%1$s" %2$s>%3$s</option>',
									esc_attr( $value ),
									$selected ? 'selected="selected"' : '',
									esc_html( $p['name'] . ' — ' . $p['sku'] )
								);
							}
							?>
						</select>
					</div>
					<div style="flex: 1 1 45%; min-width: 280px;">
						<p><?php esc_html_e( 'Billing state/county (leave empty for all)', 'sendsms-for-woocommerce' ); ?></p>
						<select name="judete[]" multiple="multiple" class="wc-enhanced-select" data-placeholder="<?php esc_attr_e( 'Select states...', 'sendsms-for-woocommerce' ); ?>" style="width: 100%;">
							<?php
							foreach ( $billing_states as $s ) {
								$value    = 'id_' . $s['code'];
								$selected = in_array( $value, $filter['state_keys'], true );
								printf(
									'<option value="%1$s" %2$s>%3$s</option>',
									esc_attr( $value ),
									$selected ? 'selected="selected"' : '',
									esc_html( $s['label'] )
								);
							}
							?>
						</select>
					</div>
				</div>
				<p>
					<button type="submit" class="button button-default button-large"><?php esc_html_e( 'Apply filter', 'sendsms-for-woocommerce' ); ?></button>
				</p>
			</form>

			<hr />

			<h2>
				<?php
				printf(
					/* translators: %d: number of matched phone numbers. */
					esc_html( _n( 'Filter results: %d phone number', 'Filter results: %d phone numbers', count( $phones ), 'sendsms-for-woocommerce' ) ),
					(int) count( $phones )
				);
				?>
			</h2>

			<form method="post" action="" id="rosendsms-campaign-form">
				<div style="display: flex; flex-wrap: wrap; gap: 2em;">
					<div style="flex: 1 1 60%; min-width: 320px;">
						<label for="rosendsms-campaign-message"><?php esc_html_e( 'Message', 'sendsms-for-woocommerce' ); ?></label>
						<textarea id="rosendsms-campaign-message" name="content" class="rosendsms-content" style="width: 100%; height: 250px;"></textarea>
						<p class="description rosendsms-length-counter"><?php esc_html_e( 'The field is empty.', 'sendsms-for-woocommerce' ); ?></p>
					</div>
					<div style="flex: 1 1 30%; min-width: 280px;">
						<p><?php esc_html_e( 'Phone numbers:', 'sendsms-for-woocommerce' ); ?></p>
						<div style="margin-bottom: 0.5em;">
							<label>
								<input type="checkbox" id="rosendsms-send-to-all" name="rosendsms_to_all" checked />
								<?php esc_html_e( 'Send SMS to every number from the filter.', 'sendsms-for-woocommerce' ); ?>
							</label>
						</div>
						<select name="phones[]" id="rosendsms-phones" multiple="multiple" class="wc-enhanced-select" data-placeholder="<?php esc_attr_e( 'Select phone numbers...', 'sendsms-for-woocommerce' ); ?>" style="width: 100%;">
							<?php
							foreach ( $phones as $p ) {
								printf(
									'<option value="%1$s" selected>%1$s</option>',
									esc_attr( $p )
								);
							}
							?>
						</select>
					</div>
				</div>
				<p style="clear: both;">
					<button type="submit" class="button button-primary button-large" id="rosendsms-campaign-send"><?php esc_html_e( 'Send the message', 'sendsms-for-woocommerce' ); ?></button>
					<button type="button" class="button button-secondary button-large" id="rosendsms-campaign-estimate"><?php esc_html_e( 'Estimate the price', 'sendsms-for-woocommerce' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Pull the filter values from $_GET, defensively sanitised.
	 *
	 * @return array{filtering:bool, period_start:string, period_end:string, min_amount:string, state_keys:string[], product_keys:string[]}
	 */
	private function read_filter_from_get(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filter rendering.
		$filtering = isset( $_REQUEST['filtering'] ) && 'true' === $_REQUEST['filtering'];

		// When actively filtering, require the nonce too.
		if ( $filtering && ! ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::FILTER_NONCE_ACTION ) ) ) {
			$filtering = false;
		}

		$period_start = isset( $_GET['perioada_start'] ) ? sanitize_text_field( wp_unslash( $_GET['perioada_start'] ) ) : '';
		$period_end   = isset( $_GET['perioada_final'] ) ? sanitize_text_field( wp_unslash( $_GET['perioada_final'] ) ) : '';
		$min_amount   = isset( $_GET['suma'] )           ? sanitize_text_field( wp_unslash( $_GET['suma'] ) )           : '';
		$state_keys   = isset( $_GET['judete'] )         ? array_map( 'sanitize_text_field', wp_unslash( (array) $_GET['judete'] ) ) : array();
		$product_keys = isset( $_GET['produse'] )        ? array_map( 'sanitize_text_field', wp_unslash( (array) $_GET['produse'] ) ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array(
			'filtering'    => $filtering,
			'period_start' => $this->validate_date( $period_start ),
			'period_end'   => $this->validate_date( $period_end ),
			'min_amount'   => $min_amount,
			'state_keys'   => $state_keys,
			'product_keys' => $product_keys,
		);
	}

	/**
	 * Return the date string only if it parses as Y-m-d.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function validate_date( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );
		return $dt && $dt->format( 'Y-m-d' ) === $value ? $value : '';
	}
}
