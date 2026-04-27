<?php
/**
 * Uninstall handler for EuroComply DSA Transparency.
 *
 * Drops options, license, and all three DSA tables (notices, statements,
 * traders). DSA Art. 15 retention obligations may require keeping historical
 * records — site operators should export to JSON/CSV before uninstalling.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'eurocomply_dsa_settings' );
delete_option( 'eurocomply_dsa_license' );
delete_option( 'eurocomply_dsa_notices_db_version' );
delete_option( 'eurocomply_dsa_statements_db_version' );
delete_option( 'eurocomply_dsa_traders_db_version' );

$tables = array(
	$wpdb->prefix . 'eurocomply_dsa_notices',
	$wpdb->prefix . 'eurocomply_dsa_statements',
	$wpdb->prefix . 'eurocomply_dsa_traders',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
