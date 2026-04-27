<?php
/**
 * Digital Product Passport builder.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DppBuilder {

	/**
	 * @return array{json:string,xml:string,toy:array<string,mixed>|null}
	 */
	public static function build( int $toy_id ) : array {
		$toy = ToyStore::get( $toy_id );
		if ( null === $toy ) {
			return array( 'json' => '', 'xml' => '', 'toy' => null );
		}
		$ops    = OperatorStore::for_toy( $toy_id );
		$s      = Settings::get();
		$assess = array_values(
			array_filter(
				AssessmentStore::all(),
				static function ( $a ) use ( $toy_id ) {
					return (int) $a['toy_id'] === $toy_id;
				}
			)
		);
		$subs   = array_values(
			array_filter(
				SubstanceStore::all(),
				static function ( $s ) use ( $toy_id ) {
					return (int) $s['toy_id'] === $toy_id;
				}
			)
		);

		$payload = array(
			'schema'         => 'eurocomply-toy-dpp-1',
			'generated_at'   => gmdate( 'c' ),
			'entity'         => array(
				'name'    => (string) $s['entity_name'],
				'country' => (string) $s['entity_country'],
				'role'    => (string) $s['role'],
				'eori'    => (string) $s['eori'],
			),
			'toy'            => array(
				'id'             => (int) $toy['id'],
				'name'           => (string) $toy['name'],
				'model'          => (string) $toy['model'],
				'gtin'           => (string) $toy['gtin'],
				'batch'          => (string) $toy['batch'],
				'age_range'      => (string) $toy['age_range'],
				'under_36'       => (bool) $toy['under_36'],
				'category'       => (string) $toy['category'],
				'origin_country' => (string) $toy['origin_country'],
				'ce_marked'      => (bool) $toy['ce_marked'],
				'doc_url'        => (string) $toy['doc_url'],
				'image_url'      => (string) $toy['image_url'],
				'warnings'       => wp_strip_all_tags( (string) $toy['warnings'] ),
				'materials'      => wp_strip_all_tags( (string) $toy['materials'] ),
			),
			'operators'      => array_map(
				static function ( $o ) {
					return array(
						'role'    => (string) $o['role'],
						'name'    => (string) $o['name'],
						'country' => (string) $o['country'],
						'address' => (string) $o['address'],
						'email'   => (string) $o['email'],
						'eori'    => (string) $o['eori'],
						'vat'     => (string) $o['vat'],
					);
				},
				$ops
			),
			'assessments'    => array_map(
				static function ( $a ) {
					return array(
						'module'           => (string) $a['module'],
						'notified_body'    => (string) $a['notified_body'],
						'notified_body_id' => (string) $a['notified_body_id'],
						'certificate_no'   => (string) $a['certificate_no'],
						'issued_at'        => (string) ( $a['issued_at']   ?? '' ),
						'valid_until'      => (string) ( $a['valid_until'] ?? '' ),
						'standards'        => wp_strip_all_tags( (string) $a['standards'] ),
					);
				},
				$assess
			),
			'substances'     => array_map(
				static function ( $s ) {
					return array(
						'name'           => (string) $s['name'],
						'cas'            => (string) $s['cas'],
						'classification' => (string) $s['classification'],
						'limit_value'    => (string) $s['limit_value'],
						'measured_value' => (string) $s['measured_value'],
						'pass_fail'      => (string) $s['pass_fail'],
					);
				},
				$subs
			),
		);

		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<DigitalProductPassport xmlns="urn:toy:eurocomply:0.1" generatedAt="' . self::esc( gmdate( 'c' ) ) . '">' . "\n";
		$xml .= '  <Entity>' . "\n";
		$xml .= '    <Name>' . self::esc( (string) $s['entity_name'] ) . '</Name>' . "\n";
		$xml .= '    <Country>' . self::esc( (string) $s['entity_country'] ) . '</Country>' . "\n";
		$xml .= '    <Role>' . self::esc( (string) $s['role'] ) . '</Role>' . "\n";
		$xml .= '    <EORI>' . self::esc( (string) $s['eori'] ) . '</EORI>' . "\n";
		$xml .= '  </Entity>' . "\n";
		$xml .= '  <Toy id="' . (int) $toy['id'] . '" gtin="' . self::esc( (string) $toy['gtin'] ) . '" ageRange="' . self::esc( (string) $toy['age_range'] ) . '" under36="' . ( $toy['under_36'] ? '1' : '0' ) . '" ceMarked="' . ( $toy['ce_marked'] ? '1' : '0' ) . '">' . "\n";
		$xml .= '    <Name>' . self::esc( (string) $toy['name'] ) . '</Name>' . "\n";
		$xml .= '    <Model>' . self::esc( (string) $toy['model'] ) . '</Model>' . "\n";
		$xml .= '    <Batch>' . self::esc( (string) $toy['batch'] ) . '</Batch>' . "\n";
		$xml .= '    <OriginCountry>' . self::esc( (string) $toy['origin_country'] ) . '</OriginCountry>' . "\n";
		$xml .= '    <Warnings>' . self::esc( wp_strip_all_tags( (string) $toy['warnings'] ) ) . '</Warnings>' . "\n";
		$xml .= '    <Materials>' . self::esc( wp_strip_all_tags( (string) $toy['materials'] ) ) . '</Materials>' . "\n";
		$xml .= '  </Toy>' . "\n";
		$xml .= '  <Operators>' . "\n";
		foreach ( $ops as $o ) {
			$xml .= '    <Operator role="' . self::esc( (string) $o['role'] ) . '">' . "\n";
			$xml .= '      <Name>' . self::esc( (string) $o['name'] ) . '</Name>' . "\n";
			$xml .= '      <Country>' . self::esc( (string) $o['country'] ) . '</Country>' . "\n";
			$xml .= '      <EORI>' . self::esc( (string) $o['eori'] ) . '</EORI>' . "\n";
			$xml .= '    </Operator>' . "\n";
		}
		$xml .= '  </Operators>' . "\n";
		$xml .= '  <Assessments>' . "\n";
		foreach ( $assess as $a ) {
			$xml .= '    <Assessment module="' . self::esc( (string) $a['module'] ) . '" certNo="' . self::esc( (string) $a['certificate_no'] ) . '" notifiedBody="' . self::esc( (string) $a['notified_body_id'] ) . '" />' . "\n";
		}
		$xml .= '  </Assessments>' . "\n";
		$xml .= '  <Substances>' . "\n";
		foreach ( $subs as $s2 ) {
			$xml .= '    <Substance classification="' . self::esc( (string) $s2['classification'] ) . '" cas="' . self::esc( (string) $s2['cas'] ) . '" pass="' . self::esc( (string) $s2['pass_fail'] ) . '">' . self::esc( (string) $s2['name'] ) . '</Substance>' . "\n";
		}
		$xml .= '  </Substances>' . "\n";
		$xml .= '</DigitalProductPassport>' . "\n";

		return array( 'json' => (string) $json, 'xml' => $xml, 'toy' => $toy );
	}

	private static function esc( string $s ) : string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
