<?php
/**
 * Uninstall.
 *
 * @package EuroComply\DORA
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
foreach ( array( 'incidents', 'third_parties', 'tests', 'policies', 'intel' ) as $t ) {
	$table = $wpdb->prefix . 'eurocomply_dora_' . $t;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array(
	'eurocomply_dora_settings',
	'eurocomply_dora_license',
	'eurocomply_dora_incidents_db_version',
	'eurocomply_dora_tpp_db_version',
	'eurocomply_dora_tests_db_version',
	'eurocomply_dora_policies_db_version',
	'eurocomply_dora_intel_db_version',
) as $opt ) {
	delete_option( $opt );
}
