<?php
/**
 * Bridge from EuroComply GDPR DSAR (#11) to
 * EuroComply NIS2 & CRA (#12).
 *
 * Fires `eurocomply_dsar_breach_detected` when an operator flags a
 * DSAR request as breach-related, and listens for the matching
 * incident id pushed back by the NIS2 plugin via the
 * `eurocomply_dsar_breach_nis2_incident_id` filter.
 *
 * Degrades gracefully when the NIS2 plugin is not installed: the
 * action simply has no listeners and no incident is created.
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nis2Bridge {

	/**
	 * NIS2 plugin's bootstrap class as detected at runtime. Kept loose
	 * as a string (not a use statement) so this plugin can be
	 * activated and lint-clean even when wp-nis2-resilience isn't
	 * installed.
	 */
	private const NIS2_PLUGIN_FQN = '\\EuroComply\\NIS2\\Plugin';

	public static function register() : void {
		// Capture the new NIS2 incident id and persist it on the DSAR row.
		add_filter( 'eurocomply_dsar_breach_nis2_incident_id', array( __CLASS__, 'capture_incident_id' ), 10, 3 );
	}

	/**
	 * Whether the EuroComply NIS2 (#12) sister plugin is active.
	 */
	public static function nis2_active() : bool {
		return class_exists( self::NIS2_PLUGIN_FQN );
	}

	/**
	 * Set the breach flag on a DSAR row and notify the NIS2 plugin.
	 *
	 * Returns the same payload that was pushed onto the action so
	 * callers can record the cross-reference id back into the row.
	 *
	 * @return array{ok:bool,incident_id:int,nis2_active:bool}
	 */
	public static function flag_request_as_breach( int $request_id, string $summary = '' ) : array {
		$row = RequestStore::get( $request_id );
		if ( ! is_array( $row ) ) {
			return array(
				'ok'          => false,
				'incident_id' => 0,
				'nis2_active' => self::nis2_active(),
			);
		}

		$existing = (int) ( $row['nis2_incident_id'] ?? 0 );
		RequestStore::update( $request_id, array( 'breach_flag' => 1 ) );

		$payload = array(
			'request_type'         => (string) ( $row['request_type'] ?? 'access' ),
			'requester_email'      => (string) ( $row['requester_email'] ?? '' ),
			'requester_name'       => (string) ( $row['requester_name'] ?? '' ),
			'summary'              => $summary,
			'severity'             => 'high',
			'existing_incident_id' => $existing,
		);

		do_action( 'eurocomply_dsar_breach_detected', $request_id, $payload );

		// `capture_incident_id` writes the id back into the row by the
		// time this returns; re-read to surface the result.
		$row_after = RequestStore::get( $request_id );
		$incident  = is_array( $row_after ) ? (int) ( $row_after['nis2_incident_id'] ?? 0 ) : 0;

		return array(
			'ok'          => true,
			'incident_id' => $incident,
			'nis2_active' => self::nis2_active(),
		);
	}

	public static function unflag_request( int $request_id ) : bool {
		$row = RequestStore::get( $request_id );
		if ( ! is_array( $row ) ) {
			return false;
		}
		// The linked NIS2 incident is intentionally NOT deleted here:
		// once an incident has been logged for the 24h/72h clock it must
		// be closed in the NIS2 UI, not silently removed.
		return RequestStore::update( $request_id, array( 'breach_flag' => 0 ) );
	}

	/**
	 * Filter callback: persists the NIS2 incident id on the DSAR row.
	 *
	 * @param int                 $incident_id NIS2 incident id created by the bridge.
	 * @param int                 $dsar_id     Source DSAR request id.
	 * @param array<string,mixed> $payload     Original bridge payload.
	 */
	public static function capture_incident_id( int $incident_id, int $dsar_id, array $payload ) : int {
		unset( $payload );
		if ( $incident_id > 0 && $dsar_id > 0 ) {
			RequestStore::update( $dsar_id, array( 'nis2_incident_id' => $incident_id ) );
		}
		return $incident_id;
	}
}
