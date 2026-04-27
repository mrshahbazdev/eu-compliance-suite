<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'suppliers', 'plots', 'shipments', 'risk' ) as $name ) {
	$table = $wpdb->prefix . 'eurocomply_eudr_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ( array(
	'eurocomply_eudr_settings',
	'eurocomply_eudr_license',
	'eurocomply_eudr_country_risk',
	'eurocomply_eudr_suppliers_db_version',
	'eurocomply_eudr_plots_db_version',
	'eurocomply_eudr_shipments_db_version',
	'eurocomply_eudr_risk_db_version',
) as $opt ) {
	delete_option( $opt );
}
