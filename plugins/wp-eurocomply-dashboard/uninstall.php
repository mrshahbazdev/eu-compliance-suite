<?php
/**
 * Uninstall cleanup.
 *
 * @package EuroComply\Dashboard
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$snapshots = $wpdb->prefix . 'eurocomply_dashboard_snapshots';
$wpdb->query( "DROP TABLE IF EXISTS {$snapshots}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'eurocomply_dashboard_settings' );
delete_option( 'eurocomply_dashboard_license' );
delete_option( 'eurocomply_dashboard_snapshots_db_version' );

$ts = wp_next_scheduled( 'eurocomply_dashboard_daily_snapshot' );
if ( $ts ) {
	wp_unschedule_event( $ts, 'eurocomply_dashboard_daily_snapshot' );
}
