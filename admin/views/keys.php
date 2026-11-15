<?php
/**
 * Keys view.
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

$wcag_settings = WCAG_Settings::all();
$wcag_gated    = array_map( 'intval', (array) $wcag_settings['gated_keys'] );
$wcag_keys     = WCAG_Store::wc_keys();
?>
<p class="description" style="max-width:800px">
	<?php esc_html_e( 'A key in proposal mode can still read everything its WooCommerce permission allows. Its writes on the gated resources (see Settings) are stored and answered with HTTP 202 instead of being applied. Keys not ticked behave exactly as before.', 'approval-gate-for-woocommerce' ); ?>
	<?php if ( $wcag_settings['header_opt_in'] ) : ?>
		<?php esc_html_e( 'Any key may also opt in per request with the header X-WC-Approval: propose.', 'approval-gate-for-woocommerce' ); ?>
	<?php endif; ?>
</p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="wcag_action" />
	<input type="hidden" name="do" value="save_keys" />
	<?php wp_nonce_field( 'wcag_action' ); ?>
	<table class="widefat striped" style="max-width:900px">
		<thead><tr>
			<th style="width:120px"><?php esc_html_e( 'Proposal mode', 'approval-gate-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Key', 'approval-gate-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'User', 'approval-gate-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'WooCommerce permission', 'approval-gate-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Proposals', 'approval-gate-for-woocommerce' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( ! $wcag_keys ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No WooCommerce API keys yet. Create one under WooCommerce → Settings → Advanced → REST API.', 'approval-gate-for-woocommerce' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $wcag_keys as $wcag_k ) : ?>
			<?php
			$wcag_u = get_user_by( 'id', $wcag_k->user_id );
			$wcag_n = WCAG_Store::query(
				array(
					'key_id'   => (int) $wcag_k->key_id,
					'per_page' => 1,
				)
			)['total'];
			?>
			<tr>
				<td><label><input type="checkbox" name="gated_keys[]" value="<?php echo (int) $wcag_k->key_id; ?>" <?php checked( in_array( (int) $wcag_k->key_id, $wcag_gated, true ) ); ?> <?php disabled( 'read' === $wcag_k->permissions ); ?> /> <?php echo in_array( (int) $wcag_k->key_id, $wcag_gated, true ) ? esc_html__( 'on', 'approval-gate-for-woocommerce' ) : ''; ?></label></td>
				<td><strong><?php echo esc_html( $wcag_k->description ); ?></strong> <span style="color:#888">#<?php echo (int) $wcag_k->key_id; ?> · <?php echo esc_html( $wcag_k->truncated_key ); ?></span></td>
				<td><?php echo $wcag_u ? esc_html( $wcag_u->display_name ) : '—'; ?></td>
				<td><?php echo esc_html( $wcag_k->permissions ); ?>
				<?php
				if ( 'read' === $wcag_k->permissions ) :
					?>
					<span style="color:#888">(<?php esc_html_e( 'cannot write anyway', 'approval-gate-for-woocommerce' ); ?>)</span><?php endif; ?></td>
				<td>
				<?php
				if ( $wcag_n ) :
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcag&tab=history&key_id=' . (int) $wcag_k->key_id ) ); ?>"><?php echo (int) $wcag_n; ?></a>
					<?php
else :
	?>
					0<?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p><button class="button button-primary"><?php esc_html_e( 'Save', 'approval-gate-for-woocommerce' ); ?></button></p>
</form>
