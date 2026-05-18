<?php
/**
 * Typed accessor for the plugin's single options array.
 *
 * @package SendSMS\ForWooCommerce
 */

namespace SendSMS\ForWooCommerce\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the legacy `wc_sendsms_plugin_options` option.
 *
 * The shape is preserved verbatim so existing v1.x installs do not lose
 * configuration when upgrading. All reads go through this class; nothing
 * else in the codebase should touch `get_option( 'wc_sendsms_plugin_options' )`
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
	 * Option key. Preserved from v1.x.
	 */
	const OPTION_KEY = 'wc_sendsms_plugin_options';

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
	 * Settings API validate callback.
	 *
	 * Two behaviours preserved from v1.x:
	 *  - Preserve the stored password if the submitted value is empty
	 *    (the password field is never re-rendered into HTML).
	 *  - Booleanise the "1"/"" convention for checkbox-style keys.
	 *
	 * @param array|null $input Incoming form values.
	 * @return array Sanitised options.
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		// Preserve stored password if the field is left empty.
		if ( empty( $input['password'] ) ) {
			$existing = get_option( self::OPTION_KEY );
			if ( is_array( $existing ) && ! empty( $existing['password'] ) ) {
				$input['password'] = $existing['password'];
			}
		}

		return $input;
	}
}
