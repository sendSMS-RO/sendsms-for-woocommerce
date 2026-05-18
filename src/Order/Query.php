<?php
/**
 * Read-only queries against past WooCommerce orders for the campaign page.
 *
 * @package SendSMS\ForWooCommerce
 */

namespace SendSMS\ForWooCommerce\Order;

use WC_Order;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Helper around `wc_get_orders` for the campaign UI.
 *
 * HPOS-compatible: uses the WC order API exclusively.
 */
final class Query {

	/**
	 * Fetch all completed orders (no filters).
	 *
	 * @return WC_Order[]
	 */
	public static function completed_orders(): array {
		$orders = wc_get_orders(
			array(
				'limit'  => -1,
				'status' => 'completed',
				'type'   => 'shop_order',
				'return' => 'objects',
			)
		);
		return self::keep_only_shop_orders( is_array( $orders ) ? $orders : array() );
	}

	/**
	 * Fetch completed orders matching the campaign filter form.
	 *
	 * @param string   $period_start "YYYY-MM-DD" or empty.
	 * @param string   $period_end   "YYYY-MM-DD" or empty.
	 * @param string   $min_amount   Decimal amount as string, or empty.
	 * @param string[] $state_keys   List of strings like ["id_RO-CJ", "id_RO-B"], or empty.
	 * @param string[] $product_keys List of strings like ["id_42", "id_98"], or empty.
	 * @return WC_Order[]
	 */
	public static function filtered_completed_orders(
		string $period_start,
		string $period_end,
		string $min_amount,
		array $state_keys,
		array $product_keys
	): array {
		$args = array(
			'limit'  => -1,
			'status' => 'completed',
			'type'   => 'shop_order',
			'return' => 'objects',
		);

		if ( '' !== $period_start && '' !== $period_end ) {
			// `wc_get_orders` accepts "from...to" as a date_created range.
			$inclusive_end          = gmdate( 'Y-m-d', strtotime( $period_end . ' +1 day' ) );
			$args['date_created']   = $period_start . '...' . $inclusive_end;
		} elseif ( '' !== $period_start ) {
			$args['date_created'] = '>=' . $period_start;
		} elseif ( '' !== $period_end ) {
			$inclusive_end        = gmdate( 'Y-m-d', strtotime( $period_end . ' +1 day' ) );
			$args['date_created'] = '<' . $inclusive_end;
		}

		$orders = wc_get_orders( $args );
		$orders = self::keep_only_shop_orders( is_array( $orders ) ? $orders : array() );

		$min        = is_numeric( $min_amount ) ? (float) $min_amount : 0.0;
		$state_set  = self::strip_prefix( $state_keys );
		$product_set = self::strip_prefix( $product_keys );

		$result = array();
		foreach ( $orders as $order ) {
			if ( $min > 0.0 && (float) $order->get_total() < $min ) {
				continue;
			}
			if ( ! empty( $state_set ) && ! in_array( $order->get_billing_state(), $state_set, true ) ) {
				continue;
			}
			if ( ! empty( $product_set ) ) {
				$order_pids = array();
				foreach ( $order->get_items() as $item ) {
					$order_pids[] = (string) $item->get_product_id();
				}
				if ( empty( array_intersect( $product_set, $order_pids ) ) ) {
					continue;
				}
			}
			$result[] = $order;
		}
		return $result;
	}

	/**
	 * Reduce orders to a list of unique, validated phone numbers.
	 *
	 * @param WC_Order[] $orders The orders to extract from.
	 * @param string     $cc     Country code (see {@see PhoneNumber}).
	 * @return string[]
	 */
	public static function unique_phones( array $orders, string $cc ): array {
		$out = array();
		foreach ( $orders as $order ) {
			$phone = PhoneNumber::normalize( (string) $order->get_billing_phone(), $cc );
			if ( '' !== $phone ) {
				$out[] = $phone;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * List published products + variations for the product multi-select.
	 *
	 * @return array<int,array{name:string, sku:string, id:int}> Sorted by name.
	 */
	public static function product_options(): array {
		$loop = new WP_Query(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$out = array();
		foreach ( $loop->posts as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$name = (string) $product->get_name();
			$sku  = (string) $product->get_sku();
			if ( '' === $sku ) {
				$sku = 'ID-' . (int) $pid;
			}
			if ( $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
				$parent    = $parent_id ? wc_get_product( $parent_id ) : null;
				if ( $parent ) {
					$name = $parent->get_name() . ' - ' . $name;
				}
			}
			$out[] = array(
				'name' => $name,
				'sku'  => $sku,
				'id'   => (int) $pid,
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		return $out;
	}

	/**
	 * List unique billing states from completed orders, mapped to display names.
	 *
	 * @return array<int,array{code:string, label:string}> Sorted by label.
	 */
	public static function billing_state_options(): array {
		$orders = self::completed_orders();
		$found  = array();
		foreach ( $orders as $order ) {
			$state_code   = (string) $order->get_billing_state();
			$country_code = (string) $order->get_billing_country();
			if ( '' === $state_code || isset( $found[ $state_code ] ) ) {
				continue;
			}
			$states           = WC()->countries ? WC()->countries->get_states( $country_code ) : array();
			$found[ $state_code ] = isset( $states[ $state_code ] ) ? (string) $states[ $state_code ] : $state_code;
		}
		asort( $found );
		$out = array();
		foreach ( $found as $code => $label ) {
			$out[] = array(
				'code'  => (string) $code,
				'label' => (string) $label,
			);
		}
		return $out;
	}

	/**
	 * Filter out anything that isn't a shop_order (e.g. refunds slipping through).
	 *
	 * @param WC_Order[]|object[] $orders Raw return from wc_get_orders().
	 * @return WC_Order[]
	 */
	private static function keep_only_shop_orders( array $orders ): array {
		$out = array();
		foreach ( $orders as $order ) {
			if ( $order instanceof WC_Order && 'shop_order' === $order->get_type() ) {
				$out[] = $order;
			}
		}
		return $out;
	}

	/**
	 * Strip the "id_" prefix from a list of multi-select values.
	 *
	 * @param string[] $values Multi-select option values from the campaign form.
	 * @return string[]
	 */
	private static function strip_prefix( array $values ): array {
		$out = array();
		foreach ( $values as $v ) {
			$v = (string) $v;
			if ( 0 === strpos( $v, 'id_' ) ) {
				$out[] = substr( $v, 3 );
			} else {
				$out[] = $v;
			}
		}
		return $out;
	}
}
