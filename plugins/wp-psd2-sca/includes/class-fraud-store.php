<?php
/**
 * Fraud event log + Art. 96(6) PSD2 quarterly fraud report data source.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FraudStore {

	private const DB_VERSION_OPTION = 'eurocomply_psd2_fraud_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_psd2_fraud';
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
			category VARCHAR(32) NOT NULL DEFAULT '',
			channel VARCHAR(32) NOT NULL DEFAULT 'remote_card',
			amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			currency CHAR(3) NOT NULL DEFAULT 'EUR',
			reimbursed TINYINT UNSIGNED NOT NULL DEFAULT 0,
			reimbursed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			refunded_within_window TINYINT UNSIGNED NOT NULL DEFAULT 0,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY period (period),
			KEY category (category)
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
				'created_at'             => current_time( 'mysql' ),
				'period'                 => sanitize_text_field( (string) ( $row['period'] ?? Settings::current_period() ) ),
				'category'               => sanitize_key( (string) ( $row['category'] ?? '' ) ),
				'channel'                => sanitize_key( (string) ( $row['channel'] ?? 'remote_card' ) ),
				'amount'                 => (float) ( $row['amount'] ?? 0 ),
				'currency'               => strtoupper( substr( (string) ( $row['currency'] ?? 'EUR' ), 0, 3 ) ),
				'reimbursed'             => ! empty( $row['reimbursed'] ) ? 1 : 0,
				'reimbursed_amount'      => (float) ( $row['reimbursed_amount'] ?? 0 ),
				'refunded_within_window' => ! empty( $row['refunded_within_window'] ) ? 1 : 0,
				'notes'                  => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
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

	public static function for_period( string $period ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE period = %s ORDER BY id ASC", $period ), ARRAY_A );
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

	public static function fraud_value( string $period ) : float {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE period = %s", $period ) );
	}

	public static function refund_compliance( string $period ) : float {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE period = %s AND reimbursed = 1", $period ) );
		if ( $total <= 0 ) {
			return 1.0;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$on_time = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE period = %s AND reimbursed = 1 AND refunded_within_window = 1", $period ) );
		return round( $on_time / $total, 4 );
	}
}
