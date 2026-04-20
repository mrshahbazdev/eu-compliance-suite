<?php
/**
 * Uninstall cleanup for EuroComply Cookie Consent.
 *
 * @package EuroComply\CookieConsent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'eurocomply_cc_settings' );
delete_option( 'eurocomply_cc_license' );

$table = $wpdb->prefix . 'eurocomply_cc_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
