<?php
/**
 * Live JS cookie sniffer.
 *
 * Injects a tiny inline script in wp_footer that, sampled at the configured
 * rate, POSTs document.cookie keys to admin-ajax. Names only — never values.
 *
 * @package EuroComply\EPrivacy
 */

declare( strict_types = 1 );

namespace EuroComply\EPrivacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CookieObserver {

	private static ?CookieObserver $instance = null;

	public static function instance() : CookieObserver {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'wp_footer', array( $this, 'inline_script' ) );
		add_action( 'wp_ajax_nopriv_eurocomply_eprivacy_observe', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_eurocomply_eprivacy_observe',        array( $this, 'handle_ajax' ) );
	}

	public function inline_script() : void {
		$s = Settings::get();
		if ( empty( $s['enable_cookie_observer'] ) ) {
			return;
		}
		if ( is_admin() ) {
			return;
		}
		if ( wp_doing_ajax() ) {
			return;
		}
		$rate  = (int) $s['observer_sample_rate'];
		$nonce = wp_create_nonce( 'eurocomply_eprivacy_observe' );
		$url   = admin_url( 'admin-ajax.php' );

		$payload = wp_json_encode(
			array(
				'rate'  => max( 1, min( 100, $rate ) ),
				'nonce' => $nonce,
				'url'   => $url,
				'page'  => esc_url_raw( ( isset( $_SERVER['REQUEST_URI'] ) ? home_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : home_url( '/' ) ) ),
			)
		);
		?>
		<script>
		(function(cfg){
			try {
				if (Math.random() * 100 > cfg.rate) return;
				if (!document.cookie) return;
				if (sessionStorage.getItem('_eurocomplyEprivacyDone')) return;
				sessionStorage.setItem('_eurocomplyEprivacyDone', '1');
				var names = document.cookie.split(';').map(function (p) {
					var idx = p.indexOf('=');
					return (idx === -1 ? p : p.slice(0, idx)).trim();
				}).filter(Boolean);
				if (!names.length) return;
				var fd = new FormData();
				fd.append('action', 'eurocomply_eprivacy_observe');
				fd.append('nonce',  cfg.nonce);
				fd.append('page',   cfg.page);
				fd.append('names',  names.join(','));
				fetch(cfg.url, { method: 'POST', credentials: 'same-origin', body: fd, keepalive: true }).catch(function(){});
			} catch (e) {}
		})(<?php echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>);
		</script>
		<?php
	}

	public function handle_ajax() : void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'eurocomply_eprivacy_observe' ) ) {
			wp_send_json_error( array( 'message' => 'bad nonce' ), 403 );
		}
		$page  = isset( $_POST['page'] ) ? esc_url_raw( wp_unslash( (string) $_POST['page'] ) ) : '';
		$names = isset( $_POST['names'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['names'] ) ) : '';
		if ( '' === $names ) {
			wp_send_json_success( array( 'recorded' => 0 ) );
		}

		$session_hash = '';
		if ( isset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ) ) {
			$session_hash = hash_hmac(
				'sha256',
				(string) $_SERVER['REMOTE_ADDR'] . '|' . (string) $_SERVER['HTTP_USER_AGENT'],
				wp_salt( 'nonce' )
			);
		}
		$host  = (string) wp_parse_url( $page, PHP_URL_HOST );
		$count = 0;
		foreach ( explode( ',', $names ) as $n ) {
			$n = trim( (string) $n );
			if ( '' === $n || mb_strlen( $n ) > 200 ) {
				continue;
			}
			CookieStore::record( $page, $n, $host, $session_hash );
			$count++;
			if ( $count >= 30 ) { // hard ceiling per request
				break;
			}
		}
		wp_send_json_success( array( 'recorded' => $count ) );
	}
}
