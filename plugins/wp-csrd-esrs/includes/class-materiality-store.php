<?php
/**
 * Double-materiality assessment register.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MaterialityStore {

	private const DB_VERSION_OPTION = 'eurocomply_csrd_materiality_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_csrd_materiality';
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
			topic VARCHAR(16) NOT NULL DEFAULT '',
			subtopic VARCHAR(120) NOT NULL DEFAULT '',
			impact_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			financial_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
			impact_material TINYINT UNSIGNED NOT NULL DEFAULT 0,
			financial_material TINYINT UNSIGNED NOT NULL DEFAULT 0,
			horizon VARCHAR(16) NOT NULL DEFAULT 'short',
			value_chain VARCHAR(32) NOT NULL DEFAULT 'own',
			rationale LONGTEXT NULL,
			year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY topic (topic),
			KEY year (year)
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
		$impact    = max( 0, min( 5, (int) ( $row['impact_score']     ?? 0 ) ) );
		$financial = max( 0, min( 5, (int) ( $row['financial_score']  ?? 0 ) ) );
		$threshold = (int) ( $row['threshold'] ?? 3 );

		$wpdb->insert(
			self::table_name(),
			array(
				'created_at'         => current_time( 'mysql' ),
				'topic'              => sanitize_key( (string) ( $row['topic'] ?? '' ) ),
				'subtopic'           => sanitize_text_field( (string) ( $row['subtopic'] ?? '' ) ),
				'impact_score'       => $impact,
				'financial_score'    => $financial,
				'impact_material'    => $impact >= $threshold ? 1 : 0,
				'financial_material' => $financial >= $threshold ? 1 : 0,
				'horizon'            => in_array( (string) ( $row['horizon'] ?? '' ), array( 'short', 'medium', 'long' ), true ) ? (string) $row['horizon'] : 'short',
				'value_chain'        => in_array( (string) ( $row['value_chain'] ?? '' ), array( 'own', 'upstream', 'downstream', 'all' ), true ) ? (string) $row['value_chain'] : 'own',
				'rationale'          => wp_kses_post( (string) ( $row['rationale'] ?? '' ) ),
				'year'               => (int) ( $row['year'] ?? gmdate( 'Y' ) ),
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

	public static function for_year( int $year ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE year = %d ORDER BY topic ASC, subtopic ASC", $year ), ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_material_for_year( int $year ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE year = %d AND ( impact_material = 1 OR financial_material = 1 )", $year ) );
	}
}
