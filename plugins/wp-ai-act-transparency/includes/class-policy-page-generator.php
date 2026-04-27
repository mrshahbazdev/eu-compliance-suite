<?php
/**
 * Auto-generated AI transparency policy page content.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolicyPageGenerator {

	public static function render() : string {
		$s         = Settings::get();
		$providers = Settings::ai_providers_known();
		$purposes  = Settings::ai_purposes();
		$rows      = ProviderStore::all();

		ob_start();
		echo '<div class="eurocomply-aiact-policy">';
		echo '<h2>' . esc_html__( 'AI transparency policy', 'eurocomply-ai-act' ) . '</h2>';
		echo '<p>' . sprintf(
			/* translators: %s: organisation name */
			esc_html__( 'This policy explains how %s uses artificial intelligence systems and how the use of AI is disclosed to readers and visitors, in line with Article 50 of Regulation (EU) 2024/1689 (the EU AI Act).', 'eurocomply-ai-act' ),
			'<strong>' . esc_html( (string) $s['organisation_name'] ) . '</strong>'
		) . '</p>';

		echo '<h3>' . esc_html__( 'When and how we use AI', 'eurocomply-ai-act' ) . '</h3>';
		echo '<p>' . esc_html__( 'AI systems may be used in the production, editing, translation, summarisation or moderation of content, and in chatbots and recommendation systems on this website. When AI is used to produce or substantially edit content that is published as text, image, audio or video, that fact is disclosed visibly on the affected page and in the page metadata (HTML meta tag and JSON-LD CreativeWork node).', 'eurocomply-ai-act' ) . '</p>';

		echo '<h3>' . esc_html__( 'Tools and providers we use', 'eurocomply-ai-act' ) . '</h3>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No AI providers are currently registered.', 'eurocomply-ai-act' ) . '</p>';
		} else {
			echo '<table class="eurocomply-aiact-table"><thead><tr>';
			foreach ( array( __( 'Tool', 'eurocomply-ai-act' ), __( 'Provider', 'eurocomply-ai-act' ), __( 'Purpose', 'eurocomply-ai-act' ) ) as $h ) {
				echo '<th>' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr>';
				printf( '<td>%s</td>', esc_html( (string) $r['label'] ) );
				printf( '<td>%s</td>', esc_html( (string) ( $providers[ $r['provider_slug'] ] ?? $r['provider_slug'] ) ) );
				$purpose_label = isset( $purposes[ $r['purpose'] ] ) ? (string) $purposes[ $r['purpose'] ] : '';
				printf( '<td>%s</td>', esc_html( $purpose_label ) );
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h3>' . esc_html__( 'Chatbots and AI-driven interactions (Art. 50(1))', 'eurocomply-ai-act' ) . '</h3>';
		echo '<p>' . wp_kses_post( (string) $s['chatbot_disclosure_text'] ) . '</p>';

		echo '<h3>' . esc_html__( 'Synthetic media and deepfakes (Art. 50(3))', 'eurocomply-ai-act' ) . '</h3>';
		echo '<p>' . wp_kses_post( (string) $s['label_text_deepfake'] ) . '</p>';

		echo '<h3>' . esc_html__( 'Provenance and machine-readable markers', 'eurocomply-ai-act' ) . '</h3>';
		echo '<p>' . esc_html__( 'AI-generated images and media that ship with C2PA (Coalition for Content Provenance and Authenticity) manifests are linked from the per-page label. Pro deployments verify these manifests server-side; standard deployments display them as published by the upstream tool.', 'eurocomply-ai-act' ) . '</p>';

		echo '<h3>' . esc_html__( 'How to contact us', 'eurocomply-ai-act' ) . '</h3>';
		echo '<p>' . sprintf(
			/* translators: %s: contact email */
			esc_html__( 'For any question on this policy, contact %s.', 'eurocomply-ai-act' ),
			'<a href="mailto:' . esc_attr( (string) $s['contact_email'] ) . '">' . esc_html( (string) $s['contact_email'] ) . '</a>'
		) . '</p>';

		echo '</div>';
		return (string) ob_get_clean();
	}

	public static function ensure_page() : int {
		$s = Settings::get();
		$id = (int) $s['public_policy_page_id'];
		if ( $id > 0 && get_post( $id ) ) {
			return $id;
		}
		$page_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => __( 'AI transparency policy', 'eurocomply-ai-act' ),
			'post_content' => '<!-- wp:shortcode -->[eurocomply_ai_policy]<!-- /wp:shortcode -->',
		) );
		if ( is_wp_error( $page_id ) || 0 === $page_id ) {
			return 0;
		}
		$updated                          = $s;
		$updated['public_policy_page_id'] = (int) $page_id;
		update_option( Settings::OPTION_KEY, $updated, false );
		return (int) $page_id;
	}
}
