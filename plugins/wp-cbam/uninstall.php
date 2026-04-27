<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'imports', 'reports', 'verifiers' ) as $name ) {
	$table = $wpdb->prefix . 'eurocomply_cbam_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ( array(
	'eurocomply_cbam_settings',
	'eurocomply_cbam_license',
	'eurocomply_cbam_imports_db_version',
	'eurocomply_cbam_reports_db_version',
	'eurocomply_cbam_verifiers_db_version',
) as $opt ) {
	delete_option( $opt );
}

foreach ( array(
	'_eurocomply_cbam_cn8',
	'_eurocomply_cbam_origin_country',
	'_eurocomply_cbam_direct_tco2e',
	'_eurocomply_cbam_indirect_tco2e',
	'_eurocomply_cbam_production_route',
	'_eurocomply_cbam_verified',
	'_eurocomply_cbam_supplier',
) as $meta_key ) {
	delete_metadata( 'post', 0, $meta_key, '', true );
}
