<?php
/**
 * Uninstall cleanup.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$providers = $wpdb->prefix . 'eurocomply_aiact_providers';
$log       = $wpdb->prefix . 'eurocomply_aiact_log';

$wpdb->query( "DROP TABLE IF EXISTS {$providers}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$log}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'eurocomply_ai_act_settings' );
delete_option( 'eurocomply_ai_act_license' );
delete_option( 'eurocomply_aiact_providers_db_version' );
delete_option( 'eurocomply_aiact_log_db_version' );

$meta_keys = array(
	'_eurocomply_aiact_generated',
	'_eurocomply_aiact_provider',
	'_eurocomply_aiact_model',
	'_eurocomply_aiact_purpose',
	'_eurocomply_aiact_human_edited',
	'_eurocomply_aiact_deepfake',
	'_eurocomply_aiact_prompt',
	'_eurocomply_aiact_c2pa_url',
);
foreach ( $meta_keys as $k ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $k ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
}
