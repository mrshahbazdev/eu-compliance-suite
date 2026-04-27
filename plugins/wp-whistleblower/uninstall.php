<?php
/**
 * Uninstall cleanup.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$reports = $wpdb->prefix . 'eurocomply_wb_reports';
$access  = $wpdb->prefix . 'eurocomply_wb_access';
$wpdb->query( "DROP TABLE IF EXISTS {$reports}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$access}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'eurocomply_wb_settings' );
delete_option( 'eurocomply_wb_license' );
delete_option( 'eurocomply_wb_reports_db_version' );
delete_option( 'eurocomply_wb_access_db_version' );

if ( function_exists( 'remove_role' ) && get_role( 'eurocomply_wb_recipient' ) ) {
	remove_role( 'eurocomply_wb_recipient' );
}
