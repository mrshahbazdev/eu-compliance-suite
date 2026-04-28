<?php
/**
 * Sister-plugin bridge #7: AI Act (#14) ↔ DSA (#9).
 *
 * Listens for DSA statement-of-reasons records issued against AI-Act
 * marked posts and writes a cross-reference entry into the AI Act
 * disclosure log so the AI provenance audit trail captures every
 * subsequent moderation action.
 *
 * Degrades gracefully when DSA Transparency plugin is not active.
 *
 * @package EuroComply\AiAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DsaBridge {

	private const STATEMENT_STORE_FQN = '\\EuroComply\\DSA\\StatementStore';

	public static function register() : void {
		add_action( 'eurocomply_dsa_statement_recorded', array( __CLASS__, 'on_statement_recorded' ), 10, 2 );
	}

	public static function is_active() : bool {
		return class_exists( self::STATEMENT_STORE_FQN );
	}

	/**
	 * @param array<string,mixed> $row
	 */
	public static function on_statement_recorded( int $statement_id, $row ) : void {
		if ( $statement_id <= 0 || ! is_array( $row ) ) {
			return;
		}
		$post_id = isset( $row['target_post_id'] ) ? (int) $row['target_post_id'] : 0;
		if ( $post_id <= 0 ) {
			return;
		}

		$meta = PostMeta::get_for_post( $post_id );
		if ( empty( $meta['generated'] ) ) {
			return;
		}

		DisclosureLog::record(
			array(
				'post_id'  => $post_id,
				'action'   => 'dsa_statement_issued',
				'provider' => isset( $meta['provider'] ) ? (string) $meta['provider'] : '',
				'purpose'  => 'dsa-art-17:' . ( isset( $row['restriction_type'] ) ? sanitize_key( (string) $row['restriction_type'] ) : 'unknown' ),
				'user_id'  => isset( $row['issued_by'] ) ? (int) $row['issued_by'] : get_current_user_id(),
			)
		);
	}

	/**
	 * Count AI-marked posts that have at least one DSA statement of reasons
	 * issued against them in the given window. Returns 0 when DSA plugin
	 * is not active.
	 */
	public static function dsa_decisions_against_marked_posts( int $since_ts, int $until_ts ) : int {
		if ( ! self::is_active() ) {
			return 0;
		}
		$fqn = self::STATEMENT_STORE_FQN;
		if ( ! is_callable( array( $fqn, 'ai_generated_count' ) ) ) {
			return 0;
		}
		return (int) call_user_func( array( $fqn, 'ai_generated_count' ), $since_ts, $until_ts );
	}
}
