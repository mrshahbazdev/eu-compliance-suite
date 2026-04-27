<?php
/**
 * Uninstall — drop options, license, and issues table.
 *
 * The auto-created statement page is preserved so that content editors can adopt it manually.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'eurocomply_eaa_settings' );
delete_option( 'eurocomply_eaa_license' );
delete_option( 'eurocomply_eaa_statement_page_id' );

global $wpdb;
$table = $wpdb->prefix . 'eurocomply_eaa_issues';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
