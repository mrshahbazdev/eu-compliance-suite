<?php
/**
 * Uninstall handler for EuroComply Omnibus.
 *
 * Drops options, license, and the price history table. Per-product
 * `_eurocomply_omnibus_last` meta rows are deleted too — they're internal
 * bookkeeping, not the compliance record itself.
 *
 * @package EuroComply\Omnibus
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'eurocomply_omnibus_settings' );
delete_option( 'eurocomply_omnibus_license' );
delete_option( 'eurocomply_omnibus_db_version' );

$table = $wpdb->prefix . 'eurocomply_omnibus_history';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
		'_eurocomply_omnibus_last'
	)
);
