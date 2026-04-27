<?php
/**
 * Uninstall handler for EuroComply E-Invoicing.
 *
 * Drops options, license, and the invoice log table. Generated invoice PDFs
 * in the uploads directory are intentionally preserved — admins may need
 * them for the 10-year GoBD / EU bookkeeping retention requirement.
 *
 * @package EuroComply\EInvoicing
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'eurocomply_einv_settings' );
delete_option( 'eurocomply_einv_license' );
delete_option( 'eurocomply_einv_db_version' );

$table = $wpdb->prefix . 'eurocomply_einv_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
