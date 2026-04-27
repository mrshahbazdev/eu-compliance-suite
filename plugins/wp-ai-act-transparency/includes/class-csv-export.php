<?php
/**
 * CSV export.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	public const ACTION = 'eurocomply_ai_act_csv';
	public const NONCE  = 'eurocomply_ai_act_csv';

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

	public static function url( string $dataset = 'posts' ) : string {
		return wp_nonce_url(
			add_query_arg( 'dataset', $dataset, admin_url( 'admin-post.php?action=' . self::ACTION ) ),
			self::NONCE,
			'_wpnonce'
		);
	}

	public function handle() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-ai-act' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$dataset = isset( $_GET['dataset'] ) ? sanitize_key( (string) $_GET['dataset'] ) : 'posts';
		$limit   = License::is_pro() ? 5000 : 500;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-ai-act-' . $dataset . '-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$fp = fopen( 'php://output', 'w' );
		if ( ! $fp ) {
			exit;
		}

		if ( 'providers' === $dataset ) {
			fputcsv( $fp, array( 'id', 'created_at', 'label', 'provider_slug', 'model', 'purpose', 'country', 'vendor_legal_name', 'gpai', 'high_risk' ) );
			foreach ( ProviderStore::all( $limit ) as $r ) {
				fputcsv( $fp, array(
					(int) $r['id'],
					(string) $r['created_at'],
					(string) $r['label'],
					(string) $r['provider_slug'],
					(string) $r['model'],
					(string) $r['purpose'],
					(string) $r['country'],
					(string) $r['vendor_legal_name'],
					(int) $r['gpai'],
					(int) $r['high_risk'],
				) );
			}
		} elseif ( 'log' === $dataset ) {
			fputcsv( $fp, array( 'id', 'occurred_at', 'post_id', 'action', 'provider', 'purpose', 'user_id', 'user_login' ) );
			foreach ( DisclosureLog::recent( $limit ) as $r ) {
				fputcsv( $fp, array(
					(int) $r['id'],
					(string) $r['occurred_at'],
					(int) $r['post_id'],
					(string) $r['action'],
					(string) $r['provider'],
					(string) $r['purpose'],
					(int) $r['user_id'],
					(string) $r['user_login'],
				) );
			}
		} else {
			fputcsv( $fp, array( 'post_id', 'title', 'post_type', 'status', 'provider', 'model', 'purpose', 'human_edited', 'deepfake', 'c2pa_url' ) );
			$ids = PostMeta::ai_marked_post_ids( $limit );
			foreach ( $ids as $pid ) {
				$m = PostMeta::get_for_post( (int) $pid );
				$p = get_post( (int) $pid );
				fputcsv( $fp, array(
					(int) $pid,
					$p instanceof \WP_Post ? (string) $p->post_title : '',
					$p instanceof \WP_Post ? (string) $p->post_type : '',
					$p instanceof \WP_Post ? (string) $p->post_status : '',
					(string) $m['provider'],
					(string) $m['model'],
					(string) $m['purpose'],
					$m['human_edited'] ? 1 : 0,
					$m['deepfake'] ? 1 : 0,
					(string) $m['c2pa_url'],
				) );
			}
		}

		fclose( $fp );
		exit;
	}
}
