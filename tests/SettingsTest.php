<?php
/**
 * Settings sanitisation.
 *
 * @package ApprovalGate
 */

use PHPUnit\Framework\TestCase;

/**
 * WCAG_Settings.
 */
class SettingsTest extends TestCase {

	/**
	 * Defaults when nothing saved.
	 */
	public function test_defaults() {
		$GLOBALS['wcag_test_options'] = array();
		$this->assertSame( 7, WCAG_Settings::get( 'expire_days' ) );
		$this->assertSame( array(), WCAG_Settings::get( 'gated_keys' ) );
		$this->assertContains( 'orders', WCAG_Settings::get( 'resources' ) );
	}

	/**
	 * Sanitize clamps and filters.
	 */
	public function test_sanitize() {
		$s = WCAG_Settings::sanitize(
			array(
				'gated_keys'     => array( '3', 3, 'x', 7 ),
				'resources'      => array( 'orders', 'bogus', 'coupons' ),
				'expire_days'    => 500,
				'decision_url'   => 'javascript:alert(1)',
				'notify_email'   => 'a@example.com, nope, b@example.com',
				'retention_days' => 1,
			)
		);
		$this->assertSame( array( 3, 0, 7 ), $s['gated_keys'] );
		$this->assertSame( array( 'orders', 'coupons' ), $s['resources'] );
		$this->assertSame( 90, $s['expire_days'] );
		$this->assertSame( '', $s['decision_url'] );
		$this->assertSame( 'a@example.com, b@example.com', $s['notify_email'] );
		$this->assertSame( 7, $s['retention_days'] );
		$this->assertSame( 0, $s['header_opt_in'] );
	}

	/**
	 * Saved values merge over defaults.
	 */
	public function test_merge() {
		$GLOBALS['wcag_test_options'] = array( WCAG_Settings::OPTION => array( 'expire_days' => 2 ) );
		$this->assertSame( 2, WCAG_Settings::get( 'expire_days' ) );
		$this->assertSame( 1, WCAG_Settings::get( 'header_opt_in' ) );
	}
}
