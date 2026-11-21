/**
 * Evolurise Approval Gate for WooCommerce: review queue and proposal screens.
 *
 * Strings come from wp_localize_script() as window.evoagAdmin.
 */
( function () {
	'use strict';

	var i18n = window.evoagAdmin || {};

	function disableBulkSelects( form, disabled ) {
		form.querySelectorAll( 'select[name="action"],select[name="action2"]' ).forEach( function ( s ) {
			s.disabled = disabled;
		} );
	}

	// Review queue: bulk actions and the per-row "Approve & apply" / "Reject" links.
	var form = document.getElementById( 'evoag-queue-form' );
	if ( form ) {
		var doField = document.getElementById( 'evoag-do' );
		var rowField = document.getElementById( 'evoag-row-id' );

		form.addEventListener( 'submit', function ( event ) {
			var top = form.querySelector( 'select[name="action"]' );
			var bottom = form.querySelector( 'select[name="action2"]' );
			var chosen = top && top.value !== '-1' ? top.value : ( bottom && bottom.value !== '-1' ? bottom.value : '' );
			if ( chosen && ! doField.value ) {
				doField.value = chosen;
			}
			disableBulkSelects( form, true );
			if ( doField.value.indexOf( 'approve' ) !== -1 && ! window.confirm( i18n.confirmMany ) ) {
				disableBulkSelects( form, false );
				doField.value = '';
				event.preventDefault();
			}
		} );

		form.querySelectorAll( '.evoag-quick' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var action = link.getAttribute( 'data-do' );
				if ( action === 'approve' && ! window.confirm( i18n.confirmOne ) ) {
					return;
				}
				doField.value = action;
				rowField.value = link.getAttribute( 'data-id' );
				disableBulkSelects( form, true );
				form.submit();
			} );
		} );
	}

	// Single proposal: confirm before applying.
	document.querySelectorAll( '.evoag-confirm-approve' ).forEach( function ( button ) {
		button.addEventListener( 'click', function ( event ) {
			if ( ! window.confirm( i18n.confirmOne ) ) {
				event.preventDefault();
			}
		} );
	} );
}() );
