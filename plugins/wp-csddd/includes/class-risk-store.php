<?php
/**
 * Adverse-impact register (Annex Part I + II).
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RiskStore {

	private const SCHEMA_VERSION = '1.0.0';
	private const SCHEMA_OPTION  = 'eurocomply_csddd_risk_schema';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_csddd_risks';
	}

	public static function install() : void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			supplier_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			category VARCHAR(64) NOT NULL DEFAULT '',
			annex VARCHAR(16) NOT NULL DEFAULT '',
			severity VARCHAR(16) NOT NULL DEFAULT 'medium',
			likelihood TINYINT UNSIGNED NOT NULL DEFAULT 50,
			status VARCHAR(32) NOT NULL DEFAULT 'identified',
			identified_at DATE NOT NULL,
			resolved_at DATE NULL,
			source VARCHAR(64) NOT NULL DEFAULT 'internal',
			description TEXT NULL,
			PRIMARY KEY (id),
			KEY supplier_id (supplier_id),
			KEY category (category),
			KEY severity (severity),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function insert( array $data ) : int {
		global $wpdb;
		$cats = Settings::risk_categories();
		$cat  = isset( $data['category'] ) ? sanitize_key( (string) $data['category'] ) : '';
		if ( ! isset( $cats[ $cat ] ) ) {
			return 0;
		}
		$row = array(
			'supplier_id'   => isset( $data['supplier_id'] ) ? max( 0, (int) $data['supplier_id'] ) : 0,
			'category'      => $cat,
			'annex'         => $cats[ $cat ]['annex'],
			'severity'      => isset( $data['severity'] ) && array_key_exists( (string) $data['severity'], Settings::severity_levels() ) ? (string) $data['severity'] : 'medium',
			'likelihood'    => isset( $data['likelihood'] ) ? max( 0, min( 100, (int) $data['likelihood'] ) ) : 50,
			'status'        => isset( $data['status'] ) && array_key_exists( (string) $data['status'], Settings::risk_status() ) ? (string) $data['status'] : 'identified',
			'identified_at' => isset( $data['identified_at'] ) ? sanitize_text_field( (string) $data['identified_at'] ) : gmdate( 'Y-m-d' ),
			'resolved_at'   => isset( $data['resolved_at'] ) ? sanitize_text_field( (string) $data['resolved_at'] ) : null,
			'source'        => isset( $data['source'] ) ? sanitize_key( (string) $data['source'] ) : 'internal',
			'description'   => isset( $data['description'] ) ? wp_kses_post( (string) $data['description'] ) : '',
		);
		$ok = $wpdb->insert( self::table_name(), $row );
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : bool {
		global $wpdb;
		return false !== $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( int $limit = 500 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY severity DESC, id DESC LIMIT %d', $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function unresolved_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE status NOT IN ('resolved')" );
	}

	public static function critical_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE severity = 'critical' AND status NOT IN ('resolved')" );
	}
}
