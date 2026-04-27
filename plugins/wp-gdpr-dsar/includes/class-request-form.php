<?php
/**
 * Public DSAR request form.
 *
 * Shortcode: [eurocomply_dsar_form]
 *
 * @package EuroComply\DSAR
 */

declare( strict_types = 1 );

namespace EuroComply\DSAR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestForm {

	public const SHORTCODE        = 'eurocomply_dsar_form';
	public const NONCE_SUBMIT     = 'eurocomply_dsar_submit';
	public const ACTION_SUBMIT    = 'eurocomply_dsar_submit';
	public const ACTION_VERIFY    = 'eurocomply_dsar_verify';

	private static ?RequestForm $instance = null;

	public static function instance() : RequestForm {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_SUBMIT, array( $this, 'handle_submit' ) );
		add_action( 'admin_post_' . self::ACTION_SUBMIT, array( $this, 'handle_submit' ) );
		add_action( 'init', array( $this, 'maybe_verify' ) );
	}

	/**
	 * @param array<string,mixed>|string $atts
	 */
	public function render( $atts ) : string {
		$s = Settings::get();
		if ( empty( $s['allow_anonymous_requests'] ) && ! is_user_logged_in() ) {
			return '<div class="eurocomply-dsar-notice">' . esc_html__( 'You must be logged in to submit a data request.', 'eurocomply-dsar' ) . '</div>';
		}

		$notices = $this->pop_notices();
		ob_start();
		?>
		<div class="eurocomply-dsar-form-wrap">
			<?php foreach ( $notices as $notice ) : ?>
				<div class="eurocomply-dsar-notice eurocomply-dsar-notice--<?php echo esc_attr( (string) $notice['type'] ); ?>">
					<?php echo esc_html( (string) $notice['message'] ); ?>
				</div>
			<?php endforeach; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eurocomply-dsar-form">
				<?php wp_nonce_field( self::NONCE_SUBMIT, '_eurocomply_dsar_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SUBMIT ); ?>" />

				<p>
					<label for="eurocomply-dsar-email">
						<?php esc_html_e( 'Your email address', 'eurocomply-dsar' ); ?>
						<span aria-hidden="true">*</span>
					</label>
					<input type="email" id="eurocomply-dsar-email" name="requester_email" required="required" value="<?php echo esc_attr( is_user_logged_in() ? (string) wp_get_current_user()->user_email : '' ); ?>" />
				</p>

				<p>
					<label for="eurocomply-dsar-name"><?php esc_html_e( 'Your name', 'eurocomply-dsar' ); ?></label>
					<input type="text" id="eurocomply-dsar-name" name="requester_name" value="<?php echo esc_attr( is_user_logged_in() ? (string) wp_get_current_user()->display_name : '' ); ?>" />
				</p>

				<p>
					<label for="eurocomply-dsar-type"><?php esc_html_e( 'Type of request', 'eurocomply-dsar' ); ?></label>
					<select id="eurocomply-dsar-type" name="request_type">
						<option value="access"><?php esc_html_e( 'Access a copy of my data (Art. 15)', 'eurocomply-dsar' ); ?></option>
						<option value="portability"><?php esc_html_e( 'Export my data in a portable format (Art. 20)', 'eurocomply-dsar' ); ?></option>
						<option value="rectify"><?php esc_html_e( 'Correct inaccurate data (Art. 16)', 'eurocomply-dsar' ); ?></option>
						<option value="erase"><?php esc_html_e( 'Erase my data / right to be forgotten (Art. 17)', 'eurocomply-dsar' ); ?></option>
						<option value="object"><?php esc_html_e( 'Object to processing (Art. 21)', 'eurocomply-dsar' ); ?></option>
						<option value="restrict"><?php esc_html_e( 'Restrict processing (Art. 18)', 'eurocomply-dsar' ); ?></option>
					</select>
				</p>

				<p>
					<label for="eurocomply-dsar-details"><?php esc_html_e( 'Details (optional)', 'eurocomply-dsar' ); ?></label>
					<textarea id="eurocomply-dsar-details" name="details" rows="5"></textarea>
				</p>

				<p>
					<label>
						<input type="checkbox" name="consent" value="1" required="required" />
						<?php esc_html_e( 'I confirm that the information above is accurate and relates to me.', 'eurocomply-dsar' ); ?>
					</label>
				</p>

				<?php // Honeypot. ?>
				<p style="position:absolute;left:-9999px;" aria-hidden="true">
					<label><?php esc_html_e( 'Website', 'eurocomply-dsar' ); ?>
						<input type="text" name="eurocomply_dsar_website" value="" autocomplete="off" tabindex="-1" />
					</label>
				</p>

				<button type="submit" class="eurocomply-dsar-submit"><?php esc_html_e( 'Submit request', 'eurocomply-dsar' ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function handle_submit() : void {
		$s = Settings::get();

		if ( ! isset( $_POST['_eurocomply_dsar_nonce'] ) || ! wp_verify_nonce( (string) $_POST['_eurocomply_dsar_nonce'], self::NONCE_SUBMIT ) ) {
			$this->bounce( 'error', __( 'Security check failed. Please try again.', 'eurocomply-dsar' ) );
		}

		// Honeypot.
		if ( ! empty( $_POST['eurocomply_dsar_website'] ) ) {
			$this->bounce( 'success', __( 'Request received.', 'eurocomply-dsar' ) );
		}

		$email = isset( $_POST['requester_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['requester_email'] ) ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			$this->bounce( 'error', __( 'A valid email address is required.', 'eurocomply-dsar' ) );
		}

		$type = isset( $_POST['request_type'] ) ? sanitize_key( (string) $_POST['request_type'] ) : 'access';
		if ( ! in_array( $type, RequestStore::TYPES, true ) ) {
			$type = 'access';
		}

		if ( empty( $_POST['consent'] ) ) {
			$this->bounce( 'error', __( 'Please confirm that the information is accurate and relates to you.', 'eurocomply-dsar' ) );
		}

		$ip      = self::client_ip();
		$ip_hash = RequestStore::ip_hash( $ip );

		$rate = max( 0, (int) $s['rate_limit_per_hour'] );
		if ( $rate > 0 && RequestStore::count_ip_recent( $ip_hash, 60 ) >= $rate ) {
			$this->bounce( 'error', __( 'You have submitted too many requests recently. Please try again later.', 'eurocomply-dsar' ) );
		}

		$name    = isset( $_POST['requester_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['requester_name'] ) ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['details'] ) ) : '';

		$user      = get_user_by( 'email', $email );
		$user_id   = $user ? (int) $user->ID : 0;
		$token     = RequestStore::generate_token();
		$token_ttl = max( 1, (int) $s['verification_token_ttl_h'] );
		$deadline  = gmdate( 'Y-m-d H:i:s', time() + max( 1, (int) $s['response_deadline_days'] ) * DAY_IN_SECONDS );
		$token_exp = gmdate( 'Y-m-d H:i:s', time() + $token_ttl * HOUR_IN_SECONDS );

		$status = ! empty( $s['verification_required'] ) ? 'verifying' : 'received';
		if ( is_user_logged_in() && $user_id === get_current_user_id() && $user_id > 0 ) {
			$status = 'received';
		}

		$id = RequestStore::record(
			array(
				'request_type'         => $type,
				'status'               => $status,
				'user_id'              => $user_id,
				'requester_email'      => $email,
				'requester_name'       => $name,
				'ip_hash'              => $ip_hash,
				'details'              => $details,
				'verified'             => 'verifying' !== $status ? 1 : 0,
				'verification_token'   => $token,
				'verification_expires' => $token_exp,
				'deadline_at'          => $deadline,
			)
		);

		if ( $id <= 0 ) {
			$this->bounce( 'error', __( 'We could not save your request. Please try again later.', 'eurocomply-dsar' ) );
		}

		if ( 'verifying' === $status ) {
			$this->send_verification_email( $email, $name, $token, $type );
		} elseif ( ! empty( $s['auto_ack_email'] ) ) {
			$this->send_ack_email( $email, $name, $type );
		}

		/**
		 * Fires after a DSAR is recorded.
		 *
		 * @param int $id
		 * @param array{request_type:string,status:string,requester_email:string} $data
		 */
		do_action( 'eurocomply_dsar_received', $id, array(
			'request_type'    => $type,
			'status'          => $status,
			'requester_email' => $email,
		) );

		$this->notify_admins( $id, $email, $type );

		$message = 'verifying' === $status
			? __( 'Request submitted. Please check your email to confirm your identity.', 'eurocomply-dsar' )
			: __( 'Request submitted. We will respond within the statutory deadline.', 'eurocomply-dsar' );
		$this->bounce( 'success', $message );
	}

	public function maybe_verify() : void {
		if ( empty( $_GET['eurocomply_dsar_verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = sanitize_text_field( wp_unslash( (string) $_GET['eurocomply_dsar_verify'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $token ) {
			return;
		}

		$row = RequestStore::get_by_token( $token );
		if ( ! $row ) {
			wp_die( esc_html__( 'Invalid or expired verification link.', 'eurocomply-dsar' ), esc_html__( 'DSAR verification', 'eurocomply-dsar' ), array( 'response' => 400 ) );
		}
		if ( ! empty( $row['verification_expires'] ) && strtotime( (string) $row['verification_expires'] ) < time() ) {
			wp_die( esc_html__( 'Verification link has expired. Please submit a new request.', 'eurocomply-dsar' ), esc_html__( 'DSAR verification', 'eurocomply-dsar' ), array( 'response' => 400 ) );
		}

		RequestStore::update(
			(int) $row['id'],
			array(
				'verified' => 1,
				'status'   => 'received',
			)
		);

		$page_id  = (int) ( Settings::get()['page_id'] ?? 0 );
		$redirect = $page_id > 0 ? add_query_arg( 'eurocomply_dsar_verified', '1', get_permalink( $page_id ) ) : add_query_arg( 'eurocomply_dsar_verified', '1', home_url( '/' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function send_verification_email( string $email, string $name, string $token, string $type ) : void {
		$s        = Settings::get();
		$verify_url = add_query_arg( 'eurocomply_dsar_verify', rawurlencode( $token ), home_url( '/' ) );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Confirm your data request', 'eurocomply-dsar' ),
			$s['from_name']
		);
		$message = sprintf(
			/* translators: 1: name, 2: type label, 3: verification URL, 4: ttl hours */
			__( "Hello %1\$s,\n\nWe received a \"%2\$s\" request from this email address. Please confirm it by opening the link below within %4\$d hours:\n\n%3\$s\n\nIf you did not make this request, ignore this message — no action will be taken.", 'eurocomply-dsar' ),
			$name ?: $email,
			self::type_label( $type ),
			$verify_url,
			(int) $s['verification_token_ttl_h']
		);
		$this->mail( $email, $subject, $message );
	}

	private function send_ack_email( string $email, string $name, string $type ) : void {
		$s       = Settings::get();
		$subject = sprintf( /* translators: %s: site name */ __( '[%s] Your data request has been received', 'eurocomply-dsar' ), $s['from_name'] );
		$message = sprintf(
			/* translators: 1: name, 2: type label, 3: deadline days */
			__( "Hello %1\$s,\n\nWe have received your \"%2\$s\" request and will respond within %3\$d days as required by Article 12(3) GDPR.", 'eurocomply-dsar' ),
			$name ?: $email,
			self::type_label( $type ),
			(int) $s['response_deadline_days']
		);
		$this->mail( $email, $subject, $message );
	}

	private function notify_admins( int $id, string $email, string $type ) : void {
		$s = Settings::get();
		if ( empty( $s['notification_emails'] ) ) {
			return;
		}
		$subject = sprintf(
			/* translators: 1: request id, 2: type label */
			__( '[DSAR #%1$d] New %2$s request', 'eurocomply-dsar' ),
			$id,
			self::type_label( $type )
		);
		$admin_link = admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=requests' );
		$message    = sprintf(
			/* translators: 1: email, 2: admin URL */
			__( "A new DSAR has been submitted by %1\$s.\nReview: %2\$s", 'eurocomply-dsar' ),
			$email,
			$admin_link
		);
		foreach ( (array) $s['notification_emails'] as $to ) {
			$this->mail( (string) $to, $subject, $message );
		}
	}

	private function mail( string $to, string $subject, string $message ) : void {
		$s       = Settings::get();
		$headers = array();
		if ( ! empty( $s['from_email'] ) && is_email( (string) $s['from_email'] ) ) {
			$headers[] = 'From: ' . sanitize_text_field( (string) $s['from_name'] ) . ' <' . sanitize_email( (string) $s['from_email'] ) . '>';
		}
		wp_mail( $to, $subject, $message, $headers );
	}

	public static function type_label( string $type ) : string {
		$labels = array(
			'access'      => __( 'Access (Art. 15)', 'eurocomply-dsar' ),
			'portability' => __( 'Portability (Art. 20)', 'eurocomply-dsar' ),
			'rectify'     => __( 'Rectification (Art. 16)', 'eurocomply-dsar' ),
			'erase'       => __( 'Erasure (Art. 17)', 'eurocomply-dsar' ),
			'object'      => __( 'Objection (Art. 21)', 'eurocomply-dsar' ),
			'restrict'    => __( 'Restriction (Art. 18)', 'eurocomply-dsar' ),
		);
		return $labels[ $type ] ?? $type;
	}

	private function bounce( string $type, string $message ) : void {
		$this->push_notice( $type, $message );
		$referer = wp_get_referer();
		if ( ! $referer ) {
			$referer = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( 'eurocomply_dsar_msg', (string) time(), $referer ) );
		exit;
	}

	private function push_notice( string $type, string $message ) : void {
		$bucket   = $this->notice_bucket();
		$bucket[] = array( 'type' => $type, 'message' => $message );
		set_transient( $this->notice_transient_key(), $bucket, HOUR_IN_SECONDS );
	}

	/**
	 * @return array<int,array{type:string,message:string}>
	 */
	private function pop_notices() : array {
		$bucket = $this->notice_bucket();
		if ( ! empty( $bucket ) ) {
			delete_transient( $this->notice_transient_key() );
		}
		return $bucket;
	}

	/**
	 * @return array<int,array{type:string,message:string}>
	 */
	private function notice_bucket() : array {
		$bucket = get_transient( $this->notice_transient_key() );
		return is_array( $bucket ) ? $bucket : array();
	}

	private function notice_transient_key() : string {
		return 'eurocomply_dsar_notice_' . md5( (string) self::client_ip() . wp_salt( 'nonce' ) );
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
