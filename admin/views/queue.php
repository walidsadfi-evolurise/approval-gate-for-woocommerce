<?php
/**
 * Queue / history view. Expects $evoag_status ('pending' or '').
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

$evoag_table = new EVOAG_List_Table( $evoag_status );
$evoag_table->prepare_items();
?>
<?php if ( 'pending' === $evoag_status ) : ?>
<p class="description">
	<?php esc_html_e( 'Each row is a REST API write that an integration asked for and that has not happened yet. Approving replays the exact request through WooCommerce, as the key\'s user. Rejecting returns your note to the integration.', 'evolurise-approval-gate-for-woocommerce' ); ?>
</p>
<?php endif; ?>
<form method="get" style="margin-bottom:8px">
	<input type="hidden" name="page" value="evoag" />
	<input type="hidden" name="tab" value="<?php echo esc_attr( 'pending' === $evoag_status ? 'queue' : 'history' ); ?>" />
	<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended ?>
	<select name="risk">
		<option value=""><?php esc_html_e( 'Any risk', 'evolurise-approval-gate-for-woocommerce' ); ?></option>
		<?php foreach ( array( 'high', 'medium', 'low' ) as $evoag_r ) : ?>
			<option value="<?php echo esc_attr( $evoag_r ); ?>" <?php selected( isset( $_GET['risk'] ) ? sanitize_key( wp_unslash( $_GET['risk'] ) ) : '', $evoag_r ); ?>><?php echo esc_html( $evoag_r ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="key_id">
		<option value="0"><?php esc_html_e( 'Any key', 'evolurise-approval-gate-for-woocommerce' ); ?></option>
		<?php foreach ( EVOAG_Store::wc_keys() as $evoag_k ) : ?>
			<option value="<?php echo (int) $evoag_k->key_id; ?>" <?php selected( isset( $_GET['key_id'] ) ? (int) $_GET['key_id'] : 0, (int) $evoag_k->key_id ); ?>><?php echo esc_html( $evoag_k->description ); ?> #<?php echo (int) $evoag_k->key_id; ?></option>
		<?php endforeach; ?>
	</select>
	<?php $evoag_table->search_box( __( 'Search', 'evolurise-approval-gate-for-woocommerce' ), 'evoag' ); ?>
	<?php // phpcs:enable ?>
	<button class="button"><?php esc_html_e( 'Filter', 'evolurise-approval-gate-for-woocommerce' ); ?></button>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="evoag-queue-form">
	<input type="hidden" name="action" value="evoag_action" />
	<input type="hidden" name="do" value="" id="evoag-do" />
	<input type="hidden" name="row_id" value="" id="evoag-row-id" />
	<?php wp_nonce_field( 'evoag_action' ); ?>
	<?php if ( 'pending' === $evoag_status ) : ?>
		<p><label for="evoag-note"><?php esc_html_e( 'Note for the integration (optional, applies to the decision you make below):', 'evolurise-approval-gate-for-woocommerce' ); ?></label><br>
		<input type="text" id="evoag-note" name="note" placeholder="<?php esc_attr_e( 'e.g. prices approved for the spring sale', 'evolurise-approval-gate-for-woocommerce' ); ?>" /></p>
	<?php endif; ?>
	<?php $evoag_table->views(); ?>
	<?php $evoag_table->display(); ?>
</form>
