<?php
/**
 * Uninstall handler for EuroComply Age Verification.
 *
 * Drops options, license, the verification log table, and product-meta keys.
 * Site operators should export the log to CSV before uninstalling if they
 * need to retain evidence for regulator audits.
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'eurocomply_av_settings' );
delete_option( 'eurocomply_av_license' );
delete_option( 'eurocomply_av_db_version' );

$table = $wpdb->prefix . 'eurocomply_av_verifications';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_eurocomply_av_min_age' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
