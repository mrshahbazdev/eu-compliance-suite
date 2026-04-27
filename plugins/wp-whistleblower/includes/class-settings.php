<?php
/**
 * Settings wrapper.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_wb_settings';

	/**
	 * @return array<string, array{label:string,reference:string}>
	 */
	public static function categories() : array {
		return array(
			'corruption'       => array( 'label' => __( 'Corruption / bribery', 'eurocomply-whistleblower' ),                'reference' => 'Art. 2(1)(a)(iii)' ),
			'fraud'            => array( 'label' => __( 'Fraud',                'eurocomply-whistleblower' ),                'reference' => 'Art. 2(1)(a)(i)'   ),
			'aml'              => array( 'label' => __( 'Money laundering / terrorist financing', 'eurocomply-whistleblower' ), 'reference' => 'Annex I.B' ),
			'harassment'       => array( 'label' => __( 'Harassment / discrimination',           'eurocomply-whistleblower' ), 'reference' => 'Art. 2(1)(a)' ),
			'safety'           => array( 'label' => __( 'Workplace safety',                      'eurocomply-whistleblower' ), 'reference' => 'Art. 2(1)(a)(iv)' ),
			'data_breach'      => array( 'label' => __( 'Data protection / privacy breach',      'eurocomply-whistleblower' ), 'reference' => 'Art. 2(1)(a)(x)' ),
			'environmental'   => array( 'label' => __( 'Environmental protection',              'eurocomply-whistleblower' ), 'reference' => 'Art. 2(1)(a)(viii)' ),
			'public_health'    => array( 'label' => __( 'Public health',                         'eurocomply-whistleblower' ), 'reference' => 'Art. 2(1)(a)(ix)' ),
			'public_procurement' => array( 'label' => __( 'Public procurement',                  'eurocomply-whistleblower' ), 'reference' => 'Art. 2(1)(a)(i)' ),
			'financial'        => array( 'label' => __( 'Financial services / markets',          'eurocomply-whistleblower' ), 'reference' => 'Art. 2(1)(a)(ii)' ),
			'tax'              => array( 'label' => __( 'Tax fraud / evasion',                   'eurocomply-whistleblower' ), 'reference' => 'Annex I.A' ),
			'other'            => array( 'label' => __( 'Other breach of EU law',                'eurocomply-whistleblower' ), 'reference' => 'Art. 2(1)(a)' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'enable_anonymous'           => 1,
			'max_file_size_mb'           => 10,
			'allowed_file_types'         => 'pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx,txt,zip',
			'recipient_user_ids'         => array(),
			'ack_deadline_days'          => 7,
			'feedback_deadline_days'     => 90,
			'public_policy_page_id'      => 0,
			'organisation_name'          => get_bloginfo( 'name' ),
			'organisation_country'       => 'DE',
			'compliance_email'           => get_bloginfo( 'admin_email' ),
			'enable_external_referral'   => 1,
			'enable_status_check'        => 1,
			'rate_limit_per_hour'        => 5,
			'form_title'                 => __( 'Confidential reporting channel', 'eurocomply-whistleblower' ),
			'form_description'           => __( 'You may submit a report anonymously or with your contact details. Reports are accessible only to designated recipients and are subject to strict confidentiality (Directive (EU) 2019/1937 Art. 16).', 'eurocomply-whistleblower' ),
			'closure_text'               => __( 'Your report has been closed. You may submit a new report at any time using the public form.', 'eurocomply-whistleblower' ),
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
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) : array {
		$out = self::defaults();

		foreach ( array( 'enable_anonymous', 'enable_external_referral', 'enable_status_check' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}

		if ( isset( $input['max_file_size_mb'] ) ) {
			$out['max_file_size_mb'] = max( 1, min( 200, (int) $input['max_file_size_mb'] ) );
		}
		if ( isset( $input['rate_limit_per_hour'] ) ) {
			$out['rate_limit_per_hour'] = max( 1, min( 100, (int) $input['rate_limit_per_hour'] ) );
		}
		if ( isset( $input['allowed_file_types'] ) ) {
			$exts = preg_split( '/[\s,]+/', strtolower( (string) $input['allowed_file_types'] ) );
			$exts = array_values( array_filter( array_map( 'sanitize_key', (array) $exts ) ) );
			$out['allowed_file_types'] = implode( ',', array_slice( $exts, 0, 30 ) );
		}
		if ( isset( $input['recipient_user_ids'] ) && is_array( $input['recipient_user_ids'] ) ) {
			$ids = array_map( 'absint', $input['recipient_user_ids'] );
			$ids = array_values( array_unique( array_filter( $ids ) ) );
			$out['recipient_user_ids'] = $ids;
		}
		// Art. 9 deadlines are statutory; we hard-cap minimum but allow more generous windows.
		if ( isset( $input['ack_deadline_days'] ) ) {
			$out['ack_deadline_days'] = max( 1, min( 7, (int) $input['ack_deadline_days'] ) );
		}
		if ( isset( $input['feedback_deadline_days'] ) ) {
			$out['feedback_deadline_days'] = max( 30, min( 180, (int) $input['feedback_deadline_days'] ) );
		}
		if ( isset( $input['public_policy_page_id'] ) ) {
			$out['public_policy_page_id'] = max( 0, (int) $input['public_policy_page_id'] );
		}
		if ( isset( $input['organisation_name'] ) ) {
			$out['organisation_name'] = sanitize_text_field( (string) $input['organisation_name'] );
		}
		if ( isset( $input['organisation_country'] ) ) {
			$cc = strtoupper( (string) $input['organisation_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['organisation_country'] = $cc;
			}
		}
		if ( isset( $input['compliance_email'] ) ) {
			$e = sanitize_email( (string) $input['compliance_email'] );
			if ( $e && is_email( $e ) ) {
				$out['compliance_email'] = $e;
			}
		}
		foreach ( array( 'form_title', 'closure_text' ) as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( (string) $input[ $k ] );
			}
		}
		if ( isset( $input['form_description'] ) ) {
			$out['form_description'] = wp_kses_post( (string) $input['form_description'] );
		}
		return $out;
	}

	/**
	 * EU member-state external authority directory (Art. 11).
	 *
	 * @return array<string, array{authority:string,email:string,website:string}>
	 */
	public static function authorities() : array {
		return array(
			'AT' => array( 'authority' => 'WKStA',                                  'email' => 'whistleblower@wksta.justiz.gv.at', 'website' => 'https://www.wksta.justiz.gv.at/' ),
			'BE' => array( 'authority' => 'Federaal Ombudsman',                     'email' => 'integriteit@federaalombudsman.be', 'website' => 'https://www.federaalombudsman.be/' ),
			'BG' => array( 'authority' => 'Commission for Personal Data Protection','email' => 'kzld@cpdp.bg',                       'website' => 'https://www.cpdp.bg/' ),
			'CY' => array( 'authority' => 'Commissioner for Personal Data',         'email' => 'commissioner@dataprotection.gov.cy', 'website' => 'https://www.dataprotection.gov.cy/' ),
			'CZ' => array( 'authority' => 'Ministerstvo spravedlnosti',             'email' => 'oznamovatel@msp.justice.cz',         'website' => 'https://oznamovatel.justice.cz/' ),
			'DE' => array( 'authority' => 'Bundesamt für Justiz – externe Meldestelle', 'email' => 'externe-meldestelle@bfj.bund.de', 'website' => 'https://www.bundesjustizamt.de/' ),
			'DK' => array( 'authority' => 'Datatilsynet',                           'email' => 'whistleblower@dt.dk',                'website' => 'https://www.datatilsynet.dk/' ),
			'EE' => array( 'authority' => 'Õiguskantsleri Kantselei',               'email' => 'info@oiguskantsler.ee',              'website' => 'https://www.oiguskantsler.ee/' ),
			'EL' => array( 'authority' => 'National Transparency Authority',        'email' => 'epm@aead.gr',                        'website' => 'https://aead.gr/' ),
			'ES' => array( 'authority' => 'Autoridad Independiente de Protección', 'email' => 'aai-pi@aai.gob.es',                  'website' => 'https://www.aai-pi.es/' ),
			'FI' => array( 'authority' => 'Oikeuskansleri',                         'email' => 'kirjaamo@okv.fi',                    'website' => 'https://oikeuskansleri.fi/' ),
			'FR' => array( 'authority' => 'Défenseur des Droits',                   'email' => 'lanceurs.alerte@defenseurdesdroits.fr', 'website' => 'https://www.defenseurdesdroits.fr/' ),
			'HR' => array( 'authority' => 'Pučki pravobranitelj',                   'email' => 'info@ombudsman.hr',                  'website' => 'https://www.ombudsman.hr/' ),
			'HU' => array( 'authority' => 'Alapvető Jogok Biztosa',                 'email' => 'panasz@ajbh.hu',                     'website' => 'https://www.ajbh.hu/' ),
			'IE' => array( 'authority' => 'Office of the Protected Disclosures Commissioner', 'email' => 'info@odpc.ie',           'website' => 'https://www.odpc.ie/' ),
			'IT' => array( 'authority' => 'ANAC',                                   'email' => 'whistleblowing@anticorruzione.it',   'website' => 'https://www.anticorruzione.it/' ),
			'LT' => array( 'authority' => 'Generalinė prokuratūra',                 'email' => 'pranesk@prokuraturos.lt',            'website' => 'https://www.prokuraturos.lt/' ),
			'LU' => array( 'authority' => 'Ministère de la Justice',                'email' => 'lanceur-alerte@mj.etat.lu',          'website' => 'https://mj.gouvernement.lu/' ),
			'LV' => array( 'authority' => 'Valsts kanceleja',                       'email' => 'trauksme@mk.gov.lv',                 'website' => 'https://www.trauksmecelejs.lv/' ),
			'MT' => array( 'authority' => 'Office of the Whistleblowing Reports Unit', 'email' => 'whistleblowing@gov.mt',           'website' => 'https://justice.gov.mt/' ),
			'NL' => array( 'authority' => 'Huis voor Klokkenluiders',               'email' => 'info@huisvoorklokkenluiders.nl',     'website' => 'https://www.huisvoorklokkenluiders.nl/' ),
			'PL' => array( 'authority' => 'Rzecznik Praw Obywatelskich',            'email' => 'sygnalisci@brpo.gov.pl',              'website' => 'https://bip.brpo.gov.pl/' ),
			'PT' => array( 'authority' => 'Mecanismo Nacional Anticorrupção',       'email' => 'mna@cprc.gov.pt',                    'website' => 'https://mnac.gov.pt/' ),
			'RO' => array( 'authority' => 'Avertizori de integritate – ANI',        'email' => 'avertizori@integritate.eu',          'website' => 'https://www.integritate.eu/' ),
			'SE' => array( 'authority' => 'Arbetsmiljöverket',                      'email' => 'visselblasare@av.se',                'website' => 'https://www.visselblasare.se/' ),
			'SI' => array( 'authority' => 'Komisija za preprečevanje korupcije',    'email' => 'anti.korupcija@kpk-rs.si',           'website' => 'https://www.kpk-rs.si/' ),
			'SK' => array( 'authority' => 'Úrad na ochranu oznamovateľov',          'email' => 'info@oznamovatelia.sk',              'website' => 'https://www.oznamovatelia.sk/' ),
		);
	}
}
