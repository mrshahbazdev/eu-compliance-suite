<?php
/**
 * Art. 9 — disclosure-of-evidence log.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DisclosureStore {

	private const SCHEMA_OPTION  = 'eurocomply_pl_disclosure_schema';
	private const SCHEMA_VERSION = '1';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_pl_disclosures';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            claim_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            requested_on DATE NULL,
            disclosed_on DATE NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'requested',
            evidence_category VARCHAR(64) NULL,
            confidentiality VARCHAR(32) NULL,
            counsel VARCHAR(190) NULL,
            court_ref VARCHAR(190) NULL,
            description LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY claim_id (claim_id),
            KEY status (status)
        ) $charset;";
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	public static function insert( array $data ) : int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			self::table_name(),
			array(
				'created_at'        => $now,
				'updated_at'        => $now,
				'claim_id'          => (int) ( $data['claim_id'] ?? 0 ),
				'product_id'        => (int) ( $data['product_id'] ?? 0 ),
				'requested_on'      => ! empty( $data['requested_on'] ) ? sanitize_text_field( (string) $data['requested_on'] ) : current_time( 'Y-m-d', true ),
				'disclosed_on'      => ! empty( $data['disclosed_on'] ) ? sanitize_text_field( (string) $data['disclosed_on'] ) : null,
				'status'            => array_key_exists( ( $data['status'] ?? '' ), Settings::disclosure_status() ) ? (string) $data['status'] : 'requested',
				'evidence_category' => sanitize_text_field( (string) ( $data['evidence_category'] ?? '' ) ),
				'confidentiality'   => sanitize_key( (string) ( $data['confidentiality'] ?? 'standard' ) ),
				'counsel'           => sanitize_text_field( (string) ( $data['counsel'] ?? '' ) ),
				'court_ref'         => sanitize_text_field( (string) ( $data['court_ref'] ?? '' ) ),
				'description'       => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function delete( int $id ) : bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function open_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE status IN ('requested','court_ordered','partial')" );
	}
}
