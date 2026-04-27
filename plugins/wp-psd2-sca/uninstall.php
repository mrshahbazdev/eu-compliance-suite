<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'transactions', 'consents', 'tpps', 'fraud' ) as $name ) {
	$table = $wpdb->prefix . 'eurocomply_psd2_' . $name;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ( array(
	'eurocomply_psd2_settings',
	'eurocomply_psd2_license',
	'eurocomply_psd2_transactions_db_version',
	'eurocomply_psd2_consents_db_version',
	'eurocomply_psd2_tpps_db_version',
	'eurocomply_psd2_fraud_db_version',
) as $opt ) {
	delete_option( $opt );
}
