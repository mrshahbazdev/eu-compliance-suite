<?php
/**
 * Uninstall cleanup.
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$suppliers = $wpdb->prefix . 'eurocomply_r2r_suppliers';
$repairers = $wpdb->prefix . 'eurocomply_r2r_repairers';

$wpdb->query( "DROP TABLE IF EXISTS {$suppliers}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$repairers}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'eurocomply_r2r_settings' );
delete_option( 'eurocomply_r2r_license' );
delete_option( 'eurocomply_r2r_suppliers_db_version' );
delete_option( 'eurocomply_r2r_repairers_db_version' );

// Remove product meta.
$meta_keys = array(
	'_eurocomply_r2r_category',
	'_eurocomply_r2r_energy_class',
	'_eurocomply_r2r_energy_kwh',
	'_eurocomply_r2r_repair_index',
	'_eurocomply_r2r_disassembly_score',
	'_eurocomply_r2r_spare_parts_years',
	'_eurocomply_r2r_spare_parts_url',
	'_eurocomply_r2r_repair_manual_url',
	'_eurocomply_r2r_eprel_id',
	'_eurocomply_r2r_warranty_years',
);
foreach ( $meta_keys as $k ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $k ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
}
