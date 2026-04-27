<?php
/**
 * Uninstall cleanup for EuroComply EU VAT Validator & OSS Rates.
 *
 * @package EuroComply\VatOss
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'eurocomply_vat_settings' );
delete_option( 'eurocomply_vat_license' );
delete_option( 'eurocomply_vat_rates_cache' );

$table = $wpdb->prefix . 'eurocomply_vat_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
