<?php
/**
 * Uninstall.
 *
 * @package EuroComply\MiCA
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
foreach ( array( 'assets', 'whitepapers', 'comms', 'complaints', 'disclosures' ) as $t ) {
	$table = $wpdb->prefix . 'eurocomply_mica_' . $t;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
foreach ( array(
	'eurocomply_mica_settings',
	'eurocomply_mica_license',
	'eurocomply_mica_assets_db_version',
	'eurocomply_mica_whitepapers_db_version',
	'eurocomply_mica_comms_db_version',
	'eurocomply_mica_complaints_db_version',
	'eurocomply_mica_disclosures_db_version',
) as $opt ) {
	delete_option( $opt );
}
