<?php
/**
 * Public shortcodes — submission form + status check.
 *
 * @package EuroComply\Whistleblower
 */

declare( strict_types = 1 );

namespace EuroComply\Whistleblower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {

	public const NONCE_FORM   = 'eurocomply_wb_form';
	public const NONCE_STATUS = 'eurocomply_wb_status';

	private static ?Shortcodes $instance = null;

	public static function instance() : Shortcodes {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_shortcode( 'eurocomply_whistleblower_form',   array( $this, 'render_form' ) );
		add_shortcode( 'eurocomply_whistleblower_status', array( $this, 'render_status' ) );
		add_shortcode( 'eurocomply_whistleblower_policy', array( $this, 'render_policy' ) );
		add_action( 'init', array( $this, 'maybe_handle_post' ) );
	}

	public function assets() : void {
		wp_register_style( 'eurocomply-wb-public', EUROCOMPLY_WB_URL . 'assets/css/public.css', array(), EUROCOMPLY_WB_VERSION );
	}

	public function render_form( $atts = array() ) : string {
		wp_enqueue_style( 'eurocomply-wb-public' );
		$settings   = Settings::get();
		$categories = Settings::categories();
		$message    = '';
		$ok         = false;
		$token      = '';

		if ( isset( $_GET['eurocomply_wb_submitted'] ) && '1' === (string) $_GET['eurocomply_wb_submitted'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$transient_key = 'eurocomply_wb_msg_' . substr( hash( 'sha256', wp_get_session_token() . wp_salt( 'nonce' ) ), 0, 16 );
			$payload       = get_transient( $transient_key );
			if ( is_array( $payload ) ) {
				$ok      = ! empty( $payload['ok'] );
				$message = (string) ( $payload['message'] ?? '' );
				$token   = (string) ( $payload['token'] ?? '' );
				delete_transient( $transient_key );
			}
		}

		ob_start();
		echo '<div class="eurocomply-wb-form-wrap">';
		echo '<h3>' . esc_html( (string) $settings['form_title'] ) . '</h3>';
		echo '<div class="eurocomply-wb-form-intro">' . wp_kses_post( (string) $settings['form_description'] ) . '</div>';

		if ( $message ) {
			$cls = $ok ? 'eurocomply-wb-msg eurocomply-wb-msg--ok' : 'eurocomply-wb-msg eurocomply-wb-msg--err';
			printf( '<div class="%s">%s</div>', esc_attr( $cls ), esc_html( $message ) );
			if ( $ok && $token ) {
				echo '<div class="eurocomply-wb-token">';
				echo '<strong>' . esc_html__( 'Your follow-up token (save this now — it is shown only once):', 'eurocomply-whistleblower' ) . '</strong><br />';
				echo '<code>' . esc_html( $token ) . '</code>';
				echo '</div>';
			}
		}

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( get_permalink() ) . '">';
		wp_nonce_field( self::NONCE_FORM, '_eurocomply_wb_nonce' );
		echo '<input type="hidden" name="eurocomply_wb_submit" value="1" />';
		echo '<input type="text" name="eurocomply_wb_website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true" />';

		echo '<p><label>' . esc_html__( 'Category', 'eurocomply-whistleblower' ) . ' <span class="req">*</span><br />';
		echo '<select name="category" required>';
		foreach ( $categories as $slug => $cat ) {
			printf( '<option value="%s">%s</option>', esc_attr( $slug ), esc_html( $cat['label'] ) );
		}
		echo '</select></label></p>';

		echo '<p><label>' . esc_html__( 'Subject', 'eurocomply-whistleblower' ) . ' <span class="req">*</span><br />';
		echo '<input type="text" name="subject" maxlength="255" required class="eurocomply-wb-input" /></label></p>';

		echo '<p><label>' . esc_html__( 'Detailed description', 'eurocomply-whistleblower' ) . ' <span class="req">*</span><br />';
		echo '<textarea name="body" rows="8" required class="eurocomply-wb-textarea"></textarea></label></p>';

		echo '<p><label>' . esc_html__( 'Supporting documents (optional)', 'eurocomply-whistleblower' );
		echo '<br /><input type="file" name="eurocomply_wb_files[]" multiple />';
		echo '<br /><small>' . esc_html( sprintf( /* translators: 1: types 2: size */ __( 'Allowed types: %1$s. Max %2$d MB per file.', 'eurocomply-whistleblower' ), (string) $settings['allowed_file_types'], (int) $settings['max_file_size_mb'] ) ) . '</small></label></p>';

		if ( ! empty( $settings['enable_anonymous'] ) ) {
			echo '<fieldset class="eurocomply-wb-contact">';
			echo '<legend>' . esc_html__( 'Contact preferences', 'eurocomply-whistleblower' ) . '</legend>';
			echo '<p><label><input type="radio" name="anonymous" value="1" checked /> ' . esc_html__( 'Submit anonymously', 'eurocomply-whistleblower' ) . '</label></p>';
			echo '<p><label><input type="radio" name="anonymous" value="0" /> ' . esc_html__( 'Provide contact details', 'eurocomply-whistleblower' ) . '</label></p>';
			echo '<p><label>' . esc_html__( 'Email or phone (only used if you opt-in)', 'eurocomply-whistleblower' ) . '<br />';
			echo '<input type="text" name="contact_value" class="eurocomply-wb-input" /></label></p>';
			echo '</fieldset>';
		} else {
			echo '<p><label>' . esc_html__( 'Email', 'eurocomply-whistleblower' ) . ' <span class="req">*</span><br />';
			echo '<input type="email" name="contact_value" required class="eurocomply-wb-input" /></label></p>';
			echo '<input type="hidden" name="anonymous" value="0" />';
		}

		echo '<p><button type="submit" class="eurocomply-wb-btn">' . esc_html__( 'Submit confidential report', 'eurocomply-whistleblower' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	public function render_status( $atts = array() ) : string {
		wp_enqueue_style( 'eurocomply-wb-public' );
		$settings = Settings::get();
		if ( empty( $settings['enable_status_check'] ) ) {
			return '';
		}
		$message = '';
		$report  = null;

		if ( isset( $_GET['eurocomply_wb_status_done'] ) && '1' === (string) $_GET['eurocomply_wb_status_done'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$transient_key = 'eurocomply_wb_status_' . substr( hash( 'sha256', wp_get_session_token() . wp_salt( 'nonce' ) ), 0, 16 );
			$payload       = get_transient( $transient_key );
			if ( is_array( $payload ) ) {
				$message = (string) ( $payload['message'] ?? '' );
				$report  = isset( $payload['report'] ) && is_array( $payload['report'] ) ? $payload['report'] : null;
				delete_transient( $transient_key );
			}
		}

		ob_start();
		echo '<div class="eurocomply-wb-form-wrap">';
		echo '<h3>' . esc_html__( 'Check report status', 'eurocomply-whistleblower' ) . '</h3>';
		if ( $message ) {
			echo '<div class="eurocomply-wb-msg">' . esc_html( $message ) . '</div>';
		}
		if ( $report ) {
			$statuses = ReportStore::statuses();
			echo '<div class="eurocomply-wb-status-card">';
			printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Submitted', 'eurocomply-whistleblower' ), esc_html( (string) $report['created_at'] ) );
			printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Status', 'eurocomply-whistleblower' ), esc_html( $statuses[ (string) $report['status'] ] ?? (string) $report['status'] ) );
			if ( ! empty( $report['acknowledged_at'] ) ) {
				printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Acknowledged', 'eurocomply-whistleblower' ), esc_html( (string) $report['acknowledged_at'] ) );
			}
			if ( ! empty( $report['feedback_sent_at'] ) ) {
				printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Feedback sent', 'eurocomply-whistleblower' ), esc_html( (string) $report['feedback_sent_at'] ) );
			}
			echo '</div>';
		}
		echo '<form method="post" action="' . esc_url( get_permalink() ) . '">';
		wp_nonce_field( self::NONCE_STATUS, '_eurocomply_wb_status_nonce' );
		echo '<input type="hidden" name="eurocomply_wb_status_check" value="1" />';
		echo '<p><label>' . esc_html__( 'Follow-up token', 'eurocomply-whistleblower' );
		echo '<br /><input type="text" name="follow_up_token" required class="eurocomply-wb-input" /></label></p>';
		echo '<p><button type="submit" class="eurocomply-wb-btn">' . esc_html__( 'Check status', 'eurocomply-whistleblower' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
		return (string) ob_get_clean();
	}

	public function render_policy( $atts = array() ) : string {
		return PolicyPageGenerator::render();
	}

	public function maybe_handle_post() : void {
		if ( isset( $_POST['eurocomply_wb_submit'] ) && '1' === (string) $_POST['eurocomply_wb_submit'] ) {
			$this->handle_submission();
			return;
		}
		if ( isset( $_POST['eurocomply_wb_status_check'] ) && '1' === (string) $_POST['eurocomply_wb_status_check'] ) {
			$this->handle_status_check();
		}
	}

	private function handle_submission() : void {
		if ( ! isset( $_POST['_eurocomply_wb_nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['_eurocomply_wb_nonce'] ), self::NONCE_FORM ) ) {
			$this->stash_msg( false, __( 'Security check failed. Please reload and try again.', 'eurocomply-whistleblower' ) );
			$this->redirect_back( 'eurocomply_wb_submitted' );
			return;
		}
		// Honeypot.
		if ( ! empty( $_POST['eurocomply_wb_website'] ) ) {
			$this->stash_msg( false, __( 'Submission rejected.', 'eurocomply-whistleblower' ) );
			$this->redirect_back( 'eurocomply_wb_submitted' );
			return;
		}

		$settings = Settings::get();
		$ip       = $this->client_ip();
		$ip_hash  = ReportStore::hash_ip( $ip );

		// Hashed-IP rate-limit.
		$rl_key = 'eurocomply_wb_rl_' . substr( $ip_hash, 0, 16 );
		$count  = (int) get_transient( $rl_key );
		if ( $count >= max( 1, (int) $settings['rate_limit_per_hour'] ) ) {
			$this->stash_msg( false, __( 'Too many submissions from this network. Please try again later.', 'eurocomply-whistleblower' ) );
			$this->redirect_back( 'eurocomply_wb_submitted' );
			return;
		}

		$category = isset( $_POST['category'] ) ? sanitize_key( (string) wp_unslash( $_POST['category'] ) ) : 'other';
		$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['subject'] ) ) : '';
		$body     = isset( $_POST['body'] ) ? wp_kses_post( (string) wp_unslash( $_POST['body'] ) ) : '';
		$anon     = isset( $_POST['anonymous'] ) ? (int) $_POST['anonymous'] : 1;
		$contact  = isset( $_POST['contact_value'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['contact_value'] ) ) : '';

		if ( '' === $subject || '' === $body ) {
			$this->stash_msg( false, __( 'Subject and description are required.', 'eurocomply-whistleblower' ) );
			$this->redirect_back( 'eurocomply_wb_submitted' );
			return;
		}
		if ( empty( $settings['enable_anonymous'] ) ) {
			$anon = 0;
		}
		if ( 0 === $anon && '' === $contact ) {
			$this->stash_msg( false, __( 'Please provide a contact email or phone if you are not submitting anonymously.', 'eurocomply-whistleblower' ) );
			$this->redirect_back( 'eurocomply_wb_submitted' );
			return;
		}

		$files = $this->process_uploads( $settings );

		$token = ReportStore::generate_token();
		$id    = ReportStore::create( array(
			'follow_up_token_hash' => ReportStore::hash_token( $token ),
			'status'               => 'received',
			'category'             => $category,
			'subject'              => $subject,
			'body'                 => $body,
			'anonymous'            => $anon,
			'contact_method'       => 0 === $anon ? 'text' : '',
			'contact_value'        => 0 === $anon ? $contact : '',
			'files_json'           => $files,
			'ip_hash'              => $ip_hash,
		) );

		if ( $id <= 0 ) {
			$this->stash_msg( false, __( 'We could not record your report. Please try again.', 'eurocomply-whistleblower' ) );
			$this->redirect_back( 'eurocomply_wb_submitted' );
			return;
		}

		set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );
		AccessLog::record( $id, 'created', array( 'category' => $category, 'anonymous' => $anon ) );
		$this->notify_recipients( $id, $settings );

		$this->stash_msg( true, __( 'Report received. Save the follow-up token below — it will not be shown again.', 'eurocomply-whistleblower' ), $token );
		$this->redirect_back( 'eurocomply_wb_submitted' );
	}

	private function handle_status_check() : void {
		if ( ! isset( $_POST['_eurocomply_wb_status_nonce'] ) || ! wp_verify_nonce( (string) wp_unslash( $_POST['_eurocomply_wb_status_nonce'] ), self::NONCE_STATUS ) ) {
			$this->stash_status( __( 'Security check failed.', 'eurocomply-whistleblower' ), null );
			$this->redirect_back( 'eurocomply_wb_status_done' );
			return;
		}
		$token = isset( $_POST['follow_up_token'] ) ? trim( (string) wp_unslash( $_POST['follow_up_token'] ) ) : '';
		if ( '' === $token ) {
			$this->stash_status( __( 'Please enter a token.', 'eurocomply-whistleblower' ), null );
			$this->redirect_back( 'eurocomply_wb_status_done' );
			return;
		}
		$report = ReportStore::find_by_token( $token );
		if ( null === $report ) {
			$this->stash_status( __( 'Token not recognised.', 'eurocomply-whistleblower' ), null );
			$this->redirect_back( 'eurocomply_wb_status_done' );
			return;
		}
		$visible = array(
			'created_at'       => (string) $report['created_at'],
			'status'           => (string) $report['status'],
			'acknowledged_at'  => (string) ( $report['acknowledged_at'] ?? '' ),
			'feedback_sent_at' => (string) ( $report['feedback_sent_at'] ?? '' ),
		);
		AccessLog::record( (int) $report['id'], 'status_checked', array() );
		$this->stash_status( '', $visible );
		$this->redirect_back( 'eurocomply_wb_status_done' );
	}

	private function notify_recipients( int $id, array $settings ) : void {
		$recipients = Recipient::get_recipients();
		$emails     = array();
		foreach ( $recipients as $u ) {
			if ( $u instanceof \WP_User && $u->user_email ) {
				$emails[] = (string) $u->user_email;
			}
		}
		if ( empty( $emails ) && ! empty( $settings['compliance_email'] ) ) {
			$emails[] = (string) $settings['compliance_email'];
		}
		if ( empty( $emails ) ) {
			return;
		}
		$subject = sprintf( /* translators: %d id */ __( '[Whistleblower] New report #%d', 'eurocomply-whistleblower' ), $id );
		$message = sprintf(
			/* translators: 1: id 2: admin URL */
			__( "A new whistleblower report has been received (#%1\$d). Open the admin to review:\n%2\$s", 'eurocomply-whistleblower' ),
			$id,
			admin_url( 'admin.php?page=eurocomply-wb&tab=reports&report_id=' . $id )
		);
		wp_mail( $emails, $subject, $message );
	}

	/**
	 * Process file uploads with type/size validation.
	 *
	 * @param array<string,mixed> $settings
	 * @return array<int, array<string,mixed>>
	 */
	private function process_uploads( array $settings ) : array {
		$out = array();
		if ( empty( $_FILES['eurocomply_wb_files'] ) || ! is_array( $_FILES['eurocomply_wb_files']['name'] ) ) {
			return $out;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$max_size = max( 1, (int) $settings['max_file_size_mb'] ) * 1024 * 1024;
		$exts     = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $settings['allowed_file_types'] ) ) ) );
		$names    = (array) $_FILES['eurocomply_wb_files']['name'];

		$count = count( $names );
		for ( $i = 0; $i < $count; $i++ ) {
			$name = isset( $names[ $i ] ) ? sanitize_file_name( (string) $names[ $i ] ) : '';
			$tmp  = isset( $_FILES['eurocomply_wb_files']['tmp_name'][ $i ] ) ? (string) $_FILES['eurocomply_wb_files']['tmp_name'][ $i ] : '';
			$size = isset( $_FILES['eurocomply_wb_files']['size'][ $i ] ) ? (int) $_FILES['eurocomply_wb_files']['size'][ $i ] : 0;
			if ( '' === $name || '' === $tmp || $size <= 0 ) {
				continue;
			}
			if ( $size > $max_size ) {
				continue;
			}
			$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( ! empty( $exts ) && ! in_array( $ext, $exts, true ) ) {
				continue;
			}
			$single = array(
				'name'     => $name,
				'type'     => isset( $_FILES['eurocomply_wb_files']['type'][ $i ] ) ? (string) $_FILES['eurocomply_wb_files']['type'][ $i ] : '',
				'tmp_name' => $tmp,
				'error'    => isset( $_FILES['eurocomply_wb_files']['error'][ $i ] ) ? (int) $_FILES['eurocomply_wb_files']['error'][ $i ] : 0,
				'size'     => $size,
			);
			$result = wp_handle_upload( $single, array( 'test_form' => false ) );
			if ( ! empty( $result['error'] ) || empty( $result['url'] ) ) {
				continue;
			}
			$out[] = array(
				'name' => $name,
				'url'  => (string) $result['url'],
				'file' => (string) ( $result['file'] ?? '' ),
				'size' => $size,
			);
		}
		return $out;
	}

	private function client_ip() : string {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $candidates as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$raw = (string) wp_unslash( $_SERVER[ $key ] );
				$ip  = trim( explode( ',', $raw )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	private function stash_msg( bool $ok, string $message, string $token = '' ) : void {
		$transient_key = 'eurocomply_wb_msg_' . substr( hash( 'sha256', wp_get_session_token() . wp_salt( 'nonce' ) ), 0, 16 );
		set_transient( $transient_key, array(
			'ok'      => $ok,
			'message' => $message,
			'token'   => $token,
		), 60 );
	}

	private function stash_status( string $message, ?array $report ) : void {
		$transient_key = 'eurocomply_wb_status_' . substr( hash( 'sha256', wp_get_session_token() . wp_salt( 'nonce' ) ), 0, 16 );
		set_transient( $transient_key, array(
			'message' => $message,
			'report'  => $report,
		), 60 );
	}

	private function redirect_back( string $flag ) : void {
		$ref = wp_get_referer();
		if ( ! $ref ) {
			$ref = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( $flag, '1', $ref ) );
		exit;
	}
}
