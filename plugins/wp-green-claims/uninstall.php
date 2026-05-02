<?php
/**
 * Uninstall handler.
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array(
	$wpdb->prefix . 'eurocomply_gc_claims',
	$wpdb->prefix . 'eurocomply_gc_labels',
) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );
}

foreach ( array(
	'eurocomply_gc_settings',
	'eurocomply_gc_license',
	'eurocomply_gc_claim_schema',
	'eurocomply_gc_label_schema',
) as $opt ) {
	delete_option( $opt );
}
