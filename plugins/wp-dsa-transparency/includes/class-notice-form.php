<?php
/**
 * Public notice-and-action form (DSA Article 16).
 *
 * Registers [eurocomply_dsa_notice_form] shortcode and handles POST submissions
 * via admin-post. Writes entries into NoticeStore with a hashed IP + optional
 * honeypot + simple per-IP rate limit.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NoticeForm {

	public const NONCE        = 'eurocomply_dsa_notice';
	public const ACTION       = 'eurocomply_dsa_submit_notice';
	public const SUBMITTED_Q  = 'eurocomply_dsa_notice_submitted';

	private static ?NoticeForm $instance = null;

	public static function instance() : NoticeForm {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_shortcode( 'eurocomply_dsa_notice_form', array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submit' ) );
	}

	/**
	 * @param array<string,mixed>|string $atts
	 */
	public function render_shortcode( $atts = array() ) : string {
		$settings = Settings::get();

		if ( ! empty( $settings['notice_form_require_login'] ) && ! is_user_logged_in() ) {
			return '<p class="eurocomply-dsa-notice-login">'
				. esc_html__( 'You must be logged in to submit a DSA notice.', 'eurocomply-dsa' )
				. '</p>';
		}

		$submitted = isset( $_GET[ self::SUBMITTED_Q ] ) && '1' === $_GET[ self::SUBMITTED_Q ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_msg = isset( $_GET['eurocomply_dsa_error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['eurocomply_dsa_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		?>
		<div class="eurocomply-dsa-notice-form">
			<?php if ( $submitted ) : ?>
				<div class="eurocomply-dsa-notice-success" role="status">
					<?php esc_html_e( 'Thank you — your DSA notice has been received. A moderation decision will follow without undue delay.', 'eurocomply-dsa' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $error_msg ) : ?>
				<div class="eurocomply-dsa-notice-error" role="alert">
					<?php echo esc_html( $error_msg ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eurocomply-dsa-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE, '_eurocomply_dsa_notice_nonce' ); ?>

				<?php if ( ! empty( $settings['notice_form_honeypot'] ) ) : ?>
					<div class="eurocomply-dsa-hp" aria-hidden="true" style="position:absolute;left:-9999px;">
						<label><?php esc_html_e( 'Leave this field empty', 'eurocomply-dsa' ); ?>
							<input type="text" name="eurocomply_dsa_website" value="" autocomplete="off" tabindex="-1" />
						</label>
					</div>
				<?php endif; ?>

				<p>
					<label for="eurocomply-dsa-name"><?php esc_html_e( 'Your name', 'eurocomply-dsa' ); ?></label>
					<input type="text" id="eurocomply-dsa-name" name="reporter_name" required />
				</p>
				<p>
					<label for="eurocomply-dsa-email"><?php esc_html_e( 'Your email', 'eurocomply-dsa' ); ?></label>
					<input type="email" id="eurocomply-dsa-email" name="reporter_email" required />
				</p>
				<p>
					<label for="eurocomply-dsa-role"><?php esc_html_e( 'You are reporting as', 'eurocomply-dsa' ); ?></label>
					<select id="eurocomply-dsa-role" name="reporter_role">
						<option value="user"><?php esc_html_e( 'An individual user', 'eurocomply-dsa' ); ?></option>
						<option value="rights_holder"><?php esc_html_e( 'A rights holder', 'eurocomply-dsa' ); ?></option>
						<option value="trusted_flagger"><?php esc_html_e( 'A trusted flagger (DSA Art. 22)', 'eurocomply-dsa' ); ?></option>
						<option value="authority"><?php esc_html_e( 'A public authority', 'eurocomply-dsa' ); ?></option>
					</select>
				</p>
				<p>
					<label for="eurocomply-dsa-url"><?php esc_html_e( 'Exact URL of the content you are reporting', 'eurocomply-dsa' ); ?></label>
					<input type="url" id="eurocomply-dsa-url" name="target_url" required />
				</p>
				<p>
					<label for="eurocomply-dsa-category"><?php esc_html_e( 'Category of illegality', 'eurocomply-dsa' ); ?></label>
					<select id="eurocomply-dsa-category" name="category" required>
						<?php foreach ( (array) $settings['notice_form_categories'] as $cat ) : ?>
							<option value="<?php echo esc_attr( (string) $cat ); ?>">
								<?php echo esc_html( self::category_label( (string) $cat ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="eurocomply-dsa-legal"><?php esc_html_e( 'Legal basis (optional)', 'eurocomply-dsa' ); ?></label>
					<input type="text" id="eurocomply-dsa-legal" name="legal_basis" placeholder="<?php esc_attr_e( 'e.g. GDPR Art. 17, DSM Directive Art. 17', 'eurocomply-dsa' ); ?>" />
				</p>
				<p>
					<label for="eurocomply-dsa-desc"><?php esc_html_e( 'Why is this content illegal?', 'eurocomply-dsa' ); ?></label>
					<textarea id="eurocomply-dsa-desc" name="description" rows="5" required></textarea>
				</p>
				<p>
					<label for="eurocomply-dsa-evidence"><?php esc_html_e( 'Supporting evidence / links (optional)', 'eurocomply-dsa' ); ?></label>
					<textarea id="eurocomply-dsa-evidence" name="evidence" rows="3"></textarea>
				</p>
				<p>
					<label>
						<input type="checkbox" name="good_faith" value="1" required />
						<?php esc_html_e( 'I submit this notice in good faith and the information provided is accurate.', 'eurocomply-dsa' ); ?>
					</label>
				</p>
				<p>
					<button type="submit" class="eurocomply-dsa-submit">
						<?php esc_html_e( 'Submit DSA notice', 'eurocomply-dsa' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function handle_submit() : void {
		$settings = Settings::get();

		if ( ! empty( $settings['notice_form_require_login'] ) && ! is_user_logged_in() ) {
			$this->redirect_error( __( 'You must be logged in to submit a notice.', 'eurocomply-dsa' ) );
		}

		check_admin_referer( self::NONCE, '_eurocomply_dsa_notice_nonce' );

		if ( ! empty( $settings['notice_form_honeypot'] ) && ! empty( $_POST['eurocomply_dsa_website'] ) ) {
			// Honeypot tripped — silently drop.
			wp_safe_redirect( $this->back_url() );
			exit;
		}

		if ( empty( $_POST['good_faith'] ) ) {
			$this->redirect_error( __( 'You must confirm the notice is submitted in good faith.', 'eurocomply-dsa' ) );
		}

		$ip_hash = self::ip_hash();
		$limit   = (int) $settings['notice_form_rate_limit'];
		if ( $limit > 0 && $ip_hash ) {
			$recent = NoticeStore::count_ip_recent( $ip_hash, 60 );
			if ( $recent >= $limit ) {
				$this->redirect_error( __( 'Too many notices from your network. Please try again later.', 'eurocomply-dsa' ) );
			}
		}

		$target_url = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['target_url'] ) ) : '';
		if ( '' === $target_url ) {
			$this->redirect_error( __( 'Please provide the URL of the reported content.', 'eurocomply-dsa' ) );
		}

		$data = array(
			'reporter_name'  => isset( $_POST['reporter_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['reporter_name'] ) ) : '',
			'reporter_email' => isset( $_POST['reporter_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['reporter_email'] ) ) : '',
			'reporter_role'  => isset( $_POST['reporter_role'] ) ? sanitize_key( wp_unslash( (string) $_POST['reporter_role'] ) ) : 'user',
			'target_url'     => $target_url,
			'target_post_id' => url_to_postid( $target_url ),
			'category'       => isset( $_POST['category'] ) ? sanitize_key( wp_unslash( (string) $_POST['category'] ) ) : 'other',
			'legal_basis'    => isset( $_POST['legal_basis'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['legal_basis'] ) ) : '',
			'description'    => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) ) : '',
			'evidence'       => isset( $_POST['evidence'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['evidence'] ) ) : '',
			'status'         => 'received',
			'ip_hash'        => $ip_hash,
		);

		$id = NoticeStore::record( $data );
		if ( ! $id ) {
			$this->redirect_error( __( 'Could not record the notice. Please try again.', 'eurocomply-dsa' ) );
		}

		/**
		 * Fires after a DSA notice has been recorded.
		 *
		 * @param int                  $id
		 * @param array<string,mixed>  $data
		 */
		do_action( 'eurocomply_dsa_notice_received', $id, $data );

		if ( ! empty( $settings['contact_point_email'] ) ) {
			wp_mail(
				(string) $settings['contact_point_email'],
				sprintf( /* translators: %d: notice ID */ __( '[DSA] New notice #%d received', 'eurocomply-dsa' ), $id ),
				sprintf( /* translators: 1: notice ID, 2: URL */ __( "A new DSA Art. 16 notice has been received.\n\nID: %1\$d\nURL: %2\$s\n\nReview in wp-admin under DSA Transparency -> Notices.", 'eurocomply-dsa' ), $id, $target_url )
			);
		}

		$back = add_query_arg( self::SUBMITTED_Q, '1', $this->back_url() );
		wp_safe_redirect( $back );
		exit;
	}

	private function redirect_error( string $message ) : void {
		$back = add_query_arg( 'eurocomply_dsa_error', rawurlencode( $message ), $this->back_url() );
		wp_safe_redirect( $back );
		exit;
	}

	private function back_url() : string {
		$ref = wp_get_referer();
		if ( $ref ) {
			return $ref;
		}
		return home_url( '/' );
	}

	private static function ip_hash() : string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		if ( '' === $ip ) {
			return '';
		}
		$salt = wp_salt( 'nonce' );
		return hash( 'sha256', $ip . '|' . $salt );
	}

	public static function category_label( string $key ) : string {
		$map = array(
			'illegal_content'       => __( 'Illegal content', 'eurocomply-dsa' ),
			'counterfeit'           => __( 'Counterfeit goods', 'eurocomply-dsa' ),
			'intellectual_property' => __( 'Intellectual property infringement', 'eurocomply-dsa' ),
			'data_protection'       => __( 'Data protection / privacy violation', 'eurocomply-dsa' ),
			'consumer_protection'   => __( 'Consumer protection violation', 'eurocomply-dsa' ),
			'hate_speech'           => __( 'Hate speech / illegal incitement', 'eurocomply-dsa' ),
			'child_safety'          => __( 'Child safety / CSAM', 'eurocomply-dsa' ),
			'terrorism'             => __( 'Terrorist content', 'eurocomply-dsa' ),
			'other'                 => __( 'Other illegal content', 'eurocomply-dsa' ),
		);
		return $map[ $key ] ?? $key;
	}
}
