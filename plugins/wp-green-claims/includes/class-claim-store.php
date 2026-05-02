<?php
/**
 * Substantiation register for environmental claims.
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClaimStore {

	private const SCHEMA_VERSION = '1.0.0';
	private const SCHEMA_OPTION  = 'eurocomply_gc_claim_schema';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_gc_claims';
	}

	public static function install() : void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			claim_text VARCHAR(500) NOT NULL DEFAULT '',
			scope VARCHAR(64) NOT NULL DEFAULT 'product',
			evidence_type VARCHAR(64) NOT NULL DEFAULT 'iso_14021',
			evidence_url VARCHAR(500) NOT NULL DEFAULT '',
			verifier VARCHAR(255) NOT NULL DEFAULT '',
			verified_at DATE NULL,
			expires_at DATE NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'pending',
			notes TEXT NULL,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY status (status),
			KEY evidence_type (evidence_type)
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
			'product_id'    => isset( $data['product_id'] ) ? max( 0, (int) $data['product_id'] ) : 0,
			'claim_text'    => isset( $data['claim_text'] ) ? sanitize_text_field( (string) $data['claim_text'] ) : '',
			'scope'         => isset( $data['scope'] ) ? sanitize_key( (string) $data['scope'] ) : 'product',
			'evidence_type' => isset( $data['evidence_type'] ) && array_key_exists( (string) $data['evidence_type'], Settings::evidence_types() ) ? (string) $data['evidence_type'] : 'iso_14021',
			'evidence_url'  => isset( $data['evidence_url'] ) ? esc_url_raw( (string) $data['evidence_url'] ) : '',
			'verifier'      => isset( $data['verifier'] ) ? sanitize_text_field( (string) $data['verifier'] ) : '',
			'verified_at'   => isset( $data['verified_at'] ) && '' !== $data['verified_at'] ? sanitize_text_field( (string) $data['verified_at'] ) : null,
			'expires_at'    => isset( $data['expires_at'] ) && '' !== $data['expires_at'] ? sanitize_text_field( (string) $data['expires_at'] ) : null,
			'status'        => isset( $data['status'] ) && array_key_exists( (string) $data['status'], Settings::claim_status() ) ? (string) $data['status'] : 'pending',
			'notes'         => isset( $data['notes'] ) ? wp_kses_post( (string) $data['notes'] ) : '',
		);
		if ( '' === $row['claim_text'] ) {
			return 0;
		}
		$ok = $wpdb->insert( self::table_name(), $row );
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	public static function update_status( int $id, string $status ) : bool {
		global $wpdb;
		if ( ! array_key_exists( $status, Settings::claim_status() ) ) {
			return false;
		}
		return false !== $wpdb->update( self::table_name(), array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
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
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function by_product( int $product_id, string $only_status = 'verified' ) : array {
		global $wpdb;
		if ( '' === $only_status ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE product_id = %d ORDER BY id DESC', $product_id ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE product_id = %d AND status = %s ORDER BY id DESC', $product_id, $only_status ), ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function pending_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() . " WHERE status = 'pending'" );
	}

	public static function expired_count() : int {
		global $wpdb;
		$today = gmdate( 'Y-m-d' );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE expires_at IS NOT NULL AND expires_at < %s', $today ) );
	}
}
