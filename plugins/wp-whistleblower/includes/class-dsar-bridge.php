<?php
/**
 * Bridge from EuroComply Whistleblower (#15) to
 * EuroComply GDPR DSAR (#11).
 *
 * GDPR Art. 15 grants every reporter the right to know what data the
 * controller holds about them, including the bare fact that they have
 * filed a whistleblower report — but Directive (EU) 2019/1937 Art. 16
 * obliges the controller to keep the report body and any third-party
 * names confidential. This bridge reconciles the two:
 *
 *  1. It registers a WordPress core privacy exporter
 *     (`wp_privacy_personal_data_exporters`) that returns metadata-only
 *     entries (id, dates, status, category, subject) for every report
 *     whose identified-reporter contact email matches the DSAR
 *     requester. The DSAR plugin's ExportBuilder reads this filter via
 *     core machinery, so reports automatically appear in the access /
 *     portability ZIP without any DSAR-side changes. Bodies are
 *     intentionally redacted under Art. 16.
 *
 *  2. It lets an identified reporter who has just verified their
 *     follow-up token escalate the case into a formal GDPR access
 *     request via `create_dsar_for_report()`. The DSAR row is
 *     pre-marked verified=1 because the WB plugin has already
 *     authenticated the reporter via the token.
 *
 * Both directions degrade gracefully when the sister plugin is missing.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

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
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_privacy_exporter' ), 20 );
	}

	/**
	 * Whether the EuroComply GDPR DSAR (#11) sister plugin is active.
	 */
	public static function dsar_active() : bool {
		return class_exists( self::DSAR_STORE_FQN );
	}

	/**
	 * @param array<string,array<string,mixed>> $exporters
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_privacy_exporter( $exporters ) : array {
		if ( ! is_array( $exporters ) ) {
			$exporters = array();
		}
		$exporters['eurocomply-whistleblower'] = array(
			'exporter_friendly_name' => __( 'Whistleblower reports (metadata)', 'eurocomply-whistleblower' ),
			'callback'               => array( __CLASS__, 'export_for_email' ),
		);
		return $exporters;
	}

	/**
	 * Privacy exporter callback. Returns metadata-only entries; the
	 * report body is intentionally never included because Art. 16
	 * confidentiality also covers any third parties named within it.
	 *
	 * @return array{data:array<int,array<string,mixed>>,done:bool}
	 */
	public static function export_for_email( string $email_address, int $page = 1 ) : array {
		unset( $page );
		$reports = ReportStore::find_by_email( $email_address );
		$items   = array();
		$labels  = ReportStore::statuses();
		$cats    = Settings::categories();

		foreach ( $reports as $report ) {
			$report_id = (int) ( $report['id'] ?? 0 );
			if ( $report_id <= 0 ) {
				continue;
			}
			$category_label = isset( $cats[ (string) $report['category'] ]['label'] )
				? (string) $cats[ (string) $report['category'] ]['label']
				: (string) $report['category'];
			$status_label   = isset( $labels[ (string) $report['status'] ] )
				? (string) $labels[ (string) $report['status'] ]
				: (string) $report['status'];

			$items[] = array(
				'group_id'    => 'eurocomply-whistleblower',
				'group_label' => __( 'Whistleblower reports', 'eurocomply-whistleblower' ),
				'item_id'     => 'wb-report-' . $report_id,
				'data'        => array(
					array(
						'name'  => __( 'Report ID', 'eurocomply-whistleblower' ),
						'value' => '#' . $report_id,
					),
					array(
						'name'  => __( 'Submitted', 'eurocomply-whistleblower' ),
						'value' => (string) ( $report['created_at'] ?? '' ),
					),
					array(
						'name'  => __( 'Last updated', 'eurocomply-whistleblower' ),
						'value' => (string) ( $report['updated_at'] ?? '' ),
					),
					array(
						'name'  => __( 'Status', 'eurocomply-whistleblower' ),
						'value' => $status_label,
					),
					array(
						'name'  => __( 'Category', 'eurocomply-whistleblower' ),
						'value' => $category_label,
					),
					array(
						'name'  => __( 'Subject', 'eurocomply-whistleblower' ),
						'value' => (string) ( $report['subject'] ?? '' ),
					),
					array(
						'name'  => __( 'Acknowledged at', 'eurocomply-whistleblower' ),
						'value' => (string) ( $report['acknowledged_at'] ?? '' ),
					),
					array(
						'name'  => __( 'Feedback sent at', 'eurocomply-whistleblower' ),
						'value' => (string) ( $report['feedback_sent_at'] ?? '' ),
					),
					array(
						'name'  => __( 'Confidentiality notice', 'eurocomply-whistleblower' ),
						'value' => __( 'The body of the report is intentionally redacted from this export under Directive (EU) 2019/1937 Art. 16 (confidentiality protection of third parties named in the report). Contact the Designated Recipient for any further disclosure that is consistent with the protection of other persons.', 'eurocomply-whistleblower' ),
					),
				),
			);
		}

		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Create a GDPR DSAR access request from a verified WB reporter.
	 *
	 * The caller must have already authenticated the reporter via the
	 * follow-up token; this method only checks that the report has an
	 * email contact value to populate the DSAR row with.
	 *
	 * @return array{ok:bool,reason:string,request_id:int}
	 */
	public static function create_dsar_for_report( int $report_id ) : array {
		if ( ! self::dsar_active() ) {
			return array(
				'ok'         => false,
				'reason'     => 'dsar-missing',
				'request_id' => 0,
			);
		}
		$report = ReportStore::get( $report_id );
		if ( ! is_array( $report ) ) {
			return array(
				'ok'         => false,
				'reason'     => 'report-not-found',
				'request_id' => 0,
			);
		}
		if ( ! empty( $report['anonymous'] ) ) {
			return array(
				'ok'         => false,
				'reason'     => 'anonymous-reporter',
				'request_id' => 0,
			);
		}
		$email = sanitize_email( (string) ( $report['contact_value'] ?? '' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return array(
				'ok'         => false,
				'reason'     => 'no-email-on-record',
				'request_id' => 0,
			);
		}

		$store    = self::DSAR_STORE_FQN;
		$details  = sprintf(
			/* translators: %d: WB report id */
			__( 'Created via the EuroComply Whistleblower (#15) bridge from report #%d. Reporter authenticated via the follow-up token; verified flag set automatically.', 'eurocomply-whistleblower' ),
			$report_id
		);
		$now      = current_time( 'mysql' );
		$deadline = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );

		$request_id = (int) call_user_func(
			array( $store, 'record' ),
			array(
				'submitted_at'         => $now,
				'updated_at'           => $now,
				'deadline_at'          => $deadline,
				'request_type'         => 'access',
				'status'               => 'in_progress',
				'requester_email'      => $email,
				'requester_name'       => '',
				'verified'             => 1,
				'verification_token'   => '',
				'verification_expires' => $now,
				'ip_hash'              => '',
				'details'              => $details,
			)
		);

		return array(
			'ok'         => $request_id > 0,
			'reason'     => $request_id > 0 ? 'created' : 'create-failed',
			'request_id' => $request_id,
		);
	}
}
