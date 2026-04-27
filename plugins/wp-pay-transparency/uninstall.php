<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'categories', 'employees', 'requests', 'reports' ) as $name ) {
	$table = $wpdb->prefix . 'eurocomply_pt_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ( array(
	'eurocomply_pay_transparency_settings',
	'eurocomply_pay_transparency_license',
	'eurocomply_pt_categories_db_version',
	'eurocomply_pt_employees_db_version',
	'eurocomply_pt_requests_db_version',
	'eurocomply_pt_reports_db_version',
) as $opt ) {
	delete_option( $opt );
}

// Drop per-post pay-range meta.
foreach ( array(
	'_eurocomply_pt_pay_min',
	'_eurocomply_pt_pay_max',
	'_eurocomply_pt_pay_currency',
	'_eurocomply_pt_pay_period',
	'_eurocomply_pt_category',
) as $meta_key ) {
	delete_metadata( 'post', 0, $meta_key, '', true );
}
