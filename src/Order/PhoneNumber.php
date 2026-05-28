<?php
/**
 * Phone-number cleaning + country-code prefix logic.
 *
 * @package Sendsmsro\ForWooCommerce
 */

namespace Sendsmsro\ForWooCommerce\Order;

use Sendsmsro\ForWooCommerce\CountryCodes;

defined( 'ABSPATH' ) || exit;

/**
 * Phone-number helpers.
 *
 * Logic preserved verbatim from v1.x `wc_sendsms_validate_phone` so that
 * customers already using the plugin see no behavioural change in the numbers
 * actually sent to the API.
 */
final class PhoneNumber {

	/**
	 * Sentinel for the "international" country code (skips local prefix logic).
	 */
	const INTERNATIONAL = 'INT';

	/**
	 * Strip everything that isn't a digit.
	 *
	 * @param string $raw Raw phone string as entered by a human.
	 * @return string Digits-only string (may be empty).
	 */
	public static function digits_only( string $raw ): string {
		// First pass: keep digits and +/-, then drop +/-.
		$cleaned = str_replace( array( '+', '-' ), '', (string) filter_var( $raw, FILTER_SANITIZE_NUMBER_INT ) );
		// Defensive second pass to strip anything FILTER_SANITIZE_NUMBER_INT might have let through.
		return preg_replace( '/[^0-9]/', '', $cleaned );
	}

	/**
	 * Normalise a phone number for upstream submission.
	 *
	 * @param string $raw  Raw phone string.
	 * @param string $cc   Country code key from {@see CountryCodes}, or "INT" to leave the number alone (after digit-stripping).
	 * @return string Empty string if the input was empty. Otherwise the digits-only number, optionally prefixed with the dial code.
	 */
	public static function normalize( string $raw, string $cc = self::INTERNATIONAL ): string {
		if ( '' === $raw ) {
			return '';
		}
		$digits = self::digits_only( $raw );

		if ( self::INTERNATIONAL === $cc ) {
			return $digits;
		}

		$digits = ltrim( $digits, '0' );
		$dial   = CountryCodes::dial_code_for( $cc );
		if ( '' !== $dial && 0 !== strpos( $digits, $dial ) ) {
			$digits = $dial . $digits;
		}
		return $digits;
	}
}
