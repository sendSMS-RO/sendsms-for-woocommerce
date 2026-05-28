<?php
/**
 * Registers the WP admin menu + submenus for this plugin.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce\Admin;

use Rosendsms\ForWooCommerce\Admin\Pages\CampaignPage;
use Rosendsms\ForWooCommerce\Admin\Pages\HistoryPage;
use Rosendsms\ForWooCommerce\Admin\Pages\MainPage;
use Rosendsms\ForWooCommerce\Admin\Pages\SettingsPage;
use Rosendsms\ForWooCommerce\Admin\Pages\TestPage;
use Rosendsms\ForWooCommerce\Api\Client;
use Rosendsms\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level "SendSMS" admin menu and its submenus.
 */
final class Menu {

	const MENU_SLUG     = 'rosendsms_main';
	const SETTINGS_SLUG = 'rosendsms_settings';
	const HISTORY_SLUG  = 'rosendsms_history';
	const CAMPAIGN_SLUG = 'rosendsms_campaign';
	const TEST_SLUG     = 'rosendsms_test';

	/** @var Settings */
	private $settings;

	/** @var Client */
	private $api;

	/** @var SettingsPage */
	private $settings_page;

	/**
	 * @param Settings $settings Settings reader.
	 * @param Client   $api      API client.
	 */
	public function __construct( Settings $settings, Client $api ) {
		$this->settings      = $settings;
		$this->api           = $api;
		$this->settings_page = new SettingsPage( $settings, $api );
	}

	/**
	 * Wire up the admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this->settings_page, 'register_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the menu, submenus, and their page renderers.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'SendSMS', 'sendsms-for-woocommerce' ),
			__( 'SendSMS', 'sendsms-for-woocommerce' ),
			'manage_options',
			self::MENU_SLUG,
			array( new MainPage(), 'render' ),
			ROSENDSMS_URL . 'images/sendsms.png'
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Configuration', 'sendsms-for-woocommerce' ),
			__( 'Configuration', 'sendsms-for-woocommerce' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this->settings_page, 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'History', 'sendsms-for-woocommerce' ),
			__( 'History', 'sendsms-for-woocommerce' ),
			'manage_options',
			self::HISTORY_SLUG,
			array( new HistoryPage( $this->api->history() ), 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Campaign', 'sendsms-for-woocommerce' ),
			__( 'Campaign', 'sendsms-for-woocommerce' ),
			'manage_options',
			self::CAMPAIGN_SLUG,
			array( new CampaignPage( $this->settings ), 'render' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Send a test', 'sendsms-for-woocommerce' ),
			__( 'Send a test', 'sendsms-for-woocommerce' ),
			'manage_options',
			self::TEST_SLUG,
			array( new TestPage( $this->settings, $this->api ), 'render' )
		);
	}

	/**
	 * Enqueue admin assets only on our pages.
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$our_hooks = array(
			'toplevel_page_' . self::MENU_SLUG,
			'sendsms_page_' . self::SETTINGS_SLUG,
			'sendsms_page_' . self::HISTORY_SLUG,
			'sendsms_page_' . self::CAMPAIGN_SLUG,
			'sendsms_page_' . self::TEST_SLUG,
		);
		// WooCommerce-prefixed admin hooks vary by site translation; check substring as well.
		$is_ours = in_array( $hook, $our_hooks, true ) || false !== strpos( $hook, self::MENU_SLUG );
		if ( ! $is_ours && 'post.php' !== $hook && 'post-new.php' !== $hook && 'woocommerce_page_wc-orders' !== $hook && 'admin_page_wc-orders' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'rosendsms-admin',
			ROSENDSMS_URL . 'assets/css/admin.css',
			array(),
			ROSENDSMS_VERSION
		);

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );

		// Page-specific scripts.
		if ( false !== strpos( $hook, self::SETTINGS_SLUG ) || false !== strpos( $hook, self::TEST_SLUG ) || false !== strpos( $hook, self::CAMPAIGN_SLUG ) ) {
			wp_enqueue_script(
				'rosendsms-length-counter',
				ROSENDSMS_URL . 'assets/js/length-counter.js',
				array(),
				ROSENDSMS_VERSION,
				true
			);
			wp_localize_script(
				'rosendsms-length-counter',
				'RosendsmsL10n',
				array(
					'approx' => __( 'The approximate number of messages: ', 'sendsms-for-woocommerce' ),
					'empty'  => __( 'The field is empty.', 'sendsms-for-woocommerce' ),
				)
			);
		}

		if ( false !== strpos( $hook, self::CAMPAIGN_SLUG ) ) {
			wp_enqueue_script(
				'rosendsms-campaign',
				ROSENDSMS_URL . 'assets/js/campaign.js',
				array( 'jquery', 'wc-enhanced-select' ),
				ROSENDSMS_VERSION,
				true
			);
			wp_localize_script(
				'rosendsms-campaign',
				'RosendsmsCampaign',
				array(
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'rosendsms_send_campaign' ),
					'sending'       => __( "It's being sent...", 'sendsms-for-woocommerce' ),
					'send'          => __( 'Send the message', 'sendsms-for-woocommerce' ),
					'estimateLabel' => __( 'The estimate price is: ', 'sendsms-for-woocommerce' ),
					'estimateNote'  => __( ' (This is just an estimation, and not the actual price)', 'sendsms-for-woocommerce' ),
					'pricePerSms'   => (float) get_option( Client::PRICE_OPTION, 0 ),
					'fillMessage'   => __( 'Please fill the message box first.', 'sendsms-for-woocommerce' ),
					'sendMessage'   => __( 'Please send a message first.', 'sendsms-for-woocommerce' ),
					'getQuery'      => $this->current_get_filters(),
				)
			);
		}

		if ( 'post.php' === $hook || 'post-new.php' === $hook || false !== strpos( $hook, 'wc-orders' ) ) {
			wp_enqueue_script(
				'rosendsms-order-metabox',
				ROSENDSMS_URL . 'assets/js/order-metabox.js',
				array( 'jquery' ),
				ROSENDSMS_VERSION,
				true
			);
			wp_localize_script(
				'rosendsms-order-metabox',
				'RosendsmsMetabox',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'rosendsms_send_single' ),
					'sending' => __( "It's being sent...", 'sendsms-for-woocommerce' ),
					'send'    => __( 'Send the message', 'sendsms-for-woocommerce' ),
				)
			);
		}
	}

	/**
	 * Snapshot the campaign filter values from $_GET. Used by the campaign JS.
	 *
	 * @return array
	 */
	private function current_get_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter rendering on a manage_options page.
		$produse = isset( $_GET['produse'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_GET['produse'] ) ) : array();
		$judete  = isset( $_GET['judete'] )  ? array_map( 'sanitize_text_field', wp_unslash( (array) $_GET['judete'] ) )  : array();
		$suma    = isset( $_GET['suma'] )    ? sanitize_text_field( wp_unslash( $_GET['suma'] ) )                          : '';
		$pstart  = isset( $_GET['perioada_start'] ) ? sanitize_text_field( wp_unslash( $_GET['perioada_start'] ) )         : '';
		$pfinal  = isset( $_GET['perioada_final'] ) ? sanitize_text_field( wp_unslash( $_GET['perioada_final'] ) )         : '';
		$filter  = isset( $_REQUEST['filtering'] ) ? 'true' : 'false';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return array(
			'produse'        => $produse,
			'judete'         => $judete,
			'suma'           => $suma,
			'perioada_start' => $pstart,
			'perioada_final' => $pfinal,
			'filtering'      => $filter,
		);
	}
}
