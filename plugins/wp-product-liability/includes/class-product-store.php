<?php
/**
 * Product / component register.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductStore {

	private const SCHEMA_OPTION  = 'eurocomply_pl_product_schema';
	private const SCHEMA_VERSION = '1';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_pl_products';
	}

	public static function install() : void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            wc_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            sku VARCHAR(190) NULL,
            type VARCHAR(32) NOT NULL DEFAULT 'tangible',
            name VARCHAR(190) NOT NULL,
            manufacturer VARCHAR(190) NULL,
            manufacturer_country VARCHAR(8) NULL,
            importer VARCHAR(190) NULL,
            eu_representative VARCHAR(190) NULL,
            placed_on_market DATE NULL,
            withdrawn_at DATE NULL,
            limitation_until DATE NULL,
            extended_limitation_until DATE NULL,
            software_update_until DATE NULL,
            ai_system TINYINT(1) NOT NULL DEFAULT 0,
            substantial_modifier VARCHAR(190) NULL,
            documentation_url TEXT NULL,
            notes LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY wc_product_id (wc_product_id),
            KEY sku (sku),
            KEY type (type),
            KEY limitation_until (limitation_until)
        ) $charset;";
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	public static function insert( array $data ) : int {
		global $wpdb;
		$type = sanitize_key( (string) ( $data['type'] ?? 'tangible' ) );
		if ( ! array_key_exists( $type, Settings::product_types() ) ) {
			$type = 'tangible';
		}
		$now             = current_time( 'mysql', true );
		$placed          = ! empty( $data['placed_on_market'] ) ? sanitize_text_field( (string) $data['placed_on_market'] ) : null;
		$limitation      = null;
		$extended_limit  = null;
		$settings        = Settings::get();
		if ( $placed ) {
			$ts = strtotime( $placed );
			if ( false !== $ts ) {
				$limitation     = gmdate( 'Y-m-d', $ts + ( (int) $settings['limitation_years'] * YEAR_IN_SECONDS ) );
				$extended_limit = gmdate( 'Y-m-d', $ts + ( (int) $settings['latent_injury_years'] * YEAR_IN_SECONDS ) );
			}
		}

		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'created_at'                => $now,
				'updated_at'                => $now,
				'wc_product_id'             => (int) ( $data['wc_product_id'] ?? 0 ),
				'sku'                       => sanitize_text_field( (string) ( $data['sku'] ?? '' ) ),
				'type'                      => $type,
				'name'                      => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
				'manufacturer'              => sanitize_text_field( (string) ( $data['manufacturer'] ?? '' ) ),
				'manufacturer_country'      => substr( strtoupper( sanitize_text_field( (string) ( $data['manufacturer_country'] ?? '' ) ) ), 0, 8 ),
				'importer'                  => sanitize_text_field( (string) ( $data['importer'] ?? '' ) ),
				'eu_representative'         => sanitize_text_field( (string) ( $data['eu_representative'] ?? '' ) ),
				'placed_on_market'          => $placed,
				'withdrawn_at'              => ! empty( $data['withdrawn_at'] ) ? sanitize_text_field( (string) $data['withdrawn_at'] ) : null,
				'limitation_until'          => $limitation,
				'extended_limitation_until' => $extended_limit,
				'software_update_until'     => ! empty( $data['software_update_until'] ) ? sanitize_text_field( (string) $data['software_update_until'] ) : null,
				'ai_system'                 => empty( $data['ai_system'] ) ? 0 : 1,
				'substantial_modifier'      => sanitize_text_field( (string) ( $data['substantial_modifier'] ?? '' ) ),
				'documentation_url'         => esc_url_raw( (string) ( $data['documentation_url'] ?? '' ) ),
				'notes'                     => wp_kses_post( (string) ( $data['notes'] ?? '' ) ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function delete( int $id ) : bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function all() : array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function get( int $id ) : ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function nearing_limitation_count( int $days = 365 ) : int {
		global $wpdb;
		$today  = current_time( 'Y-m-d', true );
		$cutoff = gmdate( 'Y-m-d', current_time( 'timestamp', true ) + ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE limitation_until IS NOT NULL AND limitation_until BETWEEN %s AND %s',
				$today,
				$cutoff
			)
		);
	}

	public static function update_software_window( int $id, ?string $software_update_until ) : bool {
		global $wpdb;
		return (bool) $wpdb->update(
			self::table_name(),
			array(
				'updated_at'            => current_time( 'mysql', true ),
				'software_update_until' => $software_update_until,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}
