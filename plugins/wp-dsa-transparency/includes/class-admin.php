<?php
/**
 * Admin UI for EuroComply DSA Transparency.
 *
 * 8 tabs: Dashboard, Notices, Statements, Traders, Report, Settings, Pro, License.
 *
 * @package EuroComply\DSA
 */

declare( strict_types = 1 );

namespace EuroComply\DSA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG      = 'eurocomply-dsa';
	public const NONCE_SAVE     = 'eurocomply_dsa_save_settings';
	public const NONCE_LICENSE  = 'eurocomply_dsa_license';
	public const NONCE_NOTICE   = 'eurocomply_dsa_notice_action';
	public const NONCE_TRADER   = 'eurocomply_dsa_trader_action';
	public const NONCE_STATE    = 'eurocomply_dsa_statement_action';

	private static ?Admin $instance = null;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_eurocomply_dsa_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_dsa_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_dsa_notice_update', array( $this, 'handle_notice_update' ) );
		add_action( 'admin_post_eurocomply_dsa_verify_trader', array( $this, 'handle_verify_trader' ) );
		add_action( 'admin_post_eurocomply_dsa_statement', array( $this, 'handle_statement' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'DSA Transparency', 'eurocomply-dsa' ),
			__( 'DSA', 'eurocomply-dsa' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield-alt',
			78
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-dsa-admin',
			EUROCOMPLY_DSA_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_DSA_VERSION
		);
	}

	public function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$tabs = array(
			'dashboard'  => __( 'Dashboard', 'eurocomply-dsa' ),
			'notices'    => __( 'Notices', 'eurocomply-dsa' ),
			'statements' => __( 'Statements of Reasons', 'eurocomply-dsa' ),
			'traders'    => __( 'Traders', 'eurocomply-dsa' ),
			'report'     => __( 'Transparency Report', 'eurocomply-dsa' ),
			'settings'   => __( 'Settings', 'eurocomply-dsa' ),
			'pro'        => __( 'Pro', 'eurocomply-dsa' ),
			'license'    => __( 'License', 'eurocomply-dsa' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		echo '<div class="wrap eurocomply-dsa-admin">';
		echo '<h1>' . esc_html__( 'EuroComply DSA Transparency', 'eurocomply-dsa' )
			. ' <span class="eurocomply-dsa-version">v' . esc_html( EUROCOMPLY_DSA_VERSION ) . '</span></h1>';

		settings_errors( 'eurocomply_dsa' );

		echo '<nav class="nav-tab-wrapper eurocomply-dsa-tabs">';
		foreach ( $tabs as $slug => $label ) {
			$url  = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
			$cls  = 'nav-tab' . ( $slug === $tab ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'notices':
				$this->render_notices();
				break;
			case 'statements':
				$this->render_statements();
				break;
			case 'traders':
				$this->render_traders();
				break;
			case 'report':
				$this->render_report();
				break;
			case 'settings':
				$this->render_settings();
				break;
			case 'pro':
				$this->render_pro();
				break;
			case 'license':
				$this->render_license();
				break;
			case 'dashboard':
			default:
				$this->render_dashboard();
				break;
		}

		echo '</div>';
	}

	private function render_dashboard() : void {
		$notice_counts   = NoticeStore::status_counts();
		$trader_counts   = TraderStore::status_counts();
		$statements_30d  = StatementStore::count_since( time() - ( 30 * DAY_IN_SECONDS ) );
		$notices_30d     = NoticeStore::count_since( time() - ( 30 * DAY_IN_SECONDS ) );

		echo '<div class="eurocomply-dsa-cards">';
		$this->card( __( 'Notices received (30d)', 'eurocomply-dsa' ), (string) $notices_30d );
		$this->card( __( 'Statements issued (30d)', 'eurocomply-dsa' ), (string) $statements_30d );
		$this->card( __( 'Notices received (all time)', 'eurocomply-dsa' ), (string) array_sum( $notice_counts ) );
		$this->card( __( 'Traders verified', 'eurocomply-dsa' ), (string) ( $trader_counts['verified'] ?? 0 ) );
		$this->card( __( 'Traders pending', 'eurocomply-dsa' ), (string) ( $trader_counts['pending'] ?? 0 ) );
		echo '</div>';

		echo '<p>' . esc_html__( 'Add the public DSA notice form to any page or post with the shortcode:', 'eurocomply-dsa' ) . ' <code>[eurocomply_dsa_notice_form]</code></p>';
		echo '<p>' . esc_html__( 'Add the trader-information form to your vendor onboarding page with the shortcode:', 'eurocomply-dsa' ) . ' <code>[eurocomply_dsa_trader_form]</code></p>';

		$recent = NoticeStore::recent( 10 );
		echo '<h2>' . esc_html__( 'Recent notices', 'eurocomply-dsa' ) . '</h2>';
		$this->render_notices_table( $recent, false );
	}

	private function render_notices() : void {
		$rows = NoticeStore::recent( 200 );
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => CsvExport::ACTION,
					'dataset' => 'notices',
				),
				admin_url( 'admin-post.php' )
			),
			CsvExport::NONCE
		);
		echo '<p><a class="button" href="' . esc_url( $export_url ) . '">'
			. esc_html__( 'Export notices CSV', 'eurocomply-dsa' ) . '</a></p>';
		$this->render_notices_table( $rows, true );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function render_notices_table( array $rows, bool $with_actions ) : void {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No notices received yet.', 'eurocomply-dsa' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Submitted', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Reporter', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Target', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'eurocomply-dsa' ) . '</th>';
		if ( $with_actions ) {
			echo '<th>' . esc_html__( 'Actions', 'eurocomply-dsa' ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . (int) $row['id'] . '</td>';
			echo '<td>' . esc_html( (string) $row['submitted_at'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['reporter_name'] ) . '<br /><small>' . esc_html( (string) $row['reporter_email'] ) . '</small></td>';
			echo '<td>' . esc_html( NoticeForm::category_label( (string) $row['category'] ) ) . '</td>';
			echo '<td><a href="' . esc_url( (string) $row['target_url'] ) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html( wp_trim_words( (string) $row['target_url'], 6 ) ) . '</a></td>';
			echo '<td>' . esc_html( (string) $row['status'] ) . '</td>';
			if ( $with_actions ) {
				echo '<td>';
				$form_url = admin_url( 'admin-post.php' );
				echo '<form method="post" action="' . esc_url( $form_url ) . '" style="display:inline-flex;gap:.3rem;">';
				wp_nonce_field( self::NONCE_NOTICE, '_eurocomply_dsa_notice_nonce' );
				echo '<input type="hidden" name="action" value="eurocomply_dsa_notice_update" />';
				echo '<input type="hidden" name="notice_id" value="' . (int) $row['id'] . '" />';
				echo '<select name="status"><option value="received"' . selected( 'received', (string) $row['status'], false ) . '>'
					. esc_html__( 'Received', 'eurocomply-dsa' ) . '</option>';
				echo '<option value="under_review"' . selected( 'under_review', (string) $row['status'], false ) . '>'
					. esc_html__( 'Under review', 'eurocomply-dsa' ) . '</option>';
				echo '<option value="acted"' . selected( 'acted', (string) $row['status'], false ) . '>'
					. esc_html__( 'Acted (content removed/demoted)', 'eurocomply-dsa' ) . '</option>';
				echo '<option value="rejected"' . selected( 'rejected', (string) $row['status'], false ) . '>'
					. esc_html__( 'Rejected', 'eurocomply-dsa' ) . '</option>';
				echo '<option value="closed"' . selected( 'closed', (string) $row['status'], false ) . '>'
					. esc_html__( 'Closed', 'eurocomply-dsa' ) . '</option>';
				echo '</select>';
				echo '<button class="button" type="submit">' . esc_html__( 'Update', 'eurocomply-dsa' ) . '</button>';
				echo '</form>';
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_statements() : void {
		$rows       = StatementStore::recent( 200 );
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => CsvExport::ACTION,
					'dataset' => 'statements',
				),
				admin_url( 'admin-post.php' )
			),
			CsvExport::NONCE
		);
		echo '<p><a class="button" href="' . esc_url( $export_url ) . '">'
			. esc_html__( 'Export statements CSV', 'eurocomply-dsa' ) . '</a></p>';

		echo '<h2>' . esc_html__( 'Issue a statement of reasons', 'eurocomply-dsa' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-dsa-form-grid">';
		wp_nonce_field( self::NONCE_STATE, '_eurocomply_dsa_statement_nonce' );
		echo '<input type="hidden" name="action" value="eurocomply_dsa_statement" />';
		echo '<p><label>' . esc_html__( 'Linked notice ID (optional)', 'eurocomply-dsa' ) . '<input type="number" min="0" name="notice_id" value="0" /></label></p>';
		echo '<p><label>' . esc_html__( 'Target URL', 'eurocomply-dsa' ) . '<input type="url" name="target_url" /></label></p>';
		echo '<p><label>' . esc_html__( 'Target post ID', 'eurocomply-dsa' ) . '<input type="number" min="0" name="target_post_id" value="0" /></label></p>';
		echo '<p><label>' . esc_html__( 'Decision ground', 'eurocomply-dsa' ) . '<select name="decision_ground">'
			. '<option value="illegal_content">' . esc_html__( 'Illegal content', 'eurocomply-dsa' ) . '</option>'
			. '<option value="tos">' . esc_html__( 'Terms of service', 'eurocomply-dsa' ) . '</option>'
			. '</select></label></p>';
		echo '<p><label>' . esc_html__( 'Restriction type', 'eurocomply-dsa' ) . '<select name="restriction_type">'
			. '<option value="removed">' . esc_html__( 'Content removed', 'eurocomply-dsa' ) . '</option>'
			. '<option value="demoted">' . esc_html__( 'Content demoted', 'eurocomply-dsa' ) . '</option>'
			. '<option value="disabled_access">' . esc_html__( 'Access disabled', 'eurocomply-dsa' ) . '</option>'
			. '<option value="account_suspended">' . esc_html__( 'Account suspended', 'eurocomply-dsa' ) . '</option>'
			. '<option value="account_terminated">' . esc_html__( 'Account terminated', 'eurocomply-dsa' ) . '</option>'
			. '<option value="monetisation_suspended">' . esc_html__( 'Monetisation suspended', 'eurocomply-dsa' ) . '</option>'
			. '</select></label></p>';
		echo '<p><label>' . esc_html__( 'Category', 'eurocomply-dsa' ) . '<input type="text" name="category" value="other" /></label></p>';
		echo '<p><label>' . esc_html__( 'Facts summary', 'eurocomply-dsa' ) . '<textarea name="facts_summary" rows="4"></textarea></label></p>';
		echo '<p><label>' . esc_html__( 'Legal reference', 'eurocomply-dsa' ) . '<input type="text" name="legal_reference" /></label></p>';
		echo '<p><label>' . esc_html__( 'ToS reference', 'eurocomply-dsa' ) . '<input type="text" name="tos_reference" /></label></p>';
		echo '<p><label>' . esc_html__( 'Redress information', 'eurocomply-dsa' ) . '<textarea name="redress_info" rows="3"></textarea></label></p>';
		echo '<p><label><input type="checkbox" name="automated_detection" value="1" /> ' . esc_html__( 'Automated detection', 'eurocomply-dsa' ) . '</label>';
		echo ' <label><input type="checkbox" name="automated_decision" value="1" /> ' . esc_html__( 'Automated decision (no human in loop)', 'eurocomply-dsa' ) . '</label></p>';
		echo '<p><button class="button button-primary" type="submit">' . esc_html__( 'Issue statement', 'eurocomply-dsa' ) . '</button></p>';
		echo '</form>';

		echo '<h2>' . esc_html__( 'Recent statements', 'eurocomply-dsa' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No statements issued yet.', 'eurocomply-dsa' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Issued', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Notice', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Restriction', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Ground', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Automated', 'eurocomply-dsa' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . (int) $row['id'] . '</td>';
			echo '<td>' . esc_html( (string) $row['issued_at'] ) . '</td>';
			echo '<td>' . ( (int) $row['notice_id'] ? '#' . (int) $row['notice_id'] : '&mdash;' ) . '</td>';
			echo '<td>' . esc_html( (string) $row['restriction_type'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['decision_ground'] ) . '</td>';
			echo '<td>' . ( ! empty( $row['automated_decision'] ) ? esc_html__( 'Yes', 'eurocomply-dsa' ) : esc_html__( 'No', 'eurocomply-dsa' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_traders() : void {
		$rows       = TraderStore::recent( 200 );
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => CsvExport::ACTION,
					'dataset' => 'traders',
				),
				admin_url( 'admin-post.php' )
			),
			CsvExport::NONCE
		);
		echo '<p><a class="button" href="' . esc_url( $export_url ) . '">'
			. esc_html__( 'Export traders CSV', 'eurocomply-dsa' ) . '</a></p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No traders have submitted information yet.', 'eurocomply-dsa' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th>';
		echo '<th>' . esc_html__( 'User', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Legal name', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Country', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Trade register', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'VAT', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'eurocomply-dsa' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'eurocomply-dsa' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . (int) $row['id'] . '</td>';
			echo '<td>#' . (int) $row['user_id'] . '</td>';
			echo '<td>' . esc_html( (string) $row['legal_name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['country'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['trade_register'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['vat_number'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['verification_status'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( self::NONCE_TRADER, '_eurocomply_dsa_trader_nonce' );
			echo '<input type="hidden" name="action" value="eurocomply_dsa_verify_trader" />';
			echo '<input type="hidden" name="trader_id" value="' . (int) $row['id'] . '" />';
			echo '<button class="button" type="submit" name="verified" value="1">' . esc_html__( 'Verify', 'eurocomply-dsa' ) . '</button> ';
			echo '<button class="button" type="submit" name="verified" value="0">' . esc_html__( 'Reject', 'eurocomply-dsa' ) . '</button>';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_report() : void {
		$since = time() - ( 365 * DAY_IN_SECONDS );
		$until = time();
		$report = TransparencyReport::build( $since, $until );

		echo '<p>' . esc_html__( 'Transparency statistics for the last 12 months. Export as JSON or CSV for publication under Art. 15 / 24 DSA.', 'eurocomply-dsa' ) . '</p>';

		echo '<div class="eurocomply-dsa-cards">';
		$this->card( __( 'Notices (12m)', 'eurocomply-dsa' ), (string) $report['notices']['total'] );
		$this->card( __( 'Statements (12m)', 'eurocomply-dsa' ), (string) $report['statements']['total'] );
		$this->card( __( 'Automated decisions', 'eurocomply-dsa' ), (string) $report['statements']['automated'] );
		$this->card( __( 'Automated share', 'eurocomply-dsa' ), $report['statements']['automated_share'] . ' %' );
		echo '</div>';

		$json_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => CsvExport::ACTION,
					'dataset' => 'report',
					'format'  => 'json',
				),
				admin_url( 'admin-post.php' )
			),
			CsvExport::NONCE
		);
		$csv_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => CsvExport::ACTION,
					'dataset' => 'report',
					'format'  => 'csv',
				),
				admin_url( 'admin-post.php' )
			),
			CsvExport::NONCE
		);
		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url( $json_url ) . '">'
			. esc_html__( 'Download transparency report (JSON)', 'eurocomply-dsa' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $csv_url ) . '">'
			. esc_html__( 'Download transparency report (CSV)', 'eurocomply-dsa' ) . '</a>';
		echo '</p>';

		echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:1rem;max-height:400px;overflow:auto;">'
			. esc_html( TransparencyReport::to_json( $report ) ) . '</pre>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_SAVE, '_eurocomply_dsa_save_nonce' );
		echo '<input type="hidden" name="action" value="eurocomply_dsa_save" />';

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th>' . esc_html__( 'Trader form requires login', 'eurocomply-dsa' ) . '</th><td><label><input type="checkbox" name="trader_form_require_login" value="1" ' . checked( ! empty( $s['trader_form_require_login'] ), true, false ) . ' /> ' . esc_html__( 'Only logged-in users can submit trader info', 'eurocomply-dsa' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'Notice form requires login', 'eurocomply-dsa' ) . '</th><td><label><input type="checkbox" name="notice_form_require_login" value="1" ' . checked( ! empty( $s['notice_form_require_login'] ), true, false ) . ' /> ' . esc_html__( 'Only logged-in users can submit notices', 'eurocomply-dsa' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'Notice form honeypot', 'eurocomply-dsa' ) . '</th><td><label><input type="checkbox" name="notice_form_honeypot" value="1" ' . checked( ! empty( $s['notice_form_honeypot'] ), true, false ) . ' /> ' . esc_html__( 'Enable spam honeypot field', 'eurocomply-dsa' ) . '</label></td></tr>';
		echo '<tr><th><label for="eurocomply-dsa-rate">' . esc_html__( 'Notices per IP / hour', 'eurocomply-dsa' ) . '</label></th><td><input id="eurocomply-dsa-rate" type="number" min="0" max="100" name="notice_form_rate_limit" value="' . esc_attr( (string) $s['notice_form_rate_limit'] ) . '" /> ' . esc_html__( '0 = unlimited', 'eurocomply-dsa' ) . '</td></tr>';
		echo '<tr><th><label for="eurocomply-dsa-contact">' . esc_html__( 'Contact-point email (Art. 12)', 'eurocomply-dsa' ) . '</label></th><td><input id="eurocomply-dsa-contact" type="email" name="contact_point_email" value="' . esc_attr( (string) $s['contact_point_email'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="eurocomply-dsa-legal">' . esc_html__( 'EU legal representative (Art. 13)', 'eurocomply-dsa' ) . '</label></th><td><textarea id="eurocomply-dsa-legal" name="legal_representative" rows="3" class="large-text">' . esc_textarea( (string) $s['legal_representative'] ) . '</textarea></td></tr>';
		echo '<tr><th><label for="eurocomply-dsa-terms">' . esc_html__( 'Terms & conditions URL (Art. 14)', 'eurocomply-dsa' ) . '</label></th><td><input id="eurocomply-dsa-terms" type="url" name="terms_url" value="' . esc_attr( (string) $s['terms_url'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="eurocomply-dsa-complaints">' . esc_html__( 'Complaints / ODR URL (Art. 20)', 'eurocomply-dsa' ) . '</label></th><td><input id="eurocomply-dsa-complaints" type="url" name="complaints_url" value="' . esc_attr( (string) $s['complaints_url'] ) . '" class="regular-text" /></td></tr>';

		$period_options = array(
			'annual'     => __( 'Annual (free)', 'eurocomply-dsa' ),
			'semiannual' => __( 'Semi-annual (Pro)', 'eurocomply-dsa' ),
			'quarterly'  => __( 'Quarterly (Pro)', 'eurocomply-dsa' ),
		);
		echo '<tr><th>' . esc_html__( 'Report cadence', 'eurocomply-dsa' ) . '</th><td><select name="report_period">';
		foreach ( $period_options as $val => $label ) {
			$disabled = ( 'annual' !== $val && ! License::is_pro() ) ? ' disabled="disabled"' : '';
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $s['report_period'], $val, false ) . $disabled . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th>' . esc_html__( 'Auto-log moderation', 'eurocomply-dsa' ) . '</th><td><label><input type="checkbox" name="auto_statement_on_trash" value="1" ' . checked( ! empty( $s['auto_statement_on_trash'] ), true, false ) . ' /> ' . esc_html__( 'Automatically create a draft statement of reasons when an admin trashes a post / product (Pro — stub).', 'eurocomply-dsa' ) . '</label></td></tr>';

		echo '</tbody></table>';

		submit_button( __( 'Save settings', 'eurocomply-dsa' ) );
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<p>' . esc_html__( 'Unlock Pro features with an EC-XXXXXX license key under the License tab.', 'eurocomply-dsa' ) . '</p>';
		echo '<ul class="eurocomply-dsa-pro-list">';
		echo '<li>' . esc_html__( 'DSA Transparency Database submission — XML generator matching the Commission\'s schema.', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'Out-of-court dispute resolution workflow (Art. 21).', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'Strike / reputation system for repeat offenders (Art. 23).', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'Marketplace plugin integrations (WC Vendors, Dokan, WCFM) — auto-sync vendor trader info.', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'Scheduled annual / semi-annual / quarterly report cron with email delivery.', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'Multi-language T&Cs and notice form (WPML / Polylang).', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'Signed PDF transparency reports with embedded JSON for auditors.', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'Trusted-flagger whitelisting (Art. 22).', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'REST API endpoints for external moderation tools.', 'eurocomply-dsa' ) . '</li>';
		echo '<li>' . esc_html__( 'Higher CSV export cap (5,000 rows vs 500 free).', 'eurocomply-dsa' ) . '</li>';
		echo '</ul>';
	}

	private function render_license() : void {
		$license = License::get();
		$active  = License::is_pro();
		echo '<p>' . esc_html__( 'Status:', 'eurocomply-dsa' ) . ' <strong>'
			. ( $active ? esc_html__( 'Active (Pro)', 'eurocomply-dsa' ) : esc_html__( 'Free', 'eurocomply-dsa' ) )
			. '</strong></p>';

		if ( $active ) {
			echo '<p><code>' . esc_html( (string) ( $license['key'] ?? '' ) ) . '</code></p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::NONCE_LICENSE, '_eurocomply_dsa_license_nonce' );
			echo '<input type="hidden" name="action" value="eurocomply_dsa_license" />';
			echo '<input type="hidden" name="mode" value="deactivate" />';
			submit_button( __( 'Deactivate license', 'eurocomply-dsa' ), 'secondary' );
			echo '</form>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::NONCE_LICENSE, '_eurocomply_dsa_license_nonce' );
			echo '<input type="hidden" name="action" value="eurocomply_dsa_license" />';
			echo '<input type="hidden" name="mode" value="activate" />';
			echo '<p><label for="eurocomply-dsa-key">' . esc_html__( 'License key (EC-XXXXXX)', 'eurocomply-dsa' ) . '</label>';
			echo ' <input id="eurocomply-dsa-key" type="text" name="license_key" class="regular-text" /></p>';
			submit_button( __( 'Activate Pro license', 'eurocomply-dsa' ) );
			echo '</form>';
		}
	}

	private function card( string $label, string $value ) : void {
		echo '<div class="eurocomply-dsa-card"><div class="eurocomply-dsa-card-value">' . esc_html( $value )
			. '</div><div class="eurocomply-dsa-card-label">' . esc_html( $label ) . '</div></div>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-dsa' ) );
		}
		check_admin_referer( self::NONCE_SAVE, '_eurocomply_dsa_save_nonce' );

		$input = array();
		foreach ( array_keys( Settings::defaults() ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$raw = wp_unslash( $_POST[ $key ] );
				$input[ $key ] = is_array( $raw ) ? $raw : (string) $raw;
			}
		}
		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );
		add_settings_error( 'eurocomply_dsa', 'saved', __( 'Settings saved.', 'eurocomply-dsa' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$url = add_query_arg(
			array(
				'page'             => self::MENU_SLUG,
				'tab'              => 'settings',
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-dsa' ) );
		}
		check_admin_referer( self::NONCE_LICENSE, '_eurocomply_dsa_license_nonce' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) $_POST['mode'] ) : '';
		if ( 'deactivate' === $mode ) {
			License::deactivate();
			add_settings_error( 'eurocomply_dsa', 'license_deactivated', __( 'License deactivated.', 'eurocomply-dsa' ), 'updated' );
		} else {
			$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
			$result = License::activate( $key );
			$type   = $result['ok'] ? 'updated' : 'error';
			add_settings_error( 'eurocomply_dsa', 'license_result', $result['message'], $type );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$url = add_query_arg(
			array(
				'page'             => self::MENU_SLUG,
				'tab'              => 'license',
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_notice_update() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-dsa' ) );
		}
		check_admin_referer( self::NONCE_NOTICE, '_eurocomply_dsa_notice_nonce' );

		$id     = isset( $_POST['notice_id'] ) ? (int) $_POST['notice_id'] : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( (string) $_POST['status'] ) : 'received';
		$row    = array( 'status' => $status );
		if ( in_array( $status, array( 'acted', 'rejected', 'closed' ), true ) ) {
			$row['closed_at'] = current_time( 'mysql' );
		}
		NoticeStore::update( $id, $row );

		add_settings_error( 'eurocomply_dsa', 'notice_updated', __( 'Notice status updated.', 'eurocomply-dsa' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$url = add_query_arg(
			array(
				'page'             => self::MENU_SLUG,
				'tab'              => 'notices',
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_verify_trader() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-dsa' ) );
		}
		check_admin_referer( self::NONCE_TRADER, '_eurocomply_dsa_trader_nonce' );

		$id       = isset( $_POST['trader_id'] ) ? (int) $_POST['trader_id'] : 0;
		$verified = isset( $_POST['verified'] ) && '1' === (string) $_POST['verified'];
		TraderStore::mark_verified( $id, $verified );

		add_settings_error( 'eurocomply_dsa', 'trader_updated', __( 'Trader verification updated.', 'eurocomply-dsa' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$url = add_query_arg(
			array(
				'page'             => self::MENU_SLUG,
				'tab'              => 'traders',
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_statement() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'eurocomply-dsa' ) );
		}
		check_admin_referer( self::NONCE_STATE, '_eurocomply_dsa_statement_nonce' );

		$data = array(
			'notice_id'           => isset( $_POST['notice_id'] ) ? (int) $_POST['notice_id'] : 0,
			'target_url'          => isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['target_url'] ) ) : '',
			'target_post_id'      => isset( $_POST['target_post_id'] ) ? (int) $_POST['target_post_id'] : 0,
			'decision_ground'     => isset( $_POST['decision_ground'] ) ? sanitize_key( (string) $_POST['decision_ground'] ) : 'tos',
			'restriction_type'    => isset( $_POST['restriction_type'] ) ? sanitize_key( (string) $_POST['restriction_type'] ) : 'removed',
			'category'            => isset( $_POST['category'] ) ? sanitize_key( (string) $_POST['category'] ) : 'other',
			'facts_summary'       => isset( $_POST['facts_summary'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['facts_summary'] ) ) : '',
			'legal_reference'     => isset( $_POST['legal_reference'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['legal_reference'] ) ) : '',
			'tos_reference'       => isset( $_POST['tos_reference'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['tos_reference'] ) ) : '',
			'redress_info'        => isset( $_POST['redress_info'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['redress_info'] ) ) : '',
			'automated_detection' => ! empty( $_POST['automated_detection'] ),
			'automated_decision'  => ! empty( $_POST['automated_decision'] ),
		);

		$id = StatementStore::record( $data );
		if ( $id && ! empty( $data['notice_id'] ) ) {
			NoticeStore::update( (int) $data['notice_id'], array( 'statement_id' => $id ) );
		}

		add_settings_error( 'eurocomply_dsa', 'statement_issued', __( 'Statement of reasons issued.', 'eurocomply-dsa' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$url = add_query_arg(
			array(
				'page'             => self::MENU_SLUG,
				'tab'              => 'statements',
				'settings-updated' => 'true',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
