<?php
/**
 * Removes everything this plugin stored.
 *
 * @package AumChat
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aumchat_settings' );
delete_option( 'aumchat_rules' );

/* The cached status check, whatever key it was made for. */
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aumchat\_status\_%' OR option_name LIKE '\_transient\_timeout\_aumchat\_status\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
