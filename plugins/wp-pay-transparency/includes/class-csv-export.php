<?php
/**
 * CSV export.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_pt_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_pt_export', array( $this, 'handle' ) );
	}

	public function handle() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-pay-transparency' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'requests';
		$max     = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-pt-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-pay-transparency' ) );
		}

		switch ( $dataset ) {
			case 'categories':
				fputcsv( $out, array( 'id', 'slug', 'name', 'skills_level', 'effort_level', 'responsibility_level', 'working_conditions_level', 'pay_min', 'pay_max' ) );
				foreach ( CategoryStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['slug'],
						(string) $r['name'],
						(string) $r['skills_level'],
						(string) $r['effort_level'],
						(string) $r['responsibility_level'],
						(string) $r['working_conditions_level'],
						(string) $r['pay_min'],
						(string) $r['pay_max'],
					) );
				}
				break;

			case 'employees':
				$year = (int) Settings::get()['reporting_year'];
				fputcsv( $out, array( 'id', 'external_ref_hash', 'category_slug', 'gender', 'total_comp', 'hours_per_week', 'currency', 'year' ) );
				foreach ( EmployeeStore::for_year( $year, $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['external_ref_hash'],
						(string) $r['category_slug'],
						(string) $r['gender'],
						(string) $r['total_comp'],
						(string) $r['hours_per_week'],
						(string) $r['currency'],
						(string) $r['year'],
					) );
				}
				break;

			case 'reports':
				fputcsv( $out, array( 'id', 'created_at', 'year', 'employees_count', 'gap_overall_pct', 'gap_overall_median_pct', 'joint_assessment_required' ) );
				foreach ( ReportStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['created_at'],
						(string) $r['year'],
						(string) $r['employees_count'],
						(string) $r['gap_overall_pct'],
						(string) $r['gap_overall_median_pct'],
						(string) $r['joint_assessment_required'],
					) );
				}
				break;

			case 'requests':
			default:
				fputcsv( $out, array( 'id', 'created_at', 'updated_at', 'status', 'scope', 'category_slug', 'contact_email', 'responded_at' ) );
				foreach ( RequestStore::recent( $max ) as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['created_at'],
						(string) $r['updated_at'],
						(string) $r['status'],
						(string) $r['scope'],
						(string) $r['category_slug'],
						(string) $r['contact_email'],
						(string) ( $r['responded_at'] ?? '' ),
					) );
				}
				break;
		}

		fclose( $out );
		exit;
	}
}
