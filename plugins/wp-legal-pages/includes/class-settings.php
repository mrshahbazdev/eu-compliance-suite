<?php
/**
 * Settings model — persists business info used to fill legal templates.
 *
 * @package EuroComply\LegalPages
 */

namespace EuroComply\LegalPages;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION_KEY = 'eurocomply_legal_settings';

	/**
	 * Field definitions — drive form rendering AND template substitution.
	 *
	 * Each key is referenced as `{{field_name}}` inside template files.
	 *
	 * @return array<string,array{label:string,type:string,required?:bool,help?:string,options?:array<string,string>,group:string}>
	 */
	public function fields() {
		$legal_forms = array(
			'sole'     => __( 'Sole proprietorship / Einzelunternehmen', 'eurocomply-legal' ),
			'gbr'      => __( 'GbR / civil-law partnership', 'eurocomply-legal' ),
			'ohg'      => __( 'OHG / general partnership', 'eurocomply-legal' ),
			'kg'       => __( 'KG / limited partnership', 'eurocomply-legal' ),
			'gmbh'     => __( 'GmbH / LLC', 'eurocomply-legal' ),
			'ug'       => __( 'UG (haftungsbeschränkt)', 'eurocomply-legal' ),
			'ag'       => __( 'AG / public limited', 'eurocomply-legal' ),
			'ltd'      => __( 'Ltd. / Limited', 'eurocomply-legal' ),
			'other'    => __( 'Other', 'eurocomply-legal' ),
		);

		$countries = array(
			'DE' => __( 'Germany (DE)', 'eurocomply-legal' ),
			'AT' => __( 'Austria (AT)', 'eurocomply-legal' ),
			'CH' => __( 'Switzerland (CH)', 'eurocomply-legal' ),
		);

		// Pro-only countries.
		$pro_countries = array(
			'FR' => 'France (FR) — Pro',
			'NL' => 'Netherlands (NL) — Pro',
			'IT' => 'Italy (IT) — Pro',
			'ES' => 'Spain (ES) — Pro',
			'BE' => 'Belgium (BE) — Pro',
			'PL' => 'Poland (PL) — Pro',
			'SE' => 'Sweden (SE) — Pro',
			'DK' => 'Denmark (DK) — Pro',
			'FI' => 'Finland (FI) — Pro',
			'IE' => 'Ireland (IE) — Pro',
			'PT' => 'Portugal (PT) — Pro',
			'CZ' => 'Czechia (CZ) — Pro',
			'HU' => 'Hungary (HU) — Pro',
			'RO' => 'Romania (RO) — Pro',
			'GR' => 'Greece (GR) — Pro',
			'SK' => 'Slovakia (SK) — Pro',
			'SI' => 'Slovenia (SI) — Pro',
			'HR' => 'Croatia (HR) — Pro',
			'BG' => 'Bulgaria (BG) — Pro',
			'EE' => 'Estonia (EE) — Pro',
			'LV' => 'Latvia (LV) — Pro',
			'LT' => 'Lithuania (LT) — Pro',
			'LU' => 'Luxembourg (LU) — Pro',
			'MT' => 'Malta (MT) — Pro',
			'CY' => 'Cyprus (CY) — Pro',
		);

		return array(
			// --- Country --------------------------------------------------------
			'country'            => array(
				'label'    => __( 'Country of jurisdiction', 'eurocomply-legal' ),
				'type'     => 'select',
				'options'  => $countries + $pro_countries,
				'required' => true,
				'group'    => 'country',
				'help'     => __( 'Country whose laws govern your business. Free tier: DE, AT, CH. All other countries require Pro.', 'eurocomply-legal' ),
			),

			// --- Company --------------------------------------------------------
			'legal_form'         => array(
				'label'    => __( 'Legal form', 'eurocomply-legal' ),
				'type'     => 'select',
				'options'  => $legal_forms,
				'required' => true,
				'group'    => 'company',
			),
			'company_name'       => array(
				'label'    => __( 'Company name (as registered)', 'eurocomply-legal' ),
				'type'     => 'text',
				'required' => true,
				'group'    => 'company',
			),
			'trade_name'         => array(
				'label' => __( 'Trade name / DBA (optional)', 'eurocomply-legal' ),
				'type'  => 'text',
				'group' => 'company',
			),

			// --- Address --------------------------------------------------------
			'street'             => array(
				'label'    => __( 'Street + house number', 'eurocomply-legal' ),
				'type'     => 'text',
				'required' => true,
				'group'    => 'address',
			),
			'postal_code'        => array(
				'label'    => __( 'Postal code', 'eurocomply-legal' ),
				'type'     => 'text',
				'required' => true,
				'group'    => 'address',
			),
			'city'               => array(
				'label'    => __( 'City', 'eurocomply-legal' ),
				'type'     => 'text',
				'required' => true,
				'group'    => 'address',
			),

			// --- Representatives ------------------------------------------------
			'representative'     => array(
				'label'    => __( 'Authorised representative(s)', 'eurocomply-legal' ),
				'type'     => 'text',
				'required' => true,
				'help'     => __( 'E.g. Managing Director / Geschäftsführer name(s). Comma-separated.', 'eurocomply-legal' ),
				'group'    => 'reps',
			),

			// --- Contact --------------------------------------------------------
			'email'              => array(
				'label'    => __( 'Contact e-mail', 'eurocomply-legal' ),
				'type'     => 'email',
				'required' => true,
				'group'    => 'contact',
			),
			'phone'              => array(
				'label' => __( 'Phone (international format)', 'eurocomply-legal' ),
				'type'  => 'text',
				'group' => 'contact',
			),
			'fax'                => array(
				'label' => __( 'Fax (optional)', 'eurocomply-legal' ),
				'type'  => 'text',
				'group' => 'contact',
			),

			// --- Registry -------------------------------------------------------
			'registry_court'     => array(
				'label' => __( 'Registry court (e.g. Amtsgericht Berlin)', 'eurocomply-legal' ),
				'type'  => 'text',
				'help'  => __( 'Required for GmbH/UG/AG/OHG/KG (Germany) and comparable forms.', 'eurocomply-legal' ),
				'group' => 'registry',
			),
			'registry_number'    => array(
				'label' => __( 'Registry number (e.g. HRB 123456)', 'eurocomply-legal' ),
				'type'  => 'text',
				'group' => 'registry',
			),
			'vat_id'             => array(
				'label' => __( 'VAT ID / USt-IdNr. (e.g. DE123456789)', 'eurocomply-legal' ),
				'type'  => 'text',
				'group' => 'registry',
			),
			'tax_number'         => array(
				'label' => __( 'Tax number / Steuernummer (optional)', 'eurocomply-legal' ),
				'type'  => 'text',
				'group' => 'registry',
			),

			// --- Responsible for content (§18 MStV DE) --------------------------
			'responsible_name'   => array(
				'label' => __( 'Person responsible for content (§18 MStV)', 'eurocomply-legal' ),
				'type'  => 'text',
				'help'  => __( 'If different from authorised representative. Required for editorial content in Germany.', 'eurocomply-legal' ),
				'group' => 'responsible',
			),
			'responsible_addr'   => array(
				'label' => __( 'Responsible person address (if different)', 'eurocomply-legal' ),
				'type'  => 'textarea',
				'group' => 'responsible',
			),

			// --- Professional (regulated professions) ---------------------------
			'professional_chamber' => array(
				'label' => __( 'Professional chamber (regulated professions only)', 'eurocomply-legal' ),
				'type'  => 'text',
				'help'  => __( 'E.g. Rechtsanwaltskammer Berlin, Ärztekammer Hamburg.', 'eurocomply-legal' ),
				'group' => 'professional',
			),
			'professional_title' => array(
				'label' => __( 'Professional title + awarding country', 'eurocomply-legal' ),
				'type'  => 'text',
				'group' => 'professional',
			),

			// --- EU ODR / Dispute resolution -----------------------------------
			'dispute_participation' => array(
				'label'   => __( 'EU online-dispute-resolution participation', 'eurocomply-legal' ),
				'type'    => 'select',
				'options' => array(
					'none'      => __( 'Not obliged / not willing', 'eurocomply-legal' ),
					'obliged'   => __( 'Obliged (consumer business)', 'eurocomply-legal' ),
					'voluntary' => __( 'Voluntary participation', 'eurocomply-legal' ),
				),
				'group'   => 'dispute',
			),
		);
	}

	public function groups() {
		return array(
			'country'      => __( 'Country', 'eurocomply-legal' ),
			'company'      => __( 'Company', 'eurocomply-legal' ),
			'address'      => __( 'Address', 'eurocomply-legal' ),
			'reps'         => __( 'Representatives', 'eurocomply-legal' ),
			'contact'      => __( 'Contact', 'eurocomply-legal' ),
			'registry'     => __( 'Registry & tax', 'eurocomply-legal' ),
			'responsible'  => __( 'Responsible for content', 'eurocomply-legal' ),
			'professional' => __( 'Regulated profession', 'eurocomply-legal' ),
			'dispute'      => __( 'Dispute resolution', 'eurocomply-legal' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		// Fill defaults.
		foreach ( $this->fields() as $key => $def ) {
			if ( ! isset( $stored[ $key ] ) ) {
				$stored[ $key ] = '';
			}
		}
		return $stored;
	}

	public function get( $key, $default = '' ) {
		$all = $this->get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Sanitise and persist a settings array.
	 *
	 * @param array<string,mixed> $raw
	 */
	public function save( array $raw ) {
		$clean  = array();
		$fields = $this->fields();
		foreach ( $fields as $key => $def ) {
			$value = isset( $raw[ $key ] ) ? $raw[ $key ] : '';
			switch ( $def['type'] ) {
				case 'email':
					$clean[ $key ] = sanitize_email( $value );
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( $value );
					break;
				case 'select':
					$options = isset( $def['options'] ) ? array_keys( $def['options'] ) : array();
					$clean[ $key ] = in_array( $value, $options, true ) ? $value : '';
					break;
				default:
					$clean[ $key ] = sanitize_text_field( $value );
			}
		}

		// Preserve site-integration flags that are not part of the field schema.
		if ( ! empty( $raw['footer_links_enabled'] ) ) {
			$clean['footer_links_enabled'] = '1';
		}

		update_option( self::OPTION_KEY, $clean, false );
		return $clean;
	}

	/**
	 * Validate settings and return missing required fields.
	 *
	 * @return array<int,string> list of missing field keys
	 */
	public function missing_required() {
		$missing = array();
		$all     = $this->get_all();
		foreach ( $this->fields() as $key => $def ) {
			if ( empty( $def['required'] ) ) {
				continue;
			}
			if ( '' === trim( (string) ( $all[ $key ] ?? '' ) ) ) {
				$missing[] = $key;
			}
		}
		return $missing;
	}
}
