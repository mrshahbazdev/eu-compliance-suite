<?php
/**
 * Public trader-information form (DSA Article 30 — KYBP / traceability).
 *
 * Registers [eurocomply_dsa_trader_form] shortcode for vendors/sellers to
 * submit or update their trader info. Writes entries into TraderStore keyed
 * by current user id.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TraderForm {

	public const NONCE       = 'eurocomply_dsa_trader';
	public const ACTION      = 'eurocomply_dsa_submit_trader';
	public const SUBMITTED_Q = 'eurocomply_dsa_trader_submitted';

	private static ?TraderForm $instance = null;

	public static function instance() : TraderForm {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_shortcode( 'eurocomply_dsa_trader_form', array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
	}

	public function render_shortcode() : string {
		$settings = Settings::get();
		if ( ! empty( $settings['trader_form_require_login'] ) && ! is_user_logged_in() ) {
			return '<p class="eurocomply-dsa-trader-login">'
				. esc_html__( 'You must be logged in to submit trader information.', 'eurocomply-dsa' )
				. '</p>';
		}

		$current = get_current_user_id() ? TraderStore::by_user( get_current_user_id() ) : null;
		$submitted = isset( $_GET[ self::SUBMITTED_Q ] ) && '1' === $_GET[ self::SUBMITTED_Q ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$v       = static function ( ?array $c, string $k ) : string {
			return $c && isset( $c[ $k ] ) ? (string) $c[ $k ] : '';
		};

		ob_start();
		?>
		<div class="eurocomply-dsa-trader-form">
			<?php if ( $submitted ) : ?>
				<div class="eurocomply-dsa-trader-success" role="status">
					<?php esc_html_e( 'Thank you — your trader information has been recorded and is pending review.', 'eurocomply-dsa' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $current && 'verified' === $current['verification_status'] ) : ?>
				<p class="eurocomply-dsa-trader-verified">
					<strong><?php esc_html_e( 'Your trader information is verified.', 'eurocomply-dsa' ); ?></strong>
					<?php esc_html_e( 'Submitting this form again will reset the verification status to pending until re-reviewed.', 'eurocomply-dsa' ); ?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eurocomply-dsa-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE, '_eurocomply_dsa_trader_nonce' ); ?>

				<p>
					<label><?php esc_html_e( 'Legal name of the business', 'eurocomply-dsa' ); ?>
						<input type="text" name="legal_name" required value="<?php echo esc_attr( $v( $current, 'legal_name' ) ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Trade name (if different)', 'eurocomply-dsa' ); ?>
						<input type="text" name="trade_name" value="<?php echo esc_attr( $v( $current, 'trade_name' ) ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Address line 1', 'eurocomply-dsa' ); ?>
						<input type="text" name="address_line1" required value="<?php echo esc_attr( $v( $current, 'address_line1' ) ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Address line 2', 'eurocomply-dsa' ); ?>
						<input type="text" name="address_line2" value="<?php echo esc_attr( $v( $current, 'address_line2' ) ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Postcode', 'eurocomply-dsa' ); ?>
						<input type="text" name="postcode" required value="<?php echo esc_attr( $v( $current, 'postcode' ) ); ?>" />
					</label>
					<label><?php esc_html_e( 'City', 'eurocomply-dsa' ); ?>
						<input type="text" name="city" required value="<?php echo esc_attr( $v( $current, 'city' ) ); ?>" />
					</label>
					<label><?php esc_html_e( 'Country (ISO-2)', 'eurocomply-dsa' ); ?>
						<input type="text" name="country" maxlength="2" required value="<?php echo esc_attr( $v( $current, 'country' ) ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Contact email', 'eurocomply-dsa' ); ?>
						<input type="email" name="email" required value="<?php echo esc_attr( $v( $current, 'email' ) ); ?>" />
					</label>
					<label><?php esc_html_e( 'Contact phone', 'eurocomply-dsa' ); ?>
						<input type="text" name="phone" value="<?php echo esc_attr( $v( $current, 'phone' ) ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Contact person', 'eurocomply-dsa' ); ?>
						<input type="text" name="contact_person" value="<?php echo esc_attr( $v( $current, 'contact_person' ) ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Trade register number', 'eurocomply-dsa' ); ?>
						<input type="text" name="trade_register" required value="<?php echo esc_attr( $v( $current, 'trade_register' ) ); ?>" />
					</label>
					<label><?php esc_html_e( 'VAT number', 'eurocomply-dsa' ); ?>
						<input type="text" name="vat_number" value="<?php echo esc_attr( $v( $current, 'vat_number' ) ); ?>" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'ID document reference (optional)', 'eurocomply-dsa' ); ?>
						<input type="text" name="id_document_ref" value="<?php echo esc_attr( $v( $current, 'id_document_ref' ) ); ?>" />
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="self_certification" value="1" required <?php checked( $current && ! empty( $current['self_certification'] ) ); ?> />
						<?php esc_html_e( 'I certify that my products comply with all applicable EU and national law.', 'eurocomply-dsa' ); ?>
					</label>
				</p>
				<p>
					<button type="submit" class="eurocomply-dsa-submit">
						<?php esc_html_e( 'Submit trader information', 'eurocomply-dsa' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function handle_submit() : void {
		$settings = Settings::get();
		if ( ! empty( $settings['trader_form_require_login'] ) && ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}
		check_admin_referer( self::NONCE, '_eurocomply_dsa_trader_nonce' );

		$required = array( 'legal_name', 'address_line1', 'postcode', 'city', 'country', 'email', 'trade_register', 'self_certification' );
		foreach ( $required as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				$back = add_query_arg( 'eurocomply_dsa_error', rawurlencode( __( 'Please complete all required fields and the self-certification.', 'eurocomply-dsa' ) ), wp_get_referer() ?: home_url( '/' ) );
				wp_safe_redirect( $back );
				exit;
			}
		}

		$data = array(
			'user_id'            => get_current_user_id(),
			'legal_name'         => sanitize_text_field( wp_unslash( (string) $_POST['legal_name'] ) ),
			'trade_name'         => isset( $_POST['trade_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['trade_name'] ) ) : '',
			'address_line1'      => sanitize_text_field( wp_unslash( (string) $_POST['address_line1'] ) ),
			'address_line2'      => isset( $_POST['address_line2'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['address_line2'] ) ) : '',
			'postcode'           => sanitize_text_field( wp_unslash( (string) $_POST['postcode'] ) ),
			'city'               => sanitize_text_field( wp_unslash( (string) $_POST['city'] ) ),
			'country'            => strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['country'] ) ) ),
			'email'              => sanitize_email( wp_unslash( (string) $_POST['email'] ) ),
			'phone'              => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['phone'] ) ) : '',
			'contact_person'     => isset( $_POST['contact_person'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['contact_person'] ) ) : '',
			'trade_register'     => sanitize_text_field( wp_unslash( (string) $_POST['trade_register'] ) ),
			'vat_number'         => isset( $_POST['vat_number'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vat_number'] ) ) : '',
			'id_document_ref'    => isset( $_POST['id_document_ref'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['id_document_ref'] ) ) : '',
			'self_certification' => 1,
			'verification_status' => 'pending',
		);

		$id = TraderStore::upsert( $data );
		/**
		 * Fires after a trader has submitted or updated their Art. 30 dossier.
		 *
		 * @param int                  $id
		 * @param array<string,mixed>  $data
		 */
		do_action( 'eurocomply_dsa_trader_submitted', $id, $data );

		$back = add_query_arg( self::SUBMITTED_Q, '1', wp_get_referer() ?: home_url( '/' ) );
		wp_safe_redirect( $back );
		exit;
	}
}
