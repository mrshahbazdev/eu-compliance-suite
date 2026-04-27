<?php
/**
 * Admin UI.
 *
 * @package EuroComply\CER
 */

declare( strict_types = 1 );

namespace EuroComply\CER;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG       = 'eurocomply-cer';
	public const NONCE_SAVE      = 'eurocomply_cer_save';
	public const NONCE_LICENSE   = 'eurocomply_cer_license';
	public const NONCE_SERVICE   = 'eurocomply_cer_service';
	public const NONCE_ASSET     = 'eurocomply_cer_asset';
	public const NONCE_RISK      = 'eurocomply_cer_risk';
	public const NONCE_MEASURE   = 'eurocomply_cer_measure';
	public const NONCE_INCIDENT  = 'eurocomply_cer_incident';
	public const NONCE_STAGE     = 'eurocomply_cer_stage';

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
		add_action( 'admin_post_eurocomply_cer_save',     array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_cer_license',  array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_cer_service',  array( $this, 'handle_service' ) );
		add_action( 'admin_post_eurocomply_cer_asset',    array( $this, 'handle_asset' ) );
		add_action( 'admin_post_eurocomply_cer_risk',     array( $this, 'handle_risk' ) );
		add_action( 'admin_post_eurocomply_cer_measure',  array( $this, 'handle_measure' ) );
		add_action( 'admin_post_eurocomply_cer_incident', array( $this, 'handle_incident' ) );
		add_action( 'admin_post_eurocomply_cer_stage',    array( $this, 'handle_stage' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'CER', 'eurocomply-cer' ),
			__( 'CER', 'eurocomply-cer' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-shield',
			85
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'eurocomply-cer-admin', EUROCOMPLY_CER_URL . 'assets/css/admin.css', array(), EUROCOMPLY_CER_VERSION );
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap eurocomply-cer">';
		echo '<h1>' . esc_html__( 'EuroComply CER', 'eurocomply-cer' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Directive (EU) 2022/2557 — Critical Entities Resilience. Transposition deadline 17 October 2024.', 'eurocomply-cer' ) . '</p>';

		settings_errors( 'eurocomply_cer' );

		$tabs = array(
			'dashboard' => __( 'Dashboard', 'eurocomply-cer' ),
			'sectors'   => __( 'Sectors',    'eurocomply-cer' ),
			'services'  => __( 'Services',    'eurocomply-cer' ),
			'assets'    => __( 'Assets / sites', 'eurocomply-cer' ),
			'risk'      => __( 'Risk',           'eurocomply-cer' ),
			'measures'  => __( 'Measures',         'eurocomply-cer' ),
			'incidents' => __( 'Incidents',          'eurocomply-cer' ),
			'reports'   => __( 'Reports',             'eurocomply-cer' ),
			'settings'  => __( 'Settings',             'eurocomply-cer' ),
			'pro'       => __( 'Pro',                   'eurocomply-cer' ),
			'license'   => __( 'License',                'eurocomply-cer' ),
		);

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$active = ( $tab === $key ) ? ' nav-tab-active' : '';
			$url    = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $key ), admin_url( 'admin.php' ) );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'sectors':   $this->tab_sectors();   break;
			case 'services':  $this->tab_services();  break;
			case 'assets':    $this->tab_assets();    break;
			case 'risk':      $this->tab_risk();      break;
			case 'measures':  $this->tab_measures();  break;
			case 'incidents': $this->tab_incidents(); break;
			case 'reports':   $this->tab_reports();   break;
			case 'settings':  $this->tab_settings();  break;
			case 'pro':       $this->tab_pro();       break;
			case 'license':   $this->tab_license();   break;
			case 'dashboard':
			default:          $this->tab_dashboard(); break;
		}
		echo '</div>';
	}

	private function tab_dashboard() : void {
		$s = Settings::get();
		$svc_total   = ServiceStore::count_total();
		$svc_xb      = ServiceStore::count_cross_border();
		$asset_total = AssetStore::count_total();
		$spof        = AssetStore::count_spof();
		$risk_total  = RiskStore::count_total();
		$risk_high   = RiskStore::count_high();
		$risk_due    = RiskStore::reviews_overdue();
		$m_total     = MeasureStore::count_total();
		$m_overdue   = MeasureStore::count_overdue();
		$inc_total   = IncidentStore::count_total();
		$inc_sig     = IncidentStore::count_significant();
		$od_warn     = count( IncidentStore::overdue( 'early_warning' ) );
		$od_follow   = count( IncidentStore::overdue( 'followup' ) );

		echo '<div class="eurocomply-cer-grid">';
		$this->card( __( 'Entity', 'eurocomply-cer' ), esc_html( (string) $s['entity_name'] ), Settings::sector_name( (string) $s['sector'] ) . ( $s['sub_sector'] ? ' · ' . esc_html( (string) $s['sub_sector'] ) : '' ) );
		$this->card( __( 'Reporting year', 'eurocomply-cer' ), (string) $s['reporting_year'], (string) $s['entity_country'] . ( $s['cross_border'] ? ' · ' . __( 'cross-border', 'eurocomply-cer' ) : '' ) );
		$this->card( __( 'Essential services', 'eurocomply-cer' ), (string) $svc_total, sprintf( /* translators: %d: cross-border */ __( '%d cross-border', 'eurocomply-cer' ), $svc_xb ) );
		$this->card( __( 'Assets / sites', 'eurocomply-cer' ), (string) $asset_total, sprintf( /* translators: %d: SPOF */ __( '%d single points of failure', 'eurocomply-cer' ), $spof ), $spof > 0 ? 'warn' : 'ok' );
		$this->card( __( 'Risk findings', 'eurocomply-cer' ), (string) $risk_total, sprintf( /* translators: %d: high */ __( '%d high (≥15) open', 'eurocomply-cer' ), $risk_high ), $risk_high > 0 ? 'crit' : 'ok' );
		$this->card( __( 'Risk reviews overdue', 'eurocomply-cer' ), (string) $risk_due, $risk_due > 0 ? __( 'Art. 12 4-yearly cadence', 'eurocomply-cer' ) : __( 'On schedule', 'eurocomply-cer' ), $risk_due > 0 ? 'warn' : 'ok' );
		$this->card( __( 'Measures', 'eurocomply-cer' ), (string) $m_total, sprintf( /* translators: %d: overdue */ __( '%d overdue', 'eurocomply-cer' ), $m_overdue ), $m_overdue > 0 ? 'warn' : 'ok' );
		$this->card( __( 'Incidents', 'eurocomply-cer' ), (string) $inc_total, sprintf( /* translators: %d: significant */ __( '%d significant', 'eurocomply-cer' ), $inc_sig ) );
		$this->card( __( '24h early-warning overdue', 'eurocomply-cer' ), (string) $od_warn, $od_warn > 0 ? __( 'Art. 15(1) breach risk', 'eurocomply-cer' ) : __( 'No breaches', 'eurocomply-cer' ), $od_warn > 0 ? 'crit' : 'ok' );
		$this->card( __( '1-month follow-up overdue', 'eurocomply-cer' ), (string) $od_follow, '', $od_follow > 0 ? 'warn' : 'ok' );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Recent incidents', 'eurocomply-cer' ) . '</h2>';
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
			echo '<p>' . esc_html__( 'No incidents recorded.', 'eurocomply-cer' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Detected', 'eurocomply-cer' ) . '</th>';
		echo '<th>' . esc_html__( 'Significant', 'eurocomply-cer' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-cer' ) . '</th>';
		echo '<th>' . esc_html__( 'Users affected', 'eurocomply-cer' ) . '</th>';
		echo '<th>' . esc_html__( 'Duration', 'eurocomply-cer' ) . '</th>';
		echo '<th>' . esc_html__( 'Stages', 'eurocomply-cer' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'eurocomply-cer' ) . '</th>';
		echo '<th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) ( $r['detected_at'] ?? '' ) ) . '</td>';
			$tone = $r['significant'] ? 'crit' : 'ok';
			echo '<td><span class="eurocomply-pill eurocomply-pill-' . esc_attr( $tone ) . '">' . ( $r['significant'] ? esc_html__( 'yes', 'eurocomply-cer' ) : esc_html__( 'no', 'eurocomply-cer' ) ) . '</span></td>';
			echo '<td>' . esc_html( (string) $r['category'] ) . '</td>';
			echo '<td>' . (int) $r['users_affected'] . '</td>';
			echo '<td>' . (int) $r['duration_min'] . __( ' min', 'eurocomply-cer' ) . '</td>';
			echo '<td>';
			foreach ( array( 'early_warning' => '24h', 'followup' => '30d' ) as $stage => $label ) {
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
		echo '<input type="hidden" name="action" value="eurocomply_cer_stage" />';
		echo '<input type="hidden" name="incident_id" value="' . (int) $id . '" />';
		echo '<select name="stage"><option value="early_warning">' . esc_html__( '24h early warning', 'eurocomply-cer' ) . '</option><option value="followup">' . esc_html__( '1-month follow-up', 'eurocomply-cer' ) . '</option></select> ';
		submit_button( __( 'Mark sent', 'eurocomply-cer' ), 'small', 'submit', false );
		echo '</form>';

		$dl = admin_url( 'admin-post.php' );
		echo ' <form method="post" action="' . esc_url( $dl ) . '" style="display:inline">';
		wp_nonce_field( 'eurocomply_cer_export' );
		echo '<input type="hidden" name="action" value="eurocomply_cer_export_xml" />';
		echo '<input type="hidden" name="incident_id" value="' . (int) $id . '" />';
		echo '<select name="stage"><option value="early_warning">XML 24h</option><option value="followup">XML 30d</option></select> ';
		submit_button( __( 'XML', 'eurocomply-cer' ), 'small', 'submit', false );
		echo '</form>';

		echo ' <form method="post" action="' . esc_url( $dl ) . '" style="display:inline">';
		wp_nonce_field( 'eurocomply_cer_export' );
		echo '<input type="hidden" name="action" value="eurocomply_cer_export_json" />';
		echo '<input type="hidden" name="incident_id" value="' . (int) $id . '" />';
		echo '<select name="stage"><option value="early_warning">JSON 24h</option><option value="followup">JSON 30d</option></select> ';
		submit_button( __( 'JSON', 'eurocomply-cer' ), 'small', 'submit', false );
		echo '</form>';
	}

	private function tab_sectors() : void {
		echo '<h2>' . esc_html__( 'CER sector taxonomy', 'eurocomply-cer' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Sector', 'eurocomply-cer' ) . '</th><th>' . esc_html__( 'Sub-sectors', 'eurocomply-cer' ) . '</th></tr></thead><tbody>';
		foreach ( Settings::sectors() as $key => $info ) {
			echo '<tr><td><code>' . esc_html( $key ) . '</code> · ' . esc_html( $info['name'] ) . '</td><td>' . esc_html( implode( ', ', $info['sub'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_services() : void {
		echo '<h2>' . esc_html__( 'Add essential service', 'eurocomply-cer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_SERVICE );
		echo '<input type="hidden" name="action" value="eurocomply_cer_service" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'name', __( 'Service name', 'eurocomply-cer' ), 'text' );
		echo '<tr><th><label for="sector">' . esc_html__( 'Sector', 'eurocomply-cer' ) . '</label></th><td><select name="sector" id="sector">';
		foreach ( Settings::sectors() as $key => $info ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $info['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'sub_sector', __( 'Sub-sector', 'eurocomply-cer' ), 'text' );
		$this->row( 'population_served', __( 'Population served', 'eurocomply-cer' ), 'number', '0' );
		$this->row( 'geographic_scope', __( 'Geographic scope', 'eurocomply-cer' ), 'text' );
		$this->checkbox( 'cross_border', __( 'Provided across borders (Art. 17)', 'eurocomply-cer' ) );
		$this->row( 'disruption_threshold', __( 'Disruption threshold (national rule)', 'eurocomply-cer' ), 'text' );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-cer' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add service', 'eurocomply-cer' ) );
		echo '</form>';

		$rows = ServiceStore::all();
		echo '<h2>' . esc_html__( 'Essential services', 'eurocomply-cer' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No services recorded.', 'eurocomply-cer' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Service</th><th>Sector</th><th>Sub-sector</th><th>Population</th><th>Cross-border</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td>' . esc_html( Settings::sector_name( (string) $r['sector'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['sub_sector'] ) . '</td>';
			echo '<td>' . (int) $r['population_served'] . '</td>';
			echo '<td>' . ( $r['cross_border'] ? '✓' : '' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_assets() : void {
		$svcs = ServiceStore::all();
		echo '<h2>' . esc_html__( 'Add asset / site', 'eurocomply-cer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_ASSET );
		echo '<input type="hidden" name="action" value="eurocomply_cer_asset" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="service_id">' . esc_html__( 'Linked service', 'eurocomply-cer' ) . '</label></th><td><select name="service_id" id="service_id"><option value="0">' . esc_html__( '— none —', 'eurocomply-cer' ) . '</option>';
		foreach ( $svcs as $svc ) {
			echo '<option value="' . (int) $svc['id'] . '">#' . (int) $svc['id'] . ' · ' . esc_html( (string) $svc['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->select( 'kind', __( 'Kind', 'eurocomply-cer' ), array( 'site', 'ict_system', 'supply_chain', 'utility' ), 'site' );
		$this->row( 'name',     __( 'Name',     'eurocomply-cer' ), 'text' );
		$this->row( 'country',  __( 'Country (ISO-2)', 'eurocomply-cer' ), 'text' );
		$this->row( 'address',  __( 'Address',  'eurocomply-cer' ), 'text' );
		$this->row( 'lat',      __( 'Latitude',  'eurocomply-cer' ), 'text' );
		$this->row( 'lng',      __( 'Longitude', 'eurocomply-cer' ), 'text' );
		$this->row( 'supplier', __( 'Supplier',  'eurocomply-cer' ), 'text' );
		$this->checkbox( 'single_point_of_failure', __( 'Single point of failure', 'eurocomply-cer' ) );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-cer' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add asset', 'eurocomply-cer' ) );
		echo '</form>';

		$rows = AssetStore::all();
		echo '<h2>' . esc_html__( 'Assets / sites / dependencies', 'eurocomply-cer' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No assets recorded.', 'eurocomply-cer' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Service</th><th>Kind</th><th>Name</th><th>Country</th><th>Supplier</th><th>SPOF</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['service_id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['kind'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['supplier'] ) . '</td>';
			echo '<td>' . ( $r['single_point_of_failure'] ? '⚠' : '' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_risk() : void {
		$svcs = ServiceStore::all();
		echo '<h2>' . esc_html__( 'Add risk finding (Art. 12)', 'eurocomply-cer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_RISK );
		echo '<input type="hidden" name="action" value="eurocomply_cer_risk" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="service_id">' . esc_html__( 'Linked service', 'eurocomply-cer' ) . '</label></th><td><select name="service_id" id="service_id"><option value="0">' . esc_html__( '— none —', 'eurocomply-cer' ) . '</option>';
		foreach ( $svcs as $svc ) {
			echo '<option value="' . (int) $svc['id'] . '">#' . (int) $svc['id'] . ' · ' . esc_html( (string) $svc['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="threat">' . esc_html__( 'Threat category', 'eurocomply-cer' ) . '</label></th><td><select name="threat" id="threat">';
		foreach ( RiskStore::threat_categories() as $key => $label ) {
			echo '<option value="' . esc_attr( (string) $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->select( 'likelihood', __( 'Likelihood (1–5)', 'eurocomply-cer' ), array( '1', '2', '3', '4', '5' ), '3' );
		$this->select( 'impact',     __( 'Impact (1–5)',      'eurocomply-cer' ), array( '1', '2', '3', '4', '5' ), '3' );
		$this->row( 'conducted_at',  __( 'Conducted at',     'eurocomply-cer' ), 'date' );
		$this->row( 'next_review',   __( 'Next review',     'eurocomply-cer' ), 'date' );
		$this->select( 'status', __( 'Status', 'eurocomply-cer' ), array( 'open', 'mitigated', 'accepted', 'closed' ), 'open' );
		$this->textarea( 'finding',   __( 'Finding',   'eurocomply-cer' ) );
		$this->textarea( 'treatment', __( 'Treatment', 'eurocomply-cer' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add finding', 'eurocomply-cer' ) );
		echo '</form>';

		$rows = RiskStore::all();
		echo '<h2>' . esc_html__( 'Risk findings', 'eurocomply-cer' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No risk findings recorded.', 'eurocomply-cer' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Service</th><th>Threat</th><th>L</th><th>I</th><th>Score</th><th>Status</th><th>Next review</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['service_id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['threat'] ) . '</td>';
			echo '<td>' . (int) $r['likelihood'] . '</td>';
			echo '<td>' . (int) $r['impact'] . '</td>';
			$tone = $r['score'] >= 15 ? 'crit' : ( $r['score'] >= 8 ? 'warn' : 'ok' );
			echo '<td><span class="eurocomply-pill eurocomply-pill-' . esc_attr( $tone ) . '">' . (int) $r['score'] . '</span></td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['next_review'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_measures() : void {
		$svcs = ServiceStore::all();
		echo '<h2>' . esc_html__( 'Add resilience measure (Art. 13)', 'eurocomply-cer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_MEASURE );
		echo '<input type="hidden" name="action" value="eurocomply_cer_measure" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="service_id">' . esc_html__( 'Linked service', 'eurocomply-cer' ) . '</label></th><td><select name="service_id" id="service_id"><option value="0">' . esc_html__( '— none —', 'eurocomply-cer' ) . '</option>';
		foreach ( $svcs as $svc ) {
			echo '<option value="' . (int) $svc['id'] . '">#' . (int) $svc['id'] . ' · ' . esc_html( (string) $svc['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="category">' . esc_html__( 'Category', 'eurocomply-cer' ) . '</label></th><td><select name="category" id="category">';
		foreach ( MeasureStore::categories() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'measure', __( 'Measure', 'eurocomply-cer' ), 'text' );
		$this->row( 'owner',   __( 'Owner', 'eurocomply-cer' ),   'text' );
		$this->row( 'deadline', __( 'Deadline', 'eurocomply-cer' ), 'date' );
		$this->select( 'status', __( 'Status', 'eurocomply-cer' ), array( 'planned', 'in_progress', 'implemented', 'verified' ), 'planned' );
		$this->row( 'evidence_url', __( 'Evidence URL', 'eurocomply-cer' ), 'url' );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-cer' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add measure', 'eurocomply-cer' ) );
		echo '</form>';

		$rows = MeasureStore::all();
		echo '<h2>' . esc_html__( 'Measures', 'eurocomply-cer' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No measures recorded.', 'eurocomply-cer' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Service</th><th>Category</th><th>Measure</th><th>Owner</th><th>Deadline</th><th>Status</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['service_id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['category'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['measure'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['owner'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['deadline'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_incidents() : void {
		$svcs = ServiceStore::all();
		echo '<h2>' . esc_html__( 'Record incident (Art. 15)', 'eurocomply-cer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_INCIDENT );
		echo '<input type="hidden" name="action" value="eurocomply_cer_incident" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="service_id">' . esc_html__( 'Linked service', 'eurocomply-cer' ) . '</label></th><td><select name="service_id" id="service_id"><option value="0">' . esc_html__( '— none —', 'eurocomply-cer' ) . '</option>';
		foreach ( $svcs as $svc ) {
			echo '<option value="' . (int) $svc['id'] . '">#' . (int) $svc['id'] . ' · ' . esc_html( (string) $svc['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'occurred_at', __( 'Occurred at', 'eurocomply-cer' ), 'datetime-local' );
		$this->row( 'detected_at', __( 'Detected at', 'eurocomply-cer' ), 'datetime-local' );
		$this->row( 'category',    __( 'Category',    'eurocomply-cer' ), 'text', 'physical_attack' );
		$this->checkbox( 'significant',  __( 'Causes / can cause significant disruptive effect', 'eurocomply-cer' ) );
		$this->row( 'users_affected', __( 'Users affected', 'eurocomply-cer' ), 'number', '0' );
		$this->row( 'duration_min',   __( 'Duration (min)',  'eurocomply-cer' ), 'number', '0' );
		$this->row( 'geo_spread',     __( 'Geo spread (Member States)', 'eurocomply-cer' ), 'number', '1' );
		$this->checkbox( 'cross_border', __( 'Cross-border', 'eurocomply-cer' ) );
		$this->textarea( 'summary',     __( 'Summary',     'eurocomply-cer' ) );
		$this->textarea( 'root_cause',  __( 'Root cause (follow-up)',  'eurocomply-cer' ) );
		$this->textarea( 'mitigation',  __( 'Mitigation (follow-up)',  'eurocomply-cer' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add incident', 'eurocomply-cer' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Recent incidents', 'eurocomply-cer' ) . '</h2>';
		$this->table_incidents( IncidentStore::recent( 100 ) );
	}

	private function tab_reports() : void {
		echo '<h2>' . esc_html__( 'Export', 'eurocomply-cer' ) . '</h2>';
		echo '<p>' . esc_html__( 'CSV export is capped at 500 rows in free tier; 5,000 in Pro.', 'eurocomply-cer' ) . '</p>';
		foreach ( array(
			'incidents' => __( 'Incidents', 'eurocomply-cer' ),
			'services'  => __( 'Services',  'eurocomply-cer' ),
			'assets'    => __( 'Assets',    'eurocomply-cer' ),
			'risk'      => __( 'Risk',      'eurocomply-cer' ),
			'measures'  => __( 'Measures',  'eurocomply-cer' ),
		) as $ds => $label ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;margin-bottom:8px">';
			wp_nonce_field( 'eurocomply_cer_export' );
			echo '<input type="hidden" name="action"  value="eurocomply_cer_export" />';
			echo '<input type="hidden" name="dataset" value="' . esc_attr( $ds ) . '" />';
			submit_button( sprintf( /* translators: %s: dataset */ __( 'CSV: %s', 'eurocomply-cer' ), $label ), 'secondary', 'submit', false );
			echo '</form>';
		}
	}

	private function tab_settings() : void {
		$s = Settings::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<input type="hidden" name="action" value="eurocomply_cer_save" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'entity_name',    __( 'Entity name', 'eurocomply-cer' ),    'text', (string) $s['entity_name'] );
		$this->row( 'entity_id',      __( 'Entity identifier (LEI / VAT / national)', 'eurocomply-cer' ), 'text', (string) $s['entity_id'] );
		$this->row( 'entity_country', __( 'Country (ISO-2)', 'eurocomply-cer' ), 'text', (string) $s['entity_country'] );
		echo '<tr><th><label for="sector">' . esc_html__( 'Sector', 'eurocomply-cer' ) . '</label></th><td><select name="sector" id="sector">';
		foreach ( Settings::sectors() as $key => $info ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $s['sector'], $key, false ) . '>' . esc_html( $info['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'sub_sector', __( 'Sub-sector', 'eurocomply-cer' ), 'text', (string) $s['sub_sector'] );
		$this->checkbox( 'cross_border', __( 'Provides services across borders (Art. 17)', 'eurocomply-cer' ), ! empty( $s['cross_border'] ) );
		$this->row( 'compliance_officer',  __( 'Compliance officer email', 'eurocomply-cer' ), 'email', (string) $s['compliance_officer'] );
		$this->row( 'competent_authority', __( 'Competent authority',     'eurocomply-cer' ), 'text',  (string) $s['competent_authority'] );
		$this->row( 'reporting_year',      __( 'Reporting year',          'eurocomply-cer' ), 'number', (string) $s['reporting_year'] );
		$this->row( 'risk_review_years',   __( 'Risk-review cadence (years)', 'eurocomply-cer' ), 'number', (string) $s['risk_review_years'] );
		$this->checkbox( 'enable_woo_meta', __( 'Enable WooCommerce service meta (Pro)', 'eurocomply-cer' ), ! empty( $s['enable_woo_meta'] ) );
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function tab_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-cer' ) . '</h2>';
		echo '<ul style="list-style:disc;padding-left:18px">';
		foreach ( array(
			__( 'Live competent-authority submission (early warning + follow-up)', 'eurocomply-cer' ),
			__( 'Background-check workflow (Art. 14) — DBS / CRB / Führungszeugnis integration', 'eurocomply-cer' ),
			__( 'Signed PDF reports + audit bundle',                                              'eurocomply-cer' ),
			__( 'REST / webhooks for SIEM forwarding (Splunk · ELK · Datadog · Graylog)',          'eurocomply-cer' ),
			__( 'Cross-border critical-entity coordination (Art. 17) — multi-MS dashboards',         'eurocomply-cer' ),
			__( 'Geographic-information map view (OpenStreetMap)',                                     'eurocomply-cer' ),
			__( 'WooCommerce essential-service per-product meta',                                        'eurocomply-cer' ),
			__( 'WPML / Polylang for internal policy translations',                                        'eurocomply-cer' ),
			__( 'Multi-site network aggregator',                                                              'eurocomply-cer' ),
			__( '5,000-row CSV cap',                                                                              'eurocomply-cer' ),
			__( 'Slack / Teams / PagerDuty alerts on overdue early warning',                                       'eurocomply-cer' ),
			__( 'NIS2 cross-link — share cyber-related significant incidents to NIS2 plugin',                        'eurocomply-cer' ),
		) as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}

	private function tab_license() : void {
		$d      = License::get();
		$active = License::is_pro();
		echo '<h2>' . esc_html__( 'License', 'eurocomply-cer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<input type="hidden" name="action" value="eurocomply_cer_license" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-cer' ) . '</label></th><td><input type="text" id="license_key" name="license_key" value="' . esc_attr( (string) ( $d['key'] ?? '' ) ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-cer' ) . '</th><td>' . ( $active ? '<span class="eurocomply-pill eurocomply-pill-ok">' . esc_html__( 'Active', 'eurocomply-cer' ) . '</span>' : '<span class="eurocomply-pill eurocomply-pill-warn">' . esc_html__( 'Inactive', 'eurocomply-cer' ) . '</span>' ) . '</td></tr>';
		echo '</tbody></table>';
		submit_button( $active ? __( 'Deactivate', 'eurocomply-cer' ) : __( 'Activate', 'eurocomply-cer' ), 'primary', $active ? 'deactivate' : 'activate' );
		echo '</form>';
	}

	// --- POST handlers ----------------------------------------------------

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$clean = Settings::sanitize( $input );
		update_option( Settings::OPTION_KEY, $clean, false );
		add_settings_error( 'eurocomply_cer', 'eurocomply_cer_saved', __( 'Settings saved.', 'eurocomply-cer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE_LICENSE );
		if ( isset( $_POST['deactivate'] ) ) {
			License::deactivate();
			add_settings_error( 'eurocomply_cer', 'eurocomply_cer_lic', __( 'License deactivated.', 'eurocomply-cer' ), 'updated' );
		} else {
			$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_cer', 'eurocomply_cer_lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_service() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE_SERVICE );
		ServiceStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_cer', 'eurocomply_cer_svc', __( 'Service recorded.', 'eurocomply-cer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'services', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_asset() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE_ASSET );
		AssetStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_cer', 'eurocomply_cer_ass', __( 'Asset recorded.', 'eurocomply-cer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'assets', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_risk() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE_RISK );
		RiskStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_cer', 'eurocomply_cer_risk', __( 'Risk finding recorded.', 'eurocomply-cer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'risk', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_measure() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE_MEASURE );
		MeasureStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_cer', 'eurocomply_cer_mea', __( 'Measure recorded.', 'eurocomply-cer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'measures', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_incident() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE_INCIDENT );
		IncidentStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_cer', 'eurocomply_cer_inc', __( 'Incident recorded.', 'eurocomply-cer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'incidents', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_stage() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cer' ), 403 );
		}
		check_admin_referer( self::NONCE_STAGE );
		$id    = isset( $_POST['incident_id'] ) ? (int) $_POST['incident_id'] : 0;
		$stage = isset( $_POST['stage'] )       ? sanitize_key( (string) $_POST['stage'] ) : '';
		IncidentStore::mark_sent( $id, $stage );
		add_settings_error( 'eurocomply_cer', 'eurocomply_cer_stg', __( 'Stage marked sent.', 'eurocomply-cer' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'incidents', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// --- form helpers -----------------------------------------------------

	private function row( string $name, string $label, string $type = 'text', string $default = '' ) : void {
		echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="regular-text" value="' . esc_attr( $default ) . '" /></td></tr>';
	}

	private function checkbox( string $name, string $label, bool $checked = false ) : void {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . ' /> ' . esc_html__( 'Yes', 'eurocomply-cer' ) . '</label></td></tr>';
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
