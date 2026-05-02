<?php
/**
 * Supplier register.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SupplierStore {

	private const SCHEMA_OPTION  = 'eurocomply_fl_supplier_schema';
	private const SCHEMA_VERSION = '1';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_fl_suppliers';
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
            external_ref VARCHAR(64) NULL,
            name VARCHAR(190) NOT NULL,
            country VARCHAR(8) NULL,
            region VARCHAR(64) NULL,
            sector VARCHAR(64) NULL,
            tier VARCHAR(32) NOT NULL DEFAULT 'tier_1',
            risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_audited DATE NULL,
            contact_email VARCHAR(190) NULL,
            notes LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY country (country),
            KEY sector (sector),
            KEY risk_score (risk_score)
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
				'created_at'    => $now,
				'updated_at'    => $now,
				'external_ref'  => sanitize_text_field( (string) ( $data['external_ref'] ?? '' ) ),
				'name'          => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
				'country'       => substr( strtoupper( sanitize_text_field( (string) ( $data['country'] ?? '' ) ) ), 0, 8 ),
				'region'        => sanitize_text_field( (string) ( $data['region'] ?? '' ) ),
				'sector'        => sanitize_key( (string) ( $data['sector'] ?? '' ) ),
				'tier'          => sanitize_key( (string) ( $data['tier'] ?? 'tier_1' ) ),
				'risk_score'    => max( 0, min( 100, (int) ( $data['risk_score'] ?? 0 ) ) ),
				'last_audited'  => ! empty( $data['last_audited'] ) ? sanitize_text_field( (string) $data['last_audited'] ) : null,
				'contact_email' => sanitize_email( (string) ( $data['contact_email'] ?? '' ) ),
				'notes'         => wp_kses_post( (string) ( $data['notes'] ?? '' ) ),
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
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . ' ORDER BY risk_score DESC, name ASC', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function high_risk_count() : int {
		global $wpdb;
		$threshold = (int) ( Settings::get()['high_risk_threshold'] ?? 70 );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE risk_score >= %d', $threshold ) );
	}
}
