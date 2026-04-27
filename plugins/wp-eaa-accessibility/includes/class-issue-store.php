<?php
/**
 * Persistent store for accessibility issues.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IssueStore {

	public const TABLE = 'eurocomply_eaa_issues';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function install_table() : void {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scanned_at DATETIME NOT NULL,
			object_type VARCHAR(20) NOT NULL DEFAULT 'post',
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			url TEXT NOT NULL,
			rule VARCHAR(64) NOT NULL,
			wcag VARCHAR(16) NOT NULL,
			severity VARCHAR(16) NOT NULL,
			snippet TEXT NOT NULL,
			PRIMARY KEY  (id),
			KEY object_idx (object_type, object_id),
			KEY rule_idx (rule),
			KEY severity_idx (severity)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table() : void {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Replace all issues for a given object.
	 *
	 * @param string                $object_type post|url
	 * @param int                   $object_id
	 * @param string                $url
	 * @param array<int,array<string,string>> $issues
	 */
	public static function replace_for_object( string $object_type, int $object_id, string $url, array $issues ) : void {
		global $wpdb;
		$table = self::table_name();

		$wpdb->delete(
			$table,
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
			),
			array( '%s', '%d' )
		);

		if ( empty( $issues ) ) {
			return;
		}

		$now = current_time( 'mysql' );
		foreach ( $issues as $issue ) {
			$wpdb->insert(
				$table,
				array(
					'scanned_at'  => $now,
					'object_type' => $object_type,
					'object_id'   => $object_id,
					'url'         => $url,
					'rule'        => (string) ( $issue['rule'] ?? '' ),
					'wcag'        => (string) ( $issue['wcag'] ?? '' ),
					'severity'    => (string) ( $issue['severity'] ?? 'moderate' ),
					'snippet'     => (string) ( $issue['snippet'] ?? '' ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	public static function clear_all() : void {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return array<string,int>
	 */
	public static function counts_by_severity() : array {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT severity, COUNT(*) AS n FROM {$table} GROUP BY severity", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out   = array( 'serious' => 0, 'moderate' => 0, 'minor' => 0 );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$sev = (string) ( $r['severity'] ?? '' );
				if ( isset( $out[ $sev ] ) ) {
					$out[ $sev ] = (int) $r['n'];
				}
			}
		}
		return $out;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function counts_by_rule() : array {
		global $wpdb;
		$table = self::table_name();
		$rows  = $wpdb->get_results( "SELECT rule, wcag, severity, COUNT(*) AS n FROM {$table} GROUP BY rule, wcag, severity ORDER BY n DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent_issues( int $limit = 200 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 5000, $limit ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	public static function total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
