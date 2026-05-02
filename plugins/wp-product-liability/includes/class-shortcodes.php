<?php
/**
 * Public shortcodes.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

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
		add_shortcode( 'eurocomply_pl_policy', array( $this, 'policy' ) );
		add_shortcode( 'eurocomply_pl_defect_report', array( $this, 'defect_report' ) );
		add_shortcode( 'eurocomply_pl_register', array( $this, 'public_register' ) );
		add_action( 'admin_post_nopriv_eurocomply_pl_submit_defect', array( $this, 'submit_defect' ) );
		add_action( 'admin_post_eurocomply_pl_submit_defect', array( $this, 'submit_defect' ) );
	}

	public function policy() : string {
		$s   = Settings::get();
		$out = '<section class="eurocomply-pl-policy"><h2>' . esc_html__( 'Product liability statement', 'eurocomply-product-liability' ) . '</h2>';
		$out .= '<p>' . esc_html__( 'In accordance with Directive (EU) 2024/2853, the manufacturer is liable for damage caused by a defective product. Compensation may be claimed for personal injury (including medically-recognised psychological harm), property damage in private use, and the loss or corruption of personal data.', 'eurocomply-product-liability' ) . '</p>';
		$out .= '<h3>' . esc_html__( 'Manufacturer', 'eurocomply-product-liability' ) . '</h3><p>' . nl2br( esc_html( (string) $s['manufacturer_name'] . "\n" . $s['manufacturer_address'] . "\n" . $s['manufacturer_email'] ) ) . '</p>';
		if ( ! empty( $s['eu_representative'] ) ) {
			$out .= '<h3>' . esc_html__( 'EU representative', 'eurocomply-product-liability' ) . '</h3><p>' . nl2br( esc_html( (string) $s['eu_representative'] . "\n" . $s['eu_rep_address'] ) ) . '</p>';
		}
		if ( ! empty( $s['importer_name'] ) ) {
			$out .= '<h3>' . esc_html__( 'Importer', 'eurocomply-product-liability' ) . '</h3><p>' . nl2br( esc_html( (string) $s['importer_name'] . "\n" . $s['importer_address'] ) ) . '</p>';
		}
		$out .= '<h3>' . esc_html__( 'Limitation periods', 'eurocomply-product-liability' ) . '</h3><ul>';
		$out .= '<li>' . esc_html( sprintf( __( 'Claims expire %d years after the injured person became aware of the damage, the defect and the liable economic operator.', 'eurocomply-product-liability' ), 3 ) ) . '</li>';
		$out .= '<li>' . esc_html( sprintf( __( '%d-year overall window from when the product was placed on the market.', 'eurocomply-product-liability' ), (int) $s['limitation_years'] ) ) . '</li>';
		$out .= '<li>' . esc_html( sprintf( __( 'Extended to %d years for latent personal injury.', 'eurocomply-product-liability' ), (int) $s['latent_injury_years'] ) ) . '</li>';
		$out .= '</ul></section>';
		return $out;
	}

	public function defect_report() : string {
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$nonce  = wp_nonce_field( 'eurocomply_pl_submit_defect', '_eurocomply_pl_nonce', true, false );
		$dmg    = Settings::damage_types();
		$out    = '<form method="post" action="' . $action . '" class="eurocomply-pl-defect-form">';
		$out   .= '<input type="hidden" name="action" value="eurocomply_pl_submit_defect" />' . $nonce;
		$out   .= '<p style="display:none;"><label>Hp <input type="text" name="company_extra" value="" /></label></p>';
		$out   .= '<p><label>' . esc_html__( 'Product name / SKU', 'eurocomply-product-liability' ) . '<br /><input type="text" name="product_label" required /></label></p>';
		$out   .= '<p><label><input type="checkbox" name="anonymous" value="1" /> ' . esc_html__( 'Submit anonymously', 'eurocomply-product-liability' ) . '</label></p>';
		$out   .= '<p><label>' . esc_html__( 'Contact e-mail (optional)', 'eurocomply-product-liability' ) . '<br /><input type="email" name="email" /></label></p>';
		$out   .= '<p><label>' . esc_html__( 'Country', 'eurocomply-product-liability' ) . '<br /><input type="text" name="country" maxlength="2" /></label></p>';
		$out   .= '<p><label>' . esc_html__( 'Damage type', 'eurocomply-product-liability' ) . '<br /><select name="damage_type">';
		foreach ( $dmg as $k => $l ) {
			$out .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		$out .= '</select></label></p>';
		$out .= '<p><label>' . esc_html__( 'Severity', 'eurocomply-product-liability' ) . '<br /><select name="severity"><option>low</option><option>medium</option><option>high</option><option>critical</option></select></label></p>';
		$out .= '<p><label>' . esc_html__( 'Occurred on', 'eurocomply-product-liability' ) . '<br /><input type="date" name="occurred_at" /></label></p>';
		$out .= '<p><label>' . esc_html__( 'Description', 'eurocomply-product-liability' ) . '<br /><textarea name="summary" rows="5" cols="40" required></textarea></label></p>';
		$out .= '<p><button type="submit">' . esc_html__( 'Submit report', 'eurocomply-product-liability' ) . '</button></p>';
		$out .= '</form>';
		if ( isset( $_GET['eurocomply_pl_token'] ) ) {
			$tok = sanitize_text_field( wp_unslash( (string) $_GET['eurocomply_pl_token'] ) );
			$out .= '<div class="notice"><p>' . esc_html__( 'Thank you. Your follow-up token is:', 'eurocomply-product-liability' ) . ' <code>' . esc_html( $tok ) . '</code></p></div>';
		}
		return $out;
	}

	public function public_register() : string {
		$rows = array_slice( DefectStore::all(), 0, 50 );
		$out  = '<section class="eurocomply-pl-register"><h3>' . esc_html__( 'Public defect register', 'eurocomply-product-liability' ) . '</h3>';
		if ( empty( $rows ) ) {
			$out .= '<p>' . esc_html__( 'No reports recorded yet.', 'eurocomply-product-liability' ) . '</p>';
		} else {
			$out .= '<table><thead><tr><th>' . esc_html__( 'Reported', 'eurocomply-product-liability' ) . '</th><th>' . esc_html__( 'Damage', 'eurocomply-product-liability' ) . '</th><th>' . esc_html__( 'Severity', 'eurocomply-product-liability' ) . '</th><th>' . esc_html__( 'Status', 'eurocomply-product-liability' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$out .= '<tr><td>' . esc_html( (string) $r['created_at'] ) . '</td><td>' . esc_html( (string) $r['damage_type'] ) . '</td><td>' . esc_html( (string) $r['severity'] ) . '</td><td>' . esc_html( (string) $r['status'] ) . '</td></tr>';
			}
			$out .= '</tbody></table>';
		}
		return $out . '</section>';
	}

	public function submit_defect() : void {
		check_admin_referer( 'eurocomply_pl_submit_defect', '_eurocomply_pl_nonce' );
		if ( ! empty( $_POST['company_extra'] ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
			exit;
		}
		$res = DefectStore::insert(
			array(
				'product_label'      => sanitize_text_field( wp_unslash( (string) ( $_POST['product_label'] ?? '' ) ) ),
				'reporter_anonymous' => ! empty( $_POST['anonymous'] ),
				'reporter_email'     => sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ),
				'country'            => substr( strtoupper( sanitize_text_field( wp_unslash( (string) ( $_POST['country'] ?? '' ) ) ) ), 0, 2 ),
				'damage_type'        => sanitize_key( wp_unslash( (string) ( $_POST['damage_type'] ?? '' ) ) ),
				'severity'           => sanitize_key( wp_unslash( (string) ( $_POST['severity'] ?? 'medium' ) ) ),
				'occurred_at'        => sanitize_text_field( wp_unslash( (string) ( $_POST['occurred_at'] ?? '' ) ) ),
				'summary'            => wp_kses_post( wp_unslash( (string) ( $_POST['summary'] ?? '' ) ) ),
			)
		);
		$ref = wp_get_referer() ?: home_url( '/' );
		if ( ! empty( $res['token'] ) ) {
			$ref = add_query_arg( array( 'eurocomply_pl_token' => $res['token'] ), $ref );
		}
		wp_safe_redirect( $ref );
		exit;
	}
}
