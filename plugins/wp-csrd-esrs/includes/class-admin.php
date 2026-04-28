<?php
/**
 * Admin UI.
 *
 * @package EuroComply\CSRD
 */

declare( strict_types = 1 );

namespace EuroComply\CSRD;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG          = 'eurocomply-csrd-esrs';
	private const NONCE_SAVE        = 'eurocomply_csrd_save';
	private const NONCE_LICENSE     = 'eurocomply_csrd_license';
	private const NONCE_MATERIALITY = 'eurocomply_csrd_materiality';
	private const NONCE_DATAPOINT   = 'eurocomply_csrd_datapoint';
	private const NONCE_ASSURANCE   = 'eurocomply_csrd_assurance';
	private const NONCE_REPORT      = 'eurocomply_csrd_report';

	private static ?Admin $instance = null;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_menu',            array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_eurocomply_csrd_save',        array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_csrd_license',     array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_csrd_materiality', array( $this, 'handle_materiality' ) );
		add_action( 'admin_post_eurocomply_csrd_datapoint',   array( $this, 'handle_datapoint' ) );
		add_action( 'admin_post_eurocomply_csrd_assurance',   array( $this, 'handle_assurance' ) );
		add_action( 'admin_post_eurocomply_csrd_report',      array( $this, 'handle_report' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'CSRD / ESRS', 'eurocomply-csrd-esrs' ),
			__( 'CSRD / ESRS', 'eurocomply-csrd-esrs' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-chart-area',
			81
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-csrd-admin',
			EUROCOMPLY_CSRD_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_CSRD_VERSION
		);
	}

	public function render() : void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard';
		echo '<div class="wrap eurocomply-csrd-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply CSRD / ESRS', 'eurocomply-csrd-esrs' ) . '</h1>';
		settings_errors();
		$this->tabs( $tab );
		switch ( $tab ) {
			case 'standards':   $this->render_standards();   break;
			case 'materiality': $this->render_materiality(); break;
			case 'datapoints':  $this->render_datapoints();  break;
			case 'assurance':   $this->render_assurance();   break;
			case 'reports':     $this->render_reports();     break;
			case 'settings':    $this->render_settings();    break;
			case 'pro':         $this->render_pro();         break;
			case 'license':     $this->render_license();     break;
			case 'dashboard':
			default:            $this->render_dashboard();   break;
		}
		echo '</div>';
	}

	private function tabs( string $current ) : void {
		$tabs = array(
			'dashboard'   => __( 'Dashboard',   'eurocomply-csrd-esrs' ),
			'standards'   => __( 'Standards',   'eurocomply-csrd-esrs' ),
			'materiality' => __( 'Materiality', 'eurocomply-csrd-esrs' ),
			'datapoints'  => __( 'Datapoints',  'eurocomply-csrd-esrs' ),
			'assurance'   => __( 'Assurance',   'eurocomply-csrd-esrs' ),
			'reports'     => __( 'Reports',     'eurocomply-csrd-esrs' ),
			'settings'    => __( 'Settings',    'eurocomply-csrd-esrs' ),
			'pro'         => __( 'Pro',         'eurocomply-csrd-esrs' ),
			'license'     => __( 'License',     'eurocomply-csrd-esrs' ),
		);
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
			$cls = 'nav-tab' . ( $current === $slug ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
	}

	private function card( string $label, string $value, string $tone = '' ) : void {
		$cls = 'eurocomply-csrd-card-stat' . ( '' !== $tone ? ' eurocomply-csrd-card-' . $tone : '' );
		echo '<div class="' . esc_attr( $cls ) . '">';
		echo '<div class="val">' . esc_html( $value ) . '</div>';
		echo '<div class="lbl">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	private function render_dashboard() : void {
		$s     = Settings::get();
		$year  = (int) $s['reporting_year'];
		$app   = Settings::applicability();
		$dps   = DatapointStore::count_for_year( $year );
		$mats  = MaterialityStore::count_material_for_year( $year );
		$cov   = round( ( $dps / max( 1, count( EsrsRegistry::datapoints() ) ) ) * 100, 1 );
		$ass   = AssuranceStore::latest_for_year( $year );
		$reps  = ReportStore::count_total();

		echo '<div class="eurocomply-csrd-cards">';
		$this->card( __( 'Reporting year',  'eurocomply-csrd-esrs' ), (string) $year );
		$this->card( __( 'CSRD applicable', 'eurocomply-csrd-esrs' ), $app['required'] ? __( 'Yes', 'eurocomply-csrd-esrs' ) : __( 'No', 'eurocomply-csrd-esrs' ), $app['required'] ? 'ok' : 'warn' );
		if ( $app['required'] ) {
			$this->card( __( 'First FY in scope', 'eurocomply-csrd-esrs' ), (string) $app['first_year'] );
		}
		$this->card( __( 'Datapoints filled', 'eurocomply-csrd-esrs' ), (string) $dps, $dps > 0 ? 'ok' : 'warn' );
		$this->card( __( 'Coverage', 'eurocomply-csrd-esrs' ), $cov . '%', $cov >= 50 ? 'ok' : 'warn' );
		$this->card( __( 'Material topics', 'eurocomply-csrd-esrs' ), (string) $mats, $mats > 0 ? 'ok' : 'warn' );
		$this->card( __( 'Assurance', 'eurocomply-csrd-esrs' ), $ass ? (string) $ass['level'] : __( 'pending', 'eurocomply-csrd-esrs' ), $ass ? 'ok' : 'warn' );
		$this->card( __( 'Stored reports', 'eurocomply-csrd-esrs' ), (string) $reps );
		echo '</div>';

		echo '<div class="eurocomply-csrd-info">';
		echo '<p><strong>' . esc_html__( 'Applicability', 'eurocomply-csrd-esrs' ) . ':</strong> ' . esc_html( (string) $app['note'] ) . '</p>';
		echo '<p>' . esc_html__( 'Phase-in (Directive 2022/2464): PIE/listed > 500 emp. → FY 2024; large undertakings → FY 2025; listed SMEs → FY 2026 (opt-out to 2028); third-country parents (> €150m EU rev.) → FY 2028.', 'eurocomply-csrd-esrs' ) . '</p>';
		echo '</div>';
	}

	private function render_standards() : void {
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Code',   'eurocomply-csrd-esrs' ) . '</th>';
		echo '<th>' . esc_html__( 'Name',   'eurocomply-csrd-esrs' ) . '</th>';
		echo '<th>' . esc_html__( 'Pillar', 'eurocomply-csrd-esrs' ) . '</th>';
		echo '<th>' . esc_html__( 'Datapoints (free)', 'eurocomply-csrd-esrs' ) . '</th>';
		echo '</tr></thead><tbody>';
		$counts = array();
		foreach ( EsrsRegistry::datapoints() as $dp ) {
			$std = (string) $dp['standard'];
			$counts[ $std ] = ( $counts[ $std ] ?? 0 ) + 1;
		}
		foreach ( EsrsRegistry::standards() as $code => $info ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( (string) $code ) . '</code></td>';
			echo '<td>' . esc_html( (string) $info['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $info['pillar'] ) . '</td>';
			echo '<td>' . (int) ( $counts[ $code ] ?? 0 ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<p>' . esc_html__( 'Free ships ~50 high-frequency datapoints across ESRS 2 / E1 / S1 / G1 — enough for a credible limited-assurance pass. Pro ships the full ~1,100-datapoint EFRAG ESRS XBRL taxonomy.', 'eurocomply-csrd-esrs' ) . '</p>';
	}

	private function render_materiality() : void {
		$rows   = MaterialityStore::recent( 200 );
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$s      = Settings::get();

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_csrd_export" />';
		echo '<input type="hidden" name="dataset" value="materiality" />';
		wp_nonce_field( 'eurocomply_csrd_export' );
		submit_button( __( 'Export materiality CSV', 'eurocomply-csrd-esrs' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Topic', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Subtopic', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Impact', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Financial', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Material?', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Horizon', 'eurocomply-csrd-esrs' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No assessments recorded yet.', 'eurocomply-csrd-esrs' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$mat = (int) $r['impact_material'] === 1 || (int) $r['financial_material'] === 1;
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['topic'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['subtopic'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['impact_score'] ) . '/5</td>';
			echo '<td>' . esc_html( (string) $r['financial_score'] ) . '/5</td>';
			echo '<td>' . ( $mat ? '<span class="eurocomply-csrd-pill ok">' . esc_html__( 'Material', 'eurocomply-csrd-esrs' ) . '</span>' : '<span class="eurocomply-csrd-pill warn">' . esc_html__( 'Not material', 'eurocomply-csrd-esrs' ) . '</span>' ) . '</td>';
			echo '<td>' . esc_html( (string) $r['horizon'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Delete?', 'eurocomply-csrd-esrs' ) ) . '\');">';
			echo '<input type="hidden" name="action" value="eurocomply_csrd_materiality" />';
			echo '<input type="hidden" name="op"     value="delete" />';
			echo '<input type="hidden" name="mat_id" value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( self::NONCE_MATERIALITY );
			submit_button( __( 'Delete', 'eurocomply-csrd-esrs' ), 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add assessment', 'eurocomply-csrd-esrs' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_csrd_materiality" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_MATERIALITY );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label>' . esc_html__( 'Topic', 'eurocomply-csrd-esrs' ) . '</label></th><td><select name="row[topic]" required>';
		foreach ( EsrsRegistry::topics() as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Subtopic / IRO description', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="text" name="row[subtopic]" class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Impact materiality (0–5)', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" min="0" max="5" name="row[impact_score]" required /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Financial materiality (0–5)', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" min="0" max="5" name="row[financial_score]" required /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Threshold', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" min="1" max="5" name="row[threshold]" value="3" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Time horizon', 'eurocomply-csrd-esrs' ) . '</label></th><td><select name="row[horizon]"><option value="short">' . esc_html__( 'Short (≤1y)', 'eurocomply-csrd-esrs' ) . '</option><option value="medium">' . esc_html__( 'Medium (1–5y)', 'eurocomply-csrd-esrs' ) . '</option><option value="long">' . esc_html__( 'Long (>5y)', 'eurocomply-csrd-esrs' ) . '</option></select></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Value chain', 'eurocomply-csrd-esrs' ) . '</label></th><td><select name="row[value_chain]"><option value="own">' . esc_html__( 'Own operations', 'eurocomply-csrd-esrs' ) . '</option><option value="upstream">' . esc_html__( 'Upstream', 'eurocomply-csrd-esrs' ) . '</option><option value="downstream">' . esc_html__( 'Downstream', 'eurocomply-csrd-esrs' ) . '</option><option value="all">' . esc_html__( 'All', 'eurocomply-csrd-esrs' ) . '</option></select></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Year', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" min="2020" max="2099" name="row[year]" value="' . esc_attr( (string) $s['reporting_year'] ) . '" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Rationale', 'eurocomply-csrd-esrs' ) . '</label></th><td><textarea name="row[rationale]" rows="3" class="large-text"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add assessment', 'eurocomply-csrd-esrs' ) );
		echo '</form>';
	}

	private function render_datapoints() : void {
		$s      = Settings::get();
		$year   = (int) $s['reporting_year'];
		$registry = EsrsRegistry::datapoints();
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_csrd_export" />';
		echo '<input type="hidden" name="dataset" value="datapoints" />';
		wp_nonce_field( 'eurocomply_csrd_export' );
		submit_button( __( 'Export datapoints CSV', 'eurocomply-csrd-esrs' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( SustainabilityBridge::cbam_active() || SustainabilityBridge::eudr_active() ) {
			echo '<div class="notice notice-info inline" style="margin-bottom:1em;"><p><strong>' . esc_html__( 'Sister-plugin bridges available', 'eurocomply-csrd-esrs' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Re-aggregate operational data already maintained for CBAM (Reg. (EU) 2023/956) or EUDR (Reg. (EU) 2023/1115) into the matching ESRS datapoint for the reporting year above.', 'eurocomply-csrd-esrs' ) . '</p>';
			echo '<form method="post" action="' . $action . '" style="display:inline-block;margin-right:0.5em;">';
			echo '<input type="hidden" name="action" value="eurocomply_csrd_datapoint" />';
			echo '<input type="hidden" name="op"     value="bridge_refresh" />';
			wp_nonce_field( self::NONCE_DATAPOINT );
			echo '<input type="hidden" name="row[year]" value="' . esc_attr( (string) $year ) . '" />';
			if ( SustainabilityBridge::cbam_active() ) {
				echo '<button type="submit" name="bridge" value="cbam" class="button">' . esc_html__( 'Refresh E1-6-S3 from CBAM imports', 'eurocomply-csrd-esrs' ) . '</button> ';
			}
			if ( SustainabilityBridge::eudr_active() ) {
				echo '<button type="submit" name="bridge" value="eudr" class="button">' . esc_html__( 'Refresh E5-4-INFLOW from EUDR shipments', 'eurocomply-csrd-esrs' ) . '</button>';
			}
			echo '</form></div>';
		}

		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_csrd_datapoint" />';
		echo '<input type="hidden" name="op"     value="upsert" />';
		wp_nonce_field( self::NONCE_DATAPOINT );
		echo '<input type="hidden" name="row[year]" value="' . esc_attr( (string) $year ) . '" />';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Standard', 'eurocomply-csrd-esrs' ) . '</th>';
		echo '<th>' . esc_html__( 'Disclosure', 'eurocomply-csrd-esrs' ) . '</th>';
		echo '<th>' . esc_html__( 'Datapoint', 'eurocomply-csrd-esrs' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit', 'eurocomply-csrd-esrs' ) . '</th>';
		echo '<th>' . esc_html__( 'Value', 'eurocomply-csrd-esrs' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $registry as $id => $def ) {
			$existing = DatapointStore::get( $year, $id );
			$num   = null !== ( $existing['value_numeric'] ?? null ) ? (string) $existing['value_numeric'] : '';
			$txt   = (string) ( $existing['value_text'] ?? '' );
			$kind  = (string) $def['kind'];

			echo '<tr>';
			echo '<td>' . esc_html( (string) $def['standard'] ) . '</td>';
			echo '<td>' . esc_html( (string) $def['disclosure'] ) . '</td>';
			echo '<td>' . esc_html( (string) $def['name'] ) . ' <code>' . esc_html( (string) $id ) . '</code></td>';
			echo '<td>' . esc_html( (string) $def['unit'] ) . '</td>';
			echo '<td>';
			if ( 'numeric' === $kind ) {
				echo '<input type="number" step="0.0001" name="dp[' . esc_attr( $id ) . '][value_numeric]" value="' . esc_attr( $num ) . '" />';
			} else {
				echo '<textarea name="dp[' . esc_attr( $id ) . '][value_text]" rows="2" class="large-text">' . esc_textarea( $txt ) . '</textarea>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save all datapoints', 'eurocomply-csrd-esrs' ) );
		echo '</form>';
	}

	private function render_assurance() : void {
		$rows   = AssuranceStore::recent( 50 );
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$s      = Settings::get();

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Year', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Provider', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Level', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Signed', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Signatory', 'eurocomply-csrd-esrs' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No assurance records yet.', 'eurocomply-csrd-esrs' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['year'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['provider'] ) . ( '' !== (string) $r['report_url'] ? ' <a href="' . esc_url( (string) $r['report_url'] ) . '" target="_blank" rel="noopener">↗</a>' : '' ) . '</td>';
			echo '<td><span class="eurocomply-csrd-pill ' . ( 'reasonable' === (string) $r['level'] ? 'ok' : ( 'limited' === (string) $r['level'] ? 'warn' : 'crit' ) ) . '">' . esc_html( (string) $r['level'] ) . '</span></td>';
			echo '<td>' . esc_html( (string) ( $r['signed_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['signatory'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Delete?', 'eurocomply-csrd-esrs' ) ) . '\');">';
			echo '<input type="hidden" name="action" value="eurocomply_csrd_assurance" />';
			echo '<input type="hidden" name="op"     value="delete" />';
			echo '<input type="hidden" name="ass_id" value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( self::NONCE_ASSURANCE );
			submit_button( __( 'Delete', 'eurocomply-csrd-esrs' ), 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Record assurance', 'eurocomply-csrd-esrs' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_csrd_assurance" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_ASSURANCE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label>' . esc_html__( 'Year', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" min="2020" max="2099" name="ass[year]" value="' . esc_attr( (string) $s['reporting_year'] ) . '" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Provider', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="text" name="ass[provider]" class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Level', 'eurocomply-csrd-esrs' ) . '</label></th><td><select name="ass[level]"><option value="limited">' . esc_html__( 'Limited assurance', 'eurocomply-csrd-esrs' ) . '</option><option value="reasonable">' . esc_html__( 'Reasonable assurance', 'eurocomply-csrd-esrs' ) . '</option><option value="none">' . esc_html__( 'Unaudited', 'eurocomply-csrd-esrs' ) . '</option></select></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Scope', 'eurocomply-csrd-esrs' ) . '</label></th><td><textarea name="ass[scope]" rows="3" class="large-text"></textarea></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Opinion', 'eurocomply-csrd-esrs' ) . '</label></th><td><textarea name="ass[opinion]" rows="3" class="large-text"></textarea></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Signed at', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="datetime-local" name="ass[signed_at]" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Signatory', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="text" name="ass[signatory]" class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Report URL', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="url" name="ass[report_url]" class="regular-text" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record assurance', 'eurocomply-csrd-esrs' ) );
		echo '</form>';
	}

	private function render_reports() : void {
		$s      = Settings::get();
		$rows   = ReportStore::recent( 30 );
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<h2>' . esc_html__( 'Build report', 'eurocomply-csrd-esrs' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_csrd_report" />';
		echo '<input type="hidden" name="op"     value="run" />';
		wp_nonce_field( self::NONCE_REPORT );
		echo '<input type="number" min="2020" max="2099" name="year" value="' . esc_attr( (string) $s['reporting_year'] ) . '" /> ';
		submit_button( __( 'Build CSRD report', 'eurocomply-csrd-esrs' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Stored reports', 'eurocomply-csrd-esrs' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Generated', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Year', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Datapoints', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Material', 'eurocomply-csrd-esrs' ) . '</th><th>' . esc_html__( 'Coverage', 'eurocomply-csrd-esrs' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No reports yet.', 'eurocomply-csrd-esrs' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['created_at'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['year'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['datapoints_count'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['material_topics'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['coverage_pct'] ) . '%</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline">';
			echo '<input type="hidden" name="action"    value="eurocomply_csrd_export_xbrl" />';
			echo '<input type="hidden" name="report_id" value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( 'eurocomply_csrd_export' );
			submit_button( __( 'XBRL', 'eurocomply-csrd-esrs' ), 'secondary small', 'submit', false );
			echo '</form>';
			echo ' ';
			echo '<form method="post" action="' . $action . '" style="display:inline">';
			echo '<input type="hidden" name="action"    value="eurocomply_csrd_export_json" />';
			echo '<input type="hidden" name="report_id" value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( 'eurocomply_csrd_export' );
			submit_button( __( 'JSON', 'eurocomply-csrd-esrs' ), 'secondary small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_csrd_save" />';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="company_name">' . esc_html__( 'Company name', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="text" id="company_name" name="eurocomply_csrd[company_name]" value="' . esc_attr( (string) $s['company_name'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="lei">' . esc_html__( 'Legal Entity Identifier (LEI, 20 chars)', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="text" id="lei" name="eurocomply_csrd[lei]" value="' . esc_attr( (string) $s['lei'] ) . '" maxlength="20" /></td></tr>';
		echo '<tr><th><label for="country">' . esc_html__( 'Country (ISO-2)', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="text" id="country" name="eurocomply_csrd[country]" value="' . esc_attr( (string) $s['country'] ) . '" maxlength="2" /></td></tr>';
		echo '<tr><th><label for="company_size">' . esc_html__( 'Company size', 'eurocomply-csrd-esrs' ) . '</label></th><td><select id="company_size" name="eurocomply_csrd[company_size]">';
		foreach ( array(
			'large'      => __( 'Large undertaking',                      'eurocomply-csrd-esrs' ),
			'listed_sme' => __( 'Listed SME',                             'eurocomply-csrd-esrs' ),
			'micro'      => __( 'Micro / unlisted SME (out of scope)',    'eurocomply-csrd-esrs' ),
			'non_eu'     => __( 'Third-country parent (> €150m EU rev.)', 'eurocomply-csrd-esrs' ),
		) as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( (string) $s['company_size'], $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Listed?', 'eurocomply-csrd-esrs' ) . '</label></th><td><label><input type="checkbox" name="eurocomply_csrd[is_listed]" value="1"' . checked( ! empty( $s['is_listed'] ), true, false ) . ' /> ' . esc_html__( 'On a regulated EU market', 'eurocomply-csrd-esrs' ) . '</label></td></tr>';
		echo '<tr><th><label for="employees">' . esc_html__( 'Employees (avg head count)', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" id="employees" min="0" name="eurocomply_csrd[employees]" value="' . esc_attr( (string) $s['employees'] ) . '" /></td></tr>';
		echo '<tr><th><label for="net_turnover_eur">' . esc_html__( 'Net turnover (EUR)', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" id="net_turnover_eur" min="0" name="eurocomply_csrd[net_turnover_eur]" value="' . esc_attr( (string) $s['net_turnover_eur'] ) . '" /></td></tr>';
		echo '<tr><th><label for="balance_sheet_eur">' . esc_html__( 'Balance sheet total (EUR)', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" id="balance_sheet_eur" min="0" name="eurocomply_csrd[balance_sheet_eur]" value="' . esc_attr( (string) $s['balance_sheet_eur'] ) . '" /></td></tr>';
		echo '<tr><th><label for="reporting_year">' . esc_html__( 'Active reporting year', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" min="2020" max="2099" id="reporting_year" name="eurocomply_csrd[reporting_year]" value="' . esc_attr( (string) $s['reporting_year'] ) . '" /></td></tr>';
		echo '<tr><th><label for="first_reporting_year">' . esc_html__( 'First reporting year', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="number" min="2020" max="2099" id="first_reporting_year" name="eurocomply_csrd[first_reporting_year]" value="' . esc_attr( (string) $s['first_reporting_year'] ) . '" /></td></tr>';
		echo '<tr><th><label for="assurance_level">' . esc_html__( 'Default assurance level', 'eurocomply-csrd-esrs' ) . '</label></th><td><select id="assurance_level" name="eurocomply_csrd[assurance_level]">';
		foreach ( array( 'limited' => 'limited', 'reasonable' => 'reasonable', 'none' => 'none' ) as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( (string) $s['assurance_level'], $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="assurance_provider">' . esc_html__( 'Default assurance provider', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="text" id="assurance_provider" name="eurocomply_csrd[assurance_provider]" value="' . esc_attr( (string) $s['assurance_provider'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="sustainability_officer_email">' . esc_html__( 'Sustainability officer email', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="email" id="sustainability_officer_email" name="eurocomply_csrd[sustainability_officer_email]" value="' . esc_attr( (string) $s['sustainability_officer_email'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="currency">' . esc_html__( 'Currency', 'eurocomply-csrd-esrs' ) . '</label></th><td><input type="text" id="currency" name="eurocomply_csrd[currency]" value="' . esc_attr( (string) $s['currency'] ) . '" maxlength="3" /></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-csrd-esrs' ) . '</h2>';
		echo '<ul class="ul-disc" style="margin-left:1.4em;">';
		foreach ( array(
			__( 'Full EFRAG ESRS XBRL taxonomy (~1,100 datapoints) + auto-validation',  'eurocomply-csrd-esrs' ),
			__( 'ESEF inline-XBRL (iXBRL) tagged management report',                     'eurocomply-csrd-esrs' ),
			__( 'Signed PDF sustainability statement (auditor-ready)',                   'eurocomply-csrd-esrs' ),
			__( 'Materiality matrix renderer (PDF + interactive plot)',                  'eurocomply-csrd-esrs' ),
			__( 'Supplier portal — value-chain partners upload S2/E1-Scope-3 data',      'eurocomply-csrd-esrs' ),
			__( 'GHG Protocol Scope-3 calculator (15 categories)',                       'eurocomply-csrd-esrs' ),
			__( 'EU Taxonomy (Reg. 2020/852) eligibility & alignment KPI engine',        'eurocomply-csrd-esrs' ),
			__( 'REST API: /eurocomply/v1/csrd/{datapoints,materiality,reports}',        'eurocomply-csrd-esrs' ),
			__( 'WPML / Polylang multi-language disclosures',                             'eurocomply-csrd-esrs' ),
			__( '5,000-row CSV export cap',                                                'eurocomply-csrd-esrs' ),
			__( 'Multi-site network aggregator (group consolidation)',                    'eurocomply-csrd-esrs' ),
			__( 'Assurance-engagement signed-evidence vault',                             'eurocomply-csrd-esrs' ),
		) as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_license() : void {
		$d      = License::get();
		$status = ! empty( $d['status'] ) ? (string) $d['status'] : 'inactive';
		$key    = ! empty( $d['key'] )    ? (string) $d['key']    : '';
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_csrd_license" />';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-csrd-esrs' ) . '</label></th>';
		echo '<td><input type="text" id="license_key" name="license_key" value="' . esc_attr( $key ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-csrd-esrs' ) . '</th><td><code>' . esc_html( $status ) . '</code></td></tr>';
		echo '</tbody></table>';
		submit_button( License::is_pro() ? __( 'Deactivate', 'eurocomply-csrd-esrs' ) : __( 'Activate', 'eurocomply-csrd-esrs' ) );
		echo '</form>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST['eurocomply_csrd'] ) && is_array( $_POST['eurocomply_csrd'] ) ? wp_unslash( (array) $_POST['eurocomply_csrd'] ) : array();
		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );
		add_settings_error( 'eurocomply_csrd', 'saved', __( 'Saved.', 'eurocomply-csrd-esrs' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE_LICENSE );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		if ( License::is_pro() ) {
			License::deactivate();
			add_settings_error( 'eurocomply_csrd', 'lic-off', __( 'License deactivated.', 'eurocomply-csrd-esrs' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_csrd', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_materiality() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE_MATERIALITY );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['mat_id'] ) ? (int) $_POST['mat_id'] : 0;
			if ( $id > 0 ) {
				MaterialityStore::delete( $id );
				add_settings_error( 'eurocomply_csrd', 'mat-del', __( 'Assessment deleted.', 'eurocomply-csrd-esrs' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			$id  = MaterialityStore::create( $row );
			add_settings_error( 'eurocomply_csrd', 'mat-ok', sprintf( /* translators: %d: id */ __( 'Assessment #%d added.', 'eurocomply-csrd-esrs' ), $id ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'materiality', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_datapoint() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE_DATAPOINT );
		$year = isset( $_POST['row']['year'] ) ? (int) $_POST['row']['year'] : (int) Settings::get()['reporting_year'];
		$op   = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'upsert';
		if ( 'bridge_refresh' === $op ) {
			$bridge = isset( $_POST['bridge'] ) ? sanitize_key( (string) $_POST['bridge'] ) : '';
			if ( 'cbam' === $bridge ) {
				$value = SustainabilityBridge::refresh_cbam_for_year( $year );
				$msg   = null === $value
					? __( 'CBAM plugin not active — bridge skipped.', 'eurocomply-csrd-esrs' )
					: sprintf( /* translators: 1: year, 2: tCO2e */ __( 'CBAM bridge refreshed E1-6-S3 for FY %1$d → %2$s tCO2e.', 'eurocomply-csrd-esrs' ), $year, number_format_i18n( $value, 2 ) );
				add_settings_error( 'eurocomply_csrd', 'br-cbam', $msg, 'updated' );
			} elseif ( 'eudr' === $bridge ) {
				$value = SustainabilityBridge::refresh_eudr_for_year( $year );
				$msg   = null === $value
					? __( 'EUDR plugin not active — bridge skipped.', 'eurocomply-csrd-esrs' )
					: sprintf( /* translators: 1: year, 2: tonnes */ __( 'EUDR bridge refreshed E5-4-INFLOW for FY %1$d → %2$s t.', 'eurocomply-csrd-esrs' ), $year, number_format_i18n( $value, 2 ) );
				add_settings_error( 'eurocomply_csrd', 'br-eudr', $msg, 'updated' );
			}
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'datapoints', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		$dps  = isset( $_POST['dp'] ) && is_array( $_POST['dp'] ) ? wp_unslash( (array) $_POST['dp'] ) : array();
		$saved = 0;
		foreach ( $dps as $id => $vals ) {
			if ( ! is_array( $vals ) ) {
				continue;
			}
			$has_num = isset( $vals['value_numeric'] ) && '' !== (string) $vals['value_numeric'];
			$has_txt = isset( $vals['value_text'] )    && '' !== (string) $vals['value_text'];
			if ( ! $has_num && ! $has_txt ) {
				continue;
			}
			DatapointStore::upsert(
				array(
					'year'          => $year,
					'datapoint_id'  => sanitize_text_field( (string) $id ),
					'value_numeric' => $has_num ? $vals['value_numeric'] : null,
					'value_text'    => $has_txt ? $vals['value_text']    : null,
				)
			);
			$saved++;
		}
		add_settings_error( 'eurocomply_csrd', 'dp-ok', sprintf( /* translators: %d: count */ __( '%d datapoints saved.', 'eurocomply-csrd-esrs' ), $saved ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'datapoints', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_assurance() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE_ASSURANCE );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['ass_id'] ) ? (int) $_POST['ass_id'] : 0;
			if ( $id > 0 ) {
				AssuranceStore::delete( $id );
				add_settings_error( 'eurocomply_csrd', 'ass-del', __( 'Assurance record deleted.', 'eurocomply-csrd-esrs' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['ass'] ) && is_array( $_POST['ass'] ) ? wp_unslash( (array) $_POST['ass'] ) : array();
			AssuranceStore::create( $row );
			add_settings_error( 'eurocomply_csrd', 'ass-ok', __( 'Assurance recorded.', 'eurocomply-csrd-esrs' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'assurance', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_report() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-csrd-esrs' ), 403 );
		}
		check_admin_referer( self::NONCE_REPORT );
		$year = isset( $_POST['year'] ) ? max( 2020, (int) $_POST['year'] ) : (int) Settings::get()['reporting_year'];
		$res  = ReportBuilder::build( $year );
		ReportStore::create(
			array(
				'year'             => $year,
				'datapoints_count' => (int) $res['datapoints_count'],
				'material_topics'  => (int) $res['material_topics'],
				'coverage_pct'     => (float) $res['coverage_pct'],
				'xbrl_envelope'    => (string) $res['xbrl'],
				'payload'          => (string) $res['payload'],
			)
		);
		add_settings_error(
			'eurocomply_csrd',
			'rep-ok',
			sprintf(
				/* translators: 1: year, 2: datapoints count, 3: coverage */
				esc_html__( 'CSRD report built for FY %1$d — %2$d datapoints (%3$s%% coverage).', 'eurocomply-csrd-esrs' ),
				$year,
				(int) $res['datapoints_count'],
				(string) $res['coverage_pct']
			),
			'updated'
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'reports', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
