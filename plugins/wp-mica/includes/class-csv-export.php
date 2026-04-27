<?php
/**
 * CSV / XML / JSON export.
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CsvExport {

	private const NONCE = 'eurocomply_mica_export';

	private static ?CsvExport $instance = null;

	public static function instance() : CsvExport {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_post_eurocomply_mica_export',     array( $this, 'handle_csv' ) );
		add_action( 'admin_post_eurocomply_mica_export_xml', array( $this, 'handle_xml' ) );
	}

	public function handle_csv() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$dataset = isset( $_POST['dataset'] ) ? sanitize_key( (string) $_POST['dataset'] ) : 'assets';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-mica-' . $dataset . '-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Unable to open output stream.', 'eurocomply-mica' ) );
		}
		switch ( $dataset ) {
			case 'whitepapers':
				fputcsv( $out, array( 'id', 'asset_id', 'version', 'article', 'notified_at', 'published_at', 'status' ) );
				foreach ( WhitepaperStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['asset_id'],
						(string) $r['version'],
						(string) $r['article'],
						(string) ( $r['notified_at'] ?? '' ),
						(string) ( $r['published_at'] ?? '' ),
						(string) $r['status'],
					) );
				}
				break;
			case 'comms':
				fputcsv( $out, array( 'id', 'asset_id', 'channel', 'audience', 'country', 'language', 'risk_warning', 'fair_clear', 'published_at' ) );
				foreach ( CommunicationStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['asset_id'],
						(string) $r['channel'],
						(string) $r['audience'],
						(string) $r['country'],
						(string) $r['language'],
						(string) $r['risk_warning'],
						(string) $r['fair_clear'],
						(string) ( $r['published_at'] ?? '' ),
					) );
				}
				break;
			case 'complaints':
				fputcsv( $out, array( 'id', 'received_at', 'ack_at', 'resolved_at', 'category', 'country', 'asset_id', 'status' ) );
				foreach ( ComplaintStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) ( $r['received_at'] ?? '' ),
						(string) ( $r['ack_at'] ?? '' ),
						(string) ( $r['resolved_at'] ?? '' ),
						(string) $r['category'],
						(string) $r['country'],
						(string) $r['asset_id'],
						(string) $r['status'],
					) );
				}
				break;
			case 'disclosures':
				fputcsv( $out, array( 'id', 'asset_id', 'kind', 'occurred_at', 'disclosed_at', 'delayed_until', 'notified_nca' ) );
				foreach ( DisclosureStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['asset_id'],
						(string) $r['kind'],
						(string) ( $r['occurred_at'] ?? '' ),
						(string) ( $r['disclosed_at'] ?? '' ),
						(string) ( $r['delayed_until'] ?? '' ),
						(string) $r['notified_nca'],
					) );
				}
				break;
			case 'assets':
			default:
				fputcsv( $out, array( 'id', 'name', 'ticker', 'category', 'significant', 'network', 'isin', 'pegged_to', 'status' ) );
				foreach ( AssetStore::all() as $r ) {
					fputcsv( $out, array(
						(string) $r['id'],
						(string) $r['name'],
						(string) $r['ticker'],
						(string) $r['category'],
						(string) $r['significant'],
						(string) $r['network'],
						(string) $r['isin'],
						(string) $r['pegged_to'],
						(string) $r['status'],
					) );
				}
				break;
		}
		fclose( $out );
		exit;
	}

	public function handle_xml() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 );
		}
		check_admin_referer( self::NONCE );
		$wp_id = isset( $_POST['whitepaper_id'] ) ? (int) $_POST['whitepaper_id'] : 0;
		$wp    = WhitepaperStore::get( $wp_id );
		if ( null === $wp ) {
			wp_die( esc_html__( 'White paper not found.', 'eurocomply-mica' ), 404 );
		}
		$asset = $wp['asset_id'] ? AssetStore::get( (int) $wp['asset_id'] ) : null;
		$s     = Settings::get();
		$xml   = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml  .= '<MicaWhitePaper xmlns="urn:mica:eurocomply:0.1" article="' . self::esc( (string) $wp['article'] ) . '" version="' . self::esc( (string) $wp['version'] ) . '">' . "\n";
		$xml  .= '  <Issuer>' . "\n";
		$xml  .= '    <Name>' . self::esc( (string) $s['entity_name'] ) . '</Name>' . "\n";
		$xml  .= '    <LEI>' . self::esc( (string) $s['entity_lei'] ) . '</LEI>' . "\n";
		$xml  .= '    <Country>' . self::esc( (string) $s['entity_country'] ) . '</Country>' . "\n";
		$xml  .= '    <Type>' . self::esc( (string) $s['entity_type'] ) . '</Type>' . "\n";
		$xml  .= '  </Issuer>' . "\n";
		if ( $asset ) {
			$xml .= '  <Asset id="' . (int) $asset['id'] . '" category="' . self::esc( (string) $asset['category'] ) . '">' . "\n";
			$xml .= '    <Name>' . self::esc( (string) $asset['name'] ) . '</Name>' . "\n";
			$xml .= '    <Ticker>' . self::esc( (string) $asset['ticker'] ) . '</Ticker>' . "\n";
			$xml .= '    <Network>' . self::esc( (string) $asset['network'] ) . '</Network>' . "\n";
			$xml .= '    <ISIN>' . self::esc( (string) $asset['isin'] ) . '</ISIN>' . "\n";
			$xml .= '    <PeggedTo>' . self::esc( (string) $asset['pegged_to'] ) . '</PeggedTo>' . "\n";
			$xml .= '  </Asset>' . "\n";
		}
		$xml .= '  <Summary>' . self::esc( (string) $wp['summary'] ) . '</Summary>' . "\n";
		$xml .= '  <Risks>' . self::esc( (string) $wp['risks'] ) . '</Risks>' . "\n";
		$xml .= '  <Rights>' . self::esc( (string) $wp['rights'] ) . '</Rights>' . "\n";
		$xml .= '  <DocumentURL>' . self::esc( (string) $wp['document_url'] ) . '</DocumentURL>' . "\n";
		$xml .= '  <NotifiedAt>' . self::esc( (string) ( $wp['notified_at'] ?? '' ) ) . '</NotifiedAt>' . "\n";
		$xml .= '  <PublishedAt>' . self::esc( (string) ( $wp['published_at'] ?? '' ) ) . '</PublishedAt>' . "\n";
		$xml .= '  <GeneratedAt>' . gmdate( 'Y-m-d\TH:i:s\Z' ) . '</GeneratedAt>' . "\n";
		$xml .= '</MicaWhitePaper>' . "\n";
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="eurocomply-mica-whitepaper-' . $wp_id . '.xml"' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private static function esc( string $s ) : string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
