<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\ProductLiability
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$tables = array(
	$wpdb->prefix . 'eurocomply_pl_products',
	$wpdb->prefix . 'eurocomply_pl_defects',
	$wpdb->prefix . 'eurocomply_pl_claims',
	$wpdb->prefix . 'eurocomply_pl_disclosures',
);
foreach ( $tables as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $t ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'eurocomply_pl_settings' );
delete_option( 'eurocomply_pl_license' );
delete_option( 'eurocomply_pl_product_schema' );
delete_option( 'eurocomply_pl_defect_schema' );
delete_option( 'eurocomply_pl_claim_schema' );
delete_option( 'eurocomply_pl_disclosure_schema' );
