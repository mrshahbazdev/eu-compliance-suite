<?php
/**
 * Marketing-communications register (Art. 7).
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CommunicationStore {

	private const DB_VERSION_OPTION = 'eurocomply_mica_comms_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_mica_comms';
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
			channel VARCHAR(32) NOT NULL DEFAULT 'website',
			audience VARCHAR(48) NOT NULL DEFAULT 'general',
			country CHAR(2) NOT NULL DEFAULT '',
			language VARCHAR(8) NOT NULL DEFAULT '',
			published_at DATETIME NULL DEFAULT NULL,
			withdrawn_at DATETIME NULL DEFAULT NULL,
			risk_warning TINYINT(1) NOT NULL DEFAULT 0,
			fair_clear TINYINT(1) NOT NULL DEFAULT 0,
			content_url VARCHAR(255) NOT NULL DEFAULT '',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY asset_id (asset_id),
			KEY channel (channel)
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
				'channel'      => in_array( (string) ( $row['channel'] ?? 'website' ), array( 'website', 'social', 'email', 'press', 'paid_ads', 'influencer', 'event', 'tv_radio', 'other' ), true ) ? (string) $row['channel'] : 'website',
				'audience'     => sanitize_key( (string) ( $row['audience'] ?? 'general' ) ),
				'country'      => strtoupper( substr( (string) ( $row['country'] ?? '' ), 0, 2 ) ),
				'language'     => sanitize_text_field( (string) ( $row['language'] ?? '' ) ),
				'published_at' => self::dt( $row['published_at'] ?? null ),
				'withdrawn_at' => self::dt( $row['withdrawn_at'] ?? null ),
				'risk_warning' => ! empty( $row['risk_warning'] ) ? 1 : 0,
				'fair_clear'   => ! empty( $row['fair_clear'] )   ? 1 : 0,
				'content_url'  => esc_url_raw( (string) ( $row['content_url'] ?? '' ) ),
				'notes'        => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
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

	public static function count_unflagged() : int {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE (risk_warning = 0 OR fair_clear = 0) AND published_at IS NOT NULL AND withdrawn_at IS NULL" );
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
