<?php
/**
 * Approve / reject / expire.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applying an approved proposal means replaying the stored request through the REST server,
 * as the key's user, so WooCommerce validates and executes it exactly as it would have.
 */
class WCAG_Decision {

	/**
	 * Register.
	 */
	public static function init() {
		add_action( 'wcag_expire_proposals', array( __CLASS__, 'expire' ) );
		add_action( 'wcag_proposal_created', array( __CLASS__, 'notify_new' ), 10, 1 );
	}

	/**
	 * Approve: replay, record, notify.
	 *
	 * @param int    $id   Row id.
	 * @param int    $by   Reviewer user id.
	 * @param string $note Note.
	 * @return array|WP_Error status, code, body
	 */
	public static function approve( $id, $by, $note = '' ) {
		$row = WCAG_Store::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'wcag_not_found', 'Proposal not found.' );
		}
		if ( 'pending' !== $row->status ) {
			return new WP_Error( 'wcag_not_pending', sprintf( 'Proposal is %s.', $row->status ) );
		}
		if ( $row->expires_at && strtotime( $row->expires_at . ' UTC' ) < time() ) {
			WCAG_Store::update(
				$id,
				array(
					'status'     => 'expired',
					'decided_at' => current_time( 'mysql', true ),
				)
			);
			return new WP_Error( 'wcag_expired', 'Proposal has expired.' );
		}

		$key = WCAG_Store::wc_key( (int) $row->key_id );
		if ( ! $key ) {
			WCAG_Store::update(
				$id,
				array(
					'status'        => 'failed',
					'decided_by'    => (int) $by,
					'decided_at'    => current_time( 'mysql', true ),
					'decision_note' => $note,
					'result_code'   => 0,
					'result_body'   => 'API key was deleted.',
				)
			);
			return new WP_Error( 'wcag_no_key', 'The API key that proposed this change has been deleted.' );
		}
		if ( 'read' === $key->permissions ) {
			return new WP_Error( 'wcag_read_only_key', 'The proposing API key is read-only; approving would grant it write access.' );
		}

		$user = get_user_by( 'id', (int) $row->user_id );
		if ( ! $user ) {
			WCAG_Store::update(
				$id,
				array(
					'status'        => 'failed',
					'decided_by'    => (int) $by,
					'decided_at'    => current_time( 'mysql', true ),
					'decision_note' => $note,
					'result_code'   => 0,
					'result_body'   => 'Key owner no longer exists.',
				)
			);
			return new WP_Error( 'wcag_no_user', 'The user owning the API key no longer exists.' );
		}

		$response = self::replay( $row, $user );
		$code     = $response->get_status();
		$body     = wp_json_encode( $response->get_data() );
		$ok       = $code >= 200 && $code < 300;

		WCAG_Store::update(
			$id,
			array(
				'status'        => $ok ? 'approved' : 'failed',
				'decided_by'    => (int) $by,
				'decided_at'    => current_time( 'mysql', true ),
				'decision_note' => $note,
				'result_code'   => $code,
				'result_body'   => $body,
			)
		);
		$row = WCAG_Store::get( $id );

		/**
		 * A proposal was applied (or failed to apply).
		 *
		 * @param object           $row      Row.
		 * @param WP_REST_Response $response Response from WooCommerce.
		 */
		do_action( 'wcag_proposal_decided', $row, $response );
		self::webhook( $row );

		return array(
			'status' => $row->status,
			'code'   => $code,
			'body'   => $response->get_data(),
		);
	}

	/**
	 * Reject.
	 *
	 * @param int    $id   Row id.
	 * @param int    $by   Reviewer.
	 * @param string $note Note.
	 * @return true|WP_Error
	 */
	public static function reject( $id, $by, $note = '' ) {
		$row = WCAG_Store::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'wcag_not_found', 'Proposal not found.' );
		}
		if ( 'pending' !== $row->status ) {
			return new WP_Error( 'wcag_not_pending', sprintf( 'Proposal is %s.', $row->status ) );
		}
		WCAG_Store::update(
			$id,
			array(
				'status'        => 'rejected',
				'decided_by'    => (int) $by,
				'decided_at'    => current_time( 'mysql', true ),
				'decision_note' => $note,
			)
		);
		$row = WCAG_Store::get( $id );
		do_action( 'wcag_proposal_decided', $row, null );
		self::webhook( $row );
		return true;
	}

	/**
	 * Replay the stored request through WP_REST_Server as the key's user.
	 *
	 * @param object  $row  Row.
	 * @param WP_User $user Key owner.
	 * @return WP_REST_Response
	 */
	public static function replay( $row, $user ) {
		$request = new WP_REST_Request( $row->method, $row->route );
		$query   = json_decode( (string) $row->query_params, true );
		$body    = json_decode( (string) $row->body, true );
		if ( is_array( $query ) && $query ) {
			unset( $query['consumer_key'], $query['consumer_secret'], $query['oauth_consumer_key'], $query['oauth_signature'], $query['oauth_nonce'], $query['oauth_timestamp'], $query['oauth_signature_method'] );
			$request->set_query_params( $query );
		}
		if ( is_array( $body ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		$request->set_header( 'X-WC-Approval', 'apply' );

		$previous            = get_current_user_id();
		WCAG_Gate::$applying = true;
		wp_set_current_user( $user->ID );

		$result = rest_do_request( $request );

		wp_set_current_user( $previous );
		WCAG_Gate::$applying = false;

		return $result;
	}

	/**
	 * Expire due proposals (cron).
	 *
	 * @return int
	 */
	public static function expire() {
		$n    = WCAG_Store::expire_due();
		$days = (int) WCAG_Settings::get( 'retention_days' );
		WCAG_Store::purge( $days );
		return $n;
	}

	/**
	 * Email reviewers when a proposal arrives.
	 *
	 * @param object $row Row.
	 */
	public static function notify_new( $row ) {
		if ( ! WCAG_Settings::get( 'notify_on_new' ) ) {
			return;
		}
		$to = WCAG_Settings::get( 'notify_email' );
		if ( '' === (string) $to ) {
			$to = get_option( 'admin_email' );
		}
		$subject = sprintf( '[%s] Approval needed: %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $row->summary );
		$lines   = array(
			sprintf( 'Key: %s (#%d)', $row->key_description, $row->key_id ),
			sprintf( 'Request: %s %s', $row->method, $row->route ),
			sprintf( 'Risk: %s', $row->risk ),
		);
		if ( $row->reason ) {
			$lines[] = 'Reason given: ' . $row->reason;
		}
		$lines[] = '';
		$lines[] = 'Review: ' . admin_url( 'admin.php?page=wcag&proposal=' . (int) $row->id );
		wp_mail( $to, $subject, implode( "\n", $lines ) );
		self::webhook( $row );
	}

	/**
	 * POST the proposal to the decision webhook (Slack-compatible "text" + full payload).
	 *
	 * @param object $row Row.
	 */
	public static function webhook( $row ) {
		$url = WCAG_Settings::get( 'decision_url' );
		if ( '' === (string) $url ) {
			return;
		}
		$payload = array(
			'event'    => 'pending' === $row->status ? 'proposal.created' : 'proposal.' . $row->status,
			'text'     => sprintf( '[%s] %s: %s (%s %s, risk %s)', get_bloginfo( 'name' ), $row->status, $row->summary, $row->method, $row->route, $row->risk ),
			'proposal' => WCAG_Gate::public_view( $row ),
			'site'     => home_url(),
		);
		/**
		 * Filter the decision webhook payload.
		 *
		 * @param array  $payload Payload.
		 * @param object $row     Row.
		 */
		$payload = apply_filters( 'wcag_webhook_payload', $payload, $row );
		$headers = array( 'Content-Type' => 'application/json' );
		$token   = WCAG_Settings::get( 'decision_token' );
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		wp_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => $headers,
				'body'     => wp_json_encode( $payload ),
			)
		);
	}
}
