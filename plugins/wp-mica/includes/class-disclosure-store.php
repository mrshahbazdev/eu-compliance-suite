<?php
/**
 * Insider information / market-abuse disclosure log (Art. 87–88).
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DisclosureStore {

	private const DB_VERSION_OPTION = 'eurocomply_mica_disclosures_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_mica_disclosures';
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
			kind VARCHAR(24) NOT NULL DEFAULT 'insider',
			occurred_at DATETIME NULL DEFAULT NULL,
			disclosed_at DATETIME NULL DEFAULT NULL,
			delayed_until DATETIME NULL DEFAULT NULL,
			summary LONGTEXT NULL,
			justification LONGTEXT NULL,
			channel VARCHAR(48) NOT NULL DEFAULT 'website',
			notified_nca TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY asset_id (asset_id),
			KEY kind (kind)
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
				'created_at'    => current_time( 'mysql' ),
				'asset_id'      => (int) ( $row['asset_id'] ?? 0 ),
				'kind'          => in_array( (string) ( $row['kind'] ?? 'insider' ), array( 'insider', 'market_abuse', 'suspicious_order', 'self_dealing', 'other' ), true ) ? (string) $row['kind'] : 'insider',
				'occurred_at'   => self::dt( $row['occurred_at']   ?? null ),
				'disclosed_at'  => self::dt( $row['disclosed_at']  ?? null ),
				'delayed_until' => self::dt( $row['delayed_until'] ?? null ),
				'summary'       => wp_kses_post( (string) ( $row['summary'] ?? '' ) ),
				'justification' => wp_kses_post( (string) ( $row['justification'] ?? '' ) ),
				'channel'       => sanitize_text_field( (string) ( $row['channel'] ?? 'website' ) ),
				'notified_nca'  => ! empty( $row['notified_nca'] ) ? 1 : 0,
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

	public static function count_pending() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE disclosed_at IS NULL" );
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
