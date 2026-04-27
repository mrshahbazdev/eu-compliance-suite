<?php
/**
 * Frontend rendering: AI label, JSON-LD, meta tag.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentDisplay {

	private static ?ContentDisplay $instance = null;

	public static function instance() : ContentDisplay {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'the_content', array( $this, 'inject_label' ), 9 );
		add_action( 'wp_head', array( $this, 'meta_tag' ), 5 );
		add_action( 'wp_head', array( $this, 'jsonld' ), 6 );
	}

	public function assets() : void {
		wp_enqueue_style( 'eurocomply-ai-act-public', EUROCOMPLY_AIACT_URL . 'assets/css/public.css', array(), EUROCOMPLY_AIACT_VERSION );
	}

	public function inject_label( string $content ) : string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = (int) get_the_ID();
		$meta    = PostMeta::get_for_post( $post_id );
		if ( ! $meta['generated'] && ! $meta['deepfake'] ) {
			return $content;
		}
		$s = Settings::get();
		if ( empty( $s['show_post_label'] ) ) {
			return $content;
		}
		$pos = (string) ( $s['auto_label_position'] ?? 'top' );
		if ( 'none' === $pos ) {
			return $content;
		}
		$label = self::label_html( $meta );
		if ( '' === $label ) {
			return $content;
		}
		switch ( $pos ) {
			case 'bottom':
				return $content . $label;
			case 'both':
				return $label . $content . $label;
			case 'top':
			default:
				return $label . $content;
		}
	}

	public static function label_html( array $meta ) : string {
		$s         = Settings::get();
		$providers = Settings::ai_providers_known();
		$purposes  = Settings::ai_purposes();

		$lines = array();
		if ( $meta['deepfake'] ) {
			$lines[] = (string) $s['label_text_deepfake'];
		} elseif ( $meta['generated'] ) {
			$lines[] = (string) $s['label_text_post'];
		}

		$details = array();
		if ( ! empty( $meta['provider'] ) && isset( $providers[ $meta['provider'] ] ) ) {
			$details[] = sprintf( /* translators: %s: provider name */ __( 'Provider: %s', 'eurocomply-ai-act' ), (string) $providers[ $meta['provider'] ] );
		}
		if ( ! empty( $meta['model'] ) ) {
			$details[] = sprintf( /* translators: %s: model name */ __( 'Model: %s', 'eurocomply-ai-act' ), (string) $meta['model'] );
		}
		if ( ! empty( $meta['purpose'] ) && isset( $purposes[ $meta['purpose'] ] ) ) {
			$details[] = sprintf( /* translators: %s: purpose */ __( 'Purpose: %s', 'eurocomply-ai-act' ), (string) $purposes[ $meta['purpose'] ] );
		}
		if ( ! empty( $meta['human_edited'] ) ) {
			$details[] = __( 'Reviewed by a human editor.', 'eurocomply-ai-act' );
		}

		if ( empty( $lines ) ) {
			return '';
		}

		$cls = $meta['deepfake'] ? 'eurocomply-aiact-label eurocomply-aiact-label--deepfake' : 'eurocomply-aiact-label';

		ob_start();
		echo '<aside class="' . esc_attr( $cls ) . '" role="note">';
		echo '<span class="eurocomply-aiact-label__icon" aria-hidden="true">⚠</span> ';
		echo '<span class="eurocomply-aiact-label__text">';
		echo wp_kses_post( implode( ' ', $lines ) );
		if ( ! empty( $details ) ) {
			echo ' <small class="eurocomply-aiact-label__details">' . esc_html( implode( ' · ', $details ) ) . '</small>';
		}
		if ( ! empty( $meta['c2pa_url'] ) ) {
			echo ' <small><a href="' . esc_url( (string) $meta['c2pa_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'C2PA manifest', 'eurocomply-ai-act' ) . '</a></small>';
		}
		if ( ! empty( $s['public_policy_page_id'] ) ) {
			$url = (string) get_permalink( (int) $s['public_policy_page_id'] );
			if ( '' !== $url ) {
				echo ' <small><a href="' . esc_url( $url ) . '">' . esc_html__( 'AI policy', 'eurocomply-ai-act' ) . '</a></small>';
			}
		}
		echo '</span></aside>';
		return (string) ob_get_clean();
	}

	public function meta_tag() : void {
		if ( ! is_singular() ) {
			return;
		}
		$s = Settings::get();
		if ( empty( $s['show_meta_tag'] ) ) {
			return;
		}
		$meta = PostMeta::get_for_post( (int) get_the_ID() );
		if ( ! $meta['generated'] && ! $meta['deepfake'] ) {
			return;
		}
		echo "\n<!-- EuroComply AI Act -->\n";
		echo '<meta name="ai-generated" content="' . ( $meta['generated'] ? 'true' : 'false' ) . '" />' . "\n";
		if ( $meta['deepfake'] ) {
			echo '<meta name="ai-deepfake" content="true" />' . "\n";
		}
		if ( ! empty( $meta['provider'] ) ) {
			echo '<meta name="ai-provider" content="' . esc_attr( (string) $meta['provider'] ) . '" />' . "\n";
		}
		if ( ! empty( $meta['model'] ) ) {
			echo '<meta name="ai-model" content="' . esc_attr( (string) $meta['model'] ) . '" />' . "\n";
		}
	}

	public function jsonld() : void {
		if ( ! is_singular() ) {
			return;
		}
		$s = Settings::get();
		if ( empty( $s['show_jsonld'] ) ) {
			return;
		}
		$meta = PostMeta::get_for_post( (int) get_the_ID() );
		if ( ! $meta['generated'] && ! $meta['deepfake'] ) {
			return;
		}
		$providers = Settings::ai_providers_known();
		$purposes  = Settings::ai_purposes();

		$ld = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'CreativeWork',
			'name'         => get_the_title(),
			'url'          => get_permalink(),
			'creator'      => array(
				'@type' => 'Organization',
				'name'  => (string) $s['organisation_name'],
			),
			'isAccessibleForFree' => true,
			'creativeWorkStatus'  => $meta['human_edited'] ? 'human-edited' : 'unedited',
			'maker'        => array(
				'@type' => 'SoftwareApplication',
				'name'  => isset( $providers[ $meta['provider'] ] ) ? (string) $providers[ $meta['provider'] ] : 'AI system',
			),
			'additionalProperty' => array_filter( array(
				array(
					'@type' => 'PropertyValue',
					'name'  => 'ai-generated',
					'value' => $meta['generated'] ? 'true' : 'false',
				),
				array(
					'@type' => 'PropertyValue',
					'name'  => 'ai-deepfake',
					'value' => $meta['deepfake'] ? 'true' : 'false',
				),
				! empty( $meta['model'] ) ? array(
					'@type' => 'PropertyValue',
					'name'  => 'ai-model',
					'value' => (string) $meta['model'],
				) : null,
				! empty( $meta['purpose'] ) && isset( $purposes[ $meta['purpose'] ] ) ? array(
					'@type' => 'PropertyValue',
					'name'  => 'ai-purpose',
					'value' => (string) $purposes[ $meta['purpose'] ],
				) : null,
				! empty( $meta['c2pa_url'] ) ? array(
					'@type' => 'PropertyValue',
					'name'  => 'c2pa-manifest',
					'value' => (string) $meta['c2pa_url'],
				) : null,
			) ),
		);

		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
