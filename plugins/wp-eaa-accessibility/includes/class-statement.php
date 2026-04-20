<?php
/**
 * EAA accessibility statement shortcode.
 *
 * @package EuroComply\Eaa
 */

declare( strict_types = 1 );

namespace EuroComply\Eaa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Statement {

	private static ?Statement $instance = null;

	public static function instance() : Statement {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() : void {
		add_shortcode( 'eurocomply_eaa_statement', array( $this, 'render_shortcode' ) );
	}

	public function render_shortcode( $atts = array() ) : string {
		$s      = Settings::get();
		$entity = (string) ( $s['statement_entity_name'] ?? '' );
		if ( '' === $entity ) {
			$entity = wp_parse_url( home_url(), PHP_URL_HOST ) ?: get_bloginfo( 'name' );
		}
		$email = (string) ( $s['statement_contact_email'] ?? '' );
		if ( '' === $email ) {
			$email = get_bloginfo( 'admin_email' );
		}
		$review = (string) ( $s['statement_last_review'] ?? '' );
		if ( '' === $review ) {
			$review = gmdate( 'Y-m-d' );
		}
		$conf_map = array(
			'full'    => __( 'Fully conformant with WCAG 2.1 level AA.', 'eurocomply-eaa' ),
			'partial' => __( 'Partially conformant with WCAG 2.1 level AA. Some content is not yet fully compliant; remediation is in progress.', 'eurocomply-eaa' ),
			'non'     => __( 'Non-conformant with WCAG 2.1 level AA. Accessibility remediation is in progress.', 'eurocomply-eaa' ),
		);
		$conf_key = (string) ( $s['statement_conformance'] ?? 'partial' );
		$conf     = $conf_map[ $conf_key ] ?? $conf_map['partial'];

		$out  = '<section class="eurocomply-eaa-statement" aria-labelledby="eurocomply-eaa-statement-title">';
		$out .= '<h2 id="eurocomply-eaa-statement-title">' . esc_html__( 'Accessibility statement', 'eurocomply-eaa' ) . '</h2>';
		$out .= '<p>' . esc_html( sprintf(
			/* translators: %s: entity / site name */
			__( '%s is committed to making its website accessible in accordance with the European Accessibility Act (Directive 2019/882) and WCAG 2.1 level AA.', 'eurocomply-eaa' ),
			$entity
		) ) . '</p>';
		$out .= '<h3>' . esc_html__( 'Compliance status', 'eurocomply-eaa' ) . '</h3>';
		$out .= '<p>' . esc_html( $conf ) . '</p>';
		$out .= '<h3>' . esc_html__( 'Feedback and contact', 'eurocomply-eaa' ) . '</h3>';
		$out .= '<p>' . esc_html__( 'If you notice an accessibility problem, please contact us at', 'eurocomply-eaa' ) . ' <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>.</p>';
		$out .= '<h3>' . esc_html__( 'Enforcement procedure', 'eurocomply-eaa' ) . '</h3>';
		$out .= '<p>' . esc_html__( 'If we fail to respond satisfactorily, you can contact the national enforcement body for the European Accessibility Act in your Member State.', 'eurocomply-eaa' ) . '</p>';
		$out .= '<p><small>' . esc_html( sprintf(
			/* translators: %s: review date */
			__( 'Statement last reviewed on %s.', 'eurocomply-eaa' ),
			$review
		) ) . '</small></p>';
		$out .= '</section>';

		return $out;
	}
}
