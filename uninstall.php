<?php
/**
 * Remove everything the plugin stored.
 *
 * @package ApprovalGate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wcag_table = $wpdb->prefix . 'wcag_proposals';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wcag_table ) ); // phpcs:ignore WordPress.DB

delete_option( 'wcag_settings' );
delete_option( 'wcag_db_version' );

wp_clear_scheduled_hook( 'wcag_expire_proposals' );
