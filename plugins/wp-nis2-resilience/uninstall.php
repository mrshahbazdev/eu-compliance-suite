<?php
/**
 * Uninstall cleanup.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$events    = $wpdb->prefix . 'eurocomply_nis2_events';
$incidents = $wpdb->prefix . 'eurocomply_nis2_incidents';

$wpdb->query( "DROP TABLE IF EXISTS {$events}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$incidents}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'eurocomply_nis2_settings' );
delete_option( 'eurocomply_nis2_license' );
delete_option( 'eurocomply_nis2_events_db_version' );
delete_option( 'eurocomply_nis2_incidents_db_version' );
