<?php
/**
 * Sister-plugin bridge #7: DSA (#9) ↔ AI Act (#14).
 *
 * When an admin issues a DSA statement of reasons against a post that
 * has been flagged AI-generated under AI Act Art. 50, this bridge
 * enriches the statement row with the AI provenance metadata so the
 * Article 24(2) transparency report can break decisions out by AI vs
 * human-authored content. Required for European Commission monitoring
 * under Reg. (EU) 2022/2065 Art. 24(5).
 *
 * Degrades gracefully when AI Act plugin is not active.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AiActBridge {

	private const POST_META_FQN = '\\EuroComply\\AIAct\\PostMeta';

	public static function register() : void {
		add_filter( 'eurocomply_dsa_statement_data', array( __CLASS__, 'enrich_statement' ), 10, 1 );
	}

	public static function is_active() : bool {
		return class_exists( self::POST_META_FQN );
	}

	/**
	 * @param array<string,mixed> $data
	 *
	 * @return array<string,mixed>
	 */
	public static function enrich_statement( $data ) : array {
		$data = (array) $data;
		if ( ! self::is_active() ) {
			return $data;
		}
		$post_id = isset( $data['target_post_id'] ) ? (int) $data['target_post_id'] : 0;
		if ( $post_id <= 0 ) {
			return $data;
		}

		$fqn = self::POST_META_FQN;
		if ( ! is_callable( array( $fqn, 'get_for_post' ) ) ) {
			return $data;
		}

		/** @var array<string,mixed> $meta */
		$meta = (array) call_user_func( array( $fqn, 'get_for_post' ), $post_id );
		if ( empty( $meta ) ) {
			return $data;
		}

		// Only set the AI columns when the post itself was marked as
		// AI-generated. Posts that were not flagged should leave the
		// columns NULL so transparency reports can distinguish "not AI"
		// from "AI status unknown" later if the AI Act plugin is
		// uninstalled.
		if ( empty( $meta['generated'] ) ) {
			return $data;
		}

		$data['ai_generated'] = 1;
		$data['ai_provider']  = isset( $meta['provider'] ) ? (string) $meta['provider'] : '';
		$data['ai_deepfake']  = ! empty( $meta['deepfake'] ) ? 1 : 0;

		// AI Act Art. 50(2) marker → DSA Art. 17(3)(b) "automated means
		// were used". Set automated_detection unless the operator has
		// explicitly opted out for this row.
		if ( ! array_key_exists( 'automated_detection', $data ) || null === $data['automated_detection'] ) {
			$data['automated_detection'] = 1;
		}

		return $data;
	}
}
