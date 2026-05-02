<?php
/**
 * Settings + taxonomies.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	private const OPTION = 'eurocomply_pl_settings';

	public static function defaults() : array {
		return array(
			'manufacturer_name'    => '',
			'manufacturer_address' => '',
			'manufacturer_email'   => '',
			'eu_representative'    => '',
			'eu_rep_address'       => '',
			'importer_name'        => '',
			'importer_address'     => '',
			'liability_officer'    => '',
			'defect_inbox'         => '',
			'limitation_years'     => 10,
			'latent_injury_years'  => 25,
		);
	}

	public static function get() : array {
		$opt = get_option( self::OPTION, self::defaults() );
		return wp_parse_args( is_array( $opt ) ? $opt : array(), self::defaults() );
	}

	public static function update( array $changes ) : void {
		$current = self::get();
		$next    = wp_parse_args( $changes, $current );
		update_option( self::OPTION, $next );
	}

	/**
	 * Product / component types under the new PLD scope (Art. 4).
	 */
	public static function product_types() : array {
		return array(
			'tangible'        => __( 'Tangible movable (incl. integrated software)', 'eurocomply-product-liability' ),
			'software'        => __( 'Standalone software / firmware', 'eurocomply-product-liability' ),
			'ai_system'       => __( 'AI system', 'eurocomply-product-liability' ),
			'ai_model'        => __( 'AI model (general purpose)', 'eurocomply-product-liability' ),
			'digital_service' => __( 'Related digital service', 'eurocomply-product-liability' ),
			'component'       => __( 'Component supplied separately', 'eurocomply-product-liability' ),
			'raw_material'    => __( 'Raw material', 'eurocomply-product-liability' ),
			'electricity'     => __( 'Electricity', 'eurocomply-product-liability' ),
		);
	}

	/**
	 * Damage types covered (Art. 6).
	 */
	public static function damage_types() : array {
		return array(
			'death'             => __( 'Death', 'eurocomply-product-liability' ),
			'personal_injury'   => __( 'Personal injury (incl. medically recognised psychological)', 'eurocomply-product-liability' ),
			'property_damage'   => __( 'Damage to property (private use)', 'eurocomply-product-liability' ),
			'data_loss'         => __( 'Loss or corruption of data (non-professional use)', 'eurocomply-product-liability' ),
			'medical_expenses'  => __( 'Medical expenses', 'eurocomply-product-liability' ),
		);
	}

	/**
	 * Defectiveness factors (Art. 7).
	 */
	public static function defectiveness_factors() : array {
		return array(
			'presentation'         => __( 'Presentation, instructions, warnings', 'eurocomply-product-liability' ),
			'reasonable_use'       => __( 'Reasonably foreseeable use & misuse', 'eurocomply-product-liability' ),
			'self_learning'        => __( 'Effect of self-learning / continued AI behaviour', 'eurocomply-product-liability' ),
			'connection'           => __( 'Effect on / by other products (interoperation)', 'eurocomply-product-liability' ),
			'point_in_time'        => __( 'Time when product was placed on market / put into service', 'eurocomply-product-liability' ),
			'product_safety'       => __( 'Product-safety requirements (incl. cybersecurity)', 'eurocomply-product-liability' ),
			'authority_intervention' => __( 'Recall / corrective measures by competent authority', 'eurocomply-product-liability' ),
			'specific_expectations'  => __( 'Specific expectations of end-users', 'eurocomply-product-liability' ),
			'software_update'        => __( 'Lack of software updates / security patches', 'eurocomply-product-liability' ),
		);
	}

	public static function defect_status() : array {
		return array(
			'received'      => __( 'Received', 'eurocomply-product-liability' ),
			'acknowledged'  => __( 'Acknowledged', 'eurocomply-product-liability' ),
			'investigating' => __( 'Investigating', 'eurocomply-product-liability' ),
			'no_defect'     => __( 'No defect found', 'eurocomply-product-liability' ),
			'mitigated'     => __( 'Mitigated (update / replace / refund)', 'eurocomply-product-liability' ),
			'recall'        => __( 'Recall triggered', 'eurocomply-product-liability' ),
			'closed'        => __( 'Closed', 'eurocomply-product-liability' ),
		);
	}

	public static function claim_status() : array {
		return array(
			'received'         => __( 'Received', 'eurocomply-product-liability' ),
			'in_review'        => __( 'In review', 'eurocomply-product-liability' ),
			'evidence_disclosed' => __( 'Evidence disclosed (Art. 9)', 'eurocomply-product-liability' ),
			'settled'          => __( 'Settled', 'eurocomply-product-liability' ),
			'litigated'        => __( 'In litigation', 'eurocomply-product-liability' ),
			'judgment'         => __( 'Judgment delivered', 'eurocomply-product-liability' ),
			'time_barred'      => __( 'Time-barred (Art. 17)', 'eurocomply-product-liability' ),
			'rejected'         => __( 'Rejected', 'eurocomply-product-liability' ),
			'closed'           => __( 'Closed', 'eurocomply-product-liability' ),
		);
	}

	public static function disclosure_status() : array {
		return array(
			'requested'  => __( 'Requested', 'eurocomply-product-liability' ),
			'court_ordered' => __( 'Court-ordered', 'eurocomply-product-liability' ),
			'disclosed'  => __( 'Disclosed', 'eurocomply-product-liability' ),
			'partial'    => __( 'Partially disclosed', 'eurocomply-product-liability' ),
			'refused'    => __( 'Refused', 'eurocomply-product-liability' ),
			'sealed'     => __( 'Sealed (confidential)', 'eurocomply-product-liability' ),
		);
	}

	public static function operator_roles() : array {
		return array(
			'manufacturer'           => __( 'Manufacturer', 'eurocomply-product-liability' ),
			'authorised_representative' => __( 'Authorised representative', 'eurocomply-product-liability' ),
			'importer'               => __( 'Importer', 'eurocomply-product-liability' ),
			'fulfilment_service'     => __( 'Fulfilment service provider', 'eurocomply-product-liability' ),
			'distributor'            => __( 'Distributor', 'eurocomply-product-liability' ),
			'online_platform'        => __( 'Online platform', 'eurocomply-product-liability' ),
			'substantial_modifier'   => __( 'Substantial modifier', 'eurocomply-product-liability' ),
		);
	}
}
