<?php
/**
 * Auto-create an "Accessibility statement" page on activation, containing the shortcode.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StatementPage {

	public const OPTION_PAGE_ID = 'eurocomply_eaa_statement_page_id';

	public static function maybe_create() : void {
		$existing = (int) get_option( self::OPTION_PAGE_ID, 0 );
		if ( $existing > 0 && 'page' === get_post_type( $existing ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Accessibility statement', 'eurocomply-eaa' ),
				'post_name'    => 'accessibility-statement',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[eurocomply_eaa_statement]',
			),
			false
		);
		if ( is_wp_error( $page_id ) || 0 === (int) $page_id ) {
			return;
		}
		update_option( self::OPTION_PAGE_ID, (int) $page_id );
	}

	public static function get_page_id() : int {
		return (int) get_option( self::OPTION_PAGE_ID, 0 );
	}
}
