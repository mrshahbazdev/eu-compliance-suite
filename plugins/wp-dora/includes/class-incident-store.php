<?php
/**
 * Incident register.
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IncidentStore {

	private const DB_VERSION_OPTION = 'eurocomply_dora_incidents_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_dora_incidents';
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
			occurred_at DATETIME NULL DEFAULT NULL,
			detected_at DATETIME NULL DEFAULT NULL,
			classified_at DATETIME NULL DEFAULT NULL,
			classification VARCHAR(16) NOT NULL DEFAULT 'none',
			category VARCHAR(48) NOT NULL DEFAULT '',
			severity VARCHAR(16) NOT NULL DEFAULT 'low',
			clients_affected INT(11) NOT NULL DEFAULT 0,
			data_loss TINYINT(1) NOT NULL DEFAULT 0,
			duration_min INT(11) NOT NULL DEFAULT 0,
			geo_spread INT(11) NOT NULL DEFAULT 1,
			financial_impact DECIMAL(14,2) NOT NULL DEFAULT 0,
			reputational TINYINT(1) NOT NULL DEFAULT 0,
			critical_service TINYINT(1) NOT NULL DEFAULT 0,
			status VARCHAR(24) NOT NULL DEFAULT 'open',
			initial_sent_at DATETIME NULL DEFAULT NULL,
			intermediate_sent_at DATETIME NULL DEFAULT NULL,
			final_sent_at DATETIME NULL DEFAULT NULL,
			summary LONGTEXT NULL,
			root_cause LONGTEXT NULL,
			mitigation LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY classification (classification),
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
		$now = current_time( 'mysql' );
		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'           => $now,
				'occurred_at'          => self::dt( $row['occurred_at'] ?? null ),
				'detected_at'          => self::dt( $row['detected_at'] ?? null ),
				'classified_at'        => self::dt( $row['classified_at'] ?? $now ),
				'classification'       => self::clamp_class( (string) ( $row['classification'] ?? 'none' ) ),
				'category'             => sanitize_key( (string) ( $row['category'] ?? 'other' ) ),
				'severity'             => in_array( (string) ( $row['severity'] ?? 'low' ), array( 'low', 'medium', 'high', 'critical' ), true ) ? (string) $row['severity'] : 'low',
				'clients_affected'     => (int) ( $row['clients_affected']  ?? 0 ),
				'data_loss'            => ! empty( $row['data_loss'] )       ? 1 : 0,
				'duration_min'         => (int) ( $row['duration_min']      ?? 0 ),
				'geo_spread'           => max( 1, (int) ( $row['geo_spread'] ?? 1 ) ),
				'financial_impact'     => (float) ( $row['financial_impact'] ?? 0 ),
				'reputational'         => ! empty( $row['reputational'] )    ? 1 : 0,
				'critical_service'     => ! empty( $row['critical_service'] ) ? 1 : 0,
				'status'               => in_array( (string) ( $row['status'] ?? 'open' ), array( 'open', 'investigating', 'mitigated', 'closed' ), true ) ? (string) $row['status'] : 'open',
				'summary'              => wp_kses_post( (string) ( $row['summary']    ?? '' ) ),
				'root_cause'           => wp_kses_post( (string) ( $row['root_cause'] ?? '' ) ),
				'mitigation'           => wp_kses_post( (string) ( $row['mitigation'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function mark_sent( int $id, string $stage ) : void {
		if ( ! in_array( $stage, array( 'initial', 'intermediate', 'final' ), true ) ) {
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

	public static function count_class( string $class ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE classification = %s", $class ) );
	}

	/**
	 * Major incidents whose deadline at $stage is overdue (deadline < now AND
	 * stage not yet sent).
	 */
	public static function overdue( string $stage ) : array {
		if ( ! in_array( $stage, array( 'initial', 'intermediate', 'final' ), true ) ) {
			return array();
		}
		$cutoff = array(
			'initial'      => 4 * 3600,
			'intermediate' => 72 * 3600,
			'final'        => 30 * 24 * 3600,
		);
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE classification = 'major' AND classified_at IS NOT NULL AND {$stage}_sent_at IS NULL AND TIMESTAMPDIFF(SECOND, classified_at, %s) > %d ORDER BY classified_at ASC",
				current_time( 'mysql' ),
				(int) $cutoff[ $stage ]
			),
			ARRAY_A
		);
	}

	private static function clamp_class( string $c ) : string {
		return in_array( $c, array( 'none', 'significant', 'major' ), true ) ? $c : 'none';
	}

	private static function dt( $v ) : ?string {
		if ( null === $v || '' === $v ) {
			return null;
		}
		$v = (string) $v;
		// Accept 'Y-m-d H:i:s' or 'Y-m-d\TH:i' from datetime-local.
		$v = str_replace( 'T', ' ', $v );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v ) ) {
			return strlen( $v ) === 16 ? $v . ':00' : $v;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			return $v . ' 00:00:00';
		}
		return null;
	}
}
