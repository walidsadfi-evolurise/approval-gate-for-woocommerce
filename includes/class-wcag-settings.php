<?php
/**
 * Settings.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

/**
 * One option row.
 */
class WCAG_Settings {

	const OPTION = 'wcag_settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'gated_keys'     => array(),                               // WooCommerce key IDs in proposal mode.
			'header_opt_in'  => 1,                                     // Any key may send X-WC-Approval: propose.
			'resources'      => array( 'orders', 'products', 'customers', 'coupons', 'settings', 'webhooks', 'shipping', 'taxes' ),
			'expire_days'    => 7,
			'decision_url'   => '',
			'decision_token' => '',
			'notify_email'   => '',
			'notify_on_new'  => 1,
			'retention_days' => 90,
		);
	}

	/**
	 * All.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * One.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitise.
	 *
	 * @param array $raw Raw.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$valid = array_keys( WCAG_Summary::resources() );
		return array(
			'gated_keys'     => isset( $raw['gated_keys'] ) ? array_values( array_unique( array_map( 'intval', (array) $raw['gated_keys'] ) ) ) : array(),
			'header_opt_in'  => empty( $raw['header_opt_in'] ) ? 0 : 1,
			'resources'      => isset( $raw['resources'] ) ? array_values( array_intersect( $valid, array_map( 'sanitize_key', (array) $raw['resources'] ) ) ) : array(),
			'expire_days'    => max( 1, min( 90, isset( $raw['expire_days'] ) ? (int) $raw['expire_days'] : 7 ) ),
			'decision_url'   => isset( $raw['decision_url'] ) ? esc_url_raw( trim( (string) $raw['decision_url'] ) ) : '',
			'decision_token' => isset( $raw['decision_token'] ) ? sanitize_text_field( (string) $raw['decision_token'] ) : '',
			'notify_email'   => isset( $raw['notify_email'] ) ? implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $raw['notify_email'] ) ) ) ) ) : '',
			'notify_on_new'  => empty( $raw['notify_on_new'] ) ? 0 : 1,
			'retention_days' => max( 7, min( 730, isset( $raw['retention_days'] ) ? (int) $raw['retention_days'] : 90 ) ),
		);
	}
}
