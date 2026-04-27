<?php
/**
 * Uninstall.
 *
 * @package EuroComply\ToySafety
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
foreach ( array( 'toys', 'substances', 'assessments', 'incidents', 'operators' ) as $t ) {
	$table = $wpdb->prefix . 'eurocomply_toy_' . $t;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array(
	'eurocomply_toy_settings',
	'eurocomply_toy_license',
	'eurocomply_toy_toys_db_version',
	'eurocomply_toy_substances_db_version',
	'eurocomply_toy_assessments_db_version',
	'eurocomply_toy_incidents_db_version',
	'eurocomply_toy_operators_db_version',
) as $opt ) {
	delete_option( $opt );
}
