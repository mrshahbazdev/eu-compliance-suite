<?php
/**
 * ICT third-party Register of Information (RoI) — Art. 28(3) DORA.
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThirdPartyStore {

	private const DB_VERSION_OPTION = 'eurocomply_dora_tpp_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dora_third_parties';
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			name VARCHAR(255) NOT NULL DEFAULT '',
			lei VARCHAR(20) NOT NULL DEFAULT '',
			country CHAR(2) NOT NULL DEFAULT '',
			services VARCHAR(255) NOT NULL DEFAULT '',
			criticality_tier TINYINT NOT NULL DEFAULT 3,
			supports_critical TINYINT(1) NOT NULL DEFAULT 0,
			contract_ref VARCHAR(64) NOT NULL DEFAULT '',
			contract_start DATE NULL DEFAULT NULL,
			contract_end DATE NULL DEFAULT NULL,
			subcontractor_chain LONGTEXT NULL,
			data_processed VARCHAR(48) NOT NULL DEFAULT '',
			gdpr_dpa TINYINT(1) NOT NULL DEFAULT 0,
			exit_strategy LONGTEXT NULL,
			last_review DATE NULL DEFAULT NULL,
			next_review DATE NULL DEFAULT NULL,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY criticality_tier (criticality_tier),
			KEY country (country)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function uninstall() : void {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( self::DB_VERSION_OPTION );
	}

	public static function create( array $row ) : int {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'          => current_time( 'mysql' ),
				'name'                => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'lei'                 => substr( strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) ( $row['lei'] ?? '' ) ) ), 0, 20 ),
				'country'             => strtoupper( substr( (string) ( $row['country'] ?? '' ), 0, 2 ) ),
				'services'            => sanitize_text_field( (string) ( $row['services'] ?? '' ) ),
				'criticality_tier'    => max( 1, min( 3, (int) ( $row['criticality_tier'] ?? 3 ) ) ),
				'supports_critical'   => ! empty( $row['supports_critical'] ) ? 1 : 0,
				'contract_ref'        => sanitize_text_field( (string) ( $row['contract_ref'] ?? '' ) ),
				'contract_start'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['contract_start'] ?? '' ) ) ? (string) $row['contract_start'] : null,
				'contract_end'        => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['contract_end']   ?? '' ) ) ? (string) $row['contract_end']   : null,
				'subcontractor_chain' => wp_kses_post( (string) ( $row['subcontractor_chain'] ?? '' ) ),
				'data_processed'      => sanitize_text_field( (string) ( $row['data_processed'] ?? '' ) ),
				'gdpr_dpa'            => ! empty( $row['gdpr_dpa'] ) ? 1 : 0,
				'exit_strategy'       => wp_kses_post( (string) ( $row['exit_strategy'] ?? '' ) ),
				'last_review'         => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['last_review'] ?? '' ) ) ? (string) $row['last_review'] : null,
				'next_review'         => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['next_review'] ?? '' ) ) ? (string) $row['next_review'] : null,
				'notes'               => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY criticality_tier ASC, name ASC", ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_critical() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE supports_critical = 1" );
	}

	public static function reviews_due( int $within_days = 30 ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE next_review IS NOT NULL AND next_review <= %s",
				gmdate( 'Y-m-d', time() + $within_days * 86400 )
			)
		);
	}
}
