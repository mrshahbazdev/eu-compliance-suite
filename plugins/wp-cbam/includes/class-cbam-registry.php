<?php
/**
 * CBAM goods registry: maps CN-8 codes to CBAM categories per Reg. (EU) 2023/956 Annex I.
 *
 * Kept as a static method so that a Pro TARIC-sync layer can hook before/after
 * to extend with EORI-specific or sub-heading detail without DB churn.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CbamRegistry {

	/**
	 * @return array<string,array{category:string,name:string,unit:string}>
	 *   slug => [category, name, unit (tCO2e per t / per MWh)]
	 */
	public static function categories() : array {
		return array(
			'cement'        => array( 'category' => 'cement',        'name' => __( 'Cement', 'eurocomply-cbam' ),                'unit' => 't' ),
			'electricity'   => array( 'category' => 'electricity',   'name' => __( 'Electricity', 'eurocomply-cbam' ),           'unit' => 'MWh' ),
			'fertilisers'   => array( 'category' => 'fertilisers',   'name' => __( 'Fertilisers', 'eurocomply-cbam' ),           'unit' => 't' ),
			'iron_and_steel'=> array( 'category' => 'iron_and_steel','name' => __( 'Iron & steel', 'eurocomply-cbam' ),          'unit' => 't' ),
			'aluminium'     => array( 'category' => 'aluminium',     'name' => __( 'Aluminium', 'eurocomply-cbam' ),             'unit' => 't' ),
			'hydrogen'      => array( 'category' => 'hydrogen',      'name' => __( 'Hydrogen', 'eurocomply-cbam' ),              'unit' => 't' ),
			'downstream'    => array( 'category' => 'downstream',    'name' => __( 'Downstream goods (screws/bolts/wires)', 'eurocomply-cbam' ), 'unit' => 't' ),
		);
	}

	/**
	 * Return a (small but representative) CN-8 → category map per Annex I.
	 * Real-world maps run into hundreds of entries; the Pro TARIC sync extends
	 * this dynamically.
	 *
	 * @return array<string,string> CN8 => category slug
	 */
	public static function cn_to_category() : array {
		return array(
			// Cement
			'25231000' => 'cement',
			'25232100' => 'cement',
			'25232900' => 'cement',
			'25233000' => 'cement',
			'25239000' => 'cement',
			'25070080' => 'cement',
			// Iron & steel - selected high-frequency CN8
			'72011019' => 'iron_and_steel',
			'72081000' => 'iron_and_steel',
			'72083900' => 'iron_and_steel',
			'72085200' => 'iron_and_steel',
			'72093900' => 'iron_and_steel',
			'72101290' => 'iron_and_steel',
			'73181590' => 'iron_and_steel',
			// Aluminium
			'76011000' => 'aluminium',
			'76012080' => 'aluminium',
			'76042100' => 'aluminium',
			'76061291' => 'aluminium',
			'76082089' => 'aluminium',
			// Fertilisers
			'31021010' => 'fertilisers',
			'31023010' => 'fertilisers',
			'31052000' => 'fertilisers',
			'28080000' => 'fertilisers',
			// Hydrogen
			'28041000' => 'hydrogen',
			// Electricity
			'27160000' => 'electricity',
		);
	}

	public static function category_for_cn( string $cn ) : string {
		$cn  = preg_replace( '/[^0-9]/', '', $cn );
		$map = self::cn_to_category();
		if ( isset( $map[ $cn ] ) ) {
			return (string) $map[ $cn ];
		}
		// 6-digit fallback: best-effort prefix match.
		$prefix6 = substr( $cn, 0, 6 );
		foreach ( $map as $code => $cat ) {
			if ( 0 === strpos( $code, $prefix6 ) ) {
				return (string) $cat;
			}
		}
		return '';
	}

	/**
	 * Default emissions factors per Annex IV (transitional period defaults).
	 * Returns tCO2e per unit of the category's measurement unit.
	 *
	 * @return array<string,array{direct:float,indirect:float}>
	 */
	public static function default_emissions() : array {
		return array(
			'cement'         => array( 'direct' => 0.81,  'indirect' => 0.04 ),
			'iron_and_steel' => array( 'direct' => 1.78,  'indirect' => 0.32 ),
			'aluminium'      => array( 'direct' => 1.50,  'indirect' => 6.50 ),
			'fertilisers'    => array( 'direct' => 1.91,  'indirect' => 0.21 ),
			'hydrogen'       => array( 'direct' => 9.18,  'indirect' => 1.65 ),
			'electricity'    => array( 'direct' => 0.55,  'indirect' => 0.0  ),
			'downstream'     => array( 'direct' => 1.78,  'indirect' => 0.32 ),
		);
	}
}
