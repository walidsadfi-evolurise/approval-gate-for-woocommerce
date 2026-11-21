<?php
/**
 * The gate: intercepts REST writes and stores them as proposals.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs after WooCommerce authenticated the key and checked read/write permission, before the controller.
 */
class EVOAG_Gate {

	/**
	 * True while EVOAG_Decision replays an approved proposal: the gate must let it through.
	 *
	 * @var bool
	 */
	public static $applying = false;

	/**
	 * Register.
	 */
	public static function init() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'intercept' ), 25, 3 );
	}

	/**
	 * Which consumer key does this request carry? Same sources WooCommerce reads.
	 *
	 * @return string
	 */
	public static function detect_consumer_key() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		if ( ! empty( $_GET['consumer_key'] ) ) {
			return (string) wp_unslash( $_GET['consumer_key'] );
		}
		if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
			return (string) wp_unslash( $_SERVER['PHP_AUTH_USER'] );
		}
		$header = '';
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = (string) wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( function_exists( 'getallheaders' ) ) {
			foreach ( getallheaders() as $k => $v ) {
				if ( 'authorization' === strtolower( $k ) ) {
					$header = (string) $v;
				}
			}
		}
		if ( $header && preg_match( '/oauth_consumer_key="([^"]+)"/', $header, $m ) ) {
			return rawurldecode( $m[1] );
		}
		if ( ! empty( $_GET['oauth_consumer_key'] ) ) {
			return (string) wp_unslash( $_GET['oauth_consumer_key'] );
		}
		if ( ! empty( $_POST['oauth_consumer_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- OAuth 1.0a consumer key, authenticated by WooCommerce before this runs.
			return (string) wp_unslash( $_POST['oauth_consumer_key'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		// phpcs:enable
		return '';
	}

	/**
	 * Should this request become a proposal?
	 *
	 * @param WP_REST_Request $request Request.
	 * @param object          $key     WooCommerce key row.
	 * @return bool
	 */
	public static function is_gated( $request, $key ) {
		$settings = EVOAG_Settings::all();
		$gated    = in_array( (int) $key->key_id, array_map( 'intval', (array) $settings['gated_keys'] ), true );
		if ( ! $gated && $settings['header_opt_in'] ) {
			$h     = strtolower( trim( (string) $request->get_header( 'x-wc-approval' ) ) );
			$gated = ( 'propose' === $h );
		}
		/**
		 * Decide whether a write from this key must be reviewed.
		 *
		 * @param bool            $gated   Current decision.
		 * @param object          $key     WooCommerce key row.
		 * @param WP_REST_Request $request Request.
		 */
		return (bool) apply_filters( 'evoag_is_gated', $gated, $key, $request );
	}

	/**
	 * Is this the request the client actually sent, as opposed to an internal rest_do_request()?
	 *
	 * WordPress and WooCommerce Admin dispatch nested REST requests from server-side code (for
	 * example analytics indicators while routes register). Those are not something the API client
	 * asked for, so the policy applies to the top-level request only. Sub-requests of the core
	 * batch endpoint are the exception: there the client did ask for each of them.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function is_primary_request( $request ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $uri ) {
			return true; // No HTTP context (CLI, cron): nothing to compare against.
		}
		$route = untrailingslashit( $request->get_route() );
		$path  = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		if ( '' !== $route && substr( $path, -strlen( $route ) ) === $route ) {
			return true;
		}
		$query = array();
		wp_parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $query );
		if ( isset( $query['rest_route'] ) && untrailingslashit( (string) $query['rest_route'] ) === $route ) {
			return true;
		}
		// Nested requests of /batch/v1 carry the client's own operations.
		if ( substr( $path, -9 ) === '/batch/v1' || ( isset( $query['rest_route'] ) && untrailingslashit( (string) $query['rest_route'] ) === '/batch/v1' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Intercept.
	 *
	 * @param mixed           $result  Pre-dispatch result.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function intercept( $result, $server, $request ) {
		if ( self::$applying || is_wp_error( $result ) || null !== $result ) {
			return $result;
		}
		if ( ! self::is_primary_request( $request ) ) {
			return $result; // Internal sub-request made by server-side code, not something the integration asked for.
		}
		if ( ! EVOAG_Summary::is_write( $request->get_method() ) ) {
			return $result;
		}
		$route  = $request->get_route();
		$parsed = EVOAG_Summary::parse( $route );
		if ( ! $parsed || ! in_array( $parsed['resource'], (array) EVOAG_Settings::get( 'resources' ), true ) ) {
			return $result;
		}
		if ( 0 === strpos( ltrim( $route, '/' ), 'wc/v3/approval-gate' ) ) {
			return $result;
		}

		$consumer_key = self::detect_consumer_key();
		if ( '' === $consumer_key ) {
			return $result; // Cookie/nonce or application password: a human in wp-admin, not an integration.
		}
		$key = EVOAG_Store::wc_key_by_consumer_key( $consumer_key );
		if ( ! $key || get_current_user_id() !== (int) $key->user_id ) {
			return $result; // WooCommerce did not authenticate this key.
		}
		if ( ! self::is_gated( $request, $key ) ) {
			return $result;
		}

		return self::propose( $request, $key );
	}

	/**
	 * Store the proposal and answer 202.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param object          $key     Key row.
	 * @return WP_REST_Response
	 */
	public static function propose( $request, $key ) {
		$method = strtoupper( $request->get_method() );
		$route  = $request->get_route();
		$body   = $request->get_json_params();
		if ( ! is_array( $body ) || ! $body ) {
			$body = $request->get_body_params();
		}
		$body   = is_array( $body ) ? $body : array();
		$query  = $request->get_query_params();
		$idem   = sanitize_text_field( (string) $request->get_header( 'idempotency-key' ) );
		$reason = sanitize_textarea_field( (string) $request->get_header( 'x-wc-approval-reason' ) );
		$parsed = EVOAG_Summary::parse( $route );

		$existing = EVOAG_Store::find_idempotent( (int) $key->key_id, $idem );
		if ( $existing ) {
			return self::pending_response( $existing, true );
		}

		$days = (int) EVOAG_Settings::get( 'expire_days' );
		$id   = EVOAG_Store::insert(
			array(
				'key_id'          => (int) $key->key_id,
				'key_description' => (string) $key->description,
				'user_id'         => (int) $key->user_id,
				'method'          => $method,
				'route'           => $route,
				'resource'        => $parsed ? $parsed['resource'] : '',
				'query_params'    => $query,
				'body'            => $body,
				'summary'         => mb_substr( EVOAG_Summary::summarize( $method, $route, $body ), 0, 500 ),
				'risk'            => EVOAG_Summary::risk( $method, $route, $body ),
				'reason'          => $reason,
				'idempotency_key' => '' !== $idem ? $idem : null,
				'status'          => 'pending',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ),
			)
		);
		if ( ! $id ) {
			return new WP_REST_Response(
				array(
					'code'    => 'evoag_store_failed',
					'message' => 'Could not store the proposal.',
				),
				500
			);
		}
		$row = EVOAG_Store::get( $id );

		/**
		 * A proposal was created.
		 *
		 * @param object          $row     Proposal row.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'evoag_proposal_created', $row, $request );

		return self::pending_response( $row, false );
	}

	/**
	 * 202 body shared with the status endpoint.
	 *
	 * @param object $row       Row.
	 * @param bool   $duplicate Idempotent replay.
	 * @return WP_REST_Response
	 */
	public static function pending_response( $row, $duplicate ) {
		$data = self::public_view( $row );
		if ( $duplicate ) {
			$data['duplicate_of_idempotency_key'] = true;
		}
		$response = new WP_REST_Response( $data, 'pending' === $row->status ? 202 : 200 );
		$response->header( 'X-WC-Approval-Status', $row->status );
		$response->header( 'X-WC-Approval-Id', $row->uuid );
		$response->header( 'Location', rest_url( 'wc/v3/approval-gate/proposals/' . $row->uuid ) );
		return $response;
	}

	/**
	 * What the proposing integration may see.
	 *
	 * @param object $row Row.
	 * @return array
	 */
	public static function public_view( $row ) {
		$out = array(
			'id'          => $row->uuid,
			'status'      => $row->status,
			'method'      => $row->method,
			'route'       => $row->route,
			'summary'     => $row->summary,
			'risk'        => $row->risk,
			'created_at'  => mysql_to_rfc3339( $row->created_at ),
			'expires_at'  => $row->expires_at ? mysql_to_rfc3339( $row->expires_at ) : null,
			'decided_at'  => $row->decided_at ? mysql_to_rfc3339( $row->decided_at ) : null,
			'note'        => $row->decision_note,
			'status_url'  => rest_url( 'wc/v3/approval-gate/proposals/' . $row->uuid ),
			'review_url'  => admin_url( 'admin.php?page=evoag&proposal=' . (int) $row->id ),
			'result_code' => null !== $row->result_code ? (int) $row->result_code : null,
			'result'      => null,
		);
		if ( 'approved' === $row->status || 'failed' === $row->status ) {
			$decoded       = json_decode( (string) $row->result_body, true );
			$out['result'] = null === $decoded ? $row->result_body : $decoded;
		}
		return $out;
	}
}
