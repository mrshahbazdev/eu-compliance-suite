<?php
/**
 * Pseudonymised employee pay records — used to compute the gender pay gap
 * for Art. 9 reports.
 *
 * Privacy posture: only an HMAC-keyed external_ref is retained for upserts.
 * No name, no email, no national-id is stored. Only category, gender (w/m/x/u),
 * total comp, hours per week.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmployeeStore {

	private const DB_VERSION_OPTION = 'eurocomply_pt_employees_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_pt_employees';
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
			external_ref_hash CHAR(64) NOT NULL DEFAULT '',
			category_slug VARCHAR(64) NOT NULL DEFAULT '',
			gender CHAR(1) NOT NULL DEFAULT 'u',
			total_comp DECIMAL(12,2) NOT NULL DEFAULT 0,
			hours_per_week DECIMAL(5,2) NOT NULL DEFAULT 40,
			currency CHAR(3) NOT NULL DEFAULT 'EUR',
			year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_ref_year (external_ref_hash, year),
			KEY category_slug (category_slug),
			KEY year (year),
			KEY gender (gender)
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

	public static function hash_ref( string $ref ) : string {
		return hash_hmac( 'sha256', $ref, wp_salt( 'auth' ) );
	}

	/**
	 * @param array<string,mixed> $row
	 */
	public static function upsert( array $row ) : int {
		global $wpdb;
		$ref = (string) ( $row['external_ref'] ?? '' );
		if ( '' === $ref ) {
			return 0;
		}
		$gender = strtolower( (string) ( $row['gender'] ?? 'u' ) );
		if ( ! in_array( $gender, array( 'w', 'm', 'x', 'u' ), true ) ) {
			$gender = 'u';
		}
		$year   = (int) ( $row['year'] ?? Settings::get()['reporting_year'] );
		$hash   = self::hash_ref( $ref );
		$exists = self::find_by_ref_year( $hash, $year );

		$payload = array(
			'created_at'        => current_time( 'mysql' ),
			'external_ref_hash' => $hash,
			'category_slug'     => sanitize_key( (string) ( $row['category_slug'] ?? '' ) ),
			'gender'            => $gender,
			'total_comp'        => max( 0.0, (float) ( $row['total_comp'] ?? 0 ) ),
			'hours_per_week'    => max( 0.0, min( 168.0, (float) ( $row['hours_per_week'] ?? 40 ) ) ),
			'currency'          => strtoupper( (string) ( $row['currency'] ?? Settings::get()['currency'] ) ),
			'year'              => $year > 2020 ? $year : (int) Settings::get()['reporting_year'],
		);

		if ( $exists ) {
			$wpdb->update( self::table_name(), $payload, array( 'id' => (int) $exists['id'] ) );
			return (int) $exists['id'];
		}
		$wpdb->insert( self::table_name(), $payload );
		return (int) $wpdb->insert_id;
	}

	public static function find_by_ref_year( string $hash, int $year ) : ?array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE external_ref_hash = %s AND year = %d", $hash, $year ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_year( int $year, int $limit = 5000 ) : array {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 50000, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE year = %d ORDER BY category_slug ASC LIMIT %d", $year, $limit ), ARRAY_A );
	}

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_for_year( int $year ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE year = %d", $year ) );
	}

	public static function purge_year( int $year ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE year = %d", $year ) );
	}
}
