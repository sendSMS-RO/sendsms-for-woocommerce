<?php
/**
 * Substitutes {billing_first_name} etc. into SMS templates.
 *
 * @package Sendsmsro\ForWooCommerce
 */

namespace Sendsmsro\ForWooCommerce\Order;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces placeholder tokens in an SMS template with order data.
 *
 * Romanian diacritics in name fields are folded to plain ASCII so that
 * the gateway charges a single SMS rather than upgrading to UCS-2.
 */
final class PlaceholderReplacer {

	/**
	 * Tokens supported in templates. Documented in the README and in the
	 * settings-page help text.
	 *
	 * @return string[]
	 */
	public static function supported_tokens(): array {
		return array(
			'{billing_first_name}',
			'{billing_last_name}',
			'{shipping_first_name}',
			'{shipping_last_name}',
			'{order_number}',
			'{order_date}',
			'{order_total}',
		);
	}

	/**
	 * Apply placeholder substitution to a template against the given order.
	 *
	 * @param string   $template Template containing {placeholder} tokens.
	 * @param WC_Order $order    The order being processed.
	 * @return string Rendered SMS body.
	 */
	public static function render( string $template, WC_Order $order ): string {
		$created = $order->get_date_created();
		$replacements = array(
			'{billing_first_name}'  => self::strip_diacritics( $order->get_billing_first_name() ),
			'{billing_last_name}'   => self::strip_diacritics( $order->get_billing_last_name() ),
			'{shipping_first_name}' => self::strip_diacritics( $order->get_shipping_first_name() ),
			'{shipping_last_name}'  => self::strip_diacritics( $order->get_shipping_last_name() ),
			'{order_number}'        => (string) $order->get_id(),
			'{order_date}'          => $created ? $created->date( 'd-m-Y' ) : '',
			'{order_total}'         => number_format( (float) $order->get_total(), wc_get_price_decimals(), ',', '' ),
		);
		return strtr( $template, $replacements );
	}

	/**
	 * Strip Romanian diacritics (and a couple of stray glyphs the original
	 * plugin folded) down to plain ASCII.
	 *
	 * @param string $string Input string.
	 * @return string Folded string.
	 */
	public static function strip_diacritics( string $string ): string {
		$pairs = array(
			"\xC4\x82" => 'A',
			"\xC4\x83" => 'a',
			"\xC3\x82" => 'A',
			"\xC3\xA2" => 'a',
			"\xC3\x8E" => 'I',
			"\xC3\xAE" => 'i',
			"\xC8\x98" => 'S',
			"\xC8\x99" => 's',
			"\xC8\x9A" => 'T',
			"\xC8\x9B" => 't',
			"\xC5\x9E" => 'S',
			"\xC5\x9F" => 's',
			"\xC5\xA2" => 'T',
			"\xC5\xA3" => 't',
			"\xC3\xA3" => 'a',
			"\xC2\xAD" => ' ',
			"\xE2\x80\x93" => '-',
		);
		return strtr( $string, $pairs );
	}
}
