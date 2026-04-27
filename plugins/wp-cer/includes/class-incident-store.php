<?php
/**
 * Significant-disruption incident register (Art. 15).
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IncidentStore {

	private const DB_VERSION_OPTION = 'eurocomply_cer_incidents_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_cer_incidents';
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
			occurred_at DATETIME NULL DEFAULT NULL,
			detected_at DATETIME NULL DEFAULT NULL,
			category VARCHAR(48) NOT NULL DEFAULT '',
			significant TINYINT(1) NOT NULL DEFAULT 0,
			users_affected INT(11) NOT NULL DEFAULT 0,
			duration_min INT(11) NOT NULL DEFAULT 0,
			geo_spread INT(11) NOT NULL DEFAULT 1,
			cross_border TINYINT(1) NOT NULL DEFAULT 0,
			status VARCHAR(24) NOT NULL DEFAULT 'open',
			early_warning_sent_at DATETIME NULL DEFAULT NULL,
			followup_sent_at DATETIME NULL DEFAULT NULL,
			summary LONGTEXT NULL,
			root_cause LONGTEXT NULL,
			mitigation LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY service_id (service_id),
			KEY significant (significant),
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

	public static function create( array $row ) : int {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'     => current_time( 'mysql' ),
				'service_id'     => (int) ( $row['service_id'] ?? 0 ),
				'occurred_at'    => self::dt( $row['occurred_at'] ?? null ),
				'detected_at'    => self::dt( $row['detected_at'] ?? null ),
				'category'       => sanitize_key( (string) ( $row['category'] ?? 'physical_attack' ) ),
				'significant'    => ! empty( $row['significant'] ) ? 1 : 0,
				'users_affected' => max( 0, (int) ( $row['users_affected'] ?? 0 ) ),
				'duration_min'   => max( 0, (int) ( $row['duration_min'] ?? 0 ) ),
				'geo_spread'     => max( 1, (int) ( $row['geo_spread'] ?? 1 ) ),
				'cross_border'   => ! empty( $row['cross_border'] ) ? 1 : 0,
				'status'         => in_array( (string) ( $row['status'] ?? 'open' ), array( 'open', 'investigating', 'mitigated', 'closed' ), true ) ? (string) $row['status'] : 'open',
				'summary'        => wp_kses_post( (string) ( $row['summary'] ?? '' ) ),
				'root_cause'     => wp_kses_post( (string) ( $row['root_cause'] ?? '' ) ),
				'mitigation'     => wp_kses_post( (string) ( $row['mitigation'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function mark_sent( int $id, string $stage ) : void {
		if ( ! in_array( $stage, array( 'early_warning', 'followup' ), true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( $stage . '_sent_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
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

	public static function get( int $id ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? (array) $row : null;
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_significant() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE significant = 1" );
	}

	/**
	 * Significant incidents > 24h with no early-warning sent (Art. 15(1) breach risk),
	 * and significant incidents > 30 days with no follow-up sent.
	 */
	public static function overdue( string $stage ) : array {
		$cutoff = array(
			'early_warning' => 24 * 3600,
			'followup'      => 30 * 24 * 3600,
		);
		if ( ! isset( $cutoff[ $stage ] ) ) {
			return array();
		}
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE significant = 1 AND detected_at IS NOT NULL AND {$stage}_sent_at IS NULL AND TIMESTAMPDIFF(SECOND, detected_at, %s) > %d ORDER BY detected_at ASC",
				current_time( 'mysql' ),
				(int) $cutoff[ $stage ]
			),
			ARRAY_A
		);
	}

	private static function dt( $v ) : ?string {
		if ( null === $v || '' === $v ) {
			return null;
		}
		$v = str_replace( 'T', ' ', (string) $v );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v ) ) {
			return strlen( $v ) === 16 ? $v . ':00' : $v;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			return $v . ' 00:00:00';
		}
		return null;
	}
}
