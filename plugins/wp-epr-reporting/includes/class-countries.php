<?php
/**
 * Registry of supported EPR countries + their regulator codes.
 *
 * MVP covers the seven highest-volume EU packaging-EPR registries merchants face.
 * Each entry captures the regulator name, registration-number label, and format
 * regex so the product metabox can validate input per country.
 *
 * @package EuroComply\Epr
 */

declare( strict_types = 1 );

namespace EuroComply\Epr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Countries {

	/**
	 * @return array<string,array<string,string>>
	 */
	public static function all() : array {
		return array(
			'FR' => array(
				'name'           => 'France',
				'regulator'      => 'Triman / Agec (CITEO, Ecologic)',
				'reg_label'      => 'Unique ID (IDU)',
				'reg_regex'      => '/^FR[0-9]{6}_[0-9A-Z]{4,8}$/i',
				'reg_example'    => 'FR123456_ABCD',
				'pro_auto_submit'=> '1',
			),
			'DE' => array(
				'name'           => 'Germany',
				'regulator'      => 'LUCID / Zentrale Stelle Verpackungsregister (ZSVR)',
				'reg_label'      => 'LUCID Registration Number',
				'reg_regex'      => '/^DE[0-9]{10,13}$/i',
				'reg_example'    => 'DE1234567890123',
				'pro_auto_submit'=> '1',
			),
			'ES' => array(
				'name'           => 'Spain',
				'regulator'      => 'Ecoembes / Ecovidrio (SCRAP)',
				'reg_label'      => 'NIMA / Registro',
				'reg_regex'      => '/^ES[0-9]{6,10}$/i',
				'reg_example'    => 'ES12345678',
				'pro_auto_submit'=> '0',
			),
			'IT' => array(
				'name'           => 'Italy',
				'regulator'      => 'CONAI',
				'reg_label'      => 'Codice CONAI',
				'reg_regex'      => '/^IT[0-9]{6,8}$/i',
				'reg_example'    => 'IT1234567',
				'pro_auto_submit'=> '0',
			),
			'NL' => array(
				'name'           => 'Netherlands',
				'regulator'      => 'Afvalfonds Verpakkingen',
				'reg_label'      => 'Afvalfonds ID',
				'reg_regex'      => '/^NL[0-9]{6,10}$/i',
				'reg_example'    => 'NL12345678',
				'pro_auto_submit'=> '0',
			),
			'AT' => array(
				'name'           => 'Austria',
				'regulator'      => 'ARA (Altstoff Recycling Austria)',
				'reg_label'      => 'ARA Lizenznummer',
				'reg_regex'      => '/^AT[0-9]{4,10}$/i',
				'reg_example'    => 'AT123456',
				'pro_auto_submit'=> '0',
			),
			'BE' => array(
				'name'           => 'Belgium',
				'regulator'      => 'Fost Plus / Valipac',
				'reg_label'      => 'Fost Plus ID',
				'reg_regex'      => '/^BE[0-9]{6,10}$/i',
				'reg_example'    => 'BE12345678',
				'pro_auto_submit'=> '0',
			),
		);
	}

	/**
	 * Packaging material categories shared across registries. Each registry
	 * uses its own codes internally, but merchants declare weight by material;
	 * Pro-tier export maps these to per-registry code sets.
	 *
	 * @return array<string,string>
	 */
	public static function materials() : array {
		return array(
			'paper'      => 'Paper / cardboard',
			'plastic'    => 'Plastic',
			'glass'      => 'Glass',
			'metal'      => 'Metal (aluminium / steel)',
			'wood'       => 'Wood',
			'composite'  => 'Composite / multi-material',
			'other'      => 'Other',
		);
	}

	public static function is_supported( string $code ) : bool {
		return array_key_exists( strtoupper( $code ), self::all() );
	}

	/**
	 * @return array<string,string>|null
	 */
	public static function get( string $code ) : ?array {
		$all = self::all();
		$key = strtoupper( $code );
		return $all[ $key ] ?? null;
	}
}
