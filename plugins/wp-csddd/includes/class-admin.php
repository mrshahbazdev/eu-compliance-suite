<?php
/**
 * Admin UI.
 *
 * @package EuroComply\CSDDD
 */

declare( strict_types = 1 );

namespace EuroComply\CSDDD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

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
		add_action( 'admin_post_eurocomply_csddd_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_eurocomply_csddd_save_supplier', array( $this, 'save_supplier' ) );
		add_action( 'admin_post_eurocomply_csddd_delete_supplier', array( $this, 'delete_supplier' ) );
		add_action( 'admin_post_eurocomply_csddd_save_risk', array( $this, 'save_risk' ) );
		add_action( 'admin_post_eurocomply_csddd_delete_risk', array( $this, 'delete_risk' ) );
		add_action( 'admin_post_eurocomply_csddd_save_action', array( $this, 'save_action' ) );
		add_action( 'admin_post_eurocomply_csddd_delete_action', array( $this, 'delete_action' ) );
		add_action( 'admin_post_eurocomply_csddd_set_complaint_status', array( $this, 'set_complaint_status' ) );
		add_action( 'admin_post_eurocomply_csddd_save_license', array( $this, 'save_license' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'EuroComply CSDDD', 'eurocomply-csddd' ),
			__( 'CSDDD', 'eurocomply-csddd' ),
			'manage_options',
			EUROCOMPLY_CSDDD_SLUG,
			array( $this, 'render' ),
			'dashicons-networking',
			59
		);
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'dashboard';
		$tabs = array(
			'dashboard'  => __( 'Dashboard', 'eurocomply-csddd' ),
			'suppliers'  => __( 'Suppliers', 'eurocomply-csddd' ),
			'risks'      => __( 'Risks', 'eurocomply-csddd' ),
			'actions'    => __( 'Actions', 'eurocomply-csddd' ),
			'complaints' => __( 'Complaints', 'eurocomply-csddd' ),
			'climate'    => __( 'Climate plan', 'eurocomply-csddd' ),
			'settings'   => __( 'Settings', 'eurocomply-csddd' ),
			'pro'        => __( 'Pro', 'eurocomply-csddd' ),
			'license'    => __( 'License', 'eurocomply-csddd' ),
		);

		echo '<div class="wrap"><h1>' . esc_html__( 'EuroComply CSDDD', 'eurocomply-csddd' ) . '</h1>';

		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'eurocomply-csddd' ) . '</p></div>';
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => $key ), admin_url( 'admin.php' ) );
			$cls = $tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $cls ), esc_html( $label ) );
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'suppliers':  $this->render_suppliers();  break;
			case 'risks':      $this->render_risks();      break;
			case 'actions':    $this->render_actions();    break;
			case 'complaints': $this->render_complaints(); break;
			case 'climate':    $this->render_climate();    break;
			case 'settings':   $this->render_settings();   break;
			case 'pro':        $this->render_pro();        break;
			case 'license':    $this->render_license();    break;
			case 'dashboard':
			default:           $this->render_dashboard();  break;
		}
		echo '</div>';
	}

	private function render_dashboard() : void {
		$scope = Settings::in_scope();
		$severity = RiskStore::critical_count() > 0 ? 'red' : ( RiskStore::unresolved_count() > 0 ? 'amber' : 'green' );
		echo '<div class="eurocomply-csddd-hero ' . esc_attr( $severity ) . '">';
		printf( '<p><strong>%s</strong> %s</p>', esc_html__( 'Scope:', 'eurocomply-csddd' ), esc_html( $scope['reason'] ) );
		echo '</div>';
		echo '<table class="widefat striped"><tbody>';
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Suppliers tracked', 'eurocomply-csddd' ), SupplierStore::count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'High-risk suppliers (score ≥70)', 'eurocomply-csddd' ), SupplierStore::high_risk_count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Open adverse impacts', 'eurocomply-csddd' ), RiskStore::unresolved_count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Critical adverse impacts', 'eurocomply-csddd' ), RiskStore::critical_count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Overdue actions', 'eurocomply-csddd' ), ActionStore::overdue_count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Stakeholder complaints awaiting ack (>30d)', 'eurocomply-csddd' ), ComplaintStore::overdue_ack_count() );
		echo '</tbody></table>';
	}

	private function render_suppliers() : void {
		$rows = SupplierStore::all();
		$this->form_open( 'eurocomply_csddd_save_supplier', 'eurocomply_csddd_save_supplier' );
		echo '<h3>' . esc_html__( 'Add supplier', 'eurocomply-csddd' ) . '</h3>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label>' . esc_html__( 'Name', 'eurocomply-csddd' ) . '</label></th><td><input name="name" type="text" class="regular-text" required></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'External reference', 'eurocomply-csddd' ) . '</label></th><td><input name="external_ref" type="text" class="regular-text"></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Country (ISO)', 'eurocomply-csddd' ) . '</label></th><td><input name="country" type="text" maxlength="2" size="3"></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Tier', 'eurocomply-csddd' ) . '</label></th><td><select name="tier">';
		foreach ( Settings::tier_levels() as $k => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $label ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'NACE code', 'eurocomply-csddd' ) . '</label></th><td><input name="sector_nace" type="text" size="6"></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Risk score (0–100)', 'eurocomply-csddd' ) . '</label></th><td><input name="risk_score" type="number" min="0" max="100" value="0"></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Contact email', 'eurocomply-csddd' ) . '</label></th><td><input name="contact_email" type="email" class="regular-text"></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add supplier', 'eurocomply-csddd' ) );
		echo '</form>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No suppliers yet.', 'eurocomply-csddd' ) . '</p>';
			return;
		}
		echo '<h3>' . esc_html__( 'Suppliers register', 'eurocomply-csddd' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th><th>' . esc_html__( 'Name', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Tier', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Country', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'NACE', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Risk', 'eurocomply-csddd' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_csddd_delete_supplier&id=' . (int) $r['id'] ), 'eurocomply_csddd_delete_supplier_' . (int) $r['id'] );
			echo '<tr>';
			printf( '<td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td><a href="%s" onclick="return confirm(\'%s\')">%s</a></td>', (int) $r['id'], esc_html( (string) $r['name'] ), esc_html( (string) $r['tier'] ), esc_html( (string) $r['country'] ), esc_html( (string) $r['sector_nace'] ), (int) $r['risk_score'], esc_url( $delete ), esc_js( __( 'Delete?', 'eurocomply-csddd' ) ), esc_html__( 'Delete', 'eurocomply-csddd' ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_risks() : void {
		$rows = RiskStore::all();
		$this->form_open( 'eurocomply_csddd_save_risk', 'eurocomply_csddd_save_risk' );
		echo '<h3>' . esc_html__( 'Record adverse impact', 'eurocomply-csddd' ) . '</h3>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Supplier ID', 'eurocomply-csddd' ) . '</th><td><input name="supplier_id" type="number" min="0" required></td></tr>';
		echo '<tr><th>' . esc_html__( 'Category', 'eurocomply-csddd' ) . '</th><td><select name="category" required>';
		foreach ( Settings::risk_categories() as $k => $c ) {
			printf( '<option value="%s">%s — %s</option>', esc_attr( $k ), esc_html( $c['annex'] ), esc_html( $c['label'] ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Severity', 'eurocomply-csddd' ) . '</th><td><select name="severity">';
		foreach ( Settings::severity_levels() as $k => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $label ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Likelihood (0–100)', 'eurocomply-csddd' ) . '</th><td><input name="likelihood" type="number" min="0" max="100" value="50"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Identified at', 'eurocomply-csddd' ) . '</th><td><input name="identified_at" type="date" value="' . esc_attr( gmdate( 'Y-m-d' ) ) . '"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Description', 'eurocomply-csddd' ) . '</th><td><textarea name="description" rows="3" cols="60"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record risk', 'eurocomply-csddd' ) );
		echo '</form>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No risks recorded yet.', 'eurocomply-csddd' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Supplier', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Annex', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Category', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Severity', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Status', 'eurocomply-csddd' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_csddd_delete_risk&id=' . (int) $r['id'] ), 'eurocomply_csddd_delete_risk_' . (int) $r['id'] );
			printf( '<tr><td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s" onclick="return confirm(\'%s\')">%s</a></td></tr>', (int) $r['id'], (int) $r['supplier_id'], esc_html( (string) $r['annex'] ), esc_html( (string) $r['category'] ), esc_html( (string) $r['severity'] ), esc_html( (string) $r['status'] ), esc_url( $delete ), esc_js( __( 'Delete?', 'eurocomply-csddd' ) ), esc_html__( 'Delete', 'eurocomply-csddd' ) );
		}
		echo '</tbody></table>';
	}

	private function render_actions() : void {
		$rows = ActionStore::all();
		$this->form_open( 'eurocomply_csddd_save_action', 'eurocomply_csddd_save_action' );
		echo '<h3>' . esc_html__( 'Record action plan', 'eurocomply-csddd' ) . '</h3>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Risk ID', 'eurocomply-csddd' ) . '</th><td><input name="risk_id" type="number" min="0" required></td></tr>';
		echo '<tr><th>' . esc_html__( 'Action type', 'eurocomply-csddd' ) . '</th><td><select name="action_type">';
		foreach ( Settings::action_types() as $k => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $label ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Article', 'eurocomply-csddd' ) . '</th><td><select name="article"><option value="10">Art. 10 (preventive)</option><option value="11">Art. 11 (corrective)</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Deadline', 'eurocomply-csddd' ) . '</th><td><input name="deadline" type="date"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Owner', 'eurocomply-csddd' ) . '</th><td><input name="owner" type="text" class="regular-text"></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record action', 'eurocomply-csddd' ) );
		echo '</form>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No actions recorded yet.', 'eurocomply-csddd' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Risk', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Type', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Art.', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Deadline', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Done', 'eurocomply-csddd' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_csddd_delete_action&id=' . (int) $r['id'] ), 'eurocomply_csddd_delete_action_' . (int) $r['id'] );
			printf( '<tr><td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s" onclick="return confirm(\'%s\')">%s</a></td></tr>', (int) $r['id'], (int) $r['risk_id'], esc_html( (string) $r['action_type'] ), esc_html( (string) $r['article'] ), esc_html( (string) ( $r['deadline'] ?? '' ) ), esc_html( (string) ( $r['completed_at'] ?? '' ) ), esc_url( $delete ), esc_js( __( 'Delete?', 'eurocomply-csddd' ) ), esc_html__( 'Delete', 'eurocomply-csddd' ) );
		}
		echo '</tbody></table>';
	}

	private function render_complaints() : void {
		$rows = ComplaintStore::all();
		echo '<p>' . esc_html__( 'Stakeholder complaints submitted via the [eurocomply_csddd_complaint_form] shortcode are listed below.', 'eurocomply-csddd' ) . '</p>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No complaints received yet.', 'eurocomply-csddd' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Received', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Anon?', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Supplier', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Category', 'eurocomply-csddd' ) . '</th><th>' . esc_html__( 'Status', 'eurocomply-csddd' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			printf( '<tr><td>%d</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>', (int) $r['id'], esc_html( (string) $r['created_at'] ), $r['complainant_anonymous'] ? '✓' : '–', (int) $r['supplier_id'], esc_html( (string) $r['category'] ), esc_html( (string) $r['status'] ) );
		}
		echo '</tbody></table>';
	}

	private function render_climate() : void {
		$d = Settings::get();
		echo '<h3>' . esc_html__( 'Climate transition plan (Art. 22)', 'eurocomply-csddd' ) . '</h3>';
		echo '<p>' . esc_html__( 'Operators are required to adopt and put into effect a transition plan compatible with limiting global warming to 1.5°C in line with the Paris Agreement.', 'eurocomply-csddd' ) . '</p>';
		echo '<table class="widefat striped"><tbody>';
		printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html__( 'Target year', 'eurocomply-csddd' ), esc_html( (string) $d['climate_target_year'] ) );
		printf( '<tr><th>%s</th><td>%s°C</td></tr>', esc_html__( 'Alignment', 'eurocomply-csddd' ), esc_html( (string) $d['climate_target_celsius'] ) );
		echo '</tbody></table>';
		echo '<p><em>' . esc_html__( 'Set targets in the Settings tab. Pro: link Scope 1/2/3 inventory from CSRD plugin (#19) and ESRS E1 disclosures.', 'eurocomply-csddd' ) . '</em></p>';
	}

	private function render_settings() : void {
		$d = Settings::get();
		$this->form_open( 'eurocomply_csddd_save_settings', 'eurocomply_csddd_save_settings' );
		echo '<table class="form-table"><tbody>';
		printf( '<tr><th>%s</th><td><input type="text" name="company_name" class="regular-text" value="%s"></td></tr>', esc_html__( 'Company name', 'eurocomply-csddd' ), esc_attr( (string) $d['company_name'] ) );
		printf( '<tr><th>%s</th><td><input type="number" name="employees_in_scope" min="0" value="%d"></td></tr>', esc_html__( 'Employees (worldwide)', 'eurocomply-csddd' ), (int) $d['employees_in_scope'] );
		printf( '<tr><th>%s</th><td><input type="number" name="turnover_million_eur" min="0" value="%d"> M€</td></tr>', esc_html__( 'Net turnover (worldwide)', 'eurocomply-csddd' ), (int) $d['turnover_million_eur'] );
		printf( '<tr><th>%s</th><td><input type="number" name="reporting_year" min="2024" max="2100" value="%d"></td></tr>', esc_html__( 'Reporting year', 'eurocomply-csddd' ), (int) $d['reporting_year'] );
		printf( '<tr><th>%s</th><td><input type="number" name="climate_target_year" min="2025" max="2100" value="%d"></td></tr>', esc_html__( 'Climate target year', 'eurocomply-csddd' ), (int) $d['climate_target_year'] );
		printf( '<tr><th>%s</th><td><input type="text" name="climate_target_celsius" size="6" value="%s">°C</td></tr>', esc_html__( 'Climate target (°C)', 'eurocomply-csddd' ), esc_attr( (string) $d['climate_target_celsius'] ) );
		printf( '<tr><th>%s</th><td><input type="text" name="compliance_officer" class="regular-text" value="%s"></td></tr>', esc_html__( 'Compliance officer', 'eurocomply-csddd' ), esc_attr( (string) $d['compliance_officer'] ) );
		printf( '<tr><th>%s</th><td><input type="email" name="complaint_email" class="regular-text" value="%s"></td></tr>', esc_html__( 'Complaints inbox', 'eurocomply-csddd' ), esc_attr( (string) $d['complaint_email'] ) );
		printf( '<tr><th>%s</th><td><label><input type="checkbox" name="log_changes" %s> %s</label></td></tr>', esc_html__( 'Audit log', 'eurocomply-csddd' ), checked( ! empty( $d['log_changes'] ), true, false ), esc_html__( 'Log all due-diligence changes', 'eurocomply-csddd' ) );
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		$active = License::is_pro() ? __( 'Active', 'eurocomply-csddd' ) : __( 'Inactive', 'eurocomply-csddd' );
		echo '<p>' . esc_html__( 'License status:', 'eurocomply-csddd' ) . ' <strong>' . esc_html( $active ) . '</strong></p>';
		echo '<h3>' . esc_html__( 'Pro roadmap (stubs)', 'eurocomply-csddd' ) . '</h3>';
		echo '<ul class="ul-disc">';
		foreach ( array(
			__( 'ESG-data API ingestion (RepRisk, Sustainalytics, EcoVadis)', 'eurocomply-csddd' ),
			__( 'Supplier survey portal (questionnaire + reminders)', 'eurocomply-csddd' ),
			__( 'Scheduled annual / quarterly survey distribution (cron)', 'eurocomply-csddd' ),
			__( 'Signed PDF Art. 16 annual due-diligence statement', 'eurocomply-csddd' ),
			__( 'Embedded supplier-portal complaint form (Art. 14)', 'eurocomply-csddd' ),
			__( 'REST API for SIEM / ESG-data-room integration', 'eurocomply-csddd' ),
			__( 'CSRD bridge: ESRS S1/S2/G1 disclosures auto-link', 'eurocomply-csddd' ),
			__( '5,000-row CSV cap (free tier capped at 500)', 'eurocomply-csddd' ),
			__( 'WPML / Polylang for multi-jurisdiction policy', 'eurocomply-csddd' ),
			__( 'Multi-tenant for parent companies (group view)', 'eurocomply-csddd' ),
		) as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_license() : void {
		$d = License::get();
		$this->form_open( 'eurocomply_csddd_save_license', 'eurocomply_csddd_save_license' );
		echo '<table class="form-table"><tbody>';
		printf( '<tr><th>%s</th><td><input type="text" name="license_key" class="regular-text" value="%s"></td></tr>', esc_html__( 'License key', 'eurocomply-csddd' ), esc_attr( (string) ( $d['key'] ?? '' ) ) );
		echo '</tbody></table>';
		submit_button( __( 'Save license', 'eurocomply-csddd' ) );
		echo '</form>';
	}

	private function form_open( string $action, string $nonce_action ) : void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $nonce_action );
	}

	public function save_settings() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_csddd_save_settings' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		$input = array();
		foreach ( array( 'company_name', 'employees_in_scope', 'turnover_million_eur', 'reporting_year', 'climate_target_year', 'climate_target_celsius', 'compliance_officer', 'complaint_email', 'log_changes' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$input[ $k ] = wp_unslash( $_POST[ $k ] );
			}
		}
		Settings::save( $input );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_supplier() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_csddd_save_supplier' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		$data = array();
		foreach ( array( 'external_ref', 'name', 'country', 'tier', 'sector_nace', 'risk_score', 'contact_email' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$data[ $k ] = wp_unslash( $_POST[ $k ] );
			}
		}
		SupplierStore::insert( $data );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'suppliers', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_supplier() : void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'eurocomply_csddd_delete_supplier_' . $id ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		SupplierStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'suppliers', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_risk() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_csddd_save_risk' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		$data = array();
		foreach ( array( 'supplier_id', 'category', 'severity', 'likelihood', 'identified_at', 'description' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$data[ $k ] = wp_unslash( $_POST[ $k ] );
			}
		}
		RiskStore::insert( $data );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'risks', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_risk() : void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'eurocomply_csddd_delete_risk_' . $id ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		RiskStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'risks', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_action() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_csddd_save_action' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		$data = array();
		foreach ( array( 'risk_id', 'action_type', 'article', 'deadline', 'owner' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$data[ $k ] = wp_unslash( $_POST[ $k ] );
			}
		}
		ActionStore::insert( $data );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'actions', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_action() : void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'eurocomply_csddd_delete_action_' . $id ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		ActionStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'actions', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function set_complaint_status() : void {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_csddd_complaint_status_' . $id ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';
		ComplaintStore::set_status( $id, $status );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'complaints', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_license() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_csddd_save_license' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-csddd' ) );
		}
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		if ( '' === $key ) {
			License::deactivate();
		} else {
			License::activate( $key );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_CSDDD_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
