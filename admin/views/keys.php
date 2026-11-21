<?php
/**
 * Keys view.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

$evoag_settings = EVOAG_Settings::all();
$evoag_gated    = array_map( 'intval', (array) $evoag_settings['gated_keys'] );
$evoag_keys     = EVOAG_Store::wc_keys();
?>
<p class="description" style="max-width:800px">
	<?php esc_html_e( 'A key in proposal mode can still read everything its WooCommerce permission allows. Its writes on the gated resources (see Settings) are stored and answered with HTTP 202 instead of being applied. Keys not ticked behave exactly as before.', 'evolurise-approval-gate-for-woocommerce' ); ?>
	<?php if ( $evoag_settings['header_opt_in'] ) : ?>
		<?php esc_html_e( 'Any key may also opt in per request with the header X-WC-Approval: propose.', 'evolurise-approval-gate-for-woocommerce' ); ?>
	<?php endif; ?>
</p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="evoag_action" />
	<input type="hidden" name="do" value="save_keys" />
	<?php wp_nonce_field( 'evoag_action' ); ?>
	<table class="widefat striped" style="max-width:900px">
		<thead><tr>
			<th style="width:120px"><?php esc_html_e( 'Proposal mode', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Key', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'User', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'WooCommerce permission', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Proposals', 'evolurise-approval-gate-for-woocommerce' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( ! $evoag_keys ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No WooCommerce API keys yet. Create one under WooCommerce → Settings → Advanced → REST API.', 'evolurise-approval-gate-for-woocommerce' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $evoag_keys as $evoag_k ) : ?>
			<?php
			$evoag_u = get_user_by( 'id', $evoag_k->user_id );
			$evoag_n = EVOAG_Store::query(
				array(
					'key_id'   => (int) $evoag_k->key_id,
					'per_page' => 1,
				)
			)['total'];
			?>
			<tr>
				<td><label><input type="checkbox" name="gated_keys[]" value="<?php echo (int) $evoag_k->key_id; ?>" <?php checked( in_array( (int) $evoag_k->key_id, $evoag_gated, true ) ); ?> <?php disabled( 'read' === $evoag_k->permissions ); ?> /> <?php echo in_array( (int) $evoag_k->key_id, $evoag_gated, true ) ? esc_html__( 'on', 'evolurise-approval-gate-for-woocommerce' ) : ''; ?></label></td>
				<td><strong><?php echo esc_html( $evoag_k->description ); ?></strong> <span style="color:#888">#<?php echo (int) $evoag_k->key_id; ?> · <?php echo esc_html( $evoag_k->truncated_key ); ?></span></td>
				<td><?php echo $evoag_u ? esc_html( $evoag_u->display_name ) : '—'; ?></td>
				<td><?php echo esc_html( $evoag_k->permissions ); ?>
				<?php
				if ( 'read' === $evoag_k->permissions ) :
					?>
					<span style="color:#888">(<?php esc_html_e( 'cannot write anyway', 'evolurise-approval-gate-for-woocommerce' ); ?>)</span><?php endif; ?></td>
				<td>
				<?php
				if ( $evoag_n ) :
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=evoag&tab=history&key_id=' . (int) $evoag_k->key_id ) ); ?>"><?php echo (int) $evoag_n; ?></a>
					<?php
else :
	?>
					0<?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p><button class="button button-primary"><?php esc_html_e( 'Save', 'evolurise-approval-gate-for-woocommerce' ); ?></button></p>
</form>
