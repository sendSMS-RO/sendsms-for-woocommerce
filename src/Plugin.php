<?php
/**
 * Top-level plugin loader. Holds the wiring graph between subsystems.
 *
 * @package Rosendsms\ForWooCommerce
 */

namespace Rosendsms\ForWooCommerce;

use Rosendsms\ForWooCommerce\Admin\Menu;
use Rosendsms\ForWooCommerce\Admin\OrderMetabox;
use Rosendsms\ForWooCommerce\Ajax\CampaignHandler;
use Rosendsms\ForWooCommerce\Ajax\SingleSendHandler;
use Rosendsms\ForWooCommerce\Api\Client;
use Rosendsms\ForWooCommerce\Order\NewOrderListener;
use Rosendsms\ForWooCommerce\Order\OptOut;
use Rosendsms\ForWooCommerce\Order\StatusListener;
use Rosendsms\ForWooCommerce\Storage\HistoryRepository;
use Rosendsms\ForWooCommerce\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin loader.
 *
 * Each subsystem is constructed once in {@see boot()} and registers its own
 * WordPress hooks via its `register()` method. Wiring happens in one place
 * so the full hook surface is auditable from a single file.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether {@see boot()} has run already.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Settings (option reader/writer).
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * History repository ({prefix}rosendsms_history).
	 *
	 * @var HistoryRepository|null
	 */
	private $history = null;

	/**
	 * sendsms.ro API client.
	 *
	 * @var Client|null
	 */
	private $api = null;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Singleton accessor.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Whether WooCommerce is active (single-site or network-active).
	 *
	 * @return bool
	 */
	public static function woocommerce_is_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_multisite() && is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {
			return true;
		}
		return is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Show a one-time admin notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public static function add_missing_wc_notice(): void {
		add_action(
			'admin_notices',
			static function () {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p>'
					. esc_html__( 'SendSMS for WooCommerce requires WooCommerce to be installed and active.', 'sendsms-for-woocommerce' )
					. '</p></div>';
			}
		);
	}

	/**
	 * Register all hooks. Idempotent.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// i18n.
		add_action(
			'init',
			static function () {
				load_plugin_textdomain(
					'sendsms-for-woocommerce',
					false,
					dirname( ROSENDSMS_BASENAME ) . '/languages'
				);
			}
		);

		// Ensure the schema is up to date before any subsystem queries the history table.
		Install::maybe_upgrade();

		// Shared services.
		$this->settings = new Settings();
		$this->history  = new HistoryRepository();
		$this->api      = new Client( $this->history );

		// Order-side listeners.
		( new OptOut() )->register();
		( new StatusListener( $this->settings, $this->api ) )->register();
		( new NewOrderListener( $this->settings, $this->api ) )->register();

		// Admin UI.
		( new Menu( $this->settings, $this->api ) )->register();
		( new OrderMetabox() )->register();

		// AJAX handlers.
		( new CampaignHandler( $this->settings, $this->api ) )->register();
		( new SingleSendHandler( $this->settings, $this->api ) )->register();
	}

	/**
	 * Accessors used by tests / extensions.
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	public function history(): HistoryRepository {
		return $this->history;
	}

	public function api(): Client {
		return $this->api;
	}
}
