<?php
/**
 * Remove everything the plugin stored.
 *
 * @package ApprovalGate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$evoag_table = $wpdb->prefix . 'evoag_proposals';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $evoag_table ) ); // phpcs:ignore WordPress.DB

delete_option( 'evoag_settings' );
delete_option( 'evoag_db_version' );

wp_clear_scheduled_hook( 'evoag_expire_proposals' );
