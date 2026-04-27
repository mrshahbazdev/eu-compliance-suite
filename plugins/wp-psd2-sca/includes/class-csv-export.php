<?php
/**
 * CSV / XML / JSON export.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_psd2_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_psd2_export',      array( $this, 'handle_csv' ) );
		add_action( 'admin_post_eurocomply_psd2_export_xml',  array( $this, 'handle_xml' ) );
		add_action( 'admin_post_eurocomply_psd2_export_json', array( $this, 'handle_json' ) );
	}

	public function handle_csv() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'transactions';
		$max     = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-psd2-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-psd2-sca' ) );
		}

		switch ( $dataset ) {
			case 'consents':
				fputcsv( $out, array( 'id', 'created_at', 'expires_at', 'subject', 'tpp_id', 'scope', 'revoked' ) );
				foreach ( ConsentStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['created_at'],
						(string) $r['expires_at'],
						(string) $r['subject'],
						(string) $r['tpp_id'],
						(string) $r['scope'],
						(string) $r['revoked'],
					) );
				}
				break;

			case 'tpps':
				fputcsv( $out, array( 'id', 'country', 'name', 'role', 'authorisation_id', 'competent_authority', 'contact_email', 'website' ) );
				foreach ( TppStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['country'],
						(string) $r['name'],
						(string) $r['role'],
						(string) $r['authorisation_id'],
						(string) $r['competent_authority'],
						(string) $r['contact_email'],
						(string) $r['website'],
					) );
				}
				break;

			case 'fraud':
				fputcsv( $out, array( 'id', 'created_at', 'period', 'category', 'channel', 'amount', 'currency', 'reimbursed', 'on_time_refund' ) );
				foreach ( FraudStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['created_at'],
						(string) $r['period'],
						(string) $r['category'],
						(string) $r['channel'],
						(string) $r['amount'],
						(string) $r['currency'],
						(string) $r['reimbursed'],
						(string) $r['refunded_within_window'],
					) );
				}
				break;

			case 'transactions':
			default:
				fputcsv( $out, array( 'id', 'created_at', 'period', 'order_ref', 'amount', 'currency', 'provider', 'sca_required', 'exemption', '3ds_status', '3ds_version', 'outcome', 'country', 'risk_score' ) );
				foreach ( TransactionStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['created_at'],
						(string) $r['period'],
						(string) $r['order_ref'],
						(string) $r['amount'],
						(string) $r['currency'],
						(string) $r['provider'],
						(string) $r['sca_required'],
						(string) $r['exemption'],
						(string) $r['three_ds_status'],
						(string) $r['three_ds_version'],
						(string) $r['outcome'],
						(string) $r['country'],
						(string) $r['risk_score'],
					) );
				}
				break;
		}
		fclose( $out );
		exit;
	}

	public function handle_xml() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$period = isset( $_POST['period'] ) ? sanitize_text_field( (string) $_POST['period'] ) : Settings::current_period();
		if ( ! preg_match( '/^\d{4}-Q[1-4]$/', $period ) ) {
			$period = Settings::current_period();
		}
		$res = ReportBuilder::build( $period );
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-psd2-fraud-' . $period . '.xml"' );
		echo (string) $res['xml']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function handle_json() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$period = isset( $_POST['period'] ) ? sanitize_text_field( (string) $_POST['period'] ) : Settings::current_period();
		if ( ! preg_match( '/^\d{4}-Q[1-4]$/', $period ) ) {
			$period = Settings::current_period();
		}
		$res = ReportBuilder::build( $period );
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-psd2-fraud-' . $period . '.json"' );
		echo (string) $res['payload']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
