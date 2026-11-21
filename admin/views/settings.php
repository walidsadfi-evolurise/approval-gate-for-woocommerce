<?php
/**
 * Settings view.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

$evoag_s = EVOAG_Settings::all();
$evoag_o = EVOAG_Settings::OPTION;
?>
<form method="post" action="options.php" style="max-width:900px">
	<?php settings_fields( 'evoag_settings_group' ); ?>
	<?php foreach ( (array) $evoag_s['gated_keys'] as $evoag_k ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $evoag_o ); ?>[gated_keys][]" value="<?php echo (int) $evoag_k; ?>" />
	<?php endforeach; ?>

	<h2><?php esc_html_e( 'What needs approval', 'evolurise-approval-gate-for-woocommerce' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Gated resources', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<td>
				<?php foreach ( EVOAG_Summary::resources() as $evoag_slug => $evoag_label ) : ?>
					<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $evoag_o ); ?>[resources][]" value="<?php echo esc_attr( $evoag_slug ); ?>" <?php checked( in_array( $evoag_slug, (array) $evoag_s['resources'], true ) ); ?> /> <?php echo esc_html( $evoag_label ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Writes (POST, PUT, PATCH, DELETE) from a gated key on these resources become proposals. Writes on anything unticked go straight through.', 'evolurise-approval-gate-for-woocommerce' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Per-request opt-in', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $evoag_o ); ?>[header_opt_in]" value="1" <?php checked( $evoag_s['header_opt_in'] ); ?> /> <?php esc_html_e( 'Any key may send the header X-WC-Approval: propose to have one request reviewed', 'evolurise-approval-gate-for-woocommerce' ); ?></label>
			<p class="description"><?php esc_html_e( 'Useful for agents that are allowed to act directly on small changes but want a human on the risky ones.', 'evolurise-approval-gate-for-woocommerce' ); ?></p></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Proposals expire after', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<td><input type="number" min="1" max="90" name="<?php echo esc_attr( $evoag_o ); ?>[expire_days]" value="<?php echo (int) $evoag_s['expire_days']; ?>" class="small-text" /> <?php esc_html_e( 'days', 'evolurise-approval-gate-for-woocommerce' ); ?>
			<p class="description"><?php esc_html_e( 'An unreviewed proposal is marked expired and cannot be applied afterwards; the integration sees status "expired".', 'evolurise-approval-gate-for-woocommerce' ); ?></p></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Notifications', 'evolurise-approval-gate-for-woocommerce' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Email reviewers', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $evoag_o ); ?>[notify_on_new]" value="1" <?php checked( $evoag_s['notify_on_new'] ); ?> /> <?php esc_html_e( 'Send an email for every new proposal', 'evolurise-approval-gate-for-woocommerce' ); ?></label><br>
			<input type="text" class="regular-text" name="<?php echo esc_attr( $evoag_o ); ?>[notify_email]" value="<?php echo esc_attr( $evoag_s['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
			<p class="description"><?php esc_html_e( 'Comma-separated. Empty = the site admin email.', 'evolurise-approval-gate-for-woocommerce' ); ?></p></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Webhook URL', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<td><input type="url" class="regular-text" name="<?php echo esc_attr( $evoag_o ); ?>[decision_url]" value="<?php echo esc_attr( $evoag_s['decision_url'] ); ?>" placeholder="https://hooks.slack.com/services/…" />
			<p class="description"><?php esc_html_e( 'Receives a JSON POST on proposal.created, proposal.approved, proposal.rejected and proposal.failed (Slack incoming webhooks work as-is).', 'evolurise-approval-gate-for-woocommerce' ); ?></p></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Webhook bearer token', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<td><input type="text" class="regular-text" name="<?php echo esc_attr( $evoag_o ); ?>[decision_token]" value="<?php echo esc_attr( $evoag_s['decision_token'] ); ?>" autocomplete="off" /></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Housekeeping', 'evolurise-approval-gate-for-woocommerce' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Keep decided proposals for', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<td><input type="number" min="7" max="730" name="<?php echo esc_attr( $evoag_o ); ?>[retention_days]" value="<?php echo (int) $evoag_s['retention_days']; ?>" class="small-text" /> <?php esc_html_e( 'days', 'evolurise-approval-gate-for-woocommerce' ); ?></td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="evoag_action" />
	<input type="hidden" name="do" value="expire_now" />
	<?php wp_nonce_field( 'evoag_action' ); ?>
	<button class="button"><?php esc_html_e( 'Run expiry & purge now', 'evolurise-approval-gate-for-woocommerce' ); ?></button>
</form>
