<?php
/**
 * Settings wrapper.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION_KEY = 'eurocomply_ai_act_settings';

	/**
	 * @return array<string,string>
	 */
	public static function ai_purposes() : array {
		return array(
			'text_generation'      => __( 'Text generation', 'eurocomply-ai-act' ),
			'translation'          => __( 'Translation', 'eurocomply-ai-act' ),
			'summarization'        => __( 'Summarisation', 'eurocomply-ai-act' ),
			'image_generation'     => __( 'Image generation', 'eurocomply-ai-act' ),
			'image_editing'        => __( 'Image editing', 'eurocomply-ai-act' ),
			'video_generation'     => __( 'Video generation', 'eurocomply-ai-act' ),
			'audio_generation'     => __( 'Audio / voice generation', 'eurocomply-ai-act' ),
			'code_generation'      => __( 'Code generation', 'eurocomply-ai-act' ),
			'chatbot'              => __( 'Chatbot / conversational agent', 'eurocomply-ai-act' ),
			'recommendation'       => __( 'Recommendation system', 'eurocomply-ai-act' ),
			'classification'       => __( 'Classification / moderation', 'eurocomply-ai-act' ),
			'other'                => __( 'Other', 'eurocomply-ai-act' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	public static function ai_providers_known() : array {
		return array(
			'openai'      => 'OpenAI (GPT, DALL·E, Sora, Whisper)',
			'anthropic'   => 'Anthropic (Claude)',
			'google'      => 'Google (Gemini, Imagen, SynthID)',
			'mistral'     => 'Mistral AI',
			'meta'        => 'Meta (Llama)',
			'stability'   => 'Stability AI (Stable Diffusion)',
			'midjourney'  => 'Midjourney',
			'cohere'      => 'Cohere',
			'aleph_alpha' => 'Aleph Alpha',
			'huggingface' => 'Hugging Face',
			'azure_openai'=> 'Microsoft Azure OpenAI',
			'aws_bedrock' => 'Amazon Bedrock',
			'self_hosted' => __( 'Self-hosted (local model)', 'eurocomply-ai-act' ),
			'other'       => __( 'Other', 'eurocomply-ai-act' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults() : array {
		return array(
			'show_post_label'         => 1,
			'show_image_label'        => 1,
			'show_jsonld'             => 1,
			'show_meta_tag'           => 1,
			'enforce_default_disclosure' => 0,
			'auto_label_position'     => 'top',
			'public_policy_page_id'   => 0,
			'organisation_name'       => get_bloginfo( 'name' ),
			'organisation_country'    => 'DE',
			'contact_email'           => get_bloginfo( 'admin_email' ),
			'log_post_changes'        => 1,
			'public_post_types'       => array( 'post', 'page' ),
			'label_text_post'         => __( 'This article was created with the assistance of AI.', 'eurocomply-ai-act' ),
			'label_text_image'        => __( 'AI-generated image.', 'eurocomply-ai-act' ),
			'label_text_deepfake'     => __( 'Synthetic media — this content has been digitally generated or manipulated.', 'eurocomply-ai-act' ),
			'chatbot_disclosure_text' => __( 'You are interacting with an AI chatbot. Responses may be inaccurate. Type "human" any time to request a human operator.', 'eurocomply-ai-act' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get() : array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) : array {
		$out = self::defaults();

		foreach ( array( 'show_post_label', 'show_image_label', 'show_jsonld', 'show_meta_tag', 'enforce_default_disclosure', 'log_post_changes' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
		}

		if ( isset( $input['auto_label_position'] ) && in_array( (string) $input['auto_label_position'], array( 'top', 'bottom', 'both', 'none' ), true ) ) {
			$out['auto_label_position'] = (string) $input['auto_label_position'];
		}
		if ( isset( $input['public_policy_page_id'] ) ) {
			$out['public_policy_page_id'] = max( 0, (int) $input['public_policy_page_id'] );
		}
		if ( isset( $input['organisation_name'] ) ) {
			$out['organisation_name'] = sanitize_text_field( (string) $input['organisation_name'] );
		}
		if ( isset( $input['organisation_country'] ) ) {
			$cc = strtoupper( (string) $input['organisation_country'] );
			if ( preg_match( '/^[A-Z]{2}$/', $cc ) ) {
				$out['organisation_country'] = $cc;
			}
		}
		if ( isset( $input['contact_email'] ) ) {
			$e = sanitize_email( (string) $input['contact_email'] );
			if ( $e && is_email( $e ) ) {
				$out['contact_email'] = $e;
			}
		}
		if ( isset( $input['public_post_types'] ) && is_array( $input['public_post_types'] ) ) {
			$cleaned = array();
			foreach ( $input['public_post_types'] as $pt ) {
				$pt = sanitize_key( (string) $pt );
				if ( '' !== $pt ) {
					$cleaned[] = $pt;
				}
			}
			$out['public_post_types'] = array_values( array_unique( $cleaned ) );
		}
		foreach ( array( 'label_text_post', 'label_text_image', 'label_text_deepfake', 'chatbot_disclosure_text' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = wp_kses_post( (string) $input[ $key ] );
			}
		}

		return $out;
	}
}
