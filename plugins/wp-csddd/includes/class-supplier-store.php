<?php
/**
 * Suppliers register (chain of activities, Art. 8).
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SupplierStore {

	private const SCHEMA_VERSION = '1.0.0';
	private const SCHEMA_OPTION  = 'eurocomply_csddd_supplier_schema';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_csddd_suppliers';
	}

	public static function install() : void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			external_ref VARCHAR(64) NOT NULL DEFAULT '',
			name VARCHAR(255) NOT NULL DEFAULT '',
			country CHAR(2) NOT NULL DEFAULT '',
			tier VARCHAR(32) NOT NULL DEFAULT 'tier_1',
			sector_nace VARCHAR(8) NOT NULL DEFAULT '',
			risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			contact_email VARCHAR(190) NOT NULL DEFAULT '',
			last_assessed DATE NULL,
			notes TEXT NULL,
			PRIMARY KEY (id),
			KEY tier (tier),
			KEY country (country),
			KEY risk_score (risk_score)
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
		$row = array(
			'external_ref'  => isset( $data['external_ref'] ) ? sanitize_text_field( (string) $data['external_ref'] ) : '',
			'name'          => isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '',
			'country'       => isset( $data['country'] ) ? strtoupper( substr( sanitize_text_field( (string) $data['country'] ), 0, 2 ) ) : '',
			'tier'          => isset( $data['tier'] ) ? sanitize_key( (string) $data['tier'] ) : 'tier_1',
			'sector_nace'   => isset( $data['sector_nace'] ) ? sanitize_text_field( (string) $data['sector_nace'] ) : '',
			'risk_score'    => isset( $data['risk_score'] ) ? max( 0, min( 100, (int) $data['risk_score'] ) ) : 0,
			'contact_email' => isset( $data['contact_email'] ) ? sanitize_email( (string) $data['contact_email'] ) : '',
			'last_assessed' => isset( $data['last_assessed'] ) ? sanitize_text_field( (string) $data['last_assessed'] ) : null,
			'notes'         => isset( $data['notes'] ) ? wp_kses_post( (string) $data['notes'] ) : '',
		);
		if ( '' === $row['name'] ) {
			return 0;
		}
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
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY risk_score DESC, id DESC LIMIT %d', $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function high_risk_count( int $threshold = 70 ) : int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE risk_score >= %d', $threshold ) );
	}
}
