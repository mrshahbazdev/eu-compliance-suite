<?php
/**
 * Risk-assessment register (Art. 12).
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RiskStore {

	private const DB_VERSION_OPTION = 'eurocomply_cer_risk_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_cer_risk';
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
			service_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			conducted_at DATE NULL DEFAULT NULL,
			next_review DATE NULL DEFAULT NULL,
			threat VARCHAR(48) NOT NULL DEFAULT '',
			likelihood TINYINT NOT NULL DEFAULT 1,
			impact TINYINT NOT NULL DEFAULT 1,
			score INT NOT NULL DEFAULT 0,
			finding LONGTEXT NULL,
			treatment LONGTEXT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'open',
			PRIMARY KEY  (id),
			KEY service_id (service_id),
			KEY threat (threat),
			KEY status (status)
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

	/**
	 * @return array<int,string>
	 */
	public static function threat_categories() : array {
		return array(
			'natural_hazard'    => __( 'Natural hazard',                    'eurocomply-cer' ),
			'climate'           => __( 'Climate-change-related',             'eurocomply-cer' ),
			'physical_attack'   => __( 'Physical attack / sabotage',          'eurocomply-cer' ),
			'terrorism'         => __( 'Terrorism',                            'eurocomply-cer' ),
			'cbrn'              => __( 'CBRN (chemical / biological / radiological / nuclear)', 'eurocomply-cer' ),
			'pandemic'          => __( 'Pandemic',                                'eurocomply-cer' ),
			'insider_threat'    => __( 'Insider threat',                            'eurocomply-cer' ),
			'cyber'             => __( 'Cyber (covered by NIS2 too)',                  'eurocomply-cer' ),
			'supply_chain'      => __( 'Supply-chain disruption',                       'eurocomply-cer' ),
			'energy_disruption' => __( 'Energy disruption',                              'eurocomply-cer' ),
			'hybrid_threat'     => __( 'Hybrid threat',                                  'eurocomply-cer' ),
		);
	}

	public static function create( array $row ) : int {
		global $wpdb;
		$lik    = max( 1, min( 5, (int) ( $row['likelihood'] ?? 1 ) ) );
		$impact = max( 1, min( 5, (int) ( $row['impact'] ?? 1 ) ) );
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'   => current_time( 'mysql' ),
				'service_id'   => (int) ( $row['service_id'] ?? 0 ),
				'conducted_at' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['conducted_at'] ?? '' ) ) ? (string) $row['conducted_at'] : null,
				'next_review'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $row['next_review'] ?? '' ) ) ? (string) $row['next_review'] : null,
				'threat'       => sanitize_key( (string) ( $row['threat'] ?? 'cyber' ) ),
				'likelihood'   => $lik,
				'impact'       => $impact,
				'score'        => $lik * $impact,
				'finding'      => wp_kses_post( (string) ( $row['finding'] ?? '' ) ),
				'treatment'    => wp_kses_post( (string) ( $row['treatment'] ?? '' ) ),
				'status'       => in_array( (string) ( $row['status'] ?? 'open' ), array( 'open', 'mitigated', 'accepted', 'closed' ), true ) ? (string) $row['status'] : 'open',
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
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY score DESC, id DESC", ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_high() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE score >= 15 AND status = 'open'" );
	}

	public static function reviews_overdue() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE next_review IS NOT NULL AND next_review < %s",
				gmdate( 'Y-m-d' )
			)
		);
	}
}
