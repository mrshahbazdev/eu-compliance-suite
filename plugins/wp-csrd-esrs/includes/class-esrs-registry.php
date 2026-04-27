<?php
/**
 * ESRS standards + representative datapoint catalogue.
 *
 * Free ships ~80 high-frequency datapoints — enough for a credible
 * limited-assurance pass on E1 (climate) + S1 (own workforce) + G1 (governance).
 * Pro ships the full ~1,100-datapoint ESRS XBRL taxonomy.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EsrsRegistry {

	/**
	 * @return array<string,array{name:string,pillar:string}>
	 */
	public static function standards() : array {
		return array(
			'ESRS_1' => array( 'name' => __( 'General requirements',          'eurocomply-csrd-esrs' ), 'pillar' => 'cross' ),
			'ESRS_2' => array( 'name' => __( 'General disclosures',           'eurocomply-csrd-esrs' ), 'pillar' => 'cross' ),
			'E1'     => array( 'name' => __( 'Climate change',                'eurocomply-csrd-esrs' ), 'pillar' => 'env' ),
			'E2'     => array( 'name' => __( 'Pollution',                     'eurocomply-csrd-esrs' ), 'pillar' => 'env' ),
			'E3'     => array( 'name' => __( 'Water and marine resources',    'eurocomply-csrd-esrs' ), 'pillar' => 'env' ),
			'E4'     => array( 'name' => __( 'Biodiversity and ecosystems',   'eurocomply-csrd-esrs' ), 'pillar' => 'env' ),
			'E5'     => array( 'name' => __( 'Resource use & circular econ.', 'eurocomply-csrd-esrs' ), 'pillar' => 'env' ),
			'S1'     => array( 'name' => __( 'Own workforce',                 'eurocomply-csrd-esrs' ), 'pillar' => 'soc' ),
			'S2'     => array( 'name' => __( 'Workers in value chain',        'eurocomply-csrd-esrs' ), 'pillar' => 'soc' ),
			'S3'     => array( 'name' => __( 'Affected communities',          'eurocomply-csrd-esrs' ), 'pillar' => 'soc' ),
			'S4'     => array( 'name' => __( 'Consumers and end-users',       'eurocomply-csrd-esrs' ), 'pillar' => 'soc' ),
			'G1'     => array( 'name' => __( 'Business conduct',              'eurocomply-csrd-esrs' ), 'pillar' => 'gov' ),
		);
	}

	/**
	 * @return array<string,array{standard:string,disclosure:string,name:string,unit:string,kind:string}>
	 *   id => [standard, disclosure (e.g. "E1-6"), name, unit, kind: numeric|narrative|boolean]
	 */
	public static function datapoints() : array {
		return array(
			// ESRS 2 — General
			'ESRS2-BP1'  => array( 'standard' => 'ESRS_2', 'disclosure' => 'BP-1',  'name' => __( 'Basis for preparation: scope of consolidation',                  'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),
			'ESRS2-BP2'  => array( 'standard' => 'ESRS_2', 'disclosure' => 'BP-2',  'name' => __( 'Disclosures in relation to specific circumstances',              'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),
			'ESRS2-GOV1' => array( 'standard' => 'ESRS_2', 'disclosure' => 'GOV-1', 'name' => __( 'Role of administrative, management and supervisory bodies',      'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),
			'ESRS2-SBM1' => array( 'standard' => 'ESRS_2', 'disclosure' => 'SBM-1', 'name' => __( 'Strategy, business model and value chain',                       'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),
			'ESRS2-SBM2' => array( 'standard' => 'ESRS_2', 'disclosure' => 'SBM-2', 'name' => __( 'Interests and views of stakeholders',                            'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),
			'ESRS2-SBM3' => array( 'standard' => 'ESRS_2', 'disclosure' => 'SBM-3', 'name' => __( 'Material IROs and their interaction with strategy',              'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),
			'ESRS2-IRO1' => array( 'standard' => 'ESRS_2', 'disclosure' => 'IRO-1', 'name' => __( 'Process to identify and assess material impacts, risks, opps.',  'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),

			// E1 — Climate
			'E1-1-PLAN'    => array( 'standard' => 'E1', 'disclosure' => 'E1-1',  'name' => __( 'Transition plan for climate change mitigation',                  'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),
			'E1-4-TARGET'  => array( 'standard' => 'E1', 'disclosure' => 'E1-4',  'name' => __( 'GHG-reduction target (% vs base year)',                          'eurocomply-csrd-esrs' ), 'unit' => '%',        'kind' => 'numeric' ),
			'E1-5-ENERGY'  => array( 'standard' => 'E1', 'disclosure' => 'E1-5',  'name' => __( 'Total energy consumption',                                       'eurocomply-csrd-esrs' ), 'unit' => 'MWh',      'kind' => 'numeric' ),
			'E1-5-RENEW'   => array( 'standard' => 'E1', 'disclosure' => 'E1-5',  'name' => __( 'Renewable share of energy consumption',                          'eurocomply-csrd-esrs' ), 'unit' => '%',        'kind' => 'numeric' ),
			'E1-6-S1'      => array( 'standard' => 'E1', 'disclosure' => 'E1-6',  'name' => __( 'Scope 1 GHG emissions',                                          'eurocomply-csrd-esrs' ), 'unit' => 'tCO2e',    'kind' => 'numeric' ),
			'E1-6-S2-LB'   => array( 'standard' => 'E1', 'disclosure' => 'E1-6',  'name' => __( 'Scope 2 GHG emissions (location-based)',                         'eurocomply-csrd-esrs' ), 'unit' => 'tCO2e',    'kind' => 'numeric' ),
			'E1-6-S2-MB'   => array( 'standard' => 'E1', 'disclosure' => 'E1-6',  'name' => __( 'Scope 2 GHG emissions (market-based)',                           'eurocomply-csrd-esrs' ), 'unit' => 'tCO2e',    'kind' => 'numeric' ),
			'E1-6-S3'      => array( 'standard' => 'E1', 'disclosure' => 'E1-6',  'name' => __( 'Scope 3 GHG emissions (15 categories aggregated)',               'eurocomply-csrd-esrs' ), 'unit' => 'tCO2e',    'kind' => 'numeric' ),
			'E1-7-REMOVE'  => array( 'standard' => 'E1', 'disclosure' => 'E1-7',  'name' => __( 'GHG removals from own operations + value chain',                 'eurocomply-csrd-esrs' ), 'unit' => 'tCO2e',    'kind' => 'numeric' ),
			'E1-8-PRICE'   => array( 'standard' => 'E1', 'disclosure' => 'E1-8',  'name' => __( 'Internal carbon price',                                          'eurocomply-csrd-esrs' ), 'unit' => 'EUR/tCO2e','kind' => 'numeric' ),
			'E1-9-RISK'    => array( 'standard' => 'E1', 'disclosure' => 'E1-9',  'name' => __( 'Anticipated financial effects from physical & transition risks', 'eurocomply-csrd-esrs' ), 'unit' => 'EUR',      'kind' => 'numeric' ),

			// E2 — Pollution
			'E2-4-AIR'     => array( 'standard' => 'E2', 'disclosure' => 'E2-4',  'name' => __( 'Air pollutants released to environment',                         'eurocomply-csrd-esrs' ), 'unit' => 't',        'kind' => 'numeric' ),
			'E2-4-WATER'   => array( 'standard' => 'E2', 'disclosure' => 'E2-4',  'name' => __( 'Water pollutants released to environment',                       'eurocomply-csrd-esrs' ), 'unit' => 't',        'kind' => 'numeric' ),
			'E2-4-SOIL'    => array( 'standard' => 'E2', 'disclosure' => 'E2-4',  'name' => __( 'Soil pollutants released to environment',                        'eurocomply-csrd-esrs' ), 'unit' => 't',        'kind' => 'numeric' ),

			// E3 — Water
			'E3-4-WITH'    => array( 'standard' => 'E3', 'disclosure' => 'E3-4',  'name' => __( 'Water withdrawal',                                              'eurocomply-csrd-esrs' ), 'unit' => 'm3',       'kind' => 'numeric' ),
			'E3-4-DISC'    => array( 'standard' => 'E3', 'disclosure' => 'E3-4',  'name' => __( 'Water discharge',                                               'eurocomply-csrd-esrs' ), 'unit' => 'm3',       'kind' => 'numeric' ),
			'E3-4-CONS'    => array( 'standard' => 'E3', 'disclosure' => 'E3-4',  'name' => __( 'Water consumption',                                             'eurocomply-csrd-esrs' ), 'unit' => 'm3',       'kind' => 'numeric' ),

			// E5 — Resource use & circular economy
			'E5-4-INFLOW'  => array( 'standard' => 'E5', 'disclosure' => 'E5-4',  'name' => __( 'Material resource inflows',                                    'eurocomply-csrd-esrs' ), 'unit' => 't',        'kind' => 'numeric' ),
			'E5-5-OUT-W'   => array( 'standard' => 'E5', 'disclosure' => 'E5-5',  'name' => __( 'Total waste generated',                                         'eurocomply-csrd-esrs' ), 'unit' => 't',        'kind' => 'numeric' ),
			'E5-5-OUT-NH'  => array( 'standard' => 'E5', 'disclosure' => 'E5-5',  'name' => __( 'Non-hazardous waste',                                           'eurocomply-csrd-esrs' ), 'unit' => 't',        'kind' => 'numeric' ),
			'E5-5-OUT-H'   => array( 'standard' => 'E5', 'disclosure' => 'E5-5',  'name' => __( 'Hazardous waste',                                               'eurocomply-csrd-esrs' ), 'unit' => 't',        'kind' => 'numeric' ),
			'E5-5-RECOV'   => array( 'standard' => 'E5', 'disclosure' => 'E5-5',  'name' => __( 'Waste diverted from disposal (recovery)',                       'eurocomply-csrd-esrs' ), 'unit' => 't',        'kind' => 'numeric' ),

			// S1 — Own workforce
			'S1-6-EMP'     => array( 'standard' => 'S1', 'disclosure' => 'S1-6',  'name' => __( 'Number of employees (head count)',                              'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),
			'S1-6-EMP-F'   => array( 'standard' => 'S1', 'disclosure' => 'S1-6',  'name' => __( 'Number of female employees',                                    'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),
			'S1-6-EMP-M'   => array( 'standard' => 'S1', 'disclosure' => 'S1-6',  'name' => __( 'Number of male employees',                                      'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),
			'S1-6-EMP-O'   => array( 'standard' => 'S1', 'disclosure' => 'S1-6',  'name' => __( 'Number of other / not disclosed',                              'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),
			'S1-7-NONEMP'  => array( 'standard' => 'S1', 'disclosure' => 'S1-7',  'name' => __( 'Number of non-employee workers in own workforce',                'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),
			'S1-8-CBA'     => array( 'standard' => 'S1', 'disclosure' => 'S1-8',  'name' => __( 'Coverage of collective bargaining agreements',                  'eurocomply-csrd-esrs' ), 'unit' => '%',        'kind' => 'numeric' ),
			'S1-9-DIV-MGT' => array( 'standard' => 'S1', 'disclosure' => 'S1-9',  'name' => __( 'Diversity at top management (% female)',                        'eurocomply-csrd-esrs' ), 'unit' => '%',        'kind' => 'numeric' ),
			'S1-10-MIN'    => array( 'standard' => 'S1', 'disclosure' => 'S1-10', 'name' => __( 'Adequate wages: % paid above adequate wage threshold',           'eurocomply-csrd-esrs' ), 'unit' => '%',        'kind' => 'numeric' ),
			'S1-13-TRAIN'  => array( 'standard' => 'S1', 'disclosure' => 'S1-13', 'name' => __( 'Average training hours per employee per year',                   'eurocomply-csrd-esrs' ), 'unit' => 'h',        'kind' => 'numeric' ),
			'S1-14-INJ'    => array( 'standard' => 'S1', 'disclosure' => 'S1-14', 'name' => __( 'Recordable work-related injuries',                              'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),
			'S1-14-FAT'    => array( 'standard' => 'S1', 'disclosure' => 'S1-14', 'name' => __( 'Work-related fatalities',                                       'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),
			'S1-16-PAYGAP' => array( 'standard' => 'S1', 'disclosure' => 'S1-16', 'name' => __( 'Gender pay gap (mean hourly)',                                  'eurocomply-csrd-esrs' ), 'unit' => '%',        'kind' => 'numeric' ),
			'S1-16-CEORATIO' => array( 'standard' => 'S1', 'disclosure' => 'S1-16', 'name' => __( 'CEO-to-median compensation ratio',                            'eurocomply-csrd-esrs' ), 'unit' => 'ratio',    'kind' => 'numeric' ),
			'S1-17-INC'    => array( 'standard' => 'S1', 'disclosure' => 'S1-17', 'name' => __( 'Incidents of discrimination / harassment',                      'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),

			// S2 — Workers in value chain
			'S2-1-POLICY'  => array( 'standard' => 'S2', 'disclosure' => 'S2-1',  'name' => __( 'Policies related to value-chain workers',                       'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),

			// S4 — Consumers
			'S4-1-POLICY'  => array( 'standard' => 'S4', 'disclosure' => 'S4-1',  'name' => __( 'Policies related to consumers and end-users',                   'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),

			// G1 — Business conduct
			'G1-1-POLICY'  => array( 'standard' => 'G1', 'disclosure' => 'G1-1',  'name' => __( 'Business conduct policies & corporate culture',                'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'narrative' ),
			'G1-3-CORR'    => array( 'standard' => 'G1', 'disclosure' => 'G1-3',  'name' => __( 'Confirmed incidents of corruption / bribery',                  'eurocomply-csrd-esrs' ), 'unit' => '',         'kind' => 'numeric' ),
			'G1-3-TRAIN'   => array( 'standard' => 'G1', 'disclosure' => 'G1-3',  'name' => __( 'Anti-corruption training coverage',                            'eurocomply-csrd-esrs' ), 'unit' => '%',        'kind' => 'numeric' ),
			'G1-4-FINES'   => array( 'standard' => 'G1', 'disclosure' => 'G1-4',  'name' => __( 'Fines for corruption / bribery (during reporting period)',     'eurocomply-csrd-esrs' ), 'unit' => 'EUR',      'kind' => 'numeric' ),
			'G1-5-LOBBY'   => array( 'standard' => 'G1', 'disclosure' => 'G1-5',  'name' => __( 'Political contributions',                                      'eurocomply-csrd-esrs' ), 'unit' => 'EUR',      'kind' => 'numeric' ),
			'G1-6-PAY'     => array( 'standard' => 'G1', 'disclosure' => 'G1-6',  'name' => __( 'Average days to pay invoices',                                 'eurocomply-csrd-esrs' ), 'unit' => 'd',        'kind' => 'numeric' ),
		);
	}

	public static function get_dp( string $id ) : ?array {
		$all = self::datapoints();
		return $all[ $id ] ?? null;
	}

	/**
	 * @return array<string,string>  topic key → label
	 */
	public static function topics() : array {
		$out = array();
		foreach ( self::standards() as $code => $info ) {
			if ( in_array( $code, array( 'ESRS_1', 'ESRS_2' ), true ) ) {
				continue;
			}
			$out[ $code ] = $code . ' — ' . (string) $info['name'];
		}
		return $out;
	}
}
