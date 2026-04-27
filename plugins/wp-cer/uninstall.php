<?php
/**
 * Uninstall.
 *
 * @package EuroComply\CER
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
foreach ( array( 'services', 'assets', 'risk', 'measures', 'incidents' ) as $t ) {
	$table = $wpdb->prefix . 'eurocomply_cer_' . $t;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array(
	'eurocomply_cer_settings',
	'eurocomply_cer_license',
	'eurocomply_cer_services_db_version',
	'eurocomply_cer_assets_db_version',
	'eurocomply_cer_risk_db_version',
	'eurocomply_cer_measures_db_version',
	'eurocomply_cer_incidents_db_version',
) as $opt ) {
	delete_option( $opt );
}
