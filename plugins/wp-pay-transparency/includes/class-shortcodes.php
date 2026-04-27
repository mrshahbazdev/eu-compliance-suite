<?php
/**
 * Public shortcodes:
 *   [eurocomply_pay_info_request]   - Art. 7 worker info request form
 *   [eurocomply_pay_setting_criteria] - Art. 6 admin-managed text block
 *   [eurocomply_pay_progression]    - Art. 6 progression criteria
 *   [eurocomply_pay_range id=""]    - Art. 5 standalone pay range
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {

	private const NONCE_FORM = 'eurocomply_pt_request_form';

	private static ?Shortcodes $instance = null;

	public static function instance() : Shortcodes {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_shortcode( 'eurocomply_pay_info_request',     array( $this, 'render_request_form' ) );
		add_shortcode( 'eurocomply_pay_setting_criteria', array( $this, 'render_pay_criteria' ) );
		add_shortcode( 'eurocomply_pay_progression',      array( $this, 'render_progression' ) );
		add_shortcode( 'eurocomply_pay_range',            array( $this, 'render_pay_range' ) );
		add_action( 'init', array( $this, 'maybe_handle_submission' ) );
	}

	public function render_pay_criteria( $atts = array() ) : string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$txt = trim( (string) Settings::get()['pay_setting_criteria'] );
		if ( '' === $txt ) {
			return '';
		}
		return '<div class="eurocomply-pt-criteria">' . wpautop( wp_kses_post( $txt ) ) . '</div>';
	}

	public function render_progression( $atts = array() ) : string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$txt = trim( (string) Settings::get()['progression_criteria'] );
		if ( '' === $txt ) {
			return '';
		}
		return '<div class="eurocomply-pt-progression">' . wpautop( wp_kses_post( $txt ) ) . '</div>';
	}

	/**
	 * @param array<string,string>|string $atts
	 */
	public function render_pay_range( $atts = array() ) : string {
		$atts = shortcode_atts(
			array(
				'id'  => 0,
				'min' => '',
				'max' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'eurocomply_pay_range'
		);
		$id = (int) $atts['id'];
		if ( $id > 0 ) {
			$min      = (float) get_post_meta( $id, '_eurocomply_pt_pay_min', true );
			$max      = (float) get_post_meta( $id, '_eurocomply_pt_pay_max', true );
			$currency = (string) get_post_meta( $id, '_eurocomply_pt_pay_currency', true );
			$period   = (string) get_post_meta( $id, '_eurocomply_pt_pay_period', true );
		} else {
			$min      = (float) $atts['min'];
			$max      = (float) $atts['max'];
			$currency = (string) Settings::get()['currency'];
			$period   = 'year';
		}
		if ( $min <= 0.0 && $max <= 0.0 ) {
			return '';
		}
		return '<span class="eurocomply-pt-pay-inline">' . JobAd::format_badge( $min, $max, $currency, '' === $period ? 'year' : $period ) . '</span>';
	}

	/**
	 * @param array<string,string>|string $atts
	 */
	public function render_request_form( $atts = array() ) : string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$success = isset( $_GET['eurocomply_pt_submitted'] ) && '1' === sanitize_key( (string) wp_unslash( $_GET['eurocomply_pt_submitted'] ) );

		ob_start();
		echo '<div class="eurocomply-pt-form">';

		if ( $success ) {
			$msg   = (string) get_transient( 'eurocomply_pt_msg_' . self::client_token() );
			$ok    = (string) get_transient( 'eurocomply_pt_ok_'  . self::client_token() );
			$token = (string) get_transient( 'eurocomply_pt_tok_' . self::client_token() );
			delete_transient( 'eurocomply_pt_msg_' . self::client_token() );
			delete_transient( 'eurocomply_pt_ok_'  . self::client_token() );
			delete_transient( 'eurocomply_pt_tok_' . self::client_token() );
			$cls = '1' === $ok ? 'eurocomply-pt-msg-ok' : 'eurocomply-pt-msg-err';
			echo '<div class="eurocomply-pt-msg ' . esc_attr( $cls ) . '">' . esc_html( $msg ) . '</div>';
			if ( '' !== $token ) {
				echo '<p class="eurocomply-pt-token-display"><strong>' . esc_html__( 'Reference:', 'eurocomply-pay-transparency' ) . '</strong> <code>' . esc_html( $token ) . '</code></p>';
			}
		}

		$action_url = esc_url( add_query_arg( array(), home_url( (string) ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/' ) ) ) );
		echo '<form method="post" action="' . $action_url . '">';
		wp_nonce_field( self::NONCE_FORM, '_eurocomply_pt_nonce' );
		echo '<input type="hidden" name="eurocomply_pt_action" value="submit_request" />';
		// Honeypot.
		echo '<div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">';
		echo '<label>' . esc_html__( 'Leave this field empty', 'eurocomply-pay-transparency' ) . '<input type="text" name="eurocomply_pt_website" value="" autocomplete="off" /></label>';
		echo '</div>';

		echo '<p><label>' . esc_html__( 'Your employee reference (optional)', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<input type="text" name="employee_ref" value="" /></label></p>';

		echo '<p><label>' . esc_html__( 'Contact email', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<input type="email" name="contact_email" value="" required /></label></p>';

		echo '<p><label>' . esc_html__( 'Pay category (optional)', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<select name="category_slug">';
		echo '<option value="">' . esc_html__( '— Any —', 'eurocomply-pay-transparency' ) . '</option>';
		foreach ( CategoryStore::all() as $cat ) {
			echo '<option value="' . esc_attr( (string) $cat['slug'] ) . '">' . esc_html( (string) $cat['name'] ) . '</option>';
		}
		echo '</select></label></p>';

		echo '<p><label>' . esc_html__( 'Information requested', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<select name="scope">';
		echo '<option value="pay_levels">' . esc_html__( 'Pay levels by category (Art. 7(1))',                'eurocomply-pay-transparency' ) . '</option>';
		echo '<option value="pay_setting">' . esc_html__( 'Pay-setting criteria (Art. 7(2))',                  'eurocomply-pay-transparency' ) . '</option>';
		echo '<option value="progression">' . esc_html__( 'Progression criteria (Art. 7(3))',                  'eurocomply-pay-transparency' ) . '</option>';
		echo '<option value="pay_gap_category">' . esc_html__( 'Mean pay gap by category (Art. 7(4))',         'eurocomply-pay-transparency' ) . '</option>';
		echo '</select></label></p>';

		echo '<p><label>' . esc_html__( 'Notes', 'eurocomply-pay-transparency' ) . '<br />';
		echo '<textarea name="notes" rows="4"></textarea></label></p>';

		echo '<p><button type="submit">' . esc_html__( 'Submit request', 'eurocomply-pay-transparency' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	public function maybe_handle_submission() : void {
		if ( ! isset( $_POST['eurocomply_pt_action'] ) || 'submit_request' !== sanitize_key( (string) wp_unslash( $_POST['eurocomply_pt_action'] ) ) ) {
			return;
		}
		if ( ! isset( $_POST['_eurocomply_pt_nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['_eurocomply_pt_nonce'] ), self::NONCE_FORM ) ) {
			$this->stash( false, __( 'Security check failed. Please reload and try again.', 'eurocomply-pay-transparency' ) );
			$this->redirect_back();
			return;
		}
		// Honeypot.
		if ( ! empty( $_POST['eurocomply_pt_website'] ) ) {
			$this->stash( false, __( 'Submission rejected.', 'eurocomply-pay-transparency' ) );
			$this->redirect_back();
			return;
		}

		$ip       = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$ip_hash  = RequestStore::hash_ip( $ip );
		$rl_key   = 'eurocomply_pt_rl_' . substr( $ip_hash, 0, 16 );
		$count    = (int) get_transient( $rl_key );
		$settings = Settings::get();
		if ( $count >= max( 1, (int) $settings['rate_limit_per_hour'] ) ) {
			$this->stash( false, __( 'Too many submissions from this network. Please try again later.', 'eurocomply-pay-transparency' ) );
			$this->redirect_back();
			return;
		}

		$email = sanitize_email( (string) wp_unslash( $_POST['contact_email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			$this->stash( false, __( 'A valid contact email is required.', 'eurocomply-pay-transparency' ) );
			$this->redirect_back();
			return;
		}

		$res = RequestStore::create(
			array(
				'employee_ref'  => (string) wp_unslash( $_POST['employee_ref'] ?? '' ),
				'contact_email' => $email,
				'category_slug' => (string) wp_unslash( $_POST['category_slug'] ?? '' ),
				'scope'         => (string) wp_unslash( $_POST['scope'] ?? 'pay_levels' ),
				'notes'         => (string) wp_unslash( $_POST['notes'] ?? '' ),
				'ip'            => $ip,
			)
		);

		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );
		$this->stash( true, sprintf(
			/* translators: %d: response window in days */
			esc_html__( 'Request received. We will respond within %d days (Art. 7(1)).', 'eurocomply-pay-transparency' ),
			(int) $settings['request_response_days']
		) );
		set_transient( 'eurocomply_pt_tok_' . self::client_token(), (string) $res['token'], 300 );

		// Notify the compliance email if configured.
		$to = (string) $settings['compliance_email'];
		if ( '' !== $to ) {
			wp_mail(
				$to,
				/* translators: %s: site name */
				sprintf( __( '[%s] New pay-transparency request', 'eurocomply-pay-transparency' ), get_bloginfo( 'name' ) ),
				sprintf(
					/* translators: 1: request id, 2: scope */
					__( "A new Art. 7 request has been received.\n\nID: %1\$d\nScope: %2\$s\n\nReview in WP-Admin → Pay Transparency → Requests.", 'eurocomply-pay-transparency' ),
					(int) $res['id'],
					(string) wp_unslash( $_POST['scope'] ?? 'pay_levels' )
				)
			);
		}

		$this->redirect_back();
	}

	private function stash( bool $ok, string $msg ) : void {
		set_transient( 'eurocomply_pt_msg_' . self::client_token(), $msg, 300 );
		set_transient( 'eurocomply_pt_ok_'  . self::client_token(), $ok ? '1' : '0', 300 );
	}

	private function redirect_back() : void {
		$url = isset( $_SERVER['REQUEST_URI'] ) ? home_url( (string) wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : home_url( '/' );
		$url = add_query_arg( 'eurocomply_pt_submitted', '1', remove_query_arg( 'eurocomply_pt_submitted', $url ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function client_token() : string {
		$ip = (string) ( $_SERVER['REMOTE_ADDR']     ?? '' );
		$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		return substr( hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'nonce' ) ), 0, 32 );
	}
}
