<?php
/**
 * Typed accessor for the plugin's single options array.
 *
 * @package Sendsmsro\ForWooCommerce
 */

namespace Sendsmsro\ForWooCommerce\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the `sendsmsro_options` option (v1.x `wc_sendsms_plugin_options` is
 * migrated on activation by {@see Install::migrate_from_v1()}).
 *
 * The array shape is preserved verbatim so existing v1.x installs do not lose
 * configuration when upgrading. All reads go through this class; nothing
 * else in the codebase should touch `get_option( 'sendsmsro_options' )`
 * directly.
 *
 * The option array shape:
 *  - username, password           string  sendsms.ro credentials.
 *  - from                         string  Sender label (alphanumeric, max 11 chars).
 *  - cc                           string  Country code key from CountryCodes; or "INT" to leave numbers alone.
 *  - simulation                   "1"|""  Route all SMS to simulation_number instead of the real billing phone.
 *  - simulation_number            string  Phone used when simulation is on.
 *  - send_to_owner                "1"|""  Owner-notification toggle (on new order).
 *  - send_to_owner_short          "1"|""  Owner notification: short URL flag.
 *  - send_to_owner_gdpr           "1"|""  Owner notification: append unsubscribe link.
 *  - send_to_owner_number         string  Phone the owner notification goes to.
 *  - send_to_owner_content        string  Owner notification message template.
 *  - optout                       "1"|""  Show "I don't want SMS" checkbox at checkout.
 *  - content[wc-{status}]         string  Per-status SMS template.
 *  - enabled[wc-{status}]         "1"|""  Per-status enable flag.
 *  - short[wc-{status}]           "1"|""  Per-status short URL flag.
 *  - gdpr[wc-{status}]            "1"|""  Per-status unsubscribe-link flag.
 */
final class Settings {

	/**
	 * Option key. The legacy v1.x option `wc_sendsms_plugin_options` is
	 * migrated into this key on activation; see {@see Install}.
	 */
	const OPTION_KEY = 'sendsmsro_options';

	/**
	 * Cached option array.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Fetch the raw option array. Lazy and cached for the request.
	 *
	 * @return array
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$opt         = get_option( self::OPTION_KEY, array() );
			$this->cache = is_array( $opt ) ? $opt : array();
		}
		return $this->cache;
	}

	/**
	 * Clear the in-request cache. Call after persisting.
	 *
	 * @return void
	 */
	public function refresh(): void {
		$this->cache = null;
	}

	/**
	 * Generic getter.
	 *
	 * @param string $key     Option array key.
	 * @param mixed  $default Default value if absent.
	 * @return mixed
	 */
	public function get( string $key, $default = '' ) {
		$opts = $this->all();
		return array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
	}

	// ------------------------------------------------------------------------
	// Credentials.
	// ------------------------------------------------------------------------

	public function username(): string {
		return (string) $this->get( 'username', '' );
	}

	public function password(): string {
		return (string) $this->get( 'password', '' );
	}

	public function from(): string {
		return (string) $this->get( 'from', '' );
	}

	public function has_credentials(): bool {
		return '' !== $this->username() && '' !== $this->password();
	}

	// ------------------------------------------------------------------------
	// Country code + simulation.
	// ------------------------------------------------------------------------

	public function country_code(): string {
		$cc = (string) $this->get( 'cc', 'INT' );
		return '' === $cc ? 'INT' : $cc;
	}

	public function simulation_enabled(): bool {
		return self::truthy( $this->get( 'simulation', '' ) ) && '' !== $this->simulation_number();
	}

	public function simulation_number(): string {
		return (string) $this->get( 'simulation_number', '' );
	}

	// ------------------------------------------------------------------------
	// Per-status configuration.
	// ------------------------------------------------------------------------

	/**
	 * Whether the SMS template is enabled for the given WC status.
	 *
	 * @param string $wc_status WooCommerce status key, e.g. "wc-pending".
	 */
	public function is_status_enabled( string $wc_status ): bool {
		$enabled = $this->get( 'enabled', array() );
		return is_array( $enabled ) && self::truthy( $enabled[ $wc_status ] ?? '' );
	}

	public function template_for_status( string $wc_status ): string {
		$content = $this->get( 'content', array() );
		return is_array( $content ) ? (string) ( $content[ $wc_status ] ?? '' ) : '';
	}

	public function uses_short_url( string $wc_status ): bool {
		$short = $this->get( 'short', array() );
		return is_array( $short ) && self::truthy( $short[ $wc_status ] ?? '' );
	}

	public function appends_unsubscribe_link( string $wc_status ): bool {
		$gdpr = $this->get( 'gdpr', array() );
		return is_array( $gdpr ) && self::truthy( $gdpr[ $wc_status ] ?? '' );
	}

	// ------------------------------------------------------------------------
	// Owner notifications.
	// ------------------------------------------------------------------------

	public function owner_notification_enabled(): bool {
		return self::truthy( $this->get( 'send_to_owner', '' ) )
			&& '' !== $this->owner_phone()
			&& '' !== $this->owner_template();
	}

	public function owner_phone(): string {
		return (string) $this->get( 'send_to_owner_number', '' );
	}

	public function owner_template(): string {
		return (string) $this->get( 'send_to_owner_content', '' );
	}

	public function owner_uses_short_url(): bool {
		return self::truthy( $this->get( 'send_to_owner_short', '' ) );
	}

	public function owner_appends_unsubscribe_link(): bool {
		return self::truthy( $this->get( 'send_to_owner_gdpr', '' ) );
	}

	// ------------------------------------------------------------------------
	// Checkout opt-out.
	// ------------------------------------------------------------------------

	public function checkout_optout_enabled(): bool {
		return self::truthy( $this->get( 'optout', '' ) );
	}

	// ------------------------------------------------------------------------
	// Helpers.
	// ------------------------------------------------------------------------

	/**
	 * Loose boolean coercion matching the legacy "1"/"" convention.
	 *
	 * @param mixed $value Stored value.
	 */
	private static function truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value > 0;
		}
		return '' !== (string) $value;
	}

	/**
	 * Map of settings-page tab → keys that tab manages.
	 *
	 * Used by {@see sanitize()} to merge a per-tab form submission with the
	 * existing stored options, so saving the Account tab doesn't wipe Customer
	 * or Owner settings.
	 *
	 * @return array<string, string[]>
	 */
	public static function keys_per_tab(): array {
		return array(
			'account'  => array( 'username', 'password', 'from', 'cc', 'simulation', 'simulation_number' ),
			'customer' => array( 'optout', 'enabled', 'short', 'gdpr', 'content' ),
			'owner'    => array( 'send_to_owner', 'send_to_owner_number', 'send_to_owner_content', 'send_to_owner_short', 'send_to_owner_gdpr' ),
		);
	}

	/**
	 * Settings API sanitize callback.
	 *
	 * The settings page is rendered one tab at a time, so a form submission
	 * only contains the keys that belong to the active tab. To prevent the
	 * other tabs' values from being wiped, we:
	 *
	 *   1. Load the current stored options.
	 *   2. Identify the active tab from the hidden `sendsmsro_active_tab`
	 *      input the form emits.
	 *   3. For each key that tab manages: copy the submitted value if present,
	 *      or unset it if absent (so unchecked checkboxes clear correctly).
	 *   4. Leave every other tab's keys untouched.
	 *
	 * Also preserves the stored password when the password field is left empty.
	 *
	 * @param array|null $input Submitted values from the settings form.
	 * @return array Merged options to persist.
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$existing = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		// Preserve stored password if the field was left empty.
		if ( empty( $input['password'] ) && ! empty( $existing['password'] ) ) {
			$input['password'] = $existing['password'];
		}

		// Identify which tab submitted the form. The hidden input is emitted by SettingsPage::render().
		// Nonce was already verified by WP's options.php handler before this callback runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$active_tab = isset( $_POST['sendsmsro_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['sendsmsro_active_tab'] ) ) : '';

		$tab_keys = self::keys_per_tab();
		if ( ! isset( $tab_keys[ $active_tab ] ) ) {
			// Unknown / missing tab marker. Defensive: leave the option untouched.
			return $existing;
		}

		$merged = $existing;
		foreach ( $tab_keys[ $active_tab ] as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$merged[ $key ] = self::sanitize_field( $key, $input[ $key ] );
			} else {
				unset( $merged[ $key ] );
			}
		}
		return $merged;
	}

	/**
	 * Per-field sanitization. Picks the right WP sanitizer for each known key.
	 *
	 * @param string $key   Option-array key.
	 * @param mixed  $value Raw submitted value (may be array for per-status maps).
	 * @return mixed Sanitised value, preserving the shape (string or array).
	 */
	private static function sanitize_field( string $key, $value ) {
		switch ( $key ) {
			// Text fields.
			case 'username':
			case 'from':
			case 'simulation_number':
			case 'send_to_owner_number':
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

			// Country-code dropdown — alpha-only key.
			case 'cc':
				return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

			// API key / password — preserved as-is to avoid altering case or characters the gateway requires.
			// (The reviewer's concern is sanitization, not transformation; we still bound the type and strip control chars.)
			case 'password':
				if ( ! is_scalar( $value ) ) {
					return '';
				}
				$pw = (string) $value;
				// Strip control characters; keep printable bytes (gateway tokens use base64 / hex / mixed).
				return preg_replace( '/[\x00-\x1F\x7F]/', '', $pw );

			// Textarea fields (templates).
			case 'send_to_owner_content':
				return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';

			// Per-status template map: array<wc-status, string>.
			case 'content':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$out = array();
				foreach ( $value as $wc_status => $template ) {
					$status_key = sanitize_key( (string) $wc_status );
					if ( '' === $status_key ) {
						continue;
					}
					$out[ $status_key ] = is_scalar( $template ) ? sanitize_textarea_field( (string) $template ) : '';
				}
				return $out;

			// Per-status checkbox maps: array<wc-status, "1">. Keep only present keys, coerce values to "1".
			case 'enabled':
			case 'short':
			case 'gdpr':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$out = array();
				foreach ( $value as $wc_status => $_ignored ) {
					$status_key = sanitize_key( (string) $wc_status );
					if ( '' === $status_key ) {
						continue;
					}
					$out[ $status_key ] = '1';
				}
				return $out;

			// Standalone checkboxes.
			case 'optout':
			case 'simulation':
			case 'send_to_owner':
			case 'send_to_owner_short':
			case 'send_to_owner_gdpr':
				return self::truthy( $value ) ? '1' : '';

			default:
				// Unknown key — defensively reject.
				return '';
		}
	}
}
