<?php
/**
 * Public age gate: modal markup, public assets, AJAX verification endpoint,
 * cookie-based session persistence.
 *
 * Two cookies:
 *   eurocomply_av_passed : "1" when the visitor has passed the gate (public,
 *                          short-lived, used only for client-side conditional
 *                          rendering).
 *   eurocomply_av_session: HMAC-signed token (IP hash + expiry) which the
 *                          server validates before trusting a previous pass.
 *
 * @package EuroComply\AgeVerification
 */

declare( strict_types = 1 );

namespace EuroComply\AgeVerification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AgeGate {

	public const COOKIE_NAME    = 'eurocomply_av_session';
	public const PUBLIC_COOKIE  = 'eurocomply_av_passed';
	public const NONCE_ACTION   = 'eurocomply_av_verify';
	public const AJAX_ACTION    = 'eurocomply_av_verify';
	public const SHORTCODE      = 'eurocomply_age_gate';

	private static ?AgeGate $instance = null;

	public static function instance() : AgeGate {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_modal' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
	}

	public function enqueue() : void {
		wp_enqueue_style(
			'eurocomply-av-public',
			EUROCOMPLY_AV_URL . 'assets/css/public.css',
			array(),
			EUROCOMPLY_AV_VERSION
		);
		wp_enqueue_script(
			'eurocomply-av-public',
			EUROCOMPLY_AV_URL . 'assets/js/public.js',
			array(),
			EUROCOMPLY_AV_VERSION,
			true
		);
		wp_localize_script(
			'eurocomply-av-public',
			'EuroComplyAV',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'action'  => self::AJAX_ACTION,
			)
		);
	}

	public function maybe_render_modal() : void {
		$s = Settings::get();
		if ( 'shortcode_only' === $s['gate_mode'] ) {
			return;
		}
		if ( 'site' !== $s['gate_mode'] && ! $this->current_context_requires_gate() ) {
			return;
		}
		if ( $this->is_user_exempt() ) {
			return;
		}
		if ( $this->has_valid_session() ) {
			return;
		}
		$this->render_modal();
	}

	public function shortcode( $atts ) : string {
		$atts = shortcode_atts(
			array(
				'min_age' => 0,
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		if ( $this->is_user_exempt() || $this->has_valid_session() ) {
			return '';
		}

		ob_start();
		$this->render_modal( (int) $atts['min_age'] );
		return (string) ob_get_clean();
	}

	public function handle_ajax() : void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$s            = Settings::get();
		$method       = isset( $_POST['method'] ) ? sanitize_key( (string) $_POST['method'] ) : (string) $s['verification_method'];
		$dob          = isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['dob'] ) ) : '';
		$checkbox_ok  = ! empty( $_POST['confirm'] );
		$context      = isset( $_POST['context'] ) ? sanitize_key( (string) $_POST['context'] ) : 'site';
		$context_ref  = isset( $_POST['context_ref'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['context_ref'] ) ) : '';
		$country      = isset( $_POST['country'] ) ? strtoupper( sanitize_text_field( (string) $_POST['country'] ) ) : '';
		$ip           = self::client_ip();
		$ip_hash      = VerificationStore::ip_hash( $ip );
		$required_age = $this->required_age_for_context( $context, $context_ref, $country );

		$computed_age  = 0;
		$declared_year = 0;
		$passed        = false;

		if ( 'checkbox' === $method ) {
			$passed = $checkbox_ok;
		} else {
			$ts = strtotime( $dob );
			if ( false !== $ts ) {
				$declared_year = (int) gmdate( 'Y', $ts );
				$computed_age  = $this->age_from_timestamp( $ts );
				$passed        = $computed_age >= $required_age;
			}
		}

		$token = '';
		if ( $passed ) {
			$token = $this->issue_session_token( $ip_hash, (int) $s['cookie_days'] );
			$this->set_cookies( $token, (int) $s['cookie_days'] );
		}

		if ( $passed || ! empty( $s['log_blocked_attempts'] ) ) {
			VerificationStore::record(
				array(
					'user_id'       => get_current_user_id(),
					'ip_hash'       => $ip_hash,
					'country'       => $country,
					'method'        => $method,
					'declared_year' => $declared_year,
					'computed_age'  => $computed_age,
					'required_age'  => $required_age,
					'passed'        => $passed,
					'context'       => $context,
					'context_ref'   => $context_ref,
					'session_token' => $token ? substr( hash( 'sha256', $token ), 0, 32 ) : '',
					'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				)
			);
		}

		if ( $passed ) {
			wp_send_json_success(
				array(
					'message'      => (string) $s['pass_message'],
					'required_age' => $required_age,
				)
			);
		}

		wp_send_json_error(
			array(
				'message'      => (string) $s['blocked_message'],
				'redirect_url' => (string) $s['blocked_redirect_url'],
				'required_age' => $required_age,
			),
			403
		);
	}

	private function render_modal( int $override_min_age = 0 ) : void {
		$s            = Settings::get();
		$required_age = $override_min_age > 0 ? $override_min_age : (int) $s['default_min_age'];
		$method       = (string) $s['verification_method'];
		?>
		<div class="eurocomply-av-overlay" role="dialog" aria-modal="true" aria-labelledby="eurocomply-av-title" data-min-age="<?php echo esc_attr( (string) $required_age ); ?>">
			<div class="eurocomply-av-modal">
				<h2 id="eurocomply-av-title"><?php echo esc_html( (string) $s['modal_title'] ); ?></h2>
				<p class="eurocomply-av-body"><?php echo esc_html( (string) $s['modal_body'] ); ?></p>
				<form class="eurocomply-av-form" method="post">
					<?php if ( 'dob' === $method ) : ?>
						<label for="eurocomply-av-dob">
							<?php esc_html_e( 'Your date of birth', 'eurocomply-age-verification' ); ?>
							<input type="date" id="eurocomply-av-dob" name="dob" required="required" autocomplete="bday" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
						</label>
					<?php else : ?>
						<label>
							<input type="checkbox" name="confirm" value="1" required="required" />
							<?php
							/* translators: %d: minimum age */
							echo esc_html( sprintf( __( 'I confirm I am at least %d years old.', 'eurocomply-age-verification' ), $required_age ) );
							?>
						</label>
					<?php endif; ?>
					<div class="eurocomply-av-actions">
						<button type="submit" class="eurocomply-av-submit"><?php esc_html_e( 'Enter site', 'eurocomply-age-verification' ); ?></button>
						<button type="button" class="eurocomply-av-leave"><?php esc_html_e( 'Leave', 'eurocomply-age-verification' ); ?></button>
					</div>
					<div class="eurocomply-av-status" role="status" aria-live="polite"></div>
				</form>
			</div>
		</div>
		<?php
	}

	private function current_context_requires_gate() : bool {
		$s = Settings::get();
		if ( 'site' === $s['gate_mode'] ) {
			return true;
		}
		if ( 'category' !== $s['gate_mode'] ) {
			return false;
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_queried_object_id();
			return WooCommerce::product_requires_gate( (int) $product_id );
		}
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->term_id ) ) {
				return in_array( (int) $term->term_id, (array) $s['restricted_categories'], true );
			}
		}
		return false;
	}

	private function is_user_exempt() : bool {
		$s = Settings::get();
		if ( ! empty( $s['exclude_admin_users'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}
		return false;
	}

	public function has_valid_session() : bool {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return false;
		}
		$token = (string) $_COOKIE[ self::COOKIE_NAME ];
		return $this->validate_session_token( $token );
	}

	private function issue_session_token( string $ip_hash, int $days ) : string {
		$expires = time() + max( 0, $days ) * DAY_IN_SECONDS;
		$payload = $ip_hash . '|' . $expires;
		$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		return base64_encode( $payload . '|' . $sig ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	private function validate_session_token( string $token ) : bool {
		$raw = base64_decode( $token, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return false;
		}
		$parts = explode( '|', $raw );
		if ( count( $parts ) !== 3 ) {
			return false;
		}
		list( $ip_hash, $expires, $sig ) = $parts;
		if ( (int) $expires < time() ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $ip_hash . '|' . $expires, wp_salt( 'auth' ) );
		return hash_equals( $expected, $sig );
	}

	private function set_cookies( string $token, int $days ) : void {
		$expires = $days > 0 ? time() + $days * DAY_IN_SECONDS : 0;
		$secure  = is_ssl();
		setcookie( self::COOKIE_NAME, $token, array(
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		) );
		setcookie( self::PUBLIC_COOKIE, '1', array(
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => $secure,
			'httponly' => false,
			'samesite' => 'Lax',
		) );
	}

	private function required_age_for_context( string $context, string $context_ref, string $country ) : int {
		$s = Settings::get();

		if ( 'product' === $context && is_numeric( $context_ref ) ) {
			$product_id = (int) $context_ref;
			$age        = WooCommerce::required_age_for_product( $product_id );
			if ( $age > 0 ) {
				return $age;
			}
		}
		if ( 'term' === $context && is_numeric( $context_ref ) ) {
			return Settings::min_age_for_term( (int) $context_ref );
		}
		if ( '' !== $country ) {
			return Settings::min_age_for_country( $country );
		}
		return (int) $s['default_min_age'];
	}

	private function age_from_timestamp( int $ts ) : int {
		$now   = time();
		if ( $ts > $now ) {
			return 0;
		}
		$birth = getdate( $ts );
		$today = getdate( $now );
		$age   = $today['year'] - $birth['year'];
		if ( $today['mon'] < $birth['mon'] || ( $today['mon'] === $birth['mon'] && $today['mday'] < $birth['mday'] ) ) {
			$age--;
		}
		return max( 0, $age );
	}

	public static function client_ip() : string {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $candidates as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$val = explode( ',', (string) $_SERVER[ $key ] );
				$ip  = trim( (string) $val[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
}
