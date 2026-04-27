<?php
/**
 * Public vulnerability-report shortcode (CRA-aligned).
 *
 * Shortcode: [eurocomply_nis2_vuln_report]
 *
 * @package EuroComply\NIS2
 */

declare( strict_types = 1 );

namespace EuroComply\NIS2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VulnReportForm {

	public const SHORTCODE     = 'eurocomply_nis2_vuln_report';
	public const NONCE_SUBMIT  = 'eurocomply_nis2_vuln_submit';
	public const ACTION_SUBMIT = 'eurocomply_nis2_vuln_submit';

	private static ?VulnReportForm $instance = null;

	public static function instance() : VulnReportForm {
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
	}

	public function render( $atts ) : string {
		$s = Settings::get();
		if ( empty( $s['public_vuln_form_enabled'] ) ) {
			return '';
		}
		$notices = $this->pop_notices();
		ob_start();
		?>
		<div class="eurocomply-nis2-vuln-form-wrap">
			<?php foreach ( $notices as $notice ) : ?>
				<div class="eurocomply-nis2-notice eurocomply-nis2-notice--<?php echo esc_attr( (string) $notice['type'] ); ?>"><?php echo esc_html( (string) $notice['message'] ); ?></div>
			<?php endforeach; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eurocomply-nis2-vuln-form">
				<?php wp_nonce_field( self::NONCE_SUBMIT, '_eurocomply_nis2_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SUBMIT ); ?>" />

				<p>
					<label for="eurocomply-nis2-title"><?php esc_html_e( 'Short title', 'eurocomply-nis2' ); ?> <span aria-hidden="true">*</span></label>
					<input type="text" id="eurocomply-nis2-title" name="title" required="required" maxlength="255" />
				</p>
				<p>
					<label for="eurocomply-nis2-email"><?php esc_html_e( 'Your email (optional)', 'eurocomply-nis2' ); ?></label>
					<input type="email" id="eurocomply-nis2-email" name="reporter_email" />
				</p>
				<p>
					<label for="eurocomply-nis2-severity"><?php esc_html_e( 'Your assessment', 'eurocomply-nis2' ); ?></label>
					<select id="eurocomply-nis2-severity" name="severity">
						<option value="low"><?php esc_html_e( 'Low', 'eurocomply-nis2' ); ?></option>
						<option value="medium" selected="selected"><?php esc_html_e( 'Medium', 'eurocomply-nis2' ); ?></option>
						<option value="high"><?php esc_html_e( 'High', 'eurocomply-nis2' ); ?></option>
						<option value="critical"><?php esc_html_e( 'Critical', 'eurocomply-nis2' ); ?></option>
					</select>
				</p>
				<p>
					<label for="eurocomply-nis2-details"><?php esc_html_e( 'Technical details', 'eurocomply-nis2' ); ?> <span aria-hidden="true">*</span></label>
					<textarea id="eurocomply-nis2-details" name="details" rows="8" required="required" placeholder="<?php esc_attr_e( 'Describe the vulnerability, reproduction steps, and any proof-of-concept references. Do not attach credentials or live exploits.', 'eurocomply-nis2' ); ?>"></textarea>
				</p>

				<?php // Honeypot. ?>
				<p style="position:absolute;left:-9999px;" aria-hidden="true">
					<label><?php esc_html_e( 'Website', 'eurocomply-nis2' ); ?>
						<input type="text" name="eurocomply_nis2_website" value="" autocomplete="off" tabindex="-1" />
					</label>
				</p>

				<button type="submit" class="eurocomply-nis2-submit"><?php esc_html_e( 'Submit vulnerability report', 'eurocomply-nis2' ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function handle_submit() : void {
		if ( ! isset( $_POST['_eurocomply_nis2_nonce'] ) || ! wp_verify_nonce( (string) $_POST['_eurocomply_nis2_nonce'], self::NONCE_SUBMIT ) ) {
			$this->bounce( 'error', __( 'Security check failed. Please try again.', 'eurocomply-nis2' ) );
		}
		if ( ! empty( $_POST['eurocomply_nis2_website'] ) ) {
			$this->bounce( 'success', __( 'Report received.', 'eurocomply-nis2' ) );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '';
		if ( '' === $title ) {
			$this->bounce( 'error', __( 'A short title is required.', 'eurocomply-nis2' ) );
		}
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['details'] ) ) : '';
		if ( '' === $details ) {
			$this->bounce( 'error', __( 'Details are required.', 'eurocomply-nis2' ) );
		}
		$severity = isset( $_POST['severity'] ) ? sanitize_key( (string) $_POST['severity'] ) : 'medium';
		if ( ! in_array( $severity, IncidentStore::SEVERITIES, true ) ) {
			$severity = 'medium';
		}
		$reporter = isset( $_POST['reporter_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['reporter_email'] ) ) : '';

		$id = IncidentStore::create(
			array(
				'title'          => 'VULN: ' . $title,
				'category'       => 'other',
				'severity'       => $severity,
				'status'         => 'draft',
				'impact_summary' => __( 'Reported via public vulnerability form.', 'eurocomply-nis2' ),
				'notes'          => sprintf(
					"Reporter: %s\n\n%s",
					$reporter ?: __( 'anonymous', 'eurocomply-nis2' ),
					$details
				),
			)
		);

		EventStore::record(
			array(
				'category' => 'security',
				'severity' => $severity,
				'action'   => 'vuln_report_submitted',
				'target'   => 'incident#' . $id,
				'ip_hash'  => EventStore::ip_hash( $this->client_ip() ),
				'details'  => array( 'reporter' => $reporter ),
			)
		);

		$this->notify_admins( $id, $title, $severity );

		$this->bounce( 'success', __( 'Thank you. Your report has been logged and will be reviewed by our security team.', 'eurocomply-nis2' ) );
	}

	private function notify_admins( int $id, string $title, string $severity ) : void {
		$s = Settings::get();
		if ( empty( $s['notification_emails'] ) ) {
			return;
		}
		$subject = sprintf( /* translators: 1: incident id, 2: severity */ __( '[NIS2 #%1$d] New vulnerability report (%2$s)', 'eurocomply-nis2' ), $id, $severity );
		$admin   = admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=incidents' );
		$body    = sprintf( /* translators: 1: title, 2: admin url */ __( 'A vulnerability report was submitted: %1$s.\nReview: %2$s', 'eurocomply-nis2' ), $title, $admin );
		foreach ( (array) $s['notification_emails'] as $to ) {
			wp_mail( (string) $to, $subject, $body );
		}
	}

	private function bounce( string $type, string $message ) : void {
		$this->push_notice( $type, $message );
		$referer = wp_get_referer();
		if ( ! $referer ) {
			$referer = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( 'eurocomply_nis2_msg', (string) time(), $referer ) );
		exit;
	}

	private function push_notice( string $type, string $message ) : void {
		$bucket   = $this->notice_bucket();
		$bucket[] = array( 'type' => $type, 'message' => $message );
		set_transient( $this->notice_key(), $bucket, HOUR_IN_SECONDS );
	}

	/**
	 * @return array<int,array{type:string,message:string}>
	 */
	private function pop_notices() : array {
		$bucket = $this->notice_bucket();
		if ( ! empty( $bucket ) ) {
			delete_transient( $this->notice_key() );
		}
		return $bucket;
	}

	/**
	 * @return array<int,array{type:string,message:string}>
	 */
	private function notice_bucket() : array {
		$b = get_transient( $this->notice_key() );
		return is_array( $b ) ? $b : array();
	}

	private function notice_key() : string {
		return 'eurocomply_nis2_notice_' . md5( $this->client_ip() . wp_salt( 'nonce' ) );
	}

	private function client_ip() : string {
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
