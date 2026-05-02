<?php
/**
 * Audit / certification log.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuditStore {

	private const SCHEMA_OPTION  = 'eurocomply_fl_audit_schema';
	private const SCHEMA_VERSION = '1';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_fl_audits';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            supplier_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            scheme VARCHAR(64) NOT NULL,
            audit_date DATE NULL,
            expires_at DATE NULL,
            certificate_no VARCHAR(190) NULL,
            certificate_url TEXT NULL,
            findings LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY supplier_id (supplier_id),
            KEY scheme (scheme),
            KEY expires_at (expires_at)
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
		$scheme = sanitize_key( (string) ( $data['scheme'] ?? '' ) );
		if ( ! array_key_exists( $scheme, Settings::audit_schemes() ) ) {
			return 0;
		}
		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'created_at'      => current_time( 'mysql', true ),
				'supplier_id'     => (int) ( $data['supplier_id'] ?? 0 ),
				'scheme'          => $scheme,
				'audit_date'      => ! empty( $data['audit_date'] ) ? sanitize_text_field( (string) $data['audit_date'] ) : null,
				'expires_at'      => ! empty( $data['expires_at'] ) ? sanitize_text_field( (string) $data['expires_at'] ) : null,
				'certificate_no'  => sanitize_text_field( (string) ( $data['certificate_no'] ?? '' ) ),
				'certificate_url' => esc_url_raw( (string) ( $data['certificate_url'] ?? '' ) ),
				'findings'        => wp_kses_post( (string) ( $data['findings'] ?? '' ) ),
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

	public static function expired_count() : int {
		global $wpdb;
		$today = current_time( 'Y-m-d', true );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE expires_at IS NOT NULL AND expires_at < %s', $today ) );
	}
}
