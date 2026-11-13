<?php
/**
 * Route parsing, summaries and risk.
 *
 * @package ApprovalGate
 */

use PHPUnit\Framework\TestCase;

/**
 * WCAG_Summary.
 */
class SummaryTest extends TestCase {

	/**
	 * Parse.
	 */
	public function test_parse_routes() {
		$p = WCAG_Summary::parse( '/wc/v3/orders/4821' );
		$this->assertSame( 'orders', $p['resource'] );
		$this->assertSame( 4821, $p['id'] );
		$this->assertSame( '', $p['sub'] );
		$this->assertFalse( $p['batch'] );

		$p = WCAG_Summary::parse( '/wc/v3/orders/4821/refunds' );
		$this->assertSame( 'refunds', $p['sub'] );

		$p = WCAG_Summary::parse( '/wc/v3/products/9/variations/12' );
		$this->assertSame( 'products', $p['resource'] );
		$this->assertSame( 9, $p['id'] );
		$this->assertSame( 12, $p['sub_id'] );

		$p = WCAG_Summary::parse( '/wc/v3/products/batch' );
		$this->assertTrue( $p['batch'] );
		$this->assertNull( $p['id'] );

		$this->assertNull( WCAG_Summary::parse( '/wc/v3/reports/sales' ) );
		$this->assertNull( WCAG_Summary::parse( '/wc/store/v1/cart' ) );
		$this->assertNull( WCAG_Summary::parse( '/wp/v2/posts' ) );
	}

	/**
	 * Writes.
	 */
	public function test_is_write() {
		$this->assertFalse( WCAG_Summary::is_write( 'GET' ) );
		$this->assertFalse( WCAG_Summary::is_write( 'head' ) );
		$this->assertTrue( WCAG_Summary::is_write( 'POST' ) );
		$this->assertTrue( WCAG_Summary::is_write( 'DELETE' ) );
	}

	/**
	 * Summaries.
	 */
	public function test_summaries() {
		$this->assertSame( 'update order #4821 (status: completed)', WCAG_Summary::summarize( 'PUT', '/wc/v3/orders/4821', array( 'status' => 'completed' ) ) );
		$this->assertSame( 'update product #9 (regular_price: 19.90, stock_quantity: 3)', WCAG_Summary::summarize( 'PUT', '/wc/v3/products/9', array( 'regular_price' => '19.90', 'stock_quantity' => 3 ) ) );
		$this->assertSame( 'create refund of order #4821 (amount: 10.00, reason: damaged)', WCAG_Summary::summarize( 'POST', '/wc/v3/orders/4821/refunds', array( 'amount' => '10.00', 'reason' => 'damaged' ) ) );
		$this->assertSame( 'delete coupon #3', WCAG_Summary::summarize( 'DELETE', '/wc/v3/coupons/3', array() ) );
		$this->assertSame( 'batch 5 products', WCAG_Summary::summarize( 'POST', '/wc/v3/products/batch', array( 'update' => array( 1, 2, 3 ), 'delete' => array( 4, 5 ) ) ) );
		$this->assertSame( 'create order (set_paid: true, 2 line item(s))', WCAG_Summary::summarize( 'POST', '/wc/v3/orders', array( 'set_paid' => true, 'line_items' => array( array(), array() ) ) ) );
		$this->assertSame( 'POST /foo', WCAG_Summary::summarize( 'POST', '/foo', array() ) );
	}

	/**
	 * Risk.
	 */
	public function test_risk() {
		$this->assertSame( 'high', WCAG_Summary::risk( 'DELETE', '/wc/v3/products/9', array() ) );
		$this->assertSame( 'high', WCAG_Summary::risk( 'POST', '/wc/v3/orders/1/refunds', array( 'amount' => '5' ) ) );
		$this->assertSame( 'high', WCAG_Summary::risk( 'PUT', '/wc/v3/settings/general/woocommerce_currency', array( 'value' => 'EUR' ) ) );
		$this->assertSame( 'high', WCAG_Summary::risk( 'POST', '/wc/v3/webhooks', array() ) );
		$this->assertSame( 'high', WCAG_Summary::risk( 'POST', '/wc/v3/products/batch', array( 'delete' => array( 1 ) ) ) );
		$this->assertSame( 'medium', WCAG_Summary::risk( 'PUT', '/wc/v3/products/9', array( 'regular_price' => '1' ) ) );
		$this->assertSame( 'medium', WCAG_Summary::risk( 'PUT', '/wc/v3/orders/9', array( 'status' => 'cancelled' ) ) );
		$this->assertSame( 'medium', WCAG_Summary::risk( 'POST', '/wc/v3/products/batch', array( 'update' => array( 1 ) ) ) );
		$this->assertSame( 'low', WCAG_Summary::risk( 'PUT', '/wc/v3/products/9', array( 'description' => 'x' ) ) );
		$this->assertSame( 'low', WCAG_Summary::risk( 'POST', '/wc/v3/orders/9/notes', array( 'note' => 'called the customer' ) ) );
	}

	/**
	 * Long values are truncated, non-scalars ignored.
	 */
	public function test_notable_fields_truncate() {
		$f = WCAG_Summary::notable_fields( array( 'name' => str_repeat( 'a', 100 ), 'meta_data' => array( 1 ) ) );
		$this->assertSame( 'name: ' . str_repeat( 'a', 60 ), $f );
	}
}
