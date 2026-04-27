<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'scans', 'findings', 'cookies' ) as $name ) {
	$table = $wpdb->prefix . 'eurocomply_eprivacy_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ( array(
	'eurocomply_eprivacy_settings',
	'eurocomply_eprivacy_license',
	'eurocomply_eprivacy_scans_db_version',
	'eurocomply_eprivacy_findings_db_version',
	'eurocomply_eprivacy_cookies_db_version',
) as $opt ) {
	delete_option( $opt );
}
