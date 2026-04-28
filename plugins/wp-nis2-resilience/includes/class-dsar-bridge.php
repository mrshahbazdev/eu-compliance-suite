<?php
/**
 * Bridge from EuroComply GDPR DSAR (#11) to
 * EuroComply NIS2 & CRA (#12).
 *
 * GDPR Art. 33 obliges the controller to notify the supervisory
 * authority of a personal-data breach within 72 hours; NIS2 Art. 23
 * imposes a 24h early-warning + 72h notification chain on the same
 * security event when the entity is in scope. Operators routinely
 * mismanage this overlap because the two workflows live in separate
 * tools.
 *
 * This bridge listens for the action fired by the DSAR plugin when
 * an operator flags a request as breach-related and auto-creates a
 * matching incident in the NIS2 register so a single security event
 * carries both the GDPR-side audit trail and the NIS2-side deadline
 * tracker.
 *
 * The bridge degrades gracefully when DSAR is not installed: every
 * method is safe to call with no side effects.
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DsarBridge {

	/**
	 * GDPR DSAR's `RequestStore` class as detected at runtime. Kept
	 * loose as a string (not a use statement) so this plugin can be
	 * activated and lint-clean even when wp-gdpr-dsar isn't installed.
	 */
	private const DSAR_STORE_FQN = '\\EuroComply\\DSAR\\RequestStore';

	public static function register() : void {
		add_action( 'eurocomply_dsar_breach_detected', array( __CLASS__, 'on_breach_detected' ), 10, 2 );
	}

	/**
	 * Whether the GDPR DSAR (#11) sister plugin is installed.
	 */
	public static function dsar_active() : bool {
		return class_exists( self::DSAR_STORE_FQN );
	}

	/**
	 * Handler for `eurocomply_dsar_breach_detected`.
	 *
	 * Creates an NIS2 incident if the DSAR row doesn't already have
	 * one linked, and pushes the resulting incident id back to the
	 * DSAR side via the `eurocomply_dsar_breach_nis2_incident_id`
	 * filter (the DSAR plugin registers that filter to write the id
	 * into `wp_eurocomply_dsar_requests.nis2_incident_id`).
	 *
	 * @param int                 $dsar_id DSAR request row id.
	 * @param array<string,mixed> $payload Bridge payload from DSAR.
	 */
	public static function on_breach_detected( int $dsar_id, array $payload ) : void {
		if ( $dsar_id <= 0 ) {
			return;
		}
		// Don't double-create: payload may carry the existing linked id.
		$existing = isset( $payload['existing_incident_id'] ) ? (int) $payload['existing_incident_id'] : 0;
		if ( $existing > 0 && self::incident_exists( $existing ) ) {
			return;
		}

		$incident_id = self::create_incident_from_payload( $dsar_id, $payload );
		if ( $incident_id <= 0 ) {
			return;
		}

		// Surface the new incident id back to the DSAR plugin so it can
		// store the cross-reference. DSAR adds itself as the listener on
		// this filter; nothing happens if DSAR isn't installed.
		apply_filters( 'eurocomply_dsar_breach_nis2_incident_id', $incident_id, $dsar_id, $payload );
	}

	private static function incident_exists( int $id ) : bool {
		$row = IncidentStore::get( $id );
		return is_array( $row ) && isset( $row['id'] );
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private static function create_incident_from_payload( int $dsar_id, array $payload ) : int {
		$type     = isset( $payload['request_type'] ) ? (string) $payload['request_type'] : 'access';
		$email    = isset( $payload['requester_email'] ) ? (string) $payload['requester_email'] : '';
		$summary  = isset( $payload['summary'] ) ? (string) $payload['summary'] : '';
		$severity = isset( $payload['severity'] ) && in_array( (string) $payload['severity'], IncidentStore::SEVERITIES, true )
			? (string) $payload['severity']
			: 'high';

		$title = sprintf(
			/* translators: 1: dsar request type, 2: dsar id */
			__( 'GDPR personal-data breach (DSAR %1$s request #%2$d)', 'eurocomply-nis2' ),
			$type,
			$dsar_id
		);

		$impact = '' !== $summary
			? $summary
			: __( 'Auto-created from GDPR DSAR breach flag. Review the linked DSAR request for context.', 'eurocomply-nis2' );

		$notes = sprintf(
			/* translators: 1: dsar id, 2: requester email or empty, 3: ISO timestamp */
			__( 'Linked from EuroComply GDPR DSAR plugin (#11). DSAR request id: %1$d. Requester: %2$s. Bridged at: %3$s.', 'eurocomply-nis2' ),
			$dsar_id,
			'' !== $email ? $email : '(redacted)',
			current_time( 'mysql' )
		);

		return IncidentStore::create(
			array(
				'aware_at'                => current_time( 'mysql' ),
				'title'                   => $title,
				'category'                => 'breach',
				'severity'                => $severity,
				'status'                  => 'aware',
				'impact_summary'          => $impact,
				'affected_users_estimate' => isset( $payload['affected_users_estimate'] ) ? (int) $payload['affected_users_estimate'] : 0,
				'notes'                   => $notes,
			)
		);
	}
}
