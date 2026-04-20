<?php
/**
 * Consent log storage (GDPR Art. 7(1) proof of consent).
 *
 * @package EuroComply\CookieConsent
 */

declare( strict_types = 1 );

namespace EuroComply\CookieConsent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists an append-only log of consent choices for audit purposes.
 */
final class ConsentLog {

	public const TABLE_SUFFIX = 'eurocomply_cc_log';

	/**
	 * Full table name.
	 */
	public static function table() : string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create the log table (called on activation).
	 */
	public static function install() : void {
		global $wpdb;
		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consent_id VARCHAR(64) NOT NULL,
			consent_version VARCHAR(16) NOT NULL DEFAULT '1',
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			ua_hash CHAR(64) NOT NULL DEFAULT '',
			language VARCHAR(8) NOT NULL DEFAULT '',
			region VARCHAR(8) NOT NULL DEFAULT '',
			state LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY consent_id (consent_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Hash an IP address with the site salt so the log is pseudonymised.
	 */
	public static function hash_ip( string $ip ) : string {
		if ( '' === $ip ) {
			return '';
		}
		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}

	/**
	 * Hash a user agent with the site salt.
	 */
	public static function hash_ua( string $ua ) : string {
		if ( '' === $ua ) {
			return '';
		}
		return hash( 'sha256', $ua . wp_salt( 'auth' ) );
	}

	/**
	 * Insert a log row. Accepts already-sanitised data.
	 *
	 * @param array<string,mixed> $row Row payload.
	 */
	public static function insert( array $row ) : int {
		global $wpdb;
		$defaults = array(
			'consent_id'      => '',
			'consent_version' => '1',
			'ip_hash'         => '',
			'ua_hash'         => '',
			'language'        => '',
			'region'          => '',
			'state'           => '{}',
			'created_at'      => current_time( 'mysql', true ),
		);
		$row      = array_replace( $defaults, $row );

		$ok = $wpdb->insert(
			self::table(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Count rows.
	 */
	public static function count() : int {
		global $wpdb;
		$table = self::table();
		// Table name is built from $wpdb->prefix so it is safe.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Fetch recent rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50, int $offset = 0 ) : array {
		global $wpdb;
		$table  = self::table();
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		$sql    = $wpdb->prepare(
			"SELECT id, consent_id, consent_version, language, region, state, created_at FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$limit,
			$offset
		);
		$rows   = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Truncate the log (used by Pro export / manual purge).
	 */
	public static function truncate() : void {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
