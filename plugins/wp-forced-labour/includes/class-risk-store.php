<?php
/**
 * Forced-labour risk register.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RiskStore {

	private const SCHEMA_OPTION  = 'eurocomply_fl_risk_schema';
	private const SCHEMA_VERSION = '1';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_fl_risks';
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
            supplier_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            indicator VARCHAR(64) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'medium',
            status VARCHAR(32) NOT NULL DEFAULT 'identified',
            country VARCHAR(8) NULL,
            sector VARCHAR(64) NULL,
            identified_at DATE NULL,
            resolved_at DATE NULL,
            source VARCHAR(190) NULL,
            description LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY supplier_id (supplier_id),
            KEY indicator (indicator),
            KEY severity (severity),
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
		$indicator = sanitize_key( (string) ( $data['indicator'] ?? '' ) );
		if ( ! array_key_exists( $indicator, Settings::indicators() ) ) {
			return 0;
		}
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			self::table_name(),
			array(
				'created_at'    => $now,
				'updated_at'    => $now,
				'supplier_id'   => (int) ( $data['supplier_id'] ?? 0 ),
				'indicator'     => $indicator,
				'severity'      => array_key_exists( ( $data['severity'] ?? '' ), Settings::severity_levels() ) ? (string) $data['severity'] : 'medium',
				'status'        => array_key_exists( ( $data['status'] ?? '' ), Settings::risk_status() ) ? (string) $data['status'] : 'identified',
				'country'       => substr( strtoupper( sanitize_text_field( (string) ( $data['country'] ?? '' ) ) ), 0, 8 ),
				'sector'        => sanitize_key( (string) ( $data['sector'] ?? '' ) ),
				'identified_at' => ! empty( $data['identified_at'] ) ? sanitize_text_field( (string) $data['identified_at'] ) : current_time( 'Y-m-d', true ),
				'resolved_at'   => ! empty( $data['resolved_at'] ) ? sanitize_text_field( (string) $data['resolved_at'] ) : null,
				'source'        => sanitize_text_field( (string) ( $data['source'] ?? '' ) ),
				'description'   => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
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

	public static function unresolved_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE status NOT IN ('resolved','banned')" );
	}

	public static function critical_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE severity = 'critical' AND status NOT IN ('resolved')" );
	}
}
