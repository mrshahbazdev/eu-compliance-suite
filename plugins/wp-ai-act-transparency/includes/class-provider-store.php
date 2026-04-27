<?php
/**
 * AI provider registry.
 *
 * Table: wp_eurocomply_aiact_providers
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProviderStore {

	public const DB_VERSION_OPTION = 'eurocomply_aiact_providers_db_version';
	public const DB_VERSION        = '1.0.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_aiact_providers';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			label VARCHAR(191) NOT NULL DEFAULT '',
			provider_slug VARCHAR(64) NOT NULL DEFAULT 'other',
			model VARCHAR(191) NOT NULL DEFAULT '',
			purpose VARCHAR(64) NOT NULL DEFAULT '',
			country VARCHAR(2) NOT NULL DEFAULT '',
			vendor_legal_name VARCHAR(255) NOT NULL DEFAULT '',
			gpai TINYINT UNSIGNED NOT NULL DEFAULT 0,
			high_risk TINYINT UNSIGNED NOT NULL DEFAULT 0,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY provider_slug (provider_slug),
			KEY purpose (purpose)
		) {$charset};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function create( array $data ) : int {
		global $wpdb;
		$row = self::normalise( $data );
		$row['created_at'] = current_time( 'mysql' );
		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function update( int $id, array $data ) : bool {
		global $wpdb;
		if ( $id <= 0 ) {
			return false;
		}
		$row = self::normalise( $data );
		$result = $wpdb->update( self::table_name(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $result;
	}

	public static function delete( int $id ) : bool {
		global $wpdb;
		if ( $id <= 0 ) {
			return false;
		}
		return false !== $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all( int $limit = 500 ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		$sql   = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY label ASC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private static function normalise( array $data ) : array {
		$slug = sanitize_key( (string) ( $data['provider_slug'] ?? 'other' ) );
		if ( ! isset( Settings::ai_providers_known()[ $slug ] ) ) {
			$slug = 'other';
		}
		$purpose = sanitize_key( (string) ( $data['purpose'] ?? '' ) );
		if ( '' !== $purpose && ! isset( Settings::ai_purposes()[ $purpose ] ) ) {
			$purpose = 'other';
		}
		$cc = isset( $data['country'] ) ? strtoupper( (string) $data['country'] ) : '';
		if ( '' !== $cc && ! preg_match( '/^[A-Z]{2}$/', $cc ) ) {
			$cc = '';
		}
		return array(
			'label'             => substr( sanitize_text_field( (string) ( $data['label'] ?? '' ) ), 0, 191 ),
			'provider_slug'     => $slug,
			'model'             => substr( sanitize_text_field( (string) ( $data['model'] ?? '' ) ), 0, 191 ),
			'purpose'           => $purpose,
			'country'           => $cc,
			'vendor_legal_name' => substr( sanitize_text_field( (string) ( $data['vendor_legal_name'] ?? '' ) ), 0, 255 ),
			'gpai'              => ! empty( $data['gpai'] ) ? 1 : 0,
			'high_risk'         => ! empty( $data['high_risk'] ) ? 1 : 0,
			'notes'             => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
		);
	}
}
