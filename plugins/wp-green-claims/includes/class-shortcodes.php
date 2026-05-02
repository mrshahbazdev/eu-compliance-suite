<?php
/**
 * Public-facing shortcodes.
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {

	private static ?Shortcodes $instance = null;

	public static function instance() : Shortcodes {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_shortcode( 'eurocomply_gc_substantiation', array( $this, 'substantiation' ) );
		add_shortcode( 'eurocomply_gc_durability', array( $this, 'durability' ) );
		add_shortcode( 'eurocomply_gc_disclaimer', array( $this, 'disclaimer' ) );
		add_shortcode( 'eurocomply_gc_labels', array( $this, 'labels' ) );
	}

	public function substantiation( $atts = array(), $content = '' ) : string {
		$a       = shortcode_atts( array( 'product_id' => 0 ), is_array( $atts ) ? $atts : array() );
		$product = (int) $a['product_id'];
		if ( ! $product ) {
			$product = (int) get_queried_object_id();
		}
		if ( ! $product ) {
			return '';
		}
		$rows = ClaimStore::by_product( $product, 'verified' );
		if ( empty( $rows ) ) {
			return '<p class="eurocomply-gc-empty">' . esc_html__( 'No verified environmental claims for this product yet.', 'eurocomply-green-claims' ) . '</p>';
		}
		$types = Settings::evidence_types();
		$out   = '<table class="eurocomply-gc-substantiation"><thead><tr><th>' . esc_html__( 'Claim', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'Evidence', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'Verifier', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'Verified', 'eurocomply-green-claims' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$evidence = isset( $types[ $r['evidence_type'] ] ) ? $types[ $r['evidence_type'] ] : (string) $r['evidence_type'];
			$evidence = '' !== (string) $r['evidence_url']
				? '<a href="' . esc_url( (string) $r['evidence_url'] ) . '" rel="noopener nofollow">' . esc_html( $evidence ) . '</a>'
				: esc_html( $evidence );
			$out     .= '<tr><td>' . esc_html( (string) $r['claim_text'] ) . '</td><td>' . $evidence . '</td><td>' . esc_html( (string) $r['verifier'] ) . '</td><td>' . esc_html( (string) ( $r['verified_at'] ?? '' ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	public function durability( $atts = array(), $content = '' ) : string {
		$a       = shortcode_atts( array( 'post_id' => 0 ), is_array( $atts ) ? $atts : array() );
		$post_id = (int) $a['post_id'];
		if ( ! $post_id ) {
			$post_id = (int) get_queried_object_id();
		}
		if ( ! $post_id ) {
			return '';
		}
		$m = ProductMeta::get_meta( $post_id );
		if ( 0 === $m['durability_months'] && 0 === $m['software_update_years'] && 0 === $m['repairability_score'] && 0 === $m['guarantee_months'] ) {
			return '';
		}
		$out = '<dl class="eurocomply-gc-durability">';
		if ( $m['durability_months'] > 0 ) {
			$out .= '<dt>' . esc_html__( 'Expected durability', 'eurocomply-green-claims' ) . '</dt><dd>' . sprintf( esc_html( _n( '%d month', '%d months', $m['durability_months'], 'eurocomply-green-claims' ) ), $m['durability_months'] ) . '</dd>';
		}
		if ( $m['software_update_years'] > 0 ) {
			$out .= '<dt>' . esc_html__( 'Software / security updates', 'eurocomply-green-claims' ) . '</dt><dd>' . sprintf( esc_html( _n( '%d year', '%d years', $m['software_update_years'], 'eurocomply-green-claims' ) ), $m['software_update_years'] ) . '</dd>';
		}
		if ( $m['repairability_score'] > 0 ) {
			$out .= '<dt>' . esc_html__( 'Repairability score', 'eurocomply-green-claims' ) . '</dt><dd>' . sprintf( '%d / 10', $m['repairability_score'] ) . '</dd>';
		}
		if ( $m['guarantee_months'] > 0 ) {
			$out .= '<dt>' . esc_html__( 'Extended commercial guarantee', 'eurocomply-green-claims' ) . '</dt><dd>' . sprintf( esc_html( _n( '%d month', '%d months', $m['guarantee_months'], 'eurocomply-green-claims' ) ), $m['guarantee_months'] ) . '</dd>';
		}
		$out .= '</dl>';
		return $out;
	}

	public function disclaimer( $atts = array(), $content = '' ) : string {
		return '<p class="eurocomply-gc-static-disclaimer"><small>' . esc_html__( 'Environmental claims on this site are substantiated in line with Dir. (EU) 2024/825 (Empowering Consumers for the Green Transition). Where third-party verification is pending, an explicit notice is shown.', 'eurocomply-green-claims' ) . '</small></p>';
	}

	public function labels( $atts = array(), $content = '' ) : string {
		$a    = shortcode_atts( array( 'eu_only' => '0' ), is_array( $atts ) ? $atts : array() );
		$rows = LabelStore::all();
		if ( '1' === (string) $a['eu_only'] ) {
			$rows = array_values( array_filter( $rows, static function ( $r ) {
				return ! empty( $r['recognized_eu'] );
			} ) );
		}
		if ( empty( $rows ) ) {
			return '';
		}
		$out = '<ul class="eurocomply-gc-labels">';
		foreach ( $rows as $r ) {
			$line = esc_html( (string) $r['label_name'] );
			if ( '' !== (string) $r['scheme_url'] ) {
				$line = '<a href="' . esc_url( (string) $r['scheme_url'] ) . '" rel="noopener nofollow">' . $line . '</a>';
			}
			$out .= '<li>' . $line . ( ! empty( $r['third_party_verified'] ) ? ' <span class="eurocomply-gc-3p">(' . esc_html__( '3rd-party verified', 'eurocomply-green-claims' ) . ')</span>' : '' ) . '</li>';
		}
		$out .= '</ul>';
		return $out;
	}
}
