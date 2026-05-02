<?php
/**
 * Public-facing shortcodes.
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

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
		add_shortcode( 'eurocomply_csddd_policy', array( $this, 'policy' ) );
		add_shortcode( 'eurocomply_csddd_complaint_form', array( $this, 'complaint_form' ) );
		add_action( 'init', array( $this, 'maybe_handle_complaint' ) );
	}

	public function policy( $atts = array(), $content = '' ) : string {
		$d = Settings::get();
		$out  = '<div class="eurocomply-csddd-policy">';
		$out .= '<h2>' . esc_html__( 'Due-diligence statement (Dir. (EU) 2024/1760)', 'eurocomply-csddd' ) . '</h2>';
		$out .= '<p>' . sprintf( esc_html__( '%s applies a risk-based due-diligence framework across its chain of activities, in line with the Corporate Sustainability Due Diligence Directive.', 'eurocomply-csddd' ), esc_html( (string) $d['company_name'] ) ) . '</p>';
		$out .= '<ul>';
		$out .= '<li>' . esc_html__( 'Identification, prevention, mitigation, and where necessary remediation of adverse human-rights and environmental impacts (Art. 6).', 'eurocomply-csddd' ) . '</li>';
		$out .= '<li>' . esc_html__( 'Stakeholder notification mechanism via the complaint form below (Art. 14).', 'eurocomply-csddd' ) . '</li>';
		$out .= '<li>' . sprintf( esc_html__( 'Climate transition plan aligned with %s°C by %d (Art. 22).', 'eurocomply-csddd' ), esc_html( (string) $d['climate_target_celsius'] ), (int) $d['climate_target_year'] ) . '</li>';
		$out .= '<li>' . esc_html__( 'Annual due-diligence statement published in the company\'s sustainability report (Art. 16, CSRD-linked).', 'eurocomply-csddd' ) . '</li>';
		$out .= '</ul>';
		$out .= '</div>';
		return $out;
	}

	public function complaint_form( $atts = array(), $content = '' ) : string {
		$cats   = Settings::risk_categories();
		$nonce  = wp_create_nonce( 'eurocomply_csddd_complaint' );
		$action = esc_url( site_url( '/' ) );
		$status = $this->detect_status_message();

		$out = '<form method="post" action="' . $action . '" class="eurocomply-csddd-complaint-form">';
		$out .= '<input type="hidden" name="eurocomply_csddd_complaint" value="1">';
		$out .= '<input type="hidden" name="_eurocomply_csddd_nonce" value="' . esc_attr( $nonce ) . '">';
		$out .= '<input type="text" name="eurocomply_csddd_hp" tabindex="-1" autocomplete="off" style="position:absolute;left:-10000px">';
		$out .= '<p><label>' . esc_html__( 'Email (optional, leave empty to file anonymously)', 'eurocomply-csddd' ) . '<br><input type="email" name="email"></label></p>';
		$out .= '<p><label>' . esc_html__( 'Country (ISO)', 'eurocomply-csddd' ) . '<br><input type="text" name="country" maxlength="2" size="3"></label></p>';
		$out .= '<p><label>' . esc_html__( 'Concern category', 'eurocomply-csddd' ) . '<br><select name="category" required>';
		foreach ( $cats as $k => $c ) {
			$out .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $c['label'] ) . '</option>';
		}
		$out .= '</select></label></p>';
		$out .= '<p><label>' . esc_html__( 'Supplier ID (if known)', 'eurocomply-csddd' ) . '<br><input type="number" name="supplier_id" min="0"></label></p>';
		$out .= '<p><label>' . esc_html__( 'Description', 'eurocomply-csddd' ) . '<br><textarea name="summary" rows="5" required></textarea></label></p>';
		$out .= '<p><button type="submit">' . esc_html__( 'Submit complaint', 'eurocomply-csddd' ) . '</button></p>';
		$out .= $status;
		$out .= '</form>';
		return $out;
	}

	private function detect_status_message() : string {
		if ( isset( $_GET['eurocomply_csddd_filed'] ) && '1' === $_GET['eurocomply_csddd_filed'] ) {
			$token = isset( $_GET['token'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $_GET['token'] ) : '';
			$msg   = '<p class="eurocomply-csddd-ack"><strong>' . esc_html__( 'Complaint received.', 'eurocomply-csddd' ) . '</strong>';
			if ( '' !== $token ) {
				$msg .= ' ' . esc_html__( 'Save this follow-up token (shown once):', 'eurocomply-csddd' ) . ' <code>' . esc_html( $token ) . '</code>';
			}
			$msg .= '</p>';
			return $msg;
		}
		return '';
	}

	public function maybe_handle_complaint() : void {
		if ( empty( $_POST['eurocomply_csddd_complaint'] ) ) {
			return;
		}
		if ( ! empty( $_POST['eurocomply_csddd_hp'] ) ) {
			return;
		}
		if ( empty( $_POST['_eurocomply_csddd_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_eurocomply_csddd_nonce'] ) ), 'eurocomply_csddd_complaint' ) ) {
			return;
		}
		$data = array(
			'complainant_email' => isset( $_POST['email'] ) ? wp_unslash( (string) $_POST['email'] ) : '',
			'country'           => isset( $_POST['country'] ) ? wp_unslash( (string) $_POST['country'] ) : '',
			'category'          => isset( $_POST['category'] ) ? wp_unslash( (string) $_POST['category'] ) : '',
			'supplier_id'       => isset( $_POST['supplier_id'] ) ? (int) $_POST['supplier_id'] : 0,
			'summary'           => isset( $_POST['summary'] ) ? wp_unslash( (string) $_POST['summary'] ) : '',
		);
		$result = ComplaintStore::insert( $data );
		$back   = wp_get_referer();
		if ( ! $back ) {
			$back = home_url( '/' );
		}
		$back = add_query_arg( array( 'eurocomply_csddd_filed' => '1', 'token' => $result['token'] ), $back );
		wp_safe_redirect( $back );
		exit;
	}
}
