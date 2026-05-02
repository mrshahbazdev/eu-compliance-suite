<?php
/**
 * Sustainability label registry (Annex I addition to UCPD).
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LabelStore {

	private const SCHEMA_VERSION = '1.0.0';
	private const SCHEMA_OPTION  = 'eurocomply_gc_label_schema';

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'eurocomply_gc_labels';
	}

	public static function install() : void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			label_name VARCHAR(190) NOT NULL DEFAULT '',
			scheme_owner VARCHAR(190) NOT NULL DEFAULT '',
			recognized_eu TINYINT(1) NOT NULL DEFAULT 0,
			third_party_verified TINYINT(1) NOT NULL DEFAULT 0,
			scheme_url VARCHAR(500) NOT NULL DEFAULT '',
			notes TEXT NULL,
			PRIMARY KEY (id),
			KEY recognized_eu (recognized_eu)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) ) {
			self::seed();
		}
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	private static function seed() : void {
		global $wpdb;
		$table = self::table_name();
		$rows  = array(
			array( 'EU Ecolabel', 'European Commission', 1, 1, 'https://environment.ec.europa.eu/topics/circular-economy/eu-ecolabel_en' ),
			array( 'EU Energy Label', 'European Commission', 1, 1, 'https://commission.europa.eu/energy-climate-change-environment/standards-tools-and-labels/products-labelling-rules-and-requirements/energy-label-and-ecodesign_en' ),
			array( 'EU Organic logo', 'European Commission', 1, 1, 'https://agriculture.ec.europa.eu/farming/organics/organics-glance/organic-logo_en' ),
			array( 'Nordic Swan Ecolabel', 'Nordic Council of Ministers', 0, 1, 'https://www.nordic-ecolabel.org/' ),
			array( 'Blauer Engel', 'German Federal Environment Agency', 0, 1, 'https://www.blauer-engel.de/en' ),
			array( 'NF Environnement', 'AFNOR Certification', 0, 1, 'https://www.nf-environnement.com/' ),
			array( 'Fairtrade International', 'FLO e.V.', 0, 1, 'https://www.fairtrade.net/' ),
			array( 'Rainforest Alliance', 'Rainforest Alliance', 0, 1, 'https://www.rainforest-alliance.org/' ),
			array( 'FSC', 'Forest Stewardship Council', 0, 1, 'https://fsc.org/' ),
			array( 'PEFC', 'Programme for the Endorsement of Forest Certification', 0, 1, 'https://www.pefc.org/' ),
			array( 'MSC', 'Marine Stewardship Council', 0, 1, 'https://www.msc.org/' ),
			array( 'ASC', 'Aquaculture Stewardship Council', 0, 1, 'https://www.asc-aqua.org/' ),
			array( 'GOTS', 'Global Standard gemeinnützige GmbH', 0, 1, 'https://global-standard.org/' ),
			array( 'OEKO-TEX Standard 100', 'OEKO-TEX Association', 0, 1, 'https://www.oeko-tex.com/' ),
			array( 'Cradle to Cradle Certified', 'Cradle to Cradle Products Innovation Institute', 0, 1, 'https://c2ccertified.org/' ),
			array( 'B Corporation', 'B Lab', 0, 1, 'https://www.bcorporation.net/' ),
			array( 'RSPO', 'Roundtable on Sustainable Palm Oil', 0, 1, 'https://rspo.org/' ),
			array( 'Bio Suisse Knospe', 'Bio Suisse', 0, 1, 'https://www.bio-suisse.ch/' ),
			array( 'AB Agriculture Biologique (FR)', 'Agence BIO', 0, 1, 'https://www.agencebio.org/' ),
			array( 'KRAV (SE)', 'KRAV ekonomisk förening', 0, 1, 'https://www.krav.se/' ),
		);
		foreach ( $rows as $r ) {
			$wpdb->insert(
				$table,
				array(
					'label_name'           => $r[0],
					'scheme_owner'         => $r[1],
					'recognized_eu'        => $r[2],
					'third_party_verified' => $r[3],
					'scheme_url'           => $r[4],
				)
			);
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function insert( array $data ) : int {
		global $wpdb;
		$row = array(
			'label_name'           => isset( $data['label_name'] ) ? sanitize_text_field( (string) $data['label_name'] ) : '',
			'scheme_owner'         => isset( $data['scheme_owner'] ) ? sanitize_text_field( (string) $data['scheme_owner'] ) : '',
			'recognized_eu'        => empty( $data['recognized_eu'] ) ? 0 : 1,
			'third_party_verified' => empty( $data['third_party_verified'] ) ? 0 : 1,
			'scheme_url'           => isset( $data['scheme_url'] ) ? esc_url_raw( (string) $data['scheme_url'] ) : '',
			'notes'                => isset( $data['notes'] ) ? wp_kses_post( (string) $data['notes'] ) : '',
		);
		if ( '' === $row['label_name'] ) {
			return 0;
		}
		$ok = $wpdb->insert( self::table_name(), $row );
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	public static function delete( int $id ) : bool {
		global $wpdb;
		return false !== $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() : array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . ' ORDER BY recognized_eu DESC, label_name ASC LIMIT 1000', ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	public static function count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function unverified_count() : int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE third_party_verified = 0' );
	}
}
