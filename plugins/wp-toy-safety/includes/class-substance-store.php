<?php
/**
 * Restricted-substance register.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SubstanceStore {

	private const DB_VERSION_OPTION = 'eurocomply_toy_substances_db_version';
	private const DB_VERSION        = '0.1.0';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_toy_substances';
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
			name VARCHAR(255) NOT NULL DEFAULT '',
			cas VARCHAR(48) NOT NULL DEFAULT '',
			ec_number VARCHAR(32) NOT NULL DEFAULT '',
			classification VARCHAR(48) NOT NULL DEFAULT 'cmr',
			limit_value VARCHAR(48) NOT NULL DEFAULT '',
			measured_value VARCHAR(48) NOT NULL DEFAULT '',
			pass_fail VARCHAR(8) NOT NULL DEFAULT 'pass',
			notes LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY toy_id (toy_id),
			KEY classification (classification),
			KEY pass_fail (pass_fail)
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
				'created_at'     => current_time( 'mysql' ),
				'toy_id'         => (int) ( $row['toy_id'] ?? 0 ),
				'name'           => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'cas'            => sanitize_text_field( (string) ( $row['cas'] ?? '' ) ),
				'ec_number'      => sanitize_text_field( (string) ( $row['ec_number'] ?? '' ) ),
				'classification' => in_array( (string) ( $row['classification'] ?? 'cmr' ), array_keys( self::classifications() ), true ) ? (string) $row['classification'] : 'cmr',
				'limit_value'    => sanitize_text_field( (string) ( $row['limit_value'] ?? '' ) ),
				'measured_value' => sanitize_text_field( (string) ( $row['measured_value'] ?? '' ) ),
				'pass_fail'      => in_array( (string) ( $row['pass_fail'] ?? 'pass' ), array( 'pass', 'fail', 'na' ), true ) ? (string) $row['pass_fail'] : 'pass',
				'notes'          => wp_kses_post( (string) ( $row['notes'] ?? '' ) ),
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

	public static function count_failures() : int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE pass_fail = 'fail'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** @return array<string,string> */
	public static function classifications() : array {
		return array(
			'cmr'              => __( 'CMR (Annex II Part III)',             'eurocomply-toy-safety' ),
			'endocrine'        => __( 'Endocrine disruptor (ED)',             'eurocomply-toy-safety' ),
			'pfas'             => __( 'PFAS / per- and polyfluoroalkyl',     'eurocomply-toy-safety' ),
			'lead'             => __( 'Lead (Pb)',                            'eurocomply-toy-safety' ),
			'cadmium'          => __( 'Cadmium (Cd)',                          'eurocomply-toy-safety' ),
			'mercury'          => __( 'Mercury (Hg)',                          'eurocomply-toy-safety' ),
			'arsenic'          => __( 'Arsenic (As)',                          'eurocomply-toy-safety' ),
			'phthalates'       => __( 'Phthalates (DEHP/DBP/BBP/DIBP)',        'eurocomply-toy-safety' ),
			'nitrosamines'     => __( 'Nitrosamines (≤ 36 months & mouth)',  'eurocomply-toy-safety' ),
			'bisphenols'       => __( 'Bisphenol A / S / F',                  'eurocomply-toy-safety' ),
			'fragrance'        => __( '55 prohibited fragrances (Annex II Appx. C)', 'eurocomply-toy-safety' ),
			'formaldehyde'     => __( 'Formaldehyde',                                 'eurocomply-toy-safety' ),
			'aromatic_amines'  => __( 'Primary aromatic amines',                       'eurocomply-toy-safety' ),
			'other'            => __( 'Other restricted substance',                       'eurocomply-toy-safety' ),
		);
	}

	public static function classification_label( string $key ) : string {
		$c = self::classifications();
		return $c[ $key ] ?? $key;
	}
}
