<?php
/**
 * Transaction / SCA / 3-DS2 event log.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TransactionStore {

	private const DB_VERSION_OPTION = 'eurocomply_psd2_transactions_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_psd2_transactions';
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
			period VARCHAR(7) NOT NULL DEFAULT '',
			order_ref VARCHAR(64) NOT NULL DEFAULT '',
			amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			currency CHAR(3) NOT NULL DEFAULT 'EUR',
			provider VARCHAR(32) NOT NULL DEFAULT '',
			sca_required TINYINT UNSIGNED NOT NULL DEFAULT 0,
			exemption VARCHAR(32) NOT NULL DEFAULT '',
			reason VARCHAR(255) NOT NULL DEFAULT '',
			three_ds_status VARCHAR(32) NOT NULL DEFAULT '',
			three_ds_version VARCHAR(8) NOT NULL DEFAULT '',
			outcome VARCHAR(32) NOT NULL DEFAULT '',
			country CHAR(2) NOT NULL DEFAULT '',
			risk_score DECIMAL(6,4) NOT NULL DEFAULT 0,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY period (period),
			KEY exemption (exemption),
			KEY outcome (outcome)
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
				'created_at'       => current_time( 'mysql' ),
				'period'           => sanitize_text_field( (string) ( $row['period'] ?? Settings::current_period() ) ),
				'order_ref'        => sanitize_text_field( (string) ( $row['order_ref'] ?? '' ) ),
				'amount'           => (float) ( $row['amount'] ?? 0 ),
				'currency'         => strtoupper( substr( (string) ( $row['currency'] ?? 'EUR' ), 0, 3 ) ),
				'provider'         => sanitize_key( (string) ( $row['provider'] ?? '' ) ),
				'sca_required'     => ! empty( $row['sca_required'] ) ? 1 : 0,
				'exemption'        => sanitize_key( (string) ( $row['exemption'] ?? '' ) ),
				'reason'           => sanitize_text_field( (string) ( $row['reason'] ?? '' ) ),
				'three_ds_status'  => sanitize_key( (string) ( $row['three_ds_status'] ?? '' ) ),
				'three_ds_version' => sanitize_text_field( (string) ( $row['three_ds_version'] ?? '' ) ),
				'outcome'          => sanitize_key( (string) ( $row['outcome'] ?? 'authorised' ) ),
				'country'          => strtoupper( substr( (string) ( $row['country'] ?? '' ), 0, 2 ) ),
				'risk_score'       => (float) ( $row['risk_score'] ?? 0 ),
				'notes'            => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function recent( int $limit = 100 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function for_period( string $period, int $limit = 5000 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 50000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE period = %s ORDER BY id ASC LIMIT %d", $period, $limit ), ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_for_period( string $period ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE period = %s", $period ) );
	}

	public static function exemption_breakdown( string $period ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT exemption, COUNT(*) as c, SUM(amount) as v FROM {$table} WHERE period = %s GROUP BY exemption", $period ), ARRAY_A );
		$out = array();
		foreach ( $rows as $r ) {
			$out[ (string) ( $r['exemption'] ?: 'sca' ) ] = array(
				'count' => (int) $r['c'],
				'value' => (float) $r['v'],
			);
		}
		return $out;
	}

	public static function challenge_failure_rate( string $period ) : float {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE period = %s AND sca_required = 1", $period ) );
		if ( $total <= 0 ) {
			return 0.0;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE period = %s AND sca_required = 1 AND outcome IN ('failed','abandoned')", $period ) );
		return round( $failed / $total, 4 );
	}
}
