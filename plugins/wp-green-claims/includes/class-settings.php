<?php
/**
 * Settings + taxonomy + banned-claim phrasebook.
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_gc_settings';

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'company_name'           => (string) get_bloginfo( 'name' ),
			'consumer_info_page_id'  => 0,
			'enable_scanner'         => 1,
			'block_unverified'       => 0,
			'default_durability_m'   => 24,
			'default_software_y'     => 5,
			'default_repair_score'   => 0,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * @param array<string,mixed> $input
	 */
	public static function save( array $input ) : void {
		$current = self::get();
		$out     = array(
			'company_name'          => isset( $input['company_name'] ) ? sanitize_text_field( (string) $input['company_name'] ) : $current['company_name'],
			'consumer_info_page_id' => isset( $input['consumer_info_page_id'] ) ? max( 0, (int) $input['consumer_info_page_id'] ) : $current['consumer_info_page_id'],
			'enable_scanner'        => empty( $input['enable_scanner'] ) ? 0 : 1,
			'block_unverified'      => empty( $input['block_unverified'] ) ? 0 : 1,
			'default_durability_m'  => isset( $input['default_durability_m'] ) ? max( 0, (int) $input['default_durability_m'] ) : $current['default_durability_m'],
			'default_software_y'    => isset( $input['default_software_y'] ) ? max( 0, (int) $input['default_software_y'] ) : $current['default_software_y'],
			'default_repair_score'  => isset( $input['default_repair_score'] ) ? max( 0, min( 10, (int) $input['default_repair_score'] ) ) : $current['default_repair_score'],
		);
		update_option( self::OPTION_KEY, $out, false );
	}

	/**
	 * Generic claims that are presumptively misleading under UCPD as amended (Annex I points 4a, 23a-d).
	 *
	 * @return array<int,string>
	 */
	public static function banned_phrases() : array {
		return array(
			'climate neutral', 'carbon neutral', 'co2 neutral', 'co2-neutral', 'klimaneutral', 'klimaschonend',
			'eco-friendly', 'eco friendly', 'environmentally friendly', 'umweltfreundlich',
			'biodegradable', 'biologisch abbaubar',
			'green', 'grün',
			'natural', 'natürlich',
			'sustainable', 'nachhaltig',
			'planet friendly', 'planet-friendly',
			'eco', 'bio',
			'environmentally conscious',
			'zero waste', 'zero-waste',
			'recyclable', 'recycelbar',
			'energy efficient',
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function claim_status() : array {
		return array(
			'pending'   => __( 'Pending verification', 'eurocomply-green-claims' ),
			'verified'  => __( 'Verified (third-party evidence)', 'eurocomply-green-claims' ),
			'rejected'  => __( 'Rejected (insufficient evidence)', 'eurocomply-green-claims' ),
			'withdrawn' => __( 'Withdrawn from product', 'eurocomply-green-claims' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function evidence_types() : array {
		return array(
			'lifecycle_assessment' => __( 'Life-cycle assessment (ISO 14040 / 14044)', 'eurocomply-green-claims' ),
			'pef'                  => __( 'Product Environmental Footprint (PEF)', 'eurocomply-green-claims' ),
			'oef'                  => __( 'Organisation Environmental Footprint (OEF)', 'eurocomply-green-claims' ),
			'iso_14021'            => __( 'ISO 14021 self-declared environmental claim', 'eurocomply-green-claims' ),
			'iso_14024'            => __( 'ISO 14024 type-I ecolabel', 'eurocomply-green-claims' ),
			'iso_14025'            => __( 'ISO 14025 type-III environmental product declaration', 'eurocomply-green-claims' ),
			'eu_ecolabel'          => __( 'EU Ecolabel (Reg. (EC) 66/2010)', 'eurocomply-green-claims' ),
			'energy_label'         => __( 'EU Energy Label (Reg. (EU) 2017/1369)', 'eurocomply-green-claims' ),
			'organic_eu'           => __( 'EU organic logo (Reg. (EU) 2018/848)', 'eurocomply-green-claims' ),
			'fairtrade'            => __( 'Fairtrade International', 'eurocomply-green-claims' ),
			'rspo'                 => __( 'RSPO certified sustainable palm oil', 'eurocomply-green-claims' ),
			'fsc'                  => __( 'FSC chain of custody', 'eurocomply-green-claims' ),
			'pefc'                 => __( 'PEFC chain of custody', 'eurocomply-green-claims' ),
			'msc'                  => __( 'MSC fisheries', 'eurocomply-green-claims' ),
			'asc'                  => __( 'ASC aquaculture', 'eurocomply-green-claims' ),
			'gots'                 => __( 'GOTS organic textile', 'eurocomply-green-claims' ),
			'oeko_tex'             => __( 'OEKO-TEX Standard 100', 'eurocomply-green-claims' ),
			'cradle_to_cradle'     => __( 'Cradle to Cradle Certified', 'eurocomply-green-claims' ),
			'b_corp'               => __( 'B Corporation', 'eurocomply-green-claims' ),
			'other'                => __( 'Other (specify)', 'eurocomply-green-claims' ),
		);
	}
}
