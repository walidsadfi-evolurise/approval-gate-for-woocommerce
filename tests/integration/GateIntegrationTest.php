<?php
/**
 * Real REST writes through WP_REST_Server with a WooCommerce consumer key: gated, approved, rejected.
 *
 * @package ApprovalGate
 */

/**
 * Runs against WordPress + WooCommerce (see .github/workflows/ci.yml).
 */
class GateIntegrationTest extends WP_UnitTestCase {

	/**
	 * Key row id.
	 *
	 * @var int
	 */
	private $key_id;

	/**
	 * Raw consumer key / secret.
	 *
	 * @var string
	 */
	private $ck;

	/**
	 * Secret.
	 *
	 * @var string
	 */
	private $cs;

	/**
	 * Admin user id (key owner).
	 *
	 * @var int
	 */
	private $admin;

	/**
	 * Admin + read/write key, the way WooCommerce creates them.
	 */
	public function set_up() {
		parent::set_up();
		global $wpdb;
		$this->admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->ck    = 'ck_' . wc_rand_hash();
		$this->cs    = 'cs_' . wc_rand_hash();
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			array(
				'user_id'         => $this->admin,
				'description'     => 'agent',
				'permissions'     => 'read_write',
				'consumer_key'    => wc_api_hash( $this->ck ),
				'consumer_secret' => $this->cs,
				'truncated_key'   => substr( $this->ck, -7 ),
			)
		);
		$this->key_id = (int) $wpdb->insert_id;
		update_option( WCAG_Settings::OPTION, WCAG_Settings::defaults() );
		$_SERVER['HTTPS']         = 'on';
		$_SERVER['PHP_AUTH_USER'] = $this->ck;
		$_SERVER['PHP_AUTH_PW']   = $this->cs;
		wp_set_current_user( 0 );
		// A real wp-json request defines REST_REQUEST; WooCommerce (since 11.x) only authenticates API keys when wp_is_rest_endpoint() is true.
		add_filter( 'wp_is_rest_endpoint', '__return_true' );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Clean.
	 */
	public function tear_down() {
		unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['HTTPS'] );
		remove_filter( 'wp_is_rest_endpoint', '__return_true' );
		parent::tear_down();
	}

	/**
	 * Gate the test key.
	 */
	private function gate_key() {
		$s               = WCAG_Settings::all();
		$s['gated_keys'] = array( $this->key_id );
		update_option( WCAG_Settings::OPTION, WCAG_Settings::sanitize( $s ) );
	}

	/**
	 * Dispatch like wp-json would, re-running WooCommerce authentication.
	 *
	 * @param string     $method  Method.
	 * @param string     $route   Route.
	 * @param array|null $body    JSON body.
	 * @param array      $headers Extra headers.
	 * @param array      $query   Query params.
	 * @return WP_REST_Response
	 */
	private function call( $method, $route, $body = null, $headers = array(), $query = array() ) {
		wp_set_current_user( 0 );
		$GLOBALS['current_user']   = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_SERVER['REQUEST_URI']    = '/wp-json' . $route;
		$_SERVER['REQUEST_METHOD'] = $method;
		$user_id                   = apply_filters( 'determine_current_user', false );
		wp_set_current_user( (int) $user_id );
		$request = new WP_REST_Request( $method, $route );
		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		foreach ( $headers as $k => $v ) {
			$request->set_header( $k, $v );
		}
		if ( $query ) {
			$request->set_query_params( $query );
		}
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Create a product directly.
	 *
	 * @return WC_Product
	 */
	private function product() {
		$p = new WC_Product_Simple();
		$p->set_name( 'Lamp' );
		$p->set_regular_price( '40' );
		$p->save();
		return $p;
	}

	/**
	 * An ungated key writes directly.
	 */
	public function test_ungated_key_writes_directly() {
		$p = $this->product();
		$r = $this->call( 'PUT', '/wc/v3/products/' . $p->get_id(), array( 'regular_price' => '35' ) );
		$this->assertSame( 200, $r->get_status() );
		$this->assertSame( '35', wc_get_product( $p->get_id() )->get_regular_price() );
		$this->assertSame( 0, WCAG_Store::counts()['pending'] );
	}

	/**
	 * A gated key's write is stored, answered 202, and nothing changes.
	 */
	public function test_gated_write_becomes_proposal() {
		$this->gate_key();
		$p = $this->product();
		$r = $this->call( 'PUT', '/wc/v3/products/' . $p->get_id(), array( 'regular_price' => '35' ), array( 'X-WC-Approval-Reason' => 'competitor dropped price' ) );
		$this->assertSame( 202, $r->get_status() );
		$data = $r->get_data();
		$this->assertSame( 'pending', $data['status'] );
		$this->assertSame( 'update product #' . $p->get_id() . ' (regular_price: 35)', $data['summary'] );
		$this->assertSame( 'medium', $data['risk'] );
		$this->assertSame( 'pending', $r->get_headers()['X-WC-Approval-Status'] );

		$this->assertSame( '40', wc_get_product( $p->get_id() )->get_regular_price(), 'price untouched' );
		$row = WCAG_Store::get_by_uuid( $data['id'] );
		$this->assertSame( 'competitor dropped price', $row->reason );
		$this->assertSame( $this->key_id, (int) $row->key_id );
	}

	/**
	 * Reads from a gated key still work.
	 */
	public function test_gated_key_still_reads() {
		$this->gate_key();
		$p = $this->product();
		$r = $this->call( 'GET', '/wc/v3/products/' . $p->get_id() );
		$this->assertSame( 200, $r->get_status() );
		$this->assertSame( 'Lamp', $r->get_data()['name'] );
	}

	/**
	 * Approving replays the request: WooCommerce applies it, the row records the result, the key can poll it.
	 */
	public function test_approve_applies_change() {
		$this->gate_key();
		$p    = $this->product();
		$r    = $this->call( 'PUT', '/wc/v3/products/' . $p->get_id(), array( 'regular_price' => '35', 'stock_status' => 'outofstock' ) );
		$uuid = $r->get_data()['id'];
		$row  = WCAG_Store::get_by_uuid( $uuid );

		$reviewer = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		$res      = WCAG_Decision::approve( (int) $row->id, $reviewer, 'ok for the sale' );
		$this->assertFalse( is_wp_error( $res ) );
		$this->assertSame( 'approved', $res['status'] );
		$this->assertSame( 200, $res['code'] );

		$fresh = wc_get_product( $p->get_id() );
		$this->assertSame( '35', $fresh->get_regular_price() );
		$this->assertSame( 'outofstock', $fresh->get_stock_status() );

		// The integration polls the status endpoint with its key.
		$poll = $this->call( 'GET', '/wc/v3/approval-gate/proposals/' . $uuid );
		$this->assertSame( 200, $poll->get_status() );
		$this->assertSame( 'approved', $poll->get_data()['status'] );
		$this->assertSame( 'ok for the sale', $poll->get_data()['note'] );
		$this->assertSame( '35', $poll->get_data()['result']['regular_price'] );

		// Cannot approve twice.
		$again = WCAG_Decision::approve( (int) $row->id, $reviewer );
		$this->assertTrue( is_wp_error( $again ) );
		$this->assertSame( 'wcag_not_pending', $again->get_error_code() );
	}

	/**
	 * Rejecting leaves the store alone and returns the note.
	 */
	public function test_reject() {
		$this->gate_key();
		$p    = $this->product();
		$r    = $this->call( 'DELETE', '/wc/v3/products/' . $p->get_id(), null, array(), array( 'force' => 'true' ) );
		$this->assertSame( 202, $r->get_status() );
		$this->assertSame( 'high', $r->get_data()['risk'] );
		$row = WCAG_Store::get_by_uuid( $r->get_data()['id'] );
		WCAG_Decision::reject( (int) $row->id, $this->admin, 'no deletions' );
		$this->assertInstanceOf( 'WC_Product', wc_get_product( $p->get_id() ) );
		$poll = $this->call( 'GET', '/wc/v3/approval-gate/proposals/' . $row->uuid );
		$this->assertSame( 'rejected', $poll->get_data()['status'] );
		$this->assertSame( 'no deletions', $poll->get_data()['note'] );
	}

	/**
	 * Per-request opt-in from an ungated key.
	 */
	public function test_header_opt_in() {
		$p = $this->product();
		$r = $this->call( 'PUT', '/wc/v3/products/' . $p->get_id(), array( 'regular_price' => '1' ), array( 'X-WC-Approval' => 'propose' ) );
		$this->assertSame( 202, $r->get_status() );
		$this->assertSame( '40', wc_get_product( $p->get_id() )->get_regular_price() );
	}

	/**
	 * Resources not in the gated list pass through.
	 */
	public function test_ungated_resource_passes() {
		$this->gate_key();
		$s              = WCAG_Settings::all();
		$s['resources'] = array( 'orders' );
		update_option( WCAG_Settings::OPTION, WCAG_Settings::sanitize( $s ) );
		$p = $this->product();
		$r = $this->call( 'PUT', '/wc/v3/products/' . $p->get_id(), array( 'regular_price' => '2' ) );
		$this->assertSame( 200, $r->get_status() );
	}

	/**
	 * Same Idempotency-Key from the same key returns the existing proposal.
	 */
	public function test_idempotency() {
		$this->gate_key();
		$p  = $this->product();
		$r1 = $this->call( 'PUT', '/wc/v3/products/' . $p->get_id(), array( 'regular_price' => '3' ), array( 'Idempotency-Key' => 'abc-1' ) );
		$r2 = $this->call( 'PUT', '/wc/v3/products/' . $p->get_id(), array( 'regular_price' => '3' ), array( 'Idempotency-Key' => 'abc-1' ) );
		$this->assertSame( $r1->get_data()['id'], $r2->get_data()['id'] );
		$this->assertTrue( $r2->get_data()['duplicate_of_idempotency_key'] );
		$this->assertSame( 1, WCAG_Store::counts()['pending'] );
	}

	/**
	 * Another key cannot read a proposal it did not make.
	 */
	public function test_other_key_cannot_poll() {
		$this->gate_key();
		$p    = $this->product();
		$uuid = $this->call( 'POST', '/wc/v3/products/' . $p->get_id() . '/variations', array( 'regular_price' => '9' ) )->get_data()['id'];

		global $wpdb;
		$ck2 = 'ck_' . wc_rand_hash();
		$cs2 = 'cs_' . wc_rand_hash();
		$wpdb->insert( $wpdb->prefix . 'woocommerce_api_keys', array( 'user_id' => $this->admin, 'description' => 'other', 'permissions' => 'read', 'consumer_key' => wc_api_hash( $ck2 ), 'consumer_secret' => $cs2, 'truncated_key' => substr( $ck2, -7 ) ) );
		$_SERVER['PHP_AUTH_USER'] = $ck2;
		$_SERVER['PHP_AUTH_PW']   = $cs2;
		$r = $this->call( 'GET', '/wc/v3/approval-gate/proposals/' . $uuid );
		$this->assertSame( 404, $r->get_status() );
	}

	/**
	 * Expired proposals cannot be applied.
	 */
	public function test_expiry() {
		$this->gate_key();
		$p   = $this->product();
		$r   = $this->call( 'PUT', '/wc/v3/products/' . $p->get_id(), array( 'regular_price' => '5' ) );
		$row = WCAG_Store::get_by_uuid( $r->get_data()['id'] );
		WCAG_Store::update( (int) $row->id, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
		$this->assertSame( 1, WCAG_Decision::expire() );
		$res = WCAG_Decision::approve( (int) $row->id, $this->admin );
		$this->assertTrue( is_wp_error( $res ) );
		$this->assertSame( '40', wc_get_product( $p->get_id() )->get_regular_price() );
	}
}
