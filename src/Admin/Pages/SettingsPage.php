<?php
/**
 * Tabbed settings page (Account / Customer / Owner).
 *
 * @package SendSMS\ForWooCommerce
 */

namespace SendSMS\ForWooCommerce\Admin\Pages;

use SendSMS\ForWooCommerce\Admin\Menu;
use SendSMS\ForWooCommerce\Api\Client;
use SendSMS\ForWooCommerce\CountryCodes;
use SendSMS\ForWooCommerce\Order\PlaceholderReplacer;
use SendSMS\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page renderer + Settings API registration.
 */
final class SettingsPage {

	const TAB_ACCOUNT  = 'account';
	const TAB_CUSTOMER = 'customer';
	const TAB_OWNER    = 'owner';

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
	 * Register the option group and all settings sections / fields.
	 *
	 * Called from `admin_init`.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		register_setting(
			'sendsms_fwc_settings_group',
			Settings::OPTION_KEY,
			array(
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => array(),
			)
		);

		// Each tab gets its own section so do_settings_sections() can render them in isolation.
		add_settings_section( 'sendsms_fwc_section_account',  '', '__return_false', 'sendsms_fwc_page_' . self::TAB_ACCOUNT );
		add_settings_section( 'sendsms_fwc_section_customer', '', '__return_false', 'sendsms_fwc_page_' . self::TAB_CUSTOMER );
		add_settings_section( 'sendsms_fwc_section_owner',    '', '__return_false', 'sendsms_fwc_page_' . self::TAB_OWNER );

		$this->register_account_fields();
		$this->register_customer_fields();
		$this->register_owner_fields();
	}

	/**
	 * Render the settings page (tab nav + active tab body).
	 *
	 * @return void
	 */
	public function render(): void {
		$tab = $this->current_tab();
		?>
		<div class="wrap sendsms-fwc-page">
			<h1><?php esc_html_e( 'SendSMS — Configuration', 'sendsms-for-woocommerce' ); ?></h1>

			<?php $this->render_balance_banner(); ?>
			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<?php
				foreach ( $this->tabs() as $slug => $label ) {
					$href = add_query_arg(
						array(
							'page' => Menu::SETTINGS_SLUG,
							'tab'  => $slug,
						),
						admin_url( 'admin.php' )
					);
					printf(
						'<a href="%1$s" class="nav-tab %2$s">%3$s</a>',
						esc_url( $href ),
						$tab === $slug ? 'nav-tab-active' : '',
						esc_html( $label )
					);
				}
				?>
			</h2>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'sendsms_fwc_settings_group' );
				printf( '<input type="hidden" name="sendsms_fwc_active_tab" value="%s" />', esc_attr( $tab ) );
				do_settings_sections( 'sendsms_fwc_page_' . $tab );
				submit_button( __( 'Save changes', 'sendsms-for-woocommerce' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the "you have X EUR" / "not configured" banner.
	 *
	 * @return void
	 */
	private function render_balance_banner(): void {
		if ( ! $this->settings->has_credentials() ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html__( 'The plugin is not configured yet. Enter your sendsms.ro credentials below.', 'sendsms-for-woocommerce' )
			);
			return;
		}
		$response = $this->api->get_balance( $this->settings->username(), $this->settings->password() );
		if ( ! $response->success ) {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div>',
				esc_html__( 'Could not contact the gateway to retrieve your balance. Check your credentials.', 'sendsms-for-woocommerce' )
			);
			return;
		}
		printf(
			'<div class="notice notice-info inline"><p>%s <strong>%s</strong> EUR</p></div>',
			esc_html__( 'Available balance:', 'sendsms-for-woocommerce' ),
			esc_html( (string) ( is_scalar( $response->details ) ? $response->details : '0' ) )
		);
	}

	/**
	 * The tab nav definition.
	 *
	 * @return array<string,string> Slug → label.
	 */
	private function tabs(): array {
		return array(
			self::TAB_ACCOUNT  => __( 'Account', 'sendsms-for-woocommerce' ),
			self::TAB_CUSTOMER => __( 'Customer notifications', 'sendsms-for-woocommerce' ),
			self::TAB_OWNER    => __( 'Owner notification', 'sendsms-for-woocommerce' ),
		);
	}

	/**
	 * Current tab from $_GET, clamped to the list of valid tabs.
	 *
	 * @return string
	 */
	private function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : self::TAB_ACCOUNT;
		return array_key_exists( $tab, $this->tabs() ) ? $tab : self::TAB_ACCOUNT;
	}

	// ------------------------------------------------------------------------
	// Field registration helpers.
	// ------------------------------------------------------------------------

	private function register_account_fields(): void {
		$page    = 'sendsms_fwc_page_' . self::TAB_ACCOUNT;
		$section = 'sendsms_fwc_section_account';

		add_settings_field( 'username',          __( 'Username', 'sendsms-for-woocommerce' ),         array( $this, 'render_field_username' ),         $page, $section );
		add_settings_field( 'password',          __( 'Password / API Key', 'sendsms-for-woocommerce' ), array( $this, 'render_field_password' ),       $page, $section );
		add_settings_field( 'from',              __( 'Sender label', 'sendsms-for-woocommerce' ),     array( $this, 'render_field_from' ),             $page, $section );
		add_settings_field( 'cc',                __( 'Country', 'sendsms-for-woocommerce' ),          array( $this, 'render_field_country_code' ),     $page, $section );
		add_settings_field( 'simulation',        __( 'Simulation mode', 'sendsms-for-woocommerce' ),  array( $this, 'render_field_simulation' ),       $page, $section );
		add_settings_field( 'simulation_number', __( 'Simulation phone number', 'sendsms-for-woocommerce' ), array( $this, 'render_field_simulation_number' ), $page, $section );
	}

	private function register_customer_fields(): void {
		$page    = 'sendsms_fwc_page_' . self::TAB_CUSTOMER;
		$section = 'sendsms_fwc_section_customer';

		add_settings_field( 'optout',  __( 'Checkout opt-out', 'sendsms-for-woocommerce' ),     array( $this, 'render_field_optout' ),     $page, $section );
		add_settings_field( 'content', __( 'Per-status templates', 'sendsms-for-woocommerce' ), array( $this, 'render_field_status_table' ), $page, $section );
	}

	private function register_owner_fields(): void {
		$page    = 'sendsms_fwc_page_' . self::TAB_OWNER;
		$section = 'sendsms_fwc_section_owner';

		add_settings_field( 'send_to_owner',         __( 'Send an SMS for every new order', 'sendsms-for-woocommerce' ), array( $this, 'render_field_send_to_owner' ),         $page, $section );
		add_settings_field( 'send_to_owner_number',  __( 'Recipient phone number', 'sendsms-for-woocommerce' ),          array( $this, 'render_field_owner_phone' ),           $page, $section );
		add_settings_field( 'send_to_owner_content', __( 'Message template', 'sendsms-for-woocommerce' ),                array( $this, 'render_field_owner_message' ),         $page, $section );
		add_settings_field( 'send_to_owner_short',   __( 'Short URL?', 'sendsms-for-woocommerce' ),                      array( $this, 'render_field_owner_short' ),           $page, $section );
		add_settings_field( 'send_to_owner_gdpr',    __( 'Append unsubscribe link?', 'sendsms-for-woocommerce' ),        array( $this, 'render_field_owner_gdpr' ),            $page, $section );
	}

	// ------------------------------------------------------------------------
	// Field renderers.
	// ------------------------------------------------------------------------

	public function render_field_username(): void {
		printf(
			'<input id="sendsms-fwc-username" name="%1$s[username]" type="text" value="%2$s" style="width: 400px;" />',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $this->settings->username() )
		);
	}

	public function render_field_password(): void {
		$has = '' !== $this->settings->password();
		printf(
			'<input id="sendsms-fwc-password" name="%1$s[password]" type="password" value="" placeholder="%2$s" autocomplete="new-password" style="width: 400px;" />',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $has ? '••••••••••••' : '' )
		);
		if ( $has ) {
			echo ' <span class="description">'
				. esc_html__( 'Leave empty to keep the current password.', 'sendsms-for-woocommerce' )
				. '</span>';
		}
	}

	public function render_field_from(): void {
		printf(
			'<input id="sendsms-fwc-from" name="%1$s[from]" type="text" value="%2$s" style="width: 400px;" maxlength="11" /> <span class="description">%3$s</span>',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $this->settings->from() ),
			esc_html__( 'Maximum 11 alpha-numeric characters. Shown to recipients as the SMS sender.', 'sendsms-for-woocommerce' )
		);
	}

	public function render_field_country_code(): void {
		$current = $this->settings->country_code();
		echo '<select id="sendsms-fwc-cc" name="' . esc_attr( Settings::OPTION_KEY ) . '[cc]">';
		printf(
			'<option value="INT" %1$s>%2$s</option>',
			'INT' === $current ? 'selected' : '',
			esc_html__( 'International (do not modify numbers)', 'sendsms-for-woocommerce' )
		);
		foreach ( CountryCodes::all() as $code => $dial ) {
			printf(
				'<option value="%1$s" %2$s>%3$s (+%4$s)</option>',
				esc_attr( $code ),
				$current === $code ? 'selected' : '',
				esc_html( $code ),
				esc_html( $dial )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'When set to a specific country, billing phone numbers will be normalised: leading zeros are stripped and the country dial code is prepended.', 'sendsms-for-woocommerce' ) . '</p>';
	}

	public function render_field_simulation(): void {
		printf(
			'<label><input type="checkbox" name="%1$s[simulation]" value="1"%2$s /> %3$s</label>',
			esc_attr( Settings::OPTION_KEY ),
			$this->settings->get( 'simulation', '' ) ? ' checked="checked"' : '',
			esc_html__( 'Route every outbound SMS to the simulation number below instead of the customer.', 'sendsms-for-woocommerce' )
		);
	}

	public function render_field_simulation_number(): void {
		printf(
			'<input name="%1$s[simulation_number]" type="text" value="%2$s" style="width: 400px;" />',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $this->settings->simulation_number() )
		);
	}

	public function render_field_optout(): void {
		printf(
			'<label><input type="checkbox" name="%1$s[optout]" value="1"%2$s /> %3$s</label>',
			esc_attr( Settings::OPTION_KEY ),
			$this->settings->checkout_optout_enabled() ? ' checked="checked"' : '',
			esc_html__( 'Show an "I do not want to receive SMS" checkbox at checkout.', 'sendsms-for-woocommerce' )
		);
	}

	public function render_field_status_table(): void {
		$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$examples = $this->status_template_examples();
		?>
		<p class="description">
			<?php
			esc_html_e( 'Available placeholders:', 'sendsms-for-woocommerce' );
			echo ' <code>' . implode( '</code>, <code>', array_map( 'esc_html', PlaceholderReplacer::supported_tokens() ) ) . '</code>';
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Enable a row and write a template to fire that SMS on the corresponding status transition. Leave a row disabled to skip it.', 'sendsms-for-woocommerce' ); ?>
		</p>

		<table class="widefat striped" style="margin-top: 1em;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Status', 'sendsms-for-woocommerce' ); ?></th>
					<th style="width: 70px; text-align: center;"><?php esc_html_e( 'Enabled', 'sendsms-for-woocommerce' ); ?></th>
					<th style="width: 80px; text-align: center;"><?php esc_html_e( 'Short URL', 'sendsms-for-woocommerce' ); ?></th>
					<th style="width: 80px; text-align: center;"><?php esc_html_e( 'Unsubscribe', 'sendsms-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Message template', 'sendsms-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $statuses as $key => $label ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $label ); ?></strong></td>
					<td style="text-align: center;">
						<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[enabled][<?php echo esc_attr( $key ); ?>]" value="1" <?php echo $this->settings->is_status_enabled( $key ) ? 'checked="checked"' : ''; ?> />
					</td>
					<td style="text-align: center;">
						<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[short][<?php echo esc_attr( $key ); ?>]" value="1" <?php echo $this->settings->uses_short_url( $key ) ? 'checked="checked"' : ''; ?> />
					</td>
					<td style="text-align: center;">
						<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[gdpr][<?php echo esc_attr( $key ); ?>]" value="1" <?php echo $this->settings->appends_unsubscribe_link( $key ) ? 'checked="checked"' : ''; ?> />
					</td>
					<td>
						<textarea name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[content][<?php echo esc_attr( $key ); ?>]" class="sendsms-fwc-content" style="width: 100%; min-height: 70px;"><?php echo esc_textarea( $this->settings->template_for_status( $key ) ); ?></textarea>
						<p class="description sendsms-fwc-length-counter"><?php esc_html_e( 'The field is empty.', 'sendsms-for-woocommerce' ); ?></p>
						<?php if ( isset( $examples[ $key ] ) ) : ?>
							<p class="description"><em><?php esc_html_e( 'Example:', 'sendsms-for-woocommerce' ); ?></em> <?php echo esc_html( $examples[ $key ] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public function render_field_send_to_owner(): void {
		printf(
			'<label><input type="checkbox" name="%1$s[send_to_owner]" value="1"%2$s /> %3$s</label>',
			esc_attr( Settings::OPTION_KEY ),
			$this->settings->get( 'send_to_owner', '' ) ? ' checked="checked"' : '',
			esc_html__( 'Send an SMS to the shop owner each time a new order is placed.', 'sendsms-for-woocommerce' )
		);
	}

	public function render_field_owner_phone(): void {
		printf(
			'<input name="%1$s[send_to_owner_number]" type="text" value="%2$s" style="width: 400px;" />',
			esc_attr( Settings::OPTION_KEY ),
			esc_attr( $this->settings->owner_phone() )
		);
	}

	public function render_field_owner_message(): void {
		?>
		<p class="description">
			<?php
			esc_html_e( 'Available placeholders:', 'sendsms-for-woocommerce' );
			echo ' <code>' . implode( '</code>, <code>', array_map( 'esc_html', PlaceholderReplacer::supported_tokens() ) ) . '</code>';
			?>
		</p>
		<textarea name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[send_to_owner_content]" class="sendsms-fwc-content" style="width: 400px; height: 100px;"><?php echo esc_textarea( $this->settings->owner_template() ); ?></textarea>
		<p class="description sendsms-fwc-length-counter"><?php esc_html_e( 'The field is empty.', 'sendsms-for-woocommerce' ); ?></p>
		<?php
	}

	public function render_field_owner_short(): void {
		printf(
			'<label><input type="checkbox" name="%1$s[send_to_owner_short]" value="1"%2$s /> %3$s</label>',
			esc_attr( Settings::OPTION_KEY ),
			$this->settings->owner_uses_short_url() ? ' checked="checked"' : '',
			esc_html__( 'Replace http(s) links with a short URL.', 'sendsms-for-woocommerce' )
		);
	}

	public function render_field_owner_gdpr(): void {
		printf(
			'<label><input type="checkbox" name="%1$s[send_to_owner_gdpr]" value="1"%2$s /> %3$s</label>',
			esc_attr( Settings::OPTION_KEY ),
			$this->settings->owner_appends_unsubscribe_link() ? ' checked="checked"' : '',
			esc_html__( 'Append a one-click unsubscribe link.', 'sendsms-for-woocommerce' )
		);
	}

	/**
	 * Suggested example templates for each WooCommerce status.
	 *
	 * @return array<string,string>
	 */
	private function status_template_examples(): array {
		return array(
			'wc-pending'    => __( 'Order {order_number} placed successfully. Awaiting payment of {order_total}.', 'sendsms-for-woocommerce' ),
			'wc-processing' => __( 'Order {order_number} is being prepared for shipment.', 'sendsms-for-woocommerce' ),
			'wc-on-hold'    => __( 'Order {order_number} is on hold pending stock availability.', 'sendsms-for-woocommerce' ),
			'wc-completed'  => __( 'Order {order_number} has been completed. Thank you!', 'sendsms-for-woocommerce' ),
			'wc-cancelled'  => __( 'Order {order_number} has been cancelled.', 'sendsms-for-woocommerce' ),
			'wc-refunded'   => __( 'Refund for order {order_number} has been issued.', 'sendsms-for-woocommerce' ),
			'wc-failed'     => __( 'There was a problem processing payment for order {order_number}. Please get in touch.', 'sendsms-for-woocommerce' ),
		);
	}
}
