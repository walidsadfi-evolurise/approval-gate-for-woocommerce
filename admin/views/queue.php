<?php
/**
 * Queue / history view. Expects $wcag_status ('pending' or '').
 *
 * @package ApprovalGate
 */

defined( 'ABSPATH' ) || exit;

$wcag_table = new WCAG_List_Table( $wcag_status );
$wcag_table->prepare_items();
?>
<style>
	.wcag-wrap .column-risk, .wcag-wrap .column-status { width: 70px; }
	.wcag-wrap .column-created_at, .wcag-wrap .column-expires_at { width: 110px; }
	.wcag-wrap .column-key { width: 160px; }
	.wcag-wrap .column-request code { font-size: 11px; }
	.wcag-wrap #wcag-note { width: 100%; max-width: 480px; }
</style>
<?php if ( 'pending' === $wcag_status ) : ?>
<p class="description">
	<?php esc_html_e( 'Each row is a REST API write that an integration asked for and that has not happened yet. Approving replays the exact request through WooCommerce, as the key\'s user. Rejecting returns your note to the integration.', 'approval-gate-for-woocommerce' ); ?>
</p>
<?php endif; ?>
<form method="get" style="margin-bottom:8px">
	<input type="hidden" name="page" value="wcag" />
	<input type="hidden" name="tab" value="<?php echo esc_attr( 'pending' === $wcag_status ? 'queue' : 'history' ); ?>" />
	<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended ?>
	<select name="risk">
		<option value=""><?php esc_html_e( 'Any risk', 'approval-gate-for-woocommerce' ); ?></option>
		<?php foreach ( array( 'high', 'medium', 'low' ) as $wcag_r ) : ?>
			<option value="<?php echo esc_attr( $wcag_r ); ?>" <?php selected( isset( $_GET['risk'] ) ? sanitize_key( wp_unslash( $_GET['risk'] ) ) : '', $wcag_r ); ?>><?php echo esc_html( $wcag_r ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="key_id">
		<option value="0"><?php esc_html_e( 'Any key', 'approval-gate-for-woocommerce' ); ?></option>
		<?php foreach ( WCAG_Store::wc_keys() as $wcag_k ) : ?>
			<option value="<?php echo (int) $wcag_k->key_id; ?>" <?php selected( isset( $_GET['key_id'] ) ? (int) $_GET['key_id'] : 0, (int) $wcag_k->key_id ); ?>><?php echo esc_html( $wcag_k->description ); ?> #<?php echo (int) $wcag_k->key_id; ?></option>
		<?php endforeach; ?>
	</select>
	<?php $wcag_table->search_box( __( 'Search', 'approval-gate-for-woocommerce' ), 'wcag' ); ?>
	<?php // phpcs:enable ?>
	<button class="button"><?php esc_html_e( 'Filter', 'approval-gate-for-woocommerce' ); ?></button>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wcag-queue-form">
	<input type="hidden" name="action" value="wcag_action" />
	<input type="hidden" name="do" value="" id="wcag-do" />
	<input type="hidden" name="row_id" value="" id="wcag-row-id" />
	<?php wp_nonce_field( 'wcag_action' ); ?>
	<?php if ( 'pending' === $wcag_status ) : ?>
		<p><label for="wcag-note"><?php esc_html_e( 'Note for the integration (optional, applies to the decision you make below):', 'approval-gate-for-woocommerce' ); ?></label><br>
		<input type="text" id="wcag-note" name="note" placeholder="<?php esc_attr_e( 'e.g. prices approved for the spring sale', 'approval-gate-for-woocommerce' ); ?>" /></p>
	<?php endif; ?>
	<?php $wcag_table->views(); ?>
	<?php $wcag_table->display(); ?>
</form>
<script>
	( function () {
		var form = document.getElementById( 'wcag-queue-form' );
		if ( ! form ) { return; }
		form.addEventListener( 'submit', function ( event ) {
			var sel = form.querySelector( 'select[name="action"]' );
			var sel2 = form.querySelector( 'select[name="action2"]' );
			var v = sel && sel.value !== '-1' ? sel.value : ( sel2 && sel2.value !== '-1' ? sel2.value : '' );
			if ( v && ! document.getElementById( 'wcag-do' ).value ) {
				document.getElementById( 'wcag-do' ).value = v;
			}
			var doVal = document.getElementById( 'wcag-do' ).value;
			form.querySelectorAll( 'select[name="action"],select[name="action2"]' ).forEach( function ( s ) { s.disabled = true; } );
			if ( doVal.indexOf( 'approve' ) !== -1 && ! window.confirm( '<?php echo esc_js( __( 'Apply the selected change(s) to the store now?', 'approval-gate-for-woocommerce' ) ); ?>' ) ) {
				form.querySelectorAll( 'select[name="action"],select[name="action2"]' ).forEach( function ( s ) { s.disabled = false; } );
				document.getElementById( 'wcag-do' ).value = '';
				event.preventDefault();
				return false;
			}
		} );
		document.querySelectorAll( '.wcag-quick' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				document.getElementById( 'wcag-do' ).value = a.getAttribute( 'data-do' );
				document.getElementById( 'wcag-row-id' ).value = a.getAttribute( 'data-id' );
				if ( a.getAttribute( 'data-do' ) === 'approve' && ! window.confirm( '<?php echo esc_js( __( 'Apply this change to the store now?', 'approval-gate-for-woocommerce' ) ); ?>' ) ) {
					document.getElementById( 'wcag-do' ).value = '';
					return;
				}
				form.querySelectorAll( 'select[name="action"],select[name="action2"]' ).forEach( function ( s ) { s.disabled = true; } );
				form.submit();
			} );
		} );
	} )();
</script>
