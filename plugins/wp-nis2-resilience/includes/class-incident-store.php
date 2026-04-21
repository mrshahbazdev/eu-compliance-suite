<?php
/**
 * Incident register.
 *
 * Table: wp_eurocomply_nis2_incidents
 *
 * Art. 23 NIS2 deadline semantics:
 *   aware_at           = moment the entity became aware of a significant incident
 *   early_warning_at   = 24h from aware_at
 *   notification_at    = 72h from aware_at (incident notification)
 *   intermediate_at    = 30 days from aware_at (Art. 23(4)(c))
 *   final_at           = 30 days from incident-handled date (Art. 23(4)(d))
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IncidentStore {

	public const DB_VERSION_OPTION = 'eurocomply_nis2_incidents_db_version';
	public const DB_VERSION        = '1.0.0';

	public const CATEGORIES = array(
		'ransomware',
		'ddos',
		'phishing',
		'breach',
		'malware',
		'unauth_access',
		'data_leak',
		'supply_chain',
		'physical',
		'other',
	);

	public const SEVERITIES = array( 'low', 'medium', 'high', 'critical' );

	public const STATUSES = array(
		'draft',
		'aware',
		'early_warning_sent',
		'notification_sent',
		'intermediate_sent',
		'resolved',
		'final_sent',
		'closed',
	);

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_nis2_incidents';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			aware_at DATETIME NULL DEFAULT NULL,
			resolved_at DATETIME NULL DEFAULT NULL,
			early_warning_sent_at DATETIME NULL DEFAULT NULL,
			notification_sent_at DATETIME NULL DEFAULT NULL,
			intermediate_sent_at DATETIME NULL DEFAULT NULL,
			final_sent_at DATETIME NULL DEFAULT NULL,
			closed_at DATETIME NULL DEFAULT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			category VARCHAR(32) NOT NULL DEFAULT 'other',
			severity VARCHAR(16) NOT NULL DEFAULT 'medium',
			status VARCHAR(32) NOT NULL DEFAULT 'draft',
			impact_summary LONGTEXT NULL,
			affected_systems LONGTEXT NULL,
			affected_users_estimate INT UNSIGNED NOT NULL DEFAULT 0,
			root_cause LONGTEXT NULL,
			mitigation LONGTEXT NULL,
			csirt_case_ref VARCHAR(64) NOT NULL DEFAULT '',
			reporter_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status),
			KEY severity (severity),
			KEY aware_at (aware_at)
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
		$now = current_time( 'mysql' );
		$row = array(
			'created_at'              => $now,
			'updated_at'              => $now,
			'aware_at'                => isset( $data['aware_at'] ) ? (string) $data['aware_at'] : null,
			'title'                   => isset( $data['title'] ) ? substr( (string) $data['title'], 0, 255 ) : '',
			'category'                => isset( $data['category'] ) && in_array( (string) $data['category'], self::CATEGORIES, true ) ? (string) $data['category'] : 'other',
			'severity'                => isset( $data['severity'] ) && in_array( (string) $data['severity'], self::SEVERITIES, true ) ? (string) $data['severity'] : 'medium',
			'status'                  => isset( $data['status'] ) && in_array( (string) $data['status'], self::STATUSES, true ) ? (string) $data['status'] : 'draft',
			'impact_summary'          => isset( $data['impact_summary'] ) ? (string) $data['impact_summary'] : '',
			'affected_systems'        => isset( $data['affected_systems'] ) ? (string) $data['affected_systems'] : '',
			'affected_users_estimate' => isset( $data['affected_users_estimate'] ) ? max( 0, (int) $data['affected_users_estimate'] ) : 0,
			'root_cause'              => isset( $data['root_cause'] ) ? (string) $data['root_cause'] : '',
			'mitigation'              => isset( $data['mitigation'] ) ? (string) $data['mitigation'] : '',
			'csirt_case_ref'          => isset( $data['csirt_case_ref'] ) ? substr( (string) $data['csirt_case_ref'], 0, 64 ) : '',
			'reporter_user_id'        => isset( $data['reporter_user_id'] ) ? (int) $data['reporter_user_id'] : (int) get_current_user_id(),
			'notes'                   => isset( $data['notes'] ) ? (string) $data['notes'] : '',
		);
		$ok = $wpdb->insert( self::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function update( int $id, array $data ) : bool {
		global $wpdb;
		if ( $id <= 0 || empty( $data ) ) {
			return false;
		}
		$allowed = array(
			'aware_at',
			'resolved_at',
			'early_warning_sent_at',
			'notification_sent_at',
			'intermediate_sent_at',
			'final_sent_at',
			'closed_at',
			'title',
			'category',
			'severity',
			'status',
			'impact_summary',
			'affected_systems',
			'affected_users_estimate',
			'root_cause',
			'mitigation',
			'csirt_case_ref',
			'notes',
		);
		$row = array( 'updated_at' => current_time( 'mysql' ) );
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$row[ $key ] = $data[ $key ];
			}
		}
		$result = $wpdb->update( self::table_name(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $result;
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
	public static function recent( int $limit = 100, string $status = '' ) : array {
		global $wpdb;
		$limit = max( 1, min( 5000, $limit ) );
		$table = self::table_name();
		if ( '' !== $status ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d", $status, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string,int>
	 */
	public static function status_counts() : array {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row['status'] ] = (int) $row['total'];
			}
		}
		return $out;
	}

	/**
	 * Compute the Art. 23 deadlines for a given incident row.
	 *
	 * @param array<string,mixed> $incident
	 *
	 * @return array{early_warning:string,notification:string,intermediate:string,final:string,overdue:array<int,string>}
	 */
	public static function deadlines( array $incident ) : array {
		$aware = ! empty( $incident['aware_at'] ) ? strtotime( (string) $incident['aware_at'] ) : false;
		$resolved = ! empty( $incident['resolved_at'] ) ? strtotime( (string) $incident['resolved_at'] ) : false;
		$out = array(
			'early_warning' => '',
			'notification'  => '',
			'intermediate'  => '',
			'final'         => '',
			'overdue'       => array(),
		);
		if ( $aware ) {
			$out['early_warning'] = gmdate( 'Y-m-d H:i:s', $aware + 24 * HOUR_IN_SECONDS );
			$out['notification']  = gmdate( 'Y-m-d H:i:s', $aware + 72 * HOUR_IN_SECONDS );
			$out['intermediate']  = gmdate( 'Y-m-d H:i:s', $aware + 30 * DAY_IN_SECONDS );
		}
		if ( $resolved ) {
			$out['final'] = gmdate( 'Y-m-d H:i:s', $resolved + 30 * DAY_IN_SECONDS );
		}
		$now = time();
		$map = array(
			'early_warning' => 'early_warning_sent_at',
			'notification'  => 'notification_sent_at',
			'intermediate'  => 'intermediate_sent_at',
			'final'         => 'final_sent_at',
		);
		foreach ( $map as $stage => $sent_col ) {
			$deadline = $out[ $stage ];
			if ( '' !== $deadline && strtotime( $deadline ) < $now && empty( $incident[ $sent_col ] ) ) {
				$out['overdue'][] = $stage;
			}
		}
		return $out;
	}

	public static function count_overdue() : int {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE status NOT IN ('closed') AND aware_at IS NOT NULL", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $rows ) ) {
			return 0;
		}
		$overdue = 0;
		foreach ( $rows as $row ) {
			$d = self::deadlines( $row );
			if ( ! empty( $d['overdue'] ) ) {
				$overdue++;
			}
		}
		return $overdue;
	}
}
