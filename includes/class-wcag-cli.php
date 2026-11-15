<?php
/**
 * WP-CLI.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands: wp wc approval-gate <command>.
 */
class WCAG_CLI {

	/**
	 * List proposals.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : pending|approved|rejected|expired|failed. Default pending.
	 *
	 * [--key=<key_id>]
	 * : Only this WooCommerce API key.
	 *
	 * [--limit=<n>]
	 * : Default 50.
	 *
	 * [--format=<format>]
	 * : table|json|csv|yaml|count.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wc approval-gate list
	 *     wp wc approval-gate list --status=approved --format=json
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc.
	 */
	public function list( $args, $assoc_args ) {
		$res  = WCAG_Store::query(
			array(
				'status'   => isset( $assoc_args['status'] ) ? $assoc_args['status'] : 'pending',
				'key_id'   => isset( $assoc_args['key'] ) ? (int) $assoc_args['key'] : 0,
				'per_page' => isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50,
			)
		);
		$rows = array();
		foreach ( $res['rows'] as $r ) {
			$rows[] = array(
				'id'         => $r->id,
				'uuid'       => $r->uuid,
				'status'     => $r->status,
				'risk'       => $r->risk,
				'key'        => $r->key_description . ' (#' . $r->key_id . ')',
				'request'    => $r->method . ' ' . $r->route,
				'summary'    => $r->summary,
				'created_at' => $r->created_at,
				'expires_at' => $r->expires_at,
			);
		}
		WP_CLI\Utils\format_items( isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table', $rows, array( 'id', 'status', 'risk', 'key', 'request', 'summary', 'created_at' ) );
	}

	/**
	 * Show one proposal with its full payload.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Proposal id or uuid.
	 *
	 * @param array $args Args.
	 */
	public function show( $args ) {
		$row = $this->row( $args[0] );
		WP_CLI::print_value( WCAG_Gate::public_view( $row ), array( 'format' => 'json' ) );
		WP_CLI::line( '' );
		WP_CLI::line( 'Body:' );
		WP_CLI::line( wp_json_encode( json_decode( (string) $row->body, true ), JSON_PRETTY_PRINT ) );
	}

	/**
	 * Approve and apply a proposal.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Proposal id(s) or uuid(s).
	 *
	 * [--note=<note>]
	 * : Reviewer note.
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc.
	 */
	public function approve( $args, $assoc_args ) {
		foreach ( $args as $id ) {
			$row = $this->row( $id );
			$res = WCAG_Decision::approve( (int) $row->id, get_current_user_id(), isset( $assoc_args['note'] ) ? $assoc_args['note'] : 'wp-cli' );
			if ( is_wp_error( $res ) ) {
				WP_CLI::warning( "#{$row->id}: " . $res->get_error_message() );
			} else {
				WP_CLI::success( "#{$row->id} {$res['status']} (HTTP {$res['code']})" );
			}
		}
	}

	/**
	 * Reject a proposal.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Proposal id(s) or uuid(s).
	 *
	 * [--note=<note>]
	 * : Reviewer note, returned to the integration.
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc.
	 */
	public function reject( $args, $assoc_args ) {
		foreach ( $args as $id ) {
			$row = $this->row( $id );
			$res = WCAG_Decision::reject( (int) $row->id, get_current_user_id(), isset( $assoc_args['note'] ) ? $assoc_args['note'] : 'wp-cli' );
			if ( is_wp_error( $res ) ) {
				WP_CLI::warning( "#{$row->id}: " . $res->get_error_message() );
			} else {
				WP_CLI::success( "#{$row->id} rejected" );
			}
		}
	}

	/**
	 * Put a WooCommerce API key in proposal mode (or take it out).
	 *
	 * ## OPTIONS
	 *
	 * <key_id>
	 * : WooCommerce API key id.
	 *
	 * [--off]
	 * : Remove the key from proposal mode.
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc.
	 */
	public function gate( $args, $assoc_args ) {
		$key_id = (int) $args[0];
		if ( ! WCAG_Store::wc_key( $key_id ) ) {
			WP_CLI::error( "No WooCommerce API key #{$key_id}." );
		}
		$settings = WCAG_Settings::all();
		$keys     = array_map( 'intval', (array) $settings['gated_keys'] );
		if ( isset( $assoc_args['off'] ) ) {
			$keys = array_values( array_diff( $keys, array( $key_id ) ) );
		} elseif ( ! in_array( $key_id, $keys, true ) ) {
			$keys[] = $key_id;
		}
		$settings['gated_keys'] = $keys;
		update_option( WCAG_Settings::OPTION, WCAG_Settings::sanitize( $settings ) );
		WP_CLI::success( isset( $assoc_args['off'] ) ? "Key #{$key_id} writes directly again." : "Key #{$key_id} is in proposal mode." );
	}

	/**
	 * Counts per status.
	 */
	public function stats() {
		foreach ( WCAG_Store::counts() as $status => $n ) {
			WP_CLI::line( sprintf( '%-9s %d', $status, $n ) );
		}
	}

	/**
	 * Resolve id or uuid.
	 *
	 * @param string $id Id.
	 * @return object
	 */
	private function row( $id ) {
		$row = is_numeric( $id ) ? WCAG_Store::get( (int) $id ) : WCAG_Store::get_by_uuid( $id );
		if ( ! $row ) {
			WP_CLI::error( "No proposal {$id}." );
		}
		return $row;
	}
}
