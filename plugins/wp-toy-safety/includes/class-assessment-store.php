<?php
/**
 * Conformity-assessment register.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssessmentStore {

	private const DB_VERSION_OPTION = 'eurocomply_toy_assessments_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_toy_assessments';
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
			module VARCHAR(8) NOT NULL DEFAULT 'A',
			notified_body VARCHAR(255) NOT NULL DEFAULT '',
			notified_body_id VARCHAR(8) NOT NULL DEFAULT '',
			certificate_no VARCHAR(64) NOT NULL DEFAULT '',
			issued_at DATETIME NULL DEFAULT NULL,
			valid_until DATETIME NULL DEFAULT NULL,
			standards LONGTEXT NULL,
			report_url VARCHAR(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY toy_id (toy_id),
			KEY module (module)
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
				'toy_id'           => (int) ( $row['toy_id'] ?? 0 ),
				'module'           => array_key_exists( (string) ( $row['module'] ?? '' ), Settings::modules() ) ? (string) $row['module'] : 'A',
				'notified_body'    => sanitize_text_field( (string) ( $row['notified_body'] ?? '' ) ),
				'notified_body_id' => preg_replace( '/[^0-9]/', '', (string) ( $row['notified_body_id'] ?? '' ) ),
				'certificate_no'   => sanitize_text_field( (string) ( $row['certificate_no'] ?? '' ) ),
				'issued_at'        => self::dt( $row['issued_at']   ?? null ),
				'valid_until'      => self::dt( $row['valid_until'] ?? null ),
				'standards'        => wp_kses_post( (string) ( $row['standards'] ?? '' ) ),
				'report_url'       => esc_url_raw( (string) ( $row['report_url'] ?? '' ) ),
			)
		);
		return (int) $wpdb->insert_id;
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

	public static function count_total() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_expired() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE valid_until IS NOT NULL AND valid_until < %s", current_time( 'mysql' ) ) );
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
