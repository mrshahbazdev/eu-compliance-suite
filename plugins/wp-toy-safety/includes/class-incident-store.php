<?php
/**
 * Safety Gate (RAPEX) incident register.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IncidentStore {

	private const DB_VERSION_OPTION = 'eurocomply_toy_incidents_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_toy_incidents';
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
			toy_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			occurred_at DATETIME NULL DEFAULT NULL,
			detected_at DATETIME NULL DEFAULT NULL,
			hazard VARCHAR(48) NOT NULL DEFAULT 'choking',
			severity VARCHAR(16) NOT NULL DEFAULT 'serious',
			country CHAR(2) NOT NULL DEFAULT '',
			injuries INT UNSIGNED NOT NULL DEFAULT 0,
			fatalities INT UNSIGNED NOT NULL DEFAULT 0,
			summary LONGTEXT NULL,
			corrective_action LONGTEXT NULL,
			notified_at DATETIME NULL DEFAULT NULL,
			followup_at DATETIME NULL DEFAULT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'open',
			PRIMARY KEY  (id),
			KEY toy_id (toy_id),
			KEY hazard (hazard),
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
				'created_at'        => current_time( 'mysql' ),
				'toy_id'            => (int) ( $row['toy_id'] ?? 0 ),
				'occurred_at'       => self::dt( $row['occurred_at'] ?? null ),
				'detected_at'       => self::dt( $row['detected_at'] ?? null ),
				'hazard'            => array_key_exists( (string) ( $row['hazard'] ?? '' ), self::hazards() ) ? (string) $row['hazard'] : 'choking',
				'severity'          => in_array( (string) ( $row['severity'] ?? 'serious' ), array( 'low', 'medium', 'serious', 'fatal' ), true ) ? (string) $row['severity'] : 'serious',
				'country'           => strtoupper( substr( (string) ( $row['country'] ?? '' ), 0, 2 ) ),
				'injuries'          => max( 0, (int) ( $row['injuries'] ?? 0 ) ),
				'fatalities'        => max( 0, (int) ( $row['fatalities'] ?? 0 ) ),
				'summary'           => wp_kses_post( (string) ( $row['summary'] ?? '' ) ),
				'corrective_action' => wp_kses_post( (string) ( $row['corrective_action'] ?? '' ) ),
				'notified_at'       => self::dt( $row['notified_at'] ?? null ),
				'followup_at'       => self::dt( $row['followup_at'] ?? null ),
				'status'            => in_array( (string) ( $row['status'] ?? 'open' ), array( 'open', 'investigating', 'resolved', 'recall' ), true ) ? (string) $row['status'] : 'open',
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function mark_step( int $id, string $step ) : void {
		if ( ! in_array( $step, array( 'notified', 'followup' ), true ) ) {
			return;
		}
		global $wpdb;
		$col = $step . '_at';
		$wpdb->update( self::table_name(), array( $col => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	public static function delete( int $id ) : void {
		global $wpdb;
		$wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
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

	public static function count_open() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('open','investigating')" );
	}

	public static function notify_overdue( int $hours ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE detected_at IS NOT NULL AND notified_at IS NULL AND TIMESTAMPDIFF(SECOND, detected_at, %s) > %d",
				current_time( 'mysql' ),
				max( 1, $hours ) * 3600
			)
		);
	}

	public static function followup_overdue( int $days ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE detected_at IS NOT NULL AND followup_at IS NULL AND status IN ('open','investigating') AND TIMESTAMPDIFF(SECOND, detected_at, %s) > %d",
				current_time( 'mysql' ),
				max( 1, $days ) * 86400
			)
		);
	}

	/** @return array<string,string> */
	public static function hazards() : array {
		return array(
			'choking'         => __( 'Choking / small parts',         'eurocomply-toy-safety' ),
			'strangulation'   => __( 'Strangulation / cord',           'eurocomply-toy-safety' ),
			'suffocation'     => __( 'Suffocation',                       'eurocomply-toy-safety' ),
			'sharp_edge'      => __( 'Sharp edge / point',                 'eurocomply-toy-safety' ),
			'projectile'      => __( 'Projectile',                          'eurocomply-toy-safety' ),
			'magnet'          => __( 'High-flux magnet ingestion',           'eurocomply-toy-safety' ),
			'battery'         => __( 'Button-cell battery accessibility',     'eurocomply-toy-safety' ),
			'chemical'        => __( 'Chemical exposure',                       'eurocomply-toy-safety' ),
			'electrical'      => __( 'Electrical hazard',                         'eurocomply-toy-safety' ),
			'thermal'         => __( 'Thermal / burn',                              'eurocomply-toy-safety' ),
			'noise'           => __( 'Excessive noise',                                'eurocomply-toy-safety' ),
			'flammability'    => __( 'Flammability',                                     'eurocomply-toy-safety' ),
			'mechanical'      => __( 'Mechanical / pinch',                                 'eurocomply-toy-safety' ),
			'radiation'       => __( 'Radiation / laser',                                    'eurocomply-toy-safety' ),
			'allergen'        => __( 'Allergen',                                                'eurocomply-toy-safety' ),
			'other'           => __( 'Other',                                                     'eurocomply-toy-safety' ),
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
