<?php
/**
 * Settings view.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

$wcag_s = WCAG_Settings::all();
$wcag_o = WCAG_Settings::OPTION;
?>
<form method="post" action="options.php" style="max-width:900px">
	<?php settings_fields( 'wcag_settings_group' ); ?>
	<?php foreach ( (array) $wcag_s['gated_keys'] as $wcag_k ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $wcag_o ); ?>[gated_keys][]" value="<?php echo (int) $wcag_k; ?>" />
	<?php endforeach; ?>

	<h2><?php esc_html_e( 'What needs approval', 'approval-gate-for-woocommerce' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Gated resources', 'approval-gate-for-woocommerce' ); ?></th>
			<td>
				<?php foreach ( WCAG_Summary::resources() as $wcag_slug => $wcag_label ) : ?>
					<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $wcag_o ); ?>[resources][]" value="<?php echo esc_attr( $wcag_slug ); ?>" <?php checked( in_array( $wcag_slug, (array) $wcag_s['resources'], true ) ); ?> /> <?php echo esc_html( $wcag_label ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Writes (POST, PUT, PATCH, DELETE) from a gated key on these resources become proposals. Writes on anything unticked go straight through.', 'approval-gate-for-woocommerce' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Per-request opt-in', 'approval-gate-for-woocommerce' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $wcag_o ); ?>[header_opt_in]" value="1" <?php checked( $wcag_s['header_opt_in'] ); ?> /> <?php esc_html_e( 'Any key may send the header X-WC-Approval: propose to have one request reviewed', 'approval-gate-for-woocommerce' ); ?></label>
			<p class="description"><?php esc_html_e( 'Useful for agents that are allowed to act directly on small changes but want a human on the risky ones.', 'approval-gate-for-woocommerce' ); ?></p></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Proposals expire after', 'approval-gate-for-woocommerce' ); ?></th>
			<td><input type="number" min="1" max="90" name="<?php echo esc_attr( $wcag_o ); ?>[expire_days]" value="<?php echo (int) $wcag_s['expire_days']; ?>" class="small-text" /> <?php esc_html_e( 'days', 'approval-gate-for-woocommerce' ); ?>
			<p class="description"><?php esc_html_e( 'An unreviewed proposal is marked expired and cannot be applied afterwards; the integration sees status "expired".', 'approval-gate-for-woocommerce' ); ?></p></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Notifications', 'approval-gate-for-woocommerce' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Email reviewers', 'approval-gate-for-woocommerce' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $wcag_o ); ?>[notify_on_new]" value="1" <?php checked( $wcag_s['notify_on_new'] ); ?> /> <?php esc_html_e( 'Send an email for every new proposal', 'approval-gate-for-woocommerce' ); ?></label><br>
			<input type="text" class="regular-text" name="<?php echo esc_attr( $wcag_o ); ?>[notify_email]" value="<?php echo esc_attr( $wcag_s['notify_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
			<p class="description"><?php esc_html_e( 'Comma-separated. Empty = the site admin email.', 'approval-gate-for-woocommerce' ); ?></p></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Webhook URL', 'approval-gate-for-woocommerce' ); ?></th>
			<td><input type="url" class="regular-text" name="<?php echo esc_attr( $wcag_o ); ?>[decision_url]" value="<?php echo esc_attr( $wcag_s['decision_url'] ); ?>" placeholder="https://hooks.slack.com/services/…" />
			<p class="description"><?php esc_html_e( 'Receives a JSON POST on proposal.created, proposal.approved, proposal.rejected and proposal.failed (Slack incoming webhooks work as-is).', 'approval-gate-for-woocommerce' ); ?></p></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Webhook bearer token', 'approval-gate-for-woocommerce' ); ?></th>
			<td><input type="text" class="regular-text" name="<?php echo esc_attr( $wcag_o ); ?>[decision_token]" value="<?php echo esc_attr( $wcag_s['decision_token'] ); ?>" autocomplete="off" /></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Housekeeping', 'approval-gate-for-woocommerce' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Keep decided proposals for', 'approval-gate-for-woocommerce' ); ?></th>
			<td><input type="number" min="7" max="730" name="<?php echo esc_attr( $wcag_o ); ?>[retention_days]" value="<?php echo (int) $wcag_s['retention_days']; ?>" class="small-text" /> <?php esc_html_e( 'days', 'approval-gate-for-woocommerce' ); ?></td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="wcag_action" />
	<input type="hidden" name="do" value="expire_now" />
	<?php wp_nonce_field( 'wcag_action' ); ?>
	<button class="button"><?php esc_html_e( 'Run expiry & purge now', 'approval-gate-for-woocommerce' ); ?></button>
</form>
