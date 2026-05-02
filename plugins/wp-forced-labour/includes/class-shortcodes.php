<?php
/**
 * Public shortcodes.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

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
		add_shortcode( 'eurocomply_fl_statement', array( $this, 'statement' ) );
		add_shortcode( 'eurocomply_fl_submit', array( $this, 'submission_form' ) );
		add_action( 'init', array( $this, 'maybe_handle_submission' ) );
	}

	public function statement( $atts = array() ) : string {
		$s = Settings::get();
		ob_start();
		?>
		<div class="eurocomply-fl-statement">
			<h3><?php esc_html_e( 'Forced labour due-diligence statement', 'eurocomply-forced-labour' ); ?></h3>
			<p><?php
				printf(
					/* translators: 1: company name, 2: reporting year */
					esc_html__( '%1$s applies the due-diligence framework set out in Reg. (EU) 2024/3015 prohibiting products made with forced labour on the EU market. This statement covers the reporting year %2$s.', 'eurocomply-forced-labour' ),
					esc_html( (string) $s['company_name'] ),
					esc_html( (string) $s['reporting_year'] )
				);
			?></p>
			<ul>
				<li><?php esc_html_e( 'Suppliers, including chain-of-activities partners, are mapped and risk-scored against the 11 ILO indicators of forced labour.', 'eurocomply-forced-labour' ); ?></li>
				<li><?php esc_html_e( 'Where high risk is identified, audit, certification and corrective procedures are recorded in our internal register.', 'eurocomply-forced-labour' ); ?></li>
				<li><?php esc_html_e( 'Any natural or legal person may submit information about suspected forced-labour products via the public form provided on this site.', 'eurocomply-forced-labour' ); ?></li>
				<li><?php esc_html_e( 'Where required, withdrawal procedures are executed and recorded for downstream channels.', 'eurocomply-forced-labour' ); ?></li>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function submission_form( $atts = array() ) : string {
		$indicators = Settings::indicators();
		$sectors    = Settings::high_risk_sectors();
		$success    = isset( $_GET['eurocomply_fl_submitted'] ) ? sanitize_key( wp_unslash( (string) $_GET['eurocomply_fl_submitted'] ) ) : '';
		$token      = isset( $_GET['eurocomply_fl_token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['eurocomply_fl_token'] ) ) : '';
		ob_start();
		?>
		<div class="eurocomply-fl-submit">
			<?php if ( '1' === $success ) : ?>
				<div class="notice"><p><strong><?php esc_html_e( 'Thank you. Your submission has been recorded.', 'eurocomply-forced-labour' ); ?></strong></p>
				<?php if ( '' !== $token ) : ?>
					<p><?php esc_html_e( 'Your follow-up token (please save):', 'eurocomply-forced-labour' ); ?> <code><?php echo esc_html( $token ); ?></code></p>
				<?php endif; ?>
				</div>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'eurocomply_fl_submit', 'eurocomply_fl_nonce' ); ?>
				<input type="text" name="hp" value="" autocomplete="off" tabindex="-1" style="position:absolute;left:-5000px;" aria-hidden="true" />
				<p><label><?php esc_html_e( 'Email (optional — leave blank to remain anonymous)', 'eurocomply-forced-labour' ); ?><br />
					<input type="email" name="email" /></label></p>
				<p><label><?php esc_html_e( 'Country of suspected forced labour (ISO-3166)', 'eurocomply-forced-labour' ); ?><br />
					<input type="text" name="country" maxlength="8" /></label></p>
				<p><label><?php esc_html_e( 'Sector', 'eurocomply-forced-labour' ); ?><br />
					<select name="sector"><option value=""></option>
					<?php foreach ( $sectors as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
					</select></label></p>
				<p><label><?php esc_html_e( 'Indicator', 'eurocomply-forced-labour' ); ?><br />
					<select name="indicator"><option value=""></option>
					<?php foreach ( $indicators as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
					</select></label></p>
				<p><label><?php esc_html_e( 'Summary', 'eurocomply-forced-labour' ); ?><br />
					<textarea name="summary" rows="5" cols="60" required></textarea></label></p>
				<p><button type="submit" name="eurocomply_fl_submit" value="1"><?php esc_html_e( 'Submit', 'eurocomply-forced-labour' ); ?></button></p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function maybe_handle_submission() : void {
		if ( empty( $_POST['eurocomply_fl_submit'] ) ) {
			return;
		}
		if ( ! isset( $_POST['eurocomply_fl_nonce'] ) || ! wp_verify_nonce( (string) $_POST['eurocomply_fl_nonce'], 'eurocomply_fl_submit' ) ) {
			return;
		}
		if ( ! empty( $_POST['hp'] ) ) {
			return;
		}
		$result = SubmissionStore::insert(
			array(
				'email'       => (string) ( $_POST['email'] ?? '' ),
				'country'     => (string) ( $_POST['country'] ?? '' ),
				'sector'      => (string) ( $_POST['sector'] ?? '' ),
				'indicator'   => (string) ( $_POST['indicator'] ?? '' ),
				'summary'     => (string) ( $_POST['summary'] ?? '' ),
			)
		);
		$redir = add_query_arg(
			array(
				'eurocomply_fl_submitted' => '1',
				'eurocomply_fl_token'     => $result['token'] ?? '',
			),
			(string) wp_get_referer()
		);
		wp_safe_redirect( $redir );
		exit;
	}
}
