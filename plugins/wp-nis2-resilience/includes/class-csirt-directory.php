<?php
/**
 * EU CSIRT / competent authority directory.
 *
 * These are the national computer security incident response teams under
 * NIS2 Art. 10 + 23. Contacts change rarely; verify on nis.europa.eu before
 * relying on them for statutory reporting.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsirtDirectory {

	/**
	 * @return array<string,array{name:string,website:string,email:string,portal:string}>
	 */
	public static function all() : array {
		return array(
			'AT' => array( 'name' => 'CERT.at / GovCERT Austria',      'website' => 'https://www.cert.at/',                 'email' => 'reports@cert.at',                    'portal' => '' ),
			'BE' => array( 'name' => 'CCB / CERT.be',                   'website' => 'https://ccb.belgium.be/',              'email' => 'cert@cert.be',                       'portal' => 'https://notifications.ccb.belgium.be/' ),
			'BG' => array( 'name' => 'CERT Bulgaria',                   'website' => 'https://www.govcert.bg/',              'email' => 'cert@govcert.bg',                    'portal' => '' ),
			'CY' => array( 'name' => 'CSIRT-CY',                        'website' => 'https://csirt.cy/',                    'email' => 'info@csirt.cy',                      'portal' => '' ),
			'CZ' => array( 'name' => 'NÚKIB / GovCERT.CZ',              'website' => 'https://www.nukib.cz/',                'email' => 'cert@nukib.cz',                      'portal' => '' ),
			'DE' => array( 'name' => 'BSI / CERT-Bund',                 'website' => 'https://www.bsi.bund.de/',             'email' => 'certbund@bsi.bund.de',               'portal' => 'https://www.bsi.bund.de/DE/Service-Navi/Kontakt' ),
			'DK' => array( 'name' => 'CFCS / CERTDK',                   'website' => 'https://www.cfcs.dk/',                 'email' => 'cert@cert.dk',                       'portal' => '' ),
			'EE' => array( 'name' => 'RIA / CERT-EE',                   'website' => 'https://www.ria.ee/en/cyber-security/cert-estonia', 'email' => 'cert@cert.ee',           'portal' => '' ),
			'ES' => array( 'name' => 'INCIBE-CERT / CCN-CERT',          'website' => 'https://www.incibe.es/',               'email' => 'incidencias@incibe-cert.es',         'portal' => 'https://www.incibe.es/incibe-cert/notificaciones' ),
			'FI' => array( 'name' => 'NCSC-FI',                         'website' => 'https://www.kyberturvallisuuskeskus.fi/', 'email' => 'cert@traficom.fi',                'portal' => '' ),
			'FR' => array( 'name' => 'ANSSI / CERT-FR',                 'website' => 'https://www.cert.ssi.gouv.fr/',        'email' => 'cert-fr.cossi@ssi.gouv.fr',          'portal' => '' ),
			'GR' => array( 'name' => 'Hellenic CSIRT',                  'website' => 'https://csirt.cd.mil.gr/',             'email' => 'csirt@csirt.gr',                     'portal' => '' ),
			'HR' => array( 'name' => 'CERT.HR / SOA',                   'website' => 'https://www.cert.hr/',                 'email' => 'ncert@cert.hr',                      'portal' => '' ),
			'HU' => array( 'name' => 'NKI / GovCERT-Hungary',           'website' => 'https://nki.gov.hu/',                  'email' => 'csirt@nki.gov.hu',                   'portal' => '' ),
			'IE' => array( 'name' => 'NCSC-IE',                         'website' => 'https://www.ncsc.gov.ie/',             'email' => 'certreport@decc.gov.ie',             'portal' => '' ),
			'IT' => array( 'name' => 'ACN / CSIRT-Italia',              'website' => 'https://www.csirt.gov.it/',            'email' => 'info@csirt.gov.it',                  'portal' => 'https://www.csirt.gov.it/notifica-incidente' ),
			'LT' => array( 'name' => 'NCSC-LT / CERT-LT',               'website' => 'https://www.nksc.lt/',                 'email' => 'cert@cert.lt',                       'portal' => '' ),
			'LU' => array( 'name' => 'CIRCL',                           'website' => 'https://www.circl.lu/',                'email' => 'info@circl.lu',                      'portal' => '' ),
			'LV' => array( 'name' => 'CERT.LV',                         'website' => 'https://cert.lv/',                     'email' => 'cert@cert.lv',                       'portal' => '' ),
			'MT' => array( 'name' => 'CSIRT Malta',                     'website' => 'https://www.maltacip.gov.mt/',         'email' => 'csirt.malta@gov.mt',                 'portal' => '' ),
			'NL' => array( 'name' => 'NCSC-NL',                         'website' => 'https://www.ncsc.nl/',                 'email' => 'cert@ncsc.nl',                       'portal' => 'https://www.ncsc.nl/contact/melding-doen' ),
			'PL' => array( 'name' => 'CERT Polska',                     'website' => 'https://www.cert.pl/',                 'email' => 'info@cert.pl',                       'portal' => 'https://incydent.cert.pl/' ),
			'PT' => array( 'name' => 'CNCS / CERT.PT',                  'website' => 'https://www.cncs.gov.pt/',             'email' => 'cert@cert.pt',                       'portal' => '' ),
			'RO' => array( 'name' => 'DNSC / CERT-RO',                  'website' => 'https://dnsc.ro/',                     'email' => 'cert@dnsc.ro',                       'portal' => '' ),
			'SE' => array( 'name' => 'CERT-SE',                         'website' => 'https://www.cert.se/',                 'email' => 'cert@cert.se',                       'portal' => '' ),
			'SI' => array( 'name' => 'SI-CERT',                         'website' => 'https://www.cert.si/',                 'email' => 'cert@cert.si',                       'portal' => '' ),
			'SK' => array( 'name' => 'NBU / SK-CERT',                   'website' => 'https://www.sk-cert.sk/',              'email' => 'incident@nbu.gov.sk',                'portal' => '' ),
			'EU' => array( 'name' => 'ENISA (CSIRTs Network)',          'website' => 'https://www.enisa.europa.eu/',         'email' => 'csirt-liaison@enisa.europa.eu',      'portal' => '' ),
		);
	}

	/**
	 * @return array{name:string,website:string,email:string,portal:string}|null
	 */
	public static function for_country( string $cc ) : ?array {
		$cc  = strtoupper( $cc );
		$all = self::all();
		return $all[ $cc ] ?? null;
	}
}
