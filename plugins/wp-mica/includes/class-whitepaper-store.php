<?php
/**
 * White-paper register (Art. 6 / 19 / 51).
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WhitepaperStore {

	private const DB_VERSION_OPTION = 'eurocomply_mica_whitepapers_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_mica_whitepapers';
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
			asset_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			version VARCHAR(24) NOT NULL DEFAULT '1.0',
			article VARCHAR(8) NOT NULL DEFAULT '6',
			notified_at DATETIME NULL DEFAULT NULL,
			published_at DATETIME NULL DEFAULT NULL,
			expires_at DATETIME NULL DEFAULT NULL,
			document_url VARCHAR(255) NOT NULL DEFAULT '',
			summary LONGTEXT NULL,
			risks LONGTEXT NULL,
			rights LONGTEXT NULL,
			status VARCHAR(24) NOT NULL DEFAULT 'draft',
			PRIMARY KEY  (id),
			KEY asset_id (asset_id),
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
				'created_at'   => current_time( 'mysql' ),
				'asset_id'     => (int) ( $row['asset_id'] ?? 0 ),
				'version'      => sanitize_text_field( (string) ( $row['version'] ?? '1.0' ) ),
				'article'      => in_array( (string) ( $row['article'] ?? '6' ), array( '6', '17', '19', '51' ), true ) ? (string) $row['article'] : '6',
				'notified_at'  => self::dt( $row['notified_at']  ?? null ),
				'published_at' => self::dt( $row['published_at'] ?? null ),
				'expires_at'   => self::dt( $row['expires_at']   ?? null ),
				'document_url' => esc_url_raw( (string) ( $row['document_url'] ?? '' ) ),
				'summary'      => wp_kses_post( (string) ( $row['summary'] ?? '' ) ),
				'risks'        => wp_kses_post( (string) ( $row['risks'] ?? '' ) ),
				'rights'       => wp_kses_post( (string) ( $row['rights'] ?? '' ) ),
				'status'       => in_array( (string) ( $row['status'] ?? 'draft' ), array( 'draft', 'notified', 'standstill', 'published', 'withdrawn' ), true ) ? (string) $row['status'] : 'draft',
			)
		);
		return (int) $wpdb->insert_id;
	}

	public static function mark_published( int $id ) : void {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'published_at' => current_time( 'mysql' ), 'status' => 'published' ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
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

	public static function count_status( string $status ) : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
	}

	/**
	 * Notified white papers whose 12-day standstill (Art. 8(1)) has elapsed and that have NOT been published yet.
	 */
	public static function standstill_elapsed( int $standstill_days ) : array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('notified','standstill') AND notified_at IS NOT NULL AND published_at IS NULL AND TIMESTAMPDIFF(DAY, notified_at, %s) >= %d ORDER BY notified_at ASC",
				current_time( 'mysql' ),
				max( 1, $standstill_days )
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
