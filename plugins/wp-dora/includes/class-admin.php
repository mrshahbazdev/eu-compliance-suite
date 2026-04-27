<?php
/**
 * Admin UI.
 *
 * @package EuroComply\DORA
 */

declare( strict_types = 1 );

namespace EuroComply\DORA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG       = 'eurocomply-dora';
	public const NONCE_SAVE      = 'eurocomply_dora_save';
	public const NONCE_LICENSE   = 'eurocomply_dora_license';
	public const NONCE_INCIDENT  = 'eurocomply_dora_incident';
	public const NONCE_TPP       = 'eurocomply_dora_tpp';
	public const NONCE_TEST      = 'eurocomply_dora_test';
	public const NONCE_POLICY    = 'eurocomply_dora_policy';
	public const NONCE_INTEL     = 'eurocomply_dora_intel';
	public const NONCE_STAGE     = 'eurocomply_dora_stage';

	private static ?Admin $instance = null;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_menu',  array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_eurocomply_dora_save',     array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_dora_license',  array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_dora_incident', array( $this, 'handle_incident' ) );
		add_action( 'admin_post_eurocomply_dora_tpp',      array( $this, 'handle_tpp' ) );
		add_action( 'admin_post_eurocomply_dora_test',     array( $this, 'handle_test' ) );
		add_action( 'admin_post_eurocomply_dora_policy',   array( $this, 'handle_policy' ) );
		add_action( 'admin_post_eurocomply_dora_intel',    array( $this, 'handle_intel' ) );
		add_action( 'admin_post_eurocomply_dora_stage',    array( $this, 'handle_stage' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'DORA', 'eurocomply-dora' ),
			__( 'DORA', 'eurocomply-dora' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-shield-alt',
			84
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'eurocomply-dora-admin', EUROCOMPLY_DORA_URL . 'assets/css/admin.css', array(), EUROCOMPLY_DORA_VERSION );
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap eurocomply-dora">';
		echo '<h1>' . esc_html__( 'EuroComply DORA', 'eurocomply-dora' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Reg. (EU) 2022/2554 — Digital Operational Resilience Act. Applies from 17 January 2025.', 'eurocomply-dora' ) . '</p>';

		settings_errors( 'eurocomply_dora' );

		$tabs = array(
			'dashboard'    => __( 'Dashboard',         'eurocomply-dora' ),
			'incidents'    => __( 'Incidents',          'eurocomply-dora' ),
			'third_parties'=> __( 'Third parties',       'eurocomply-dora' ),
			'tests'        => __( 'Resilience tests',     'eurocomply-dora' ),
			'policies'     => __( 'Policies',              'eurocomply-dora' ),
			'intel'        => __( 'Info sharing',           'eurocomply-dora' ),
			'reports'      => __( 'Reports',                 'eurocomply-dora' ),
			'settings'     => __( 'Settings',                 'eurocomply-dora' ),
			'pro'          => __( 'Pro',                       'eurocomply-dora' ),
			'license'      => __( 'License',                    'eurocomply-dora' ),
		);

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$active = ( $tab === $key ) ? ' nav-tab-active' : '';
			$url    = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $key ), admin_url( 'admin.php' ) );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'incidents':     $this->tab_incidents();     break;
			case 'third_parties': $this->tab_third_parties(); break;
			case 'tests':         $this->tab_tests();         break;
			case 'policies':      $this->tab_policies();      break;
			case 'intel':         $this->tab_intel();         break;
			case 'reports':       $this->tab_reports();       break;
			case 'settings':      $this->tab_settings();      break;
			case 'pro':           $this->tab_pro();           break;
			case 'license':       $this->tab_license();       break;
			case 'dashboard':
			default:              $this->tab_dashboard();     break;
		}
		echo '</div>';
	}

	private function tab_dashboard() : void {
		$s            = Settings::get();
		$total_inc    = IncidentStore::count_total();
		$major        = IncidentStore::count_class( 'major' );
		$significant  = IncidentStore::count_class( 'significant' );
		$tpp          = ThirdPartyStore::count_total();
		$tpp_critical = ThirdPartyStore::count_critical();
		$tpp_due      = ThirdPartyStore::reviews_due( 30 );
		$tests        = TestStore::count_total();
		$open_crit    = TestStore::count_open_critical();
		$policies     = PolicyStore::count_total();
		$policies_due = PolicyStore::reviews_due( 30 );

		$od_initial      = count( IncidentStore::overdue( 'initial' ) );
		$od_intermediate = count( IncidentStore::overdue( 'intermediate' ) );
		$od_final        = count( IncidentStore::overdue( 'final' ) );

		echo '<div class="eurocomply-dora-grid">';
		$this->card( __( 'Entity', 'eurocomply-dora' ), esc_html( (string) $s['entity_name'] ), Settings::entity_types()[ $s['entity_type'] ] ?? '' );
		$this->card( __( 'Reporting year', 'eurocomply-dora' ), (string) $s['reporting_year'], (string) $s['entity_country'] . ' · ' . (string) $s['entity_size'] );
		$this->card( __( 'Incidents', 'eurocomply-dora' ), (string) $total_inc, sprintf( /* translators: 1: major, 2: significant */ __( '%1$d major · %2$d significant', 'eurocomply-dora' ), $major, $significant ) );
		$this->card( __( 'Third parties (RoI)', 'eurocomply-dora' ), (string) $tpp, sprintf( /* translators: %d: critical */ __( '%d supporting critical functions', 'eurocomply-dora' ), $tpp_critical ) );
		$this->card( __( 'TPP reviews due', 'eurocomply-dora' ), (string) $tpp_due, __( 'within 30 days', 'eurocomply-dora' ), $tpp_due > 0 ? 'warn' : 'ok' );
		$this->card( __( 'Tests', 'eurocomply-dora' ), (string) $tests, sprintf( /* translators: %d: open critical */ __( '%d open critical findings', 'eurocomply-dora' ), $open_crit ), $open_crit > 0 ? 'warn' : 'ok' );
		$this->card( __( 'Policies', 'eurocomply-dora' ), (string) $policies, sprintf( /* translators: %d: due */ __( '%d reviews due', 'eurocomply-dora' ), $policies_due ), $policies_due > 0 ? 'warn' : 'ok' );
		$this->card( __( '4h initial overdue', 'eurocomply-dora' ), (string) $od_initial, $od_initial > 0 ? __( 'Art. 19(4)(a) breach risk', 'eurocomply-dora' ) : __( 'No breaches', 'eurocomply-dora' ), $od_initial > 0 ? 'crit' : 'ok' );
		$this->card( __( '72h intermediate overdue', 'eurocomply-dora' ), (string) $od_intermediate, '', $od_intermediate > 0 ? 'crit' : 'ok' );
		$this->card( __( '1-month final overdue', 'eurocomply-dora' ), (string) $od_final, '', $od_final > 0 ? 'warn' : 'ok' );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Recent incidents', 'eurocomply-dora' ) . '</h2>';
		$this->table_incidents( IncidentStore::recent( 10 ) );
	}

	private function card( string $label, string $value, string $sub = '', string $tone = '' ) : void {
		echo '<div class="eurocomply-card eurocomply-tone-' . esc_attr( $tone ?: 'plain' ) . '">';
		echo '<div class="eurocomply-card-label">' . esc_html( $label ) . '</div>';
		echo '<div class="eurocomply-card-value">' . esc_html( $value ) . '</div>';
		if ( '' !== $sub ) {
			echo '<div class="eurocomply-card-sub">' . esc_html( $sub ) . '</div>';
		}
		echo '</div>';
	}

	private function table_incidents( array $rows ) : void {
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No incidents recorded.', 'eurocomply-dora' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Occurred', 'eurocomply-dora' ) . '</th>';
		echo '<th>' . esc_html__( 'Class', 'eurocomply-dora' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-dora' ) . '</th>';
		echo '<th>' . esc_html__( 'Severity', 'eurocomply-dora' ) . '</th>';
		echo '<th>' . esc_html__( 'Stages', 'eurocomply-dora' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'eurocomply-dora' ) . '</th>';
		echo '<th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) ( $r['occurred_at'] ?? '' ) ) . '</td>';
			$tone = 'major' === $r['classification'] ? 'crit' : ( 'significant' === $r['classification'] ? 'warn' : 'ok' );
			echo '<td><span class="eurocomply-pill eurocomply-pill-' . esc_attr( $tone ) . '">' . esc_html( (string) $r['classification'] ) . '</span></td>';
			echo '<td>' . esc_html( (string) $r['category'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['severity'] ) . '</td>';
			echo '<td>';
			foreach ( array( 'initial' => '4h', 'intermediate' => '72h', 'final' => '30d' ) as $stage => $label ) {
				$sent = ! empty( $r[ $stage . '_sent_at' ] );
				$cls  = $sent ? 'eurocomply-pill-ok' : 'eurocomply-pill-warn';
				echo '<span class="eurocomply-pill ' . esc_attr( $cls ) . '">' . esc_html( $label ) . ( $sent ? '✓' : '·' ) . '</span> ';
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '<td>';
			$this->stage_buttons( (int) $r['id'] );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function stage_buttons( int $id ) : void {
		$action = admin_url( 'admin-post.php' );
		echo '<form method="post" action="' . esc_url( $action ) . '" style="display:inline">';
		wp_nonce_field( self::NONCE_STAGE );
		echo '<input type="hidden" name="action" value="eurocomply_dora_stage" />';
		echo '<input type="hidden" name="incident_id" value="' . (int) $id . '" />';
		echo '<select name="stage"><option value="initial">' . esc_html__( '4h initial', 'eurocomply-dora' ) . '</option><option value="intermediate">' . esc_html__( '72h intermediate', 'eurocomply-dora' ) . '</option><option value="final">' . esc_html__( '1-month final', 'eurocomply-dora' ) . '</option></select> ';
		submit_button( __( 'Mark sent', 'eurocomply-dora' ), 'small', 'submit', false );
		echo '</form>';

		$dl = admin_url( 'admin-post.php' );
		echo ' <form method="post" action="' . esc_url( $dl ) . '" style="display:inline">';
		wp_nonce_field( 'eurocomply_dora_export' );
		echo '<input type="hidden" name="action" value="eurocomply_dora_export_xml" />';
		echo '<input type="hidden" name="incident_id" value="' . (int) $id . '" />';
		echo '<select name="stage"><option value="initial">XML 4h</option><option value="intermediate">XML 72h</option><option value="final">XML 30d</option></select> ';
		submit_button( __( 'XML', 'eurocomply-dora' ), 'small', 'submit', false );
		echo '</form>';

		echo ' <form method="post" action="' . esc_url( $dl ) . '" style="display:inline">';
		wp_nonce_field( 'eurocomply_dora_export' );
		echo '<input type="hidden" name="action" value="eurocomply_dora_export_json" />';
		echo '<input type="hidden" name="incident_id" value="' . (int) $id . '" />';
		echo '<select name="stage"><option value="initial">JSON 4h</option><option value="intermediate">JSON 72h</option><option value="final">JSON 30d</option></select> ';
		submit_button( __( 'JSON', 'eurocomply-dora' ), 'small', 'submit', false );
		echo '</form>';
	}

	private function tab_incidents() : void {
		echo '<h2>' . esc_html__( 'Record incident', 'eurocomply-dora' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_INCIDENT );
		echo '<input type="hidden" name="action" value="eurocomply_dora_incident" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'occurred_at',    __( 'Occurred at', 'eurocomply-dora' ),    'datetime-local' );
		$this->row( 'detected_at',    __( 'Detected at', 'eurocomply-dora' ),    'datetime-local' );
		$this->row( 'category',       __( 'Category', 'eurocomply-dora' ),       'text', 'cyber_attack' );
		$this->select( 'severity',    __( 'Severity', 'eurocomply-dora' ),       array( 'low', 'medium', 'high', 'critical' ), 'medium' );
		$this->row( 'clients_affected', __( 'Clients affected', 'eurocomply-dora' ), 'number', '0' );
		$this->checkbox( 'data_loss',  __( 'Data loss', 'eurocomply-dora' ) );
		$this->row( 'duration_min',    __( 'Duration (min)', 'eurocomply-dora' ),    'number', '0' );
		$this->row( 'geo_spread',      __( 'Geo spread (Member States)', 'eurocomply-dora' ), 'number', '1' );
		$this->row( 'financial_impact', __( 'Financial impact', 'eurocomply-dora' ), 'number', '0' );
		$this->checkbox( 'reputational', __( 'Reputational impact', 'eurocomply-dora' ) );
		$this->checkbox( 'critical_service', __( 'Affects critical / important function', 'eurocomply-dora' ) );
		$this->textarea( 'summary',     __( 'Summary', 'eurocomply-dora' ) );
		$this->textarea( 'root_cause',  __( 'Root cause (intermediate / final)', 'eurocomply-dora' ) );
		$this->textarea( 'mitigation',  __( 'Mitigation (intermediate / final)', 'eurocomply-dora' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add incident', 'eurocomply-dora' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Recent incidents', 'eurocomply-dora' ) . '</h2>';
		$this->table_incidents( IncidentStore::recent( 100 ) );
	}

	private function tab_third_parties() : void {
		echo '<h2>' . esc_html__( 'Add third party', 'eurocomply-dora' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_TPP );
		echo '<input type="hidden" name="action" value="eurocomply_dora_tpp" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'name',     __( 'Name', 'eurocomply-dora' ),     'text' );
		$this->row( 'lei',      __( 'LEI',  'eurocomply-dora' ),      'text' );
		$this->row( 'country',  __( 'Country (ISO-2)', 'eurocomply-dora' ), 'text' );
		$this->row( 'services', __( 'Services', 'eurocomply-dora' ), 'text' );
		$this->select( 'criticality_tier', __( 'Criticality tier', 'eurocomply-dora' ), array( '1', '2', '3' ), '3' );
		$this->checkbox( 'supports_critical', __( 'Supports critical/important functions', 'eurocomply-dora' ) );
		$this->row( 'contract_ref', __( 'Contract reference', 'eurocomply-dora' ), 'text' );
		$this->row( 'contract_start', __( 'Contract start',   'eurocomply-dora' ), 'date' );
		$this->row( 'contract_end',   __( 'Contract end',     'eurocomply-dora' ), 'date' );
		$this->row( 'data_processed', __( 'Data processed',   'eurocomply-dora' ), 'text' );
		$this->checkbox( 'gdpr_dpa',    __( 'GDPR DPA in place', 'eurocomply-dora' ) );
		$this->row( 'last_review',  __( 'Last review', 'eurocomply-dora' ), 'date' );
		$this->row( 'next_review',  __( 'Next review', 'eurocomply-dora' ), 'date' );
		$this->textarea( 'subcontractor_chain', __( 'Sub-contractor chain', 'eurocomply-dora' ) );
		$this->textarea( 'exit_strategy', __( 'Exit strategy', 'eurocomply-dora' ) );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-dora' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add third party', 'eurocomply-dora' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Register of Information', 'eurocomply-dora' ) . '</h2>';
		$rows = ThirdPartyStore::all();
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No third parties registered.', 'eurocomply-dora' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Name</th><th>LEI</th><th>Country</th><th>Tier</th><th>Critical</th><th>Services</th><th>Contract</th><th>Next review</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['lei'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td>' . (int) $r['criticality_tier'] . '</td>';
			echo '<td>' . ( $r['supports_critical'] ? '✓' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) $r['services'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['contract_ref'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['next_review'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_tests() : void {
		echo '<h2>' . esc_html__( 'Add resilience test', 'eurocomply-dora' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_TEST );
		echo '<input type="hidden" name="action" value="eurocomply_dora_test" />';
		echo '<table class="form-table"><tbody>';
		$this->select( 'type', __( 'Type', 'eurocomply-dora' ), array( 'vuln_scan', 'pen_test', 'tlpt', 'scenario', 'bcp', 'red_team' ), 'vuln_scan' );
		$this->row( 'scope', __( 'Scope', 'eurocomply-dora' ), 'text' );
		$this->row( 'conducted_at', __( 'Conducted at', 'eurocomply-dora' ), 'date' );
		$this->row( 'finding_count', __( 'Total findings', 'eurocomply-dora' ), 'number', '0' );
		$this->row( 'critical_findings', __( 'Critical findings', 'eurocomply-dora' ), 'number', '0' );
		$this->select( 'status', __( 'Status', 'eurocomply-dora' ), array( 'planned', 'in_progress', 'complete', 'remediated' ), 'planned' );
		$this->row( 'report_url', __( 'Report URL', 'eurocomply-dora' ), 'url' );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-dora' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add test', 'eurocomply-dora' ) );
		echo '</form>';

		$rows = TestStore::all();
		echo '<h2>' . esc_html__( 'Tests', 'eurocomply-dora' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No tests recorded.', 'eurocomply-dora' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Type</th><th>Scope</th><th>Conducted</th><th>Findings</th><th>Critical</th><th>Status</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['type'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['scope'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['conducted_at'] ?? '' ) ) . '</td>';
			echo '<td>' . (int) $r['finding_count'] . '</td>';
			echo '<td>' . (int) $r['critical_findings'] . '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_policies() : void {
		echo '<h2>' . esc_html__( 'Add policy', 'eurocomply-dora' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_POLICY );
		echo '<input type="hidden" name="action" value="eurocomply_dora_policy" />';
		echo '<table class="form-table"><tbody>';
		$areas = PolicyStore::control_areas();
		echo '<tr><th><label for="control_area">' . esc_html__( 'Control area', 'eurocomply-dora' ) . '</label></th><td><select name="control_area" id="control_area">';
		foreach ( $areas as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'policy_name', __( 'Policy name', 'eurocomply-dora' ), 'text' );
		$this->row( 'version', __( 'Version', 'eurocomply-dora' ), 'text', '1.0' );
		$this->row( 'owner', __( 'Owner', 'eurocomply-dora' ), 'text' );
		$this->row( 'last_review', __( 'Last review', 'eurocomply-dora' ), 'date' );
		$this->row( 'next_review', __( 'Next review', 'eurocomply-dora' ), 'date' );
		$this->row( 'evidence_url', __( 'Evidence URL', 'eurocomply-dora' ), 'url' );
		$this->select( 'status', __( 'Status', 'eurocomply-dora' ), array( 'draft', 'approved', 'review_due', 'retired' ), 'draft' );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-dora' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add policy', 'eurocomply-dora' ) );
		echo '</form>';

		$rows = PolicyStore::all();
		echo '<h2>' . esc_html__( 'Policies', 'eurocomply-dora' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No policies recorded.', 'eurocomply-dora' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Area</th><th>Policy</th><th>Version</th><th>Owner</th><th>Last</th><th>Next</th><th>Status</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( $areas[ $r['control_area'] ] ?? (string) $r['control_area'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['policy_name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['version'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['owner'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['last_review'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['next_review'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_intel() : void {
		echo '<h2>' . esc_html__( 'Record intel', 'eurocomply-dora' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_INTEL );
		echo '<input type="hidden" name="action" value="eurocomply_dora_intel" />';
		echo '<table class="form-table"><tbody>';
		$this->select( 'direction', __( 'Direction', 'eurocomply-dora' ), array( 'received', 'shared' ), 'received' );
		$this->row( 'source', __( 'Source / counterparty', 'eurocomply-dora' ), 'text' );
		$this->select( 'tlp', __( 'TLP', 'eurocomply-dora' ), array( 'CLEAR', 'GREEN', 'AMBER', 'AMBER+STRICT', 'RED' ), 'AMBER' );
		$this->textarea( 'summary',    __( 'Summary',     'eurocomply-dora' ) );
		$this->textarea( 'indicators', __( 'Indicators', 'eurocomply-dora' ) );
		echo '</tbody></table>';
		submit_button( __( 'Record', 'eurocomply-dora' ) );
		echo '</form>';

		$rows = IntelStore::recent( 100 );
		echo '<h2>' . esc_html__( 'Recent', 'eurocomply-dora' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No intel recorded.', 'eurocomply-dora' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>When</th><th>Direction</th><th>Source</th><th>TLP</th><th>Summary</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['created_at'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['direction'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['source'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['tlp'] ) . '</td>';
			echo '<td>' . esc_html( wp_trim_words( wp_strip_all_tags( (string) $r['summary'] ), 24 ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_reports() : void {
		echo '<h2>' . esc_html__( 'Export', 'eurocomply-dora' ) . '</h2>';
		echo '<p>' . esc_html__( 'CSV export is capped at 500 rows in free tier; 5,000 in Pro.', 'eurocomply-dora' ) . '</p>';
		foreach ( array(
			'incidents'     => __( 'Incidents', 'eurocomply-dora' ),
			'third_parties' => __( 'Third parties (RoI)', 'eurocomply-dora' ),
			'tests'         => __( 'Tests', 'eurocomply-dora' ),
			'policies'      => __( 'Policies', 'eurocomply-dora' ),
			'intel'         => __( 'Information sharing', 'eurocomply-dora' ),
		) as $ds => $label ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;margin-bottom:8px">';
			wp_nonce_field( 'eurocomply_dora_export' );
			echo '<input type="hidden" name="action"  value="eurocomply_dora_export" />';
			echo '<input type="hidden" name="dataset" value="' . esc_attr( $ds ) . '" />';
			submit_button( sprintf( /* translators: %s: dataset */ __( 'CSV: %s', 'eurocomply-dora' ), $label ), 'secondary', 'submit', false );
			echo '</form>';
		}
	}

	private function tab_settings() : void {
		$s = Settings::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<input type="hidden" name="action" value="eurocomply_dora_save" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'entity_name', __( 'Entity name', 'eurocomply-dora' ), 'text', (string) $s['entity_name'] );
		$this->row( 'entity_lei',  __( 'LEI',          'eurocomply-dora' ), 'text', (string) $s['entity_lei'] );
		$this->row( 'entity_country', __( 'Country (ISO-2)', 'eurocomply-dora' ), 'text', (string) $s['entity_country'] );
		echo '<tr><th><label for="entity_type">' . esc_html__( 'Entity type', 'eurocomply-dora' ) . '</label></th><td><select name="entity_type" id="entity_type">';
		foreach ( Settings::entity_types() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['entity_type'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->select( 'entity_size', __( 'Entity size', 'eurocomply-dora' ), array( 'micro', 'small', 'standard', 'significant' ), (string) $s['entity_size'] );
		$this->row( 'compliance_officer', __( 'Compliance officer email', 'eurocomply-dora' ), 'email', (string) $s['compliance_officer'] );
		$this->row( 'competent_authority', __( 'Competent authority', 'eurocomply-dora' ), 'text', (string) $s['competent_authority'] );
		$this->row( 'csirt_email',  __( 'CSIRT email', 'eurocomply-dora' ), 'email', (string) $s['csirt_email'] );
		$this->row( 'reporting_year', __( 'Reporting year', 'eurocomply-dora' ), 'number', (string) $s['reporting_year'] );
		$this->row( 'currency', __( 'Currency', 'eurocomply-dora' ), 'text', (string) $s['currency'] );
		$this->row( 'major_clients_threshold', __( 'Major-class clients threshold', 'eurocomply-dora' ), 'number', (string) $s['major_clients_threshold'] );
		$this->row( 'major_duration_minutes',  __( 'Major-class duration (min)', 'eurocomply-dora' ),    'number', (string) $s['major_duration_minutes'] );
		$this->checkbox( 'major_data_loss_flag', __( 'Treat data-loss incidents as major-class', 'eurocomply-dora' ), ! empty( $s['major_data_loss_flag'] ) );
		$this->checkbox( 'enable_woo_meta',      __( 'Enable WooCommerce ICT-service meta (Pro)',   'eurocomply-dora' ), ! empty( $s['enable_woo_meta'] ) );
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function tab_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-dora' ) . '</h2>';
		echo '<ul style="list-style:disc;padding-left:18px">';
		foreach ( array(
			__( 'Live competent-authority submission (initial / intermediate / final)', 'eurocomply-dora' ),
			__( 'Signed PDF DORA reports + LEI-stamped audit bundle',                    'eurocomply-dora' ),
			__( 'REST / webhooks for SIEM forwarding (Splunk · ELK · Datadog · Graylog)', 'eurocomply-dora' ),
			__( 'TLPT (Threat-Led Penetration Testing) workflow (Art. 26)',              'eurocomply-dora' ),
			__( 'CTPP (Critical Third-Party Provider) registry sync',                     'eurocomply-dora' ),
			__( 'EU-wide Register of Information XBRL submission helper',                  'eurocomply-dora' ),
			__( 'WooCommerce ICT-service per-product meta',                                 'eurocomply-dora' ),
			__( 'WPML / Polylang for internal policy translations',                          'eurocomply-dora' ),
			__( 'Multi-site network aggregator',                                              'eurocomply-dora' ),
			__( '5,000-row CSV cap',                                                            'eurocomply-dora' ),
			__( 'Slack / Teams / PagerDuty alerts on overdue stages',                            'eurocomply-dora' ),
			__( 'NIS2 cross-link (when both plugins active, deduplicates incidents)',              'eurocomply-dora' ),
		) as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}

	private function tab_license() : void {
		$d = License::get();
		$active = License::is_pro();
		echo '<h2>' . esc_html__( 'License', 'eurocomply-dora' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<input type="hidden" name="action" value="eurocomply_dora_license" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-dora' ) . '</label></th><td><input type="text" id="license_key" name="license_key" value="' . esc_attr( (string) ( $d['key'] ?? '' ) ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-dora' ) . '</th><td>' . ( $active ? '<span class="eurocomply-pill eurocomply-pill-ok">' . esc_html__( 'Active', 'eurocomply-dora' ) . '</span>' : '<span class="eurocomply-pill eurocomply-pill-warn">' . esc_html__( 'Inactive', 'eurocomply-dora' ) . '</span>' ) . '</td></tr>';
		echo '</tbody></table>';
		submit_button( $active ? __( 'Deactivate', 'eurocomply-dora' ) : __( 'Activate', 'eurocomply-dora' ), 'primary', $active ? 'deactivate' : 'activate' );
		echo '</form>';
	}

	// --- POST handlers ----------------------------------------------------

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$clean = Settings::sanitize( $input );
		update_option( Settings::OPTION_KEY, $clean, false );
		add_settings_error( 'eurocomply_dora', 'eurocomply_dora_saved', __( 'Settings saved.', 'eurocomply-dora' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE_LICENSE );
		if ( isset( $_POST['deactivate'] ) ) {
			License::deactivate();
			add_settings_error( 'eurocomply_dora', 'eurocomply_dora_lic', __( 'License deactivated.', 'eurocomply-dora' ), 'updated' );
		} else {
			$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_dora', 'eurocomply_dora_lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_incident() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE_INCIDENT );
		$row = wp_unslash( $_POST );
		$cls = IncidentClassifier::classify( array(
			'clients_affected' => (int) ( $row['clients_affected'] ?? 0 ),
			'data_loss'        => ! empty( $row['data_loss'] ),
			'duration_min'     => (int) ( $row['duration_min'] ?? 0 ),
			'geo_spread'       => (int) ( $row['geo_spread'] ?? 1 ),
			'financial_impact' => (float) ( $row['financial_impact'] ?? 0 ),
			'reputational'     => ! empty( $row['reputational'] ),
			'critical_service' => ! empty( $row['critical_service'] ),
		) );
		$row['classification'] = $cls['class'];
		$row['classified_at']  = current_time( 'mysql' );
		IncidentStore::create( $row );
		add_settings_error( 'eurocomply_dora', 'eurocomply_dora_inc', sprintf( /* translators: %s: classification */ __( 'Incident recorded — auto-classified as %s.', 'eurocomply-dora' ), $cls['class'] ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'incidents', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_tpp() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE_TPP );
		ThirdPartyStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_dora', 'eurocomply_dora_tpp', __( 'Third party recorded.', 'eurocomply-dora' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'third_parties', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_test() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE_TEST );
		TestStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_dora', 'eurocomply_dora_test', __( 'Test recorded.', 'eurocomply-dora' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'tests', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_policy() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE_POLICY );
		PolicyStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_dora', 'eurocomply_dora_pol', __( 'Policy recorded.', 'eurocomply-dora' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'policies', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_intel() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE_INTEL );
		IntelStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_dora', 'eurocomply_dora_intel', __( 'Intel recorded.', 'eurocomply-dora' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'intel', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_stage() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-dora' ), 403 );
		}
		check_admin_referer( self::NONCE_STAGE );
		$id    = isset( $_POST['incident_id'] ) ? (int) $_POST['incident_id'] : 0;
		$stage = isset( $_POST['stage'] )       ? sanitize_key( (string) $_POST['stage'] ) : '';
		IncidentStore::mark_sent( $id, $stage );
		add_settings_error( 'eurocomply_dora', 'eurocomply_dora_stage', __( 'Stage marked sent.', 'eurocomply-dora' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'incidents', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// --- form helpers -----------------------------------------------------

	private function row( string $name, string $label, string $type = 'text', string $default = '' ) : void {
		echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="regular-text" value="' . esc_attr( $default ) . '" /></td></tr>';
	}

	private function checkbox( string $name, string $label, bool $checked = false ) : void {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . ' /> ' . esc_html__( 'Yes', 'eurocomply-dora' ) . '</label></td></tr>';
	}

	private function textarea( string $name, string $label, string $default = '' ) : void {
		echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="large-text" rows="3">' . esc_textarea( $default ) . '</textarea></td></tr>';
	}

	private function select( string $name, string $label, array $options, string $current ) : void {
		echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
		foreach ( $options as $opt ) {
			echo '<option value="' . esc_attr( (string) $opt ) . '"' . selected( $current, (string) $opt, false ) . '>' . esc_html( (string) $opt ) . '</option>';
		}
		echo '</select></td></tr>';
	}
}
