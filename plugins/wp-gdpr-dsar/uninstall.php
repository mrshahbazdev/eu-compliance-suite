<?php
/**
 * Uninstall cleanup.
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'eurocomply_dsar_requests';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'eurocomply_dsar_settings' );
delete_option( 'eurocomply_dsar_license' );
delete_option( 'eurocomply_dsar_db_version' );
