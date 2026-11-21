<?php
/**
 * Single proposal. Expects $row (object|null).
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

if ( ! $row ) {
	echo '<p>' . esc_html__( 'Proposal not found.', 'evolurise-approval-gate-for-woocommerce' ) . '</p>';
	return;
}
$evoag_body    = json_decode( (string) $row->body, true );
$evoag_query   = json_decode( (string) $row->query_params, true );
$evoag_result  = json_decode( (string) $row->result_body, true );
$evoag_user    = $row->user_id ? get_user_by( 'id', $row->user_id ) : null;
$evoag_decider = $row->decided_by ? get_user_by( 'id', $row->decided_by ) : null;
$evoag_pretty  = function ( $v ) {
	return esc_html( wp_json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
};
?>
<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=evoag' ) ); ?>">&larr; <?php esc_html_e( 'Back to the queue', 'evolurise-approval-gate-for-woocommerce' ); ?></a></p>
<h2 style="margin-bottom:4px"><?php echo esc_html( $row->summary ); ?> <?php echo EVOAG_Admin::risk_badge( $row->risk ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo EVOAG_Admin::status_badge( $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
<table class="widefat striped" style="max-width:900px;margin-top:12px">
	<tbody>
		<tr><th style="width:160px"><?php esc_html_e( 'Request', 'evolurise-approval-gate-for-woocommerce' ); ?></th><td><code><?php echo esc_html( $row->method ); ?></code> <code><?php echo esc_html( $row->route ); ?></code></td></tr>
		<tr><th><?php esc_html_e( 'From key', 'evolurise-approval-gate-for-woocommerce' ); ?></th><td><?php echo esc_html( $row->key_description ); ?> #<?php echo (int) $row->key_id; ?>
		<?php
		if ( $evoag_user ) :
			?>
			— <?php esc_html_e( 'runs as', 'evolurise-approval-gate-for-woocommerce' ); ?> <?php echo esc_html( $evoag_user->display_name ); ?><?php endif; ?></td></tr>
		<?php if ( $row->reason ) : ?>
		<tr><th><?php esc_html_e( 'Reason given', 'evolurise-approval-gate-for-woocommerce' ); ?></th><td><?php echo esc_html( $row->reason ); ?></td></tr>
		<?php endif; ?>
		<tr><th><?php esc_html_e( 'Proposed', 'evolurise-approval-gate-for-woocommerce' ); ?></th><td><?php echo esc_html( get_date_from_gmt( $row->created_at, 'Y-m-d H:i:s' ) ); ?>
		<?php
		if ( $row->expires_at && 'pending' === $row->status ) :
			?>
			— <?php esc_html_e( 'expires', 'evolurise-approval-gate-for-woocommerce' ); ?> <?php echo esc_html( get_date_from_gmt( $row->expires_at, 'Y-m-d H:i' ) ); ?><?php endif; ?></td></tr>
		<?php if ( $row->idempotency_key ) : ?>
		<tr><th><?php esc_html_e( 'Idempotency key', 'evolurise-approval-gate-for-woocommerce' ); ?></th><td><code><?php echo esc_html( $row->idempotency_key ); ?></code></td></tr>
		<?php endif; ?>
		<tr><th><?php esc_html_e( 'Proposal id', 'evolurise-approval-gate-for-woocommerce' ); ?></th><td><code><?php echo esc_html( $row->uuid ); ?></code></td></tr>
		<?php if ( $row->decided_at ) : ?>
		<tr><th><?php esc_html_e( 'Decided', 'evolurise-approval-gate-for-woocommerce' ); ?></th><td><?php echo esc_html( get_date_from_gmt( $row->decided_at, 'Y-m-d H:i:s' ) ); ?>
			<?php
			if ( $evoag_decider ) :
				?>
				<?php esc_html_e( 'by', 'evolurise-approval-gate-for-woocommerce' ); ?> <?php echo esc_html( $evoag_decider->display_name ); ?><?php endif; ?>
			<?php
			if ( $row->decision_note ) :
				?>
			— <?php echo esc_html( $row->decision_note ); ?><?php endif; ?></td></tr>
		<?php endif; ?>
	</tbody>
</table>

<h3><?php esc_html_e( 'Payload', 'evolurise-approval-gate-for-woocommerce' ); ?></h3>
<pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:900px;overflow:auto;max-height:420px"><?php echo is_array( $evoag_body ) && $evoag_body ? $evoag_pretty( $evoag_body ) : esc_html__( '(empty body)', 'evolurise-approval-gate-for-woocommerce' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></pre>
<?php if ( is_array( $evoag_query ) && $evoag_query ) : ?>
<h3><?php esc_html_e( 'Query parameters', 'evolurise-approval-gate-for-woocommerce' ); ?></h3>
<pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:900px;overflow:auto"><?php echo $evoag_pretty( $evoag_query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></pre>
<?php endif; ?>

<?php if ( null !== $row->result_code ) : ?>
<h3><?php esc_html_e( 'WooCommerce response', 'evolurise-approval-gate-for-woocommerce' ); ?> <code>HTTP <?php echo (int) $row->result_code; ?></code></h3>
<pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:900px;overflow:auto;max-height:420px"><?php echo null !== $evoag_result ? $evoag_pretty( $evoag_result ) : esc_html( (string) $row->result_body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></pre>
<?php endif; ?>

<?php if ( 'pending' === $row->status ) : ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:900px;margin-top:16px;padding:12px;background:#fff;border:1px solid #c3c4c7">
	<input type="hidden" name="action" value="evoag_action" />
	<input type="hidden" name="row_id" value="<?php echo (int) $row->id; ?>" />
	<?php wp_nonce_field( 'evoag_action' ); ?>
	<p><label for="evoag-note"><strong><?php esc_html_e( 'Note for the integration (optional)', 'evolurise-approval-gate-for-woocommerce' ); ?></strong></label><br>
	<input type="text" id="evoag-note" name="note" style="width:100%" /></p>
	<p>
		<button class="button button-primary evoag-confirm-approve" name="do" value="approve"><?php esc_html_e( 'Approve & apply', 'evolurise-approval-gate-for-woocommerce' ); ?></button>
		<button class="button" name="do" value="reject"><?php esc_html_e( 'Reject', 'evolurise-approval-gate-for-woocommerce' ); ?></button>
	</p>
</form>
<?php endif; ?>
