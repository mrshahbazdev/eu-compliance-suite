<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'materiality', 'datapoints', 'assurance', 'reports' ) as $name ) {
	$table = $wpdb->prefix . 'eurocomply_csrd_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ( array(
	'eurocomply_csrd_settings',
	'eurocomply_csrd_license',
	'eurocomply_csrd_materiality_db_version',
	'eurocomply_csrd_datapoints_db_version',
	'eurocomply_csrd_assurance_db_version',
	'eurocomply_csrd_reports_db_version',
) as $opt ) {
	delete_option( $opt );
}
