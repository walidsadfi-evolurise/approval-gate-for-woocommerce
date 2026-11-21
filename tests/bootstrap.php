<?php
/**
 * Test bootstrap: the pure classes only need ABSPATH defined.
 *
 * @package ApprovalGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

require_once __DIR__ . '/../includes/class-evoag-summary.php';
require_once __DIR__ . '/../includes/class-evoag-settings.php';

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * In-memory options.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['evoag_test_options'][ $key ] ) ? $GLOBALS['evoag_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Email filter.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Key filter.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Text filter.
	 *
	 * @param string $s String.
	 * @return string
	 */
	function sanitize_text_field( $s ) {
		return trim( strip_tags( (string) $s ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Keep http(s) URLs only.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return preg_match( '#^https?://#i', $url ) ? $url : '';
	}
}
