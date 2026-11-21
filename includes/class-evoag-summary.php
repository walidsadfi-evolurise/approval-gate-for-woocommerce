<?php
/**
 * Pure helpers: route parsing and human summaries of a proposed write. No WordPress dependency.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns (method, route, body) into something a reviewer can read in two seconds.
 */
class EVOAG_Summary {

	/**
	 * Resources the gate can cover.
	 *
	 * @return array slug => label
	 */
	public static function resources() {
		return array(
			'orders'    => 'Orders (incl. refunds, notes)',
			'products'  => 'Products (incl. variations, categories, tags, attributes)',
			'customers' => 'Customers',
			'coupons'   => 'Coupons',
			'settings'  => 'Settings',
			'webhooks'  => 'Webhooks',
			'shipping'  => 'Shipping',
			'taxes'     => 'Taxes',
		);
	}

	/**
	 * Parse a /wc/vN/... route.
	 *
	 * @param string $route Route.
	 * @return array|null resource, id (int|null), sub (string), sub_id (int|null), batch (bool)
	 */
	public static function parse( $route ) {
		$route = '/' . trim( (string) $route, '/' );
		if ( ! preg_match( '#^/wc/v\d+/([a-z_]+)(?:/(\d+))?(?:/([a-z_]+))?(?:/(\d+))?#', $route, $m ) ) {
			return null;
		}
		$resource = $m[1];
		$map      = array(
			'orders'    => 'orders',
			'products'  => 'products',
			'customers' => 'customers',
			'coupons'   => 'coupons',
			'settings'  => 'settings',
			'webhooks'  => 'webhooks',
			'shipping'  => 'shipping',
			'taxes'     => 'taxes',
		);
		if ( ! isset( $map[ $resource ] ) ) {
			return null;
		}
		$sub = isset( $m[3] ) ? $m[3] : '';
		return array(
			'resource' => $map[ $resource ],
			'id'       => isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : null,
			'sub'      => $sub,
			'sub_id'   => isset( $m[4] ) && '' !== $m[4] ? (int) $m[4] : null,
			'batch'    => 'batch' === $sub,
		);
	}

	/**
	 * Is this a write?
	 *
	 * @param string $method Method.
	 * @return bool
	 */
	public static function is_write( $method ) {
		return ! in_array( strtoupper( (string) $method ), array( 'GET', 'HEAD', 'OPTIONS' ), true );
	}

	/**
	 * One-line summary.
	 *
	 * @param string $method Method.
	 * @param string $route  Route.
	 * @param array  $body   Decoded JSON body.
	 * @return string
	 */
	public static function summarize( $method, $route, array $body ) {
		$p = self::parse( $route );
		if ( ! $p ) {
			return strtoupper( $method ) . ' ' . $route;
		}
		$verb = array(
			'POST'   => 'create',
			'PUT'    => 'update',
			'PATCH'  => 'update',
			'DELETE' => 'delete',
		);
		$v    = isset( $verb[ strtoupper( $method ) ] ) ? $verb[ strtoupper( $method ) ] : strtolower( $method );
		if ( $p['batch'] ) {
			$n = 0;
			foreach ( array( 'create', 'update', 'delete' ) as $k ) {
				$n += isset( $body[ $k ] ) && is_array( $body[ $k ] ) ? count( $body[ $k ] ) : 0;
			}
			return sprintf( 'batch %d %s', $n, $p['resource'] );
		}
		$target = rtrim( $p['resource'], 's' );
		if ( $p['sub'] ) {
			$target = rtrim( $p['sub'], 's' ) . ' of ' . $target . ( $p['id'] ? ' #' . $p['id'] : '' );
			if ( $p['sub_id'] ) {
				$target .= ' #' . $p['sub_id'];
			}
		} elseif ( $p['id'] ) {
			$target .= ' #' . $p['id'];
		}
		$fields = self::notable_fields( $body );
		return trim( sprintf( '%s %s%s', $v, $target, $fields ? ' (' . $fields . ')' : '' ) );
	}

	/**
	 * The fields a reviewer cares about, as "status: completed, regular_price: 19.90".
	 *
	 * @param array $body Body.
	 * @return string
	 */
	public static function notable_fields( array $body ) {
		$keys = array( 'status', 'regular_price', 'sale_price', 'price', 'stock_quantity', 'stock_status', 'name', 'sku', 'email', 'amount', 'code', 'discount_type', 'note', 'customer_note', 'set_paid', 'manage_stock', 'catalog_visibility', 'reason' );
		$out  = array();
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $body ) && is_scalar( $body[ $k ] ) ) {
				$val   = is_bool( $body[ $k ] ) ? ( $body[ $k ] ? 'true' : 'false' ) : (string) $body[ $k ];
				$out[] = $k . ': ' . mb_substr( $val, 0, 60 );
			}
		}
		if ( isset( $body['line_items'] ) && is_array( $body['line_items'] ) ) {
			$out[] = count( $body['line_items'] ) . ' line item(s)';
		}
		return implode( ', ', $out );
	}

	/**
	 * Risk hint for the reviewer: destructive or money-related changes get flagged.
	 *
	 * @param string $method Method.
	 * @param string $route  Route.
	 * @param array  $body   Body.
	 * @return string low | medium | high
	 */
	public static function risk( $method, $route, array $body ) {
		$p = self::parse( $route );
		if ( 'DELETE' === strtoupper( $method ) || ( $p && $p['batch'] && ! empty( $body['delete'] ) ) ) {
			return 'high';
		}
		if ( $p && 'refunds' === $p['sub'] ) {
			return 'high';
		}
		if ( $p && in_array( $p['resource'], array( 'settings', 'webhooks', 'customers' ), true ) ) {
			return 'high';
		}
		foreach ( array( 'regular_price', 'sale_price', 'price', 'status', 'set_paid', 'amount' ) as $k ) {
			if ( array_key_exists( $k, $body ) ) {
				return 'medium';
			}
		}
		if ( $p && $p['batch'] ) {
			return 'medium';
		}
		return 'low';
	}
}
