<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array(
	$wpdb->prefix . 'eurocomply_csddd_suppliers',
	$wpdb->prefix . 'eurocomply_csddd_risks',
	$wpdb->prefix . 'eurocomply_csddd_actions',
	$wpdb->prefix . 'eurocomply_csddd_complaints',
) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );
}

foreach ( array(
	'eurocomply_csddd_settings',
	'eurocomply_csddd_license',
	'eurocomply_csddd_supplier_schema',
	'eurocomply_csddd_risk_schema',
	'eurocomply_csddd_action_schema',
	'eurocomply_csddd_complaint_schema',
) as $opt ) {
	delete_option( $opt );
}
