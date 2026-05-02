<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array(
	$wpdb->prefix . 'eurocomply_fl_suppliers',
	$wpdb->prefix . 'eurocomply_fl_risks',
	$wpdb->prefix . 'eurocomply_fl_audits',
	$wpdb->prefix . 'eurocomply_fl_submissions',
	$wpdb->prefix . 'eurocomply_fl_withdrawals',
) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );
}

foreach ( array(
	'eurocomply_fl_settings',
	'eurocomply_fl_license',
	'eurocomply_fl_supplier_schema',
	'eurocomply_fl_risk_schema',
	'eurocomply_fl_audit_schema',
	'eurocomply_fl_submission_schema',
	'eurocomply_fl_withdrawal_schema',
) as $opt ) {
	delete_option( $opt );
}
