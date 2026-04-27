<?php
/**
 * Public shortcodes.
 *
 * [eurocomply_ai_disclosure]            Chatbot / AI-interaction disclosure (Art. 50(1)).
 * [eurocomply_ai_label id="123"]        Standalone AI label for a given post.
 * [eurocomply_ai_provider_list]         Public registry of providers used on this site.
 * [eurocomply_ai_policy]                Auto-generated policy section block.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

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
		add_shortcode( 'eurocomply_ai_disclosure', array( $this, 'render_disclosure' ) );
		add_shortcode( 'eurocomply_ai_label', array( $this, 'render_label' ) );
		add_shortcode( 'eurocomply_ai_provider_list', array( $this, 'render_provider_list' ) );
		add_shortcode( 'eurocomply_ai_policy', array( $this, 'render_policy' ) );
	}

	public function render_disclosure( $atts ) : string {
		$atts = shortcode_atts(
			array(
				'style' => 'banner', // banner | inline | modal-trigger
				'text'  => '',
			),
			(array) $atts,
			'eurocomply_ai_disclosure'
		);
		$s    = Settings::get();
		$text = '' !== (string) $atts['text'] ? wp_kses_post( (string) $atts['text'] ) : (string) $s['chatbot_disclosure_text'];

		ob_start();
		echo '<div class="eurocomply-aiact-disclosure eurocomply-aiact-disclosure--' . esc_attr( (string) $atts['style'] ) . '" role="note" aria-live="polite">';
		echo '<span class="eurocomply-aiact-disclosure__icon" aria-hidden="true">🤖</span> ';
		echo '<span class="eurocomply-aiact-disclosure__text">' . wp_kses_post( $text ) . '</span>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	public function render_label( $atts ) : string {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'eurocomply_ai_label' );
		$id   = (int) $atts['id'];
		if ( $id <= 0 ) {
			$id = (int) get_the_ID();
		}
		if ( $id <= 0 ) {
			return '';
		}
		$meta = PostMeta::get_for_post( $id );
		return ContentDisplay::label_html( $meta );
	}

	public function render_provider_list( $atts ) : string {
		$rows      = ProviderStore::all();
		$providers = Settings::ai_providers_known();
		$purposes  = Settings::ai_purposes();

		ob_start();
		echo '<div class="eurocomply-aiact-providers">';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No AI providers registered yet.', 'eurocomply-ai-act' ) . '</p>';
		} else {
			echo '<table class="eurocomply-aiact-table"><thead><tr>';
			foreach ( array( __( 'Tool', 'eurocomply-ai-act' ), __( 'Provider', 'eurocomply-ai-act' ), __( 'Model', 'eurocomply-ai-act' ), __( 'Purpose', 'eurocomply-ai-act' ), __( 'Vendor', 'eurocomply-ai-act' ), __( 'GPAI', 'eurocomply-ai-act' ) ) as $h ) {
				echo '<th>' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr>';
				printf( '<td>%s</td>', esc_html( (string) $r['label'] ) );
				printf( '<td>%s</td>', esc_html( (string) ( $providers[ $r['provider_slug'] ] ?? $r['provider_slug'] ) ) );
				printf( '<td>%s</td>', esc_html( (string) $r['model'] ) );
				$purpose_label = isset( $purposes[ $r['purpose'] ] ) ? (string) $purposes[ $r['purpose'] ] : (string) $r['purpose'];
				printf( '<td>%s</td>', esc_html( $purpose_label ) );
				$vendor = trim( (string) $r['vendor_legal_name'] . ( '' !== $r['country'] ? ' (' . $r['country'] . ')' : '' ) );
				printf( '<td>%s</td>', esc_html( $vendor ) );
				printf( '<td>%s</td>', $r['gpai'] ? esc_html__( 'Yes', 'eurocomply-ai-act' ) : '—' );
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	public function render_policy( $atts ) : string {
		return PolicyPageGenerator::render();
	}
}
