<?php
/**
 * Status endpoint for the proposing integration.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

/**
 * GET /wc/v3/approval-gate/proposals/{uuid}  — the key that proposed it polls its status.
 * GET /wc/v3/approval-gate/proposals         — that key's proposals (status filter).
 */
class WCAG_Rest {

	/**
	 * Register.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		register_rest_route(
			'wc/v3',
			'/approval-gate/proposals',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'args'                => array(
					'status'   => array(
						'type' => 'string',
						'enum' => array( 'pending', 'approved', 'rejected', 'expired', 'failed' ),
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
				),
			)
		);
		register_rest_route(
			'wc/v3',
			'/approval-gate/proposals/(?P<uuid>[0-9a-fA-F-]{36})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
			)
		);
	}

	/**
	 * Any authenticated user who can read WooCommerce data (key or cookie).
	 *
	 * @return bool
	 */
	public static function can_read() {
		return is_user_logged_in();
	}

	/**
	 * Key id of the current request, 0 for cookie/application-password sessions.
	 *
	 * @return int
	 */
	private static function current_key_id() {
		$ck = WCAG_Gate::detect_consumer_key();
		if ( '' === $ck ) {
			return 0;
		}
		$key = WCAG_Store::wc_key_by_consumer_key( $ck );
		return $key && (int) $key->user_id === get_current_user_id() ? (int) $key->key_id : 0;
	}

	/**
	 * May the caller see this row? Its own key, or a manager.
	 *
	 * @param object $row Row.
	 * @return bool
	 */
	private static function may_see( $row ) {
		if ( current_user_can( 'manage_woocommerce' ) && 0 === self::current_key_id() ) {
			return true;
		}
		return (int) $row->key_id === self::current_key_id();
	}

	/**
	 * One.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get( $request ) {
		$row = WCAG_Store::get_by_uuid( $request['uuid'] );
		if ( ! $row || ! self::may_see( $row ) ) {
			return new WP_Error( 'wcag_not_found', 'Proposal not found.', array( 'status' => 404 ) );
		}
		return WCAG_Gate::pending_response( $row, false );
	}

	/**
	 * List.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function list( $request ) {
		$key_id = self::current_key_id();
		$args   = array(
			'status'   => $request['status'],
			'per_page' => (int) $request['per_page'],
			'page'     => (int) $request['page'],
		);
		if ( $key_id ) {
			$args['key_id'] = $key_id;
		} elseif ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'wcag_forbidden', 'Not allowed.', array( 'status' => 403 ) );
		}
		$res      = WCAG_Store::query( $args );
		$response = new WP_REST_Response( array_map( array( 'WCAG_Gate', 'public_view' ), $res['rows'] ), 200 );
		$response->header( 'X-WP-Total', (string) $res['total'] );
		$response->header( 'X-WP-TotalPages', (string) ceil( $res['total'] / max( 1, $args['per_page'] ) ) );
		return $response;
	}
}
