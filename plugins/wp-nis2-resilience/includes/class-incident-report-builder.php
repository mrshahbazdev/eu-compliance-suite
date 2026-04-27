<?php
/**
 * Builds incident-report payloads for the four NIS2 Art. 23 stages.
 *
 * Produces a plain-text template + a machine-readable JSON that an admin
 * can send to the national CSIRT by email or copy into the CSIRT's own
 * web portal. This plugin deliberately does not POST to any CSIRT API —
 * every CSIRT has a different workflow, and regulators expect the
 * reporting entity to review the report before submission.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IncidentReportBuilder {

	public const STAGES = array( 'early_warning', 'notification', 'intermediate', 'final' );

	/**
	 * @return array{ok:bool,message:string,subject:string,body:string,json:string}
	 */
	public static function build( int $incident_id, string $stage ) : array {
		$incident = IncidentStore::get( $incident_id );
		if ( ! $incident ) {
			return array( 'ok' => false, 'message' => __( 'Incident not found.', 'eurocomply-nis2' ), 'subject' => '', 'body' => '', 'json' => '' );
		}
		if ( ! in_array( $stage, self::STAGES, true ) ) {
			return array( 'ok' => false, 'message' => __( 'Unknown report stage.', 'eurocomply-nis2' ), 'subject' => '', 'body' => '', 'json' => '' );
		}

		$s     = Settings::get();
		$csirt = CsirtDirectory::for_country( (string) ( $s['csirt_country'] ?? 'EU' ) );

		$subject = sprintf(
			/* translators: 1: stage label, 2: incident id, 3: organisation */
			__( 'NIS2 %1$s — incident #%2$d from %3$s', 'eurocomply-nis2' ),
			self::stage_label( $stage ),
			$incident_id,
			(string) $s['organisation_name']
		);

		$body  = self::body_for( $stage, $incident, $s, $csirt );
		$json  = self::json_for( $stage, $incident, $s, $csirt );

		return array(
			'ok'      => true,
			'message' => '',
			'subject' => $subject,
			'body'    => $body,
			'json'    => $json,
		);
	}

	/**
	 * @param array<string,mixed> $incident
	 * @param array<string,mixed> $s
	 * @param array{name:string,website:string,email:string,portal:string}|null $csirt
	 */
	private static function body_for( string $stage, array $incident, array $s, ?array $csirt ) : string {
		$title     = (string) $incident['title'];
		$category  = (string) $incident['category'];
		$severity  = (string) $incident['severity'];
		$aware_at  = (string) ( $incident['aware_at'] ?? '' );
		$impact    = (string) ( $incident['impact_summary'] ?? '' );
		$affected  = (string) ( $incident['affected_systems'] ?? '' );
		$users     = (int) ( $incident['affected_users_estimate'] ?? 0 );
		$cause     = (string) ( $incident['root_cause'] ?? '' );
		$mitig     = (string) ( $incident['mitigation'] ?? '' );
		$case_ref  = (string) ( $incident['csirt_case_ref'] ?? '' );

		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: stage label, 2: organisation name */
			__( '%1$s — submitted by %2$s pursuant to NIS2 Art. 23.', 'eurocomply-nis2' ),
			strtoupper( self::stage_label( $stage ) ),
			(string) $s['organisation_name']
		);
		$lines[] = '';
		$lines[] = sprintf( __( 'Entity type: %s', 'eurocomply-nis2' ), self::entity_label( (string) $s['entity_type'] ) );
		$lines[] = sprintf( __( 'Sector: %s', 'eurocomply-nis2' ), (string) $s['sector'] );
		$lines[] = sprintf( __( 'Security contact: %s', 'eurocomply-nis2' ), (string) $s['security_contact_email'] );
		if ( $csirt ) {
			$lines[] = sprintf( __( 'Addressed CSIRT: %1$s (%2$s)', 'eurocomply-nis2' ), (string) $csirt['name'], (string) $csirt['email'] );
		}
		$lines[] = '';
		$lines[] = sprintf( __( 'Incident ID (internal): %d', 'eurocomply-nis2' ), (int) $incident['id'] );
		$lines[] = sprintf( __( 'Title: %s', 'eurocomply-nis2' ), $title );
		$lines[] = sprintf( __( 'Category: %s', 'eurocomply-nis2' ), $category );
		$lines[] = sprintf( __( 'Severity: %s', 'eurocomply-nis2' ), $severity );
		$lines[] = sprintf( __( 'Aware since: %s', 'eurocomply-nis2' ), $aware_at ?: '—' );
		if ( '' !== $case_ref ) {
			$lines[] = sprintf( __( 'CSIRT case reference: %s', 'eurocomply-nis2' ), $case_ref );
		}

		switch ( $stage ) {
			case 'early_warning':
				$lines[] = '';
				$lines[] = __( '— Early warning (Art. 23(4)(a)) —', 'eurocomply-nis2' );
				$lines[] = __( 'This early warning is submitted within 24h of awareness. It indicates whether the significant incident is suspected to be caused by unlawful or malicious acts, or could have a cross-border impact.', 'eurocomply-nis2' );
				$lines[] = '';
				$lines[] = sprintf( __( 'Initial impact summary: %s', 'eurocomply-nis2' ), $impact ?: __( 'Under assessment.', 'eurocomply-nis2' ) );
				break;
			case 'notification':
				$lines[] = '';
				$lines[] = __( '— Incident notification (Art. 23(4)(b)) —', 'eurocomply-nis2' );
				$lines[] = __( 'This notification is submitted within 72h of awareness. It updates the early warning with a severity and impact assessment and indicators of compromise where available.', 'eurocomply-nis2' );
				$lines[] = '';
				$lines[] = sprintf( __( 'Impact: %s', 'eurocomply-nis2' ), $impact );
				$lines[] = sprintf( __( 'Affected systems: %s', 'eurocomply-nis2' ), $affected );
				$lines[] = sprintf( __( 'Estimated affected users: %d', 'eurocomply-nis2' ), $users );
				break;
			case 'intermediate':
				$lines[] = '';
				$lines[] = __( '— Intermediate report (Art. 23(4)(c)) —', 'eurocomply-nis2' );
				$lines[] = __( 'This intermediate report is submitted upon CSIRT request or at 30 days from awareness, providing status updates on the incident and on the measures adopted.', 'eurocomply-nis2' );
				$lines[] = '';
				$lines[] = sprintf( __( 'Root cause (preliminary): %s', 'eurocomply-nis2' ), $cause );
				$lines[] = sprintf( __( 'Mitigation to date: %s', 'eurocomply-nis2' ), $mitig );
				break;
			case 'final':
				$lines[] = '';
				$lines[] = __( '— Final report (Art. 23(4)(d)) —', 'eurocomply-nis2' );
				$lines[] = __( 'This final report is submitted within 30 days of the incident being handled. It gives a detailed description of the incident, its severity and impact, the type of threat or root cause that likely triggered it, applied and ongoing mitigation measures, and the cross-border impact, if any.', 'eurocomply-nis2' );
				$lines[] = '';
				$lines[] = sprintf( __( 'Impact: %s', 'eurocomply-nis2' ), $impact );
				$lines[] = sprintf( __( 'Root cause: %s', 'eurocomply-nis2' ), $cause );
				$lines[] = sprintf( __( 'Mitigation: %s', 'eurocomply-nis2' ), $mitig );
				$lines[] = sprintf( __( 'Resolved at: %s', 'eurocomply-nis2' ), (string) ( $incident['resolved_at'] ?? '—' ) );
				break;
		}

		$lines[] = '';
		$lines[] = __( 'Notes:', 'eurocomply-nis2' );
		$lines[] = (string) ( $incident['notes'] ?? '' );
		$lines[] = '';
		$lines[] = __( 'This report was generated by EuroComply NIS2 & CRA.', 'eurocomply-nis2' );

		return implode( "\n", $lines );
	}

	/**
	 * @param array<string,mixed> $incident
	 * @param array<string,mixed> $s
	 * @param array{name:string,website:string,email:string,portal:string}|null $csirt
	 */
	private static function json_for( string $stage, array $incident, array $s, ?array $csirt ) : string {
		$payload = array(
			'schema'        => 'eurocomply-nis2-report-1',
			'stage'         => $stage,
			'generated_at'  => gmdate( 'c' ),
			'entity'        => array(
				'organisation_name' => (string) $s['organisation_name'],
				'type'              => (string) $s['entity_type'],
				'sector'            => (string) $s['sector'],
				'csirt_country'     => (string) $s['csirt_country'],
				'security_contact'  => (string) $s['security_contact_email'],
			),
			'csirt'         => $csirt,
			'incident'      => array(
				'id'                      => (int) $incident['id'],
				'title'                   => (string) $incident['title'],
				'category'                => (string) $incident['category'],
				'severity'                => (string) $incident['severity'],
				'status'                  => (string) $incident['status'],
				'aware_at'                => (string) ( $incident['aware_at'] ?? '' ),
				'resolved_at'             => (string) ( $incident['resolved_at'] ?? '' ),
				'impact_summary'          => (string) ( $incident['impact_summary'] ?? '' ),
				'affected_systems'        => (string) ( $incident['affected_systems'] ?? '' ),
				'affected_users_estimate' => (int) ( $incident['affected_users_estimate'] ?? 0 ),
				'root_cause'              => (string) ( $incident['root_cause'] ?? '' ),
				'mitigation'              => (string) ( $incident['mitigation'] ?? '' ),
				'csirt_case_ref'          => (string) ( $incident['csirt_case_ref'] ?? '' ),
				'notes'                   => (string) ( $incident['notes'] ?? '' ),
			),
			'deadlines'     => IncidentStore::deadlines( $incident ),
		);
		return (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	public static function stage_label( string $stage ) : string {
		$labels = array(
			'early_warning' => __( 'Early warning (24h)', 'eurocomply-nis2' ),
			'notification'  => __( 'Incident notification (72h)', 'eurocomply-nis2' ),
			'intermediate'  => __( 'Intermediate report (30d)', 'eurocomply-nis2' ),
			'final'         => __( 'Final report (30d post-handling)', 'eurocomply-nis2' ),
		);
		return $labels[ $stage ] ?? $stage;
	}

	public static function entity_label( string $type ) : string {
		$labels = array(
			'essential'    => __( 'Essential entity', 'eurocomply-nis2' ),
			'important'    => __( 'Important entity', 'eurocomply-nis2' ),
			'out_of_scope' => __( 'Out of scope', 'eurocomply-nis2' ),
		);
		return $labels[ $type ] ?? $type;
	}
}
