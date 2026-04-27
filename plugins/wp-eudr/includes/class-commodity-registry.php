<?php
/**
 * EUDR commodity registry — Annex I of Reg. (EU) 2023/1115.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CommodityRegistry {

	/**
	 * Seven in-scope commodities + key derived products with representative HS codes (Annex I).
	 *
	 * @return array<string,array{name:string,hs:array<int,string>,examples:string}>
	 */
	public static function commodities() : array {
		return array(
			'cattle' => array(
				'name'     => __( 'Cattle', 'eurocomply-eudr' ),
				'hs'       => array( '0102', '0201', '0202', '4101', '4104', '4107' ),
				'examples' => __( 'Live bovine animals; meat (fresh, chilled, frozen); raw, tanned, finished hides & leather.', 'eurocomply-eudr' ),
			),
			'cocoa' => array(
				'name'     => __( 'Cocoa', 'eurocomply-eudr' ),
				'hs'       => array( '1801', '1802', '1803', '1804', '1805', '1806' ),
				'examples' => __( 'Cocoa beans, shells, paste, butter, powder, chocolate.', 'eurocomply-eudr' ),
			),
			'coffee' => array(
				'name'     => __( 'Coffee', 'eurocomply-eudr' ),
				'hs'       => array( '0901' ),
				'examples' => __( 'Green / roasted / decaffeinated coffee, husks, substitutes containing coffee.', 'eurocomply-eudr' ),
			),
			'oil_palm' => array(
				'name'     => __( 'Oil palm', 'eurocomply-eudr' ),
				'hs'       => array( '1207.10', '1511', '1513.21', '1513.29', '1517', '1518', '2306.60', '3823.11', '3823.12', '3823.19', '3826' ),
				'examples' => __( 'Palm fruit & nuts; crude / refined palm oil; palm-kernel oil & fractions; oil-cake; biodiesel.', 'eurocomply-eudr' ),
			),
			'rubber' => array(
				'name'     => __( 'Rubber', 'eurocomply-eudr' ),
				'hs'       => array( '4001', '4005', '4006', '4007', '4008', '4010', '4011', '4012', '4013', '4015', '4016', '4017' ),
				'examples' => __( 'Natural rubber latex, sheets, plates, profiles, tyres, hygiene articles.', 'eurocomply-eudr' ),
			),
			'soya' => array(
				'name'     => __( 'Soya', 'eurocomply-eudr' ),
				'hs'       => array( '1201', '1208.10', '1507', '2304' ),
				'examples' => __( 'Soybeans (whole / broken); soybean flour & meal; soya oil; oil-cake.', 'eurocomply-eudr' ),
			),
			'wood' => array(
				'name'     => __( 'Wood', 'eurocomply-eudr' ),
				'hs'       => array( '4401', '4403', '4406', '4407', '4408', '4409', '4410', '4411', '4412', '4413', '4414', '4415', '4416', '4417', '4418', '4419', '4420', '4421', '4701', '4702', '4703', '4704', '4705', '4801', '4802', '4803', '4804', '4805', '4806', '4807', '4808', '4809', '4810', '4811', '9403.30', '9403.40', '9403.50', '9403.60', '9406.10' ),
				'examples' => __( 'Logs, sawnwood, veneer, plywood, panels, furniture, pulp, paper, charcoal.', 'eurocomply-eudr' ),
			),
		);
	}

	public static function get( string $key ) : ?array {
		$all = self::commodities();
		return $all[ $key ] ?? null;
	}

	public static function options() : array {
		$out = array();
		foreach ( self::commodities() as $key => $info ) {
			$out[ $key ] = (string) $info['name'];
		}
		return $out;
	}
}
