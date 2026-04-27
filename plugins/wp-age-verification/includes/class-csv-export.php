<?php
/**
 * CSV export of verification-log entries.
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

namespace EuroComply\AgeVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const ACTION = 'eurocomply_av_export';
	public const NONCE  = 'eurocomply_av_export_nonce';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function handle() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-age-verification' ) );
		}
		check_admin_referer( self::NONCE );

		$limit = License::is_pro() ? 5000 : 500;
		$rows  = VerificationStore::recent( $limit );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-av-verifications-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$fp = fopen( 'php://output', 'w' );
		if ( ! $fp ) {
			exit;
		}
		fputcsv(
			$fp,
			array(
				'id',
				'attempted_at',
				'user_id',
				'ip_hash',
				'country',
				'method',
				'declared_year',
				'computed_age',
				'required_age',
				'passed',
				'context',
				'context_ref',
				'session_token_hash',
				'user_agent',
			)
		);
		foreach ( $rows as $row ) {
			fputcsv(
				$fp,
				array(
					$row['id'] ?? '',
					$row['attempted_at'] ?? '',
					$row['user_id'] ?? '',
					$row['ip_hash'] ?? '',
					$row['country'] ?? '',
					$row['method'] ?? '',
					$row['declared_year'] ?? '',
					$row['computed_age'] ?? '',
					$row['required_age'] ?? '',
					$row['passed'] ?? '',
					$row['context'] ?? '',
					$row['context_ref'] ?? '',
					$row['session_token'] ?? '',
					$row['user_agent'] ?? '',
				)
			);
		}
		fclose( $fp );
		exit;
	}
}
