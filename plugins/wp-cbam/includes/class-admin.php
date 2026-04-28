<?php
/**
 * Admin UI.
 *
 * @package EuroComply\CBAM
 */

declare( strict_types = 1 );

namespace EuroComply\CBAM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG       = 'eurocomply-cbam';
	private const NONCE_SAVE     = 'eurocomply_cbam_save';
	private const NONCE_LICENSE  = 'eurocomply_cbam_license';
	private const NONCE_IMPORT   = 'eurocomply_cbam_import';
	private const NONCE_VERIFIER = 'eurocomply_cbam_verifier';
	private const NONCE_REPORT   = 'eurocomply_cbam_report';

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
		add_action( 'admin_post_eurocomply_cbam_save',     array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_cbam_license',  array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_cbam_import',   array( $this, 'handle_import' ) );
		add_action( 'admin_post_eurocomply_cbam_verifier', array( $this, 'handle_verifier' ) );
		add_action( 'admin_post_eurocomply_cbam_report',   array( $this, 'handle_report' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'CBAM', 'eurocomply-cbam' ),
			__( 'CBAM', 'eurocomply-cbam' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-admin-site-alt3',
			80
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-cbam-admin',
			EUROCOMPLY_CBAM_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_CBAM_VERSION
		);
	}

	public function render() : void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard';
		echo '<div class="wrap eurocomply-cbam-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply CBAM', 'eurocomply-cbam' ) . '</h1>';
		settings_errors();
		$this->tabs( $tab );
		switch ( $tab ) {
			case 'goods':      $this->render_goods();     break;
			case 'imports':    $this->render_imports();   break;
			case 'reports':    $this->render_reports();   break;
			case 'declarants': $this->render_declarants(); break;
			case 'verifiers':  $this->render_verifiers(); break;
			case 'settings':   $this->render_settings();  break;
			case 'pro':        $this->render_pro();       break;
			case 'license':    $this->render_license();   break;
			case 'dashboard':
			default:           $this->render_dashboard(); break;
		}
		echo '</div>';
	}

	private function tabs( string $current ) : void {
		$tabs = array(
			'dashboard'  => __( 'Dashboard',   'eurocomply-cbam' ),
			'goods'      => __( 'Goods',       'eurocomply-cbam' ),
			'imports'    => __( 'Imports',     'eurocomply-cbam' ),
			'reports'    => __( 'Reports',     'eurocomply-cbam' ),
			'declarants' => __( 'Declarants',  'eurocomply-cbam' ),
			'verifiers'  => __( 'Verifiers',   'eurocomply-cbam' ),
			'settings'   => __( 'Settings',    'eurocomply-cbam' ),
			'pro'        => __( 'Pro',         'eurocomply-cbam' ),
			'license'    => __( 'License',     'eurocomply-cbam' ),
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
		$cls = 'eurocomply-cbam-card-stat' . ( '' !== $tone ? ' eurocomply-cbam-card-' . $tone : '' );
		echo '<div class="' . esc_attr( $cls ) . '">';
		echo '<div class="val">' . esc_html( $value ) . '</div>';
		echo '<div class="lbl">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	private function render_dashboard() : void {
		$s        = Settings::get();
		$period   = (string) $s['reporting_period'];
		$imp_n    = ImportStore::count_for_period( $period );
		$unv_n    = ImportStore::unverified_count_for_period( $period );
		$rep_n    = ReportStore::count_total();
		$ver_n    = VerifierStore::count_total();

		echo '<div class="eurocomply-cbam-cards">';
		$this->card( __( 'Reporting period',  'eurocomply-cbam' ), $period );
		$this->card( __( 'Imports (period)',  'eurocomply-cbam' ), (string) $imp_n, $imp_n > 0 ? 'ok' : 'warn' );
		$this->card( __( 'Default values',    'eurocomply-cbam' ), (string) $unv_n, $unv_n > 0 ? 'warn' : 'ok' );
		$this->card( __( 'Reports stored',    'eurocomply-cbam' ), (string) $rep_n );
		$this->card( __( 'Verifiers',         'eurocomply-cbam' ), (string) $ver_n );
		$this->card( __( 'Declarant',         'eurocomply-cbam' ), '' !== (string) $s['declarant_eori'] ? (string) $s['declarant_eori'] : __( '— set EORI —', 'eurocomply-cbam' ), '' === (string) $s['declarant_eori'] ? 'crit' : 'ok' );
		echo '</div>';

		echo '<div class="eurocomply-cbam-info">';
		echo '<p>' . esc_html__( 'Transitional period: 1 Oct 2023 – 31 Dec 2025. Quarterly Q-reports due by end of the month following each quarter (e.g. Q4 2024 → 31 Jan 2025).', 'eurocomply-cbam' ) . '</p>';
		echo '<p>' . esc_html__( 'From 1 Jan 2026: definitive period. Only authorised CBAM declarants may import goods listed in Annex I; quarterly Q-reports replaced by the annual CBAM declaration + certificate surrender.', 'eurocomply-cbam' ) . '</p>';
		echo '</div>';
	}

	private function render_goods() : void {
		echo '<h2>' . esc_html__( 'CBAM goods categories (Annex I)', 'eurocomply-cbam' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Slug',     'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Name',     'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit',     'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Default direct (tCO₂e/unit)',  'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Default indirect (tCO₂e/unit)','eurocomply-cbam' ) . '</th>';
		echo '</tr></thead><tbody>';
		$defaults = CbamRegistry::default_emissions();
		foreach ( CbamRegistry::categories() as $slug => $cat ) {
			$d = $defaults[ $slug ] ?? array( 'direct' => 0, 'indirect' => 0 );
			echo '<tr>';
			echo '<td><code>' . esc_html( $slug ) . '</code></td>';
			echo '<td>' . esc_html( (string) $cat['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $cat['unit'] ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) $d['direct'], 4 ) ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) $d['indirect'], 4 ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Sample CN-8 mapping', 'eurocomply-cbam' ) . '</h2>';
		echo '<p>' . esc_html__( 'Free ships ~30 representative CN-8 codes per Annex I. Pro: full TARIC sync (every CN-8 sub-heading + automatic updates).', 'eurocomply-cbam' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>CN-8</th><th>' . esc_html__( 'Category', 'eurocomply-cbam' ) . '</th></tr></thead><tbody>';
		foreach ( CbamRegistry::cn_to_category() as $cn => $cat ) {
			echo '<tr><td><code>' . esc_html( $cn ) . '</code></td><td>' . esc_html( $cat ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function render_imports() : void {
		$rows  = ImportStore::recent( 100 );
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$s     = Settings::get();

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_cbam_export" />';
		echo '<input type="hidden" name="dataset" value="imports" />';
		wp_nonce_field( 'eurocomply_cbam_export' );
		submit_button( __( 'Export imports CSV', 'eurocomply-cbam' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th>';
		echo '<th>' . esc_html__( 'Period',   'eurocomply-cbam' ) . '</th>';
		echo '<th>CN-8</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Origin',   'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Quantity', 'eurocomply-cbam' ) . '</th>';
		echo '<th>tCO₂e/u (D+I)</th>';
		echo '<th>' . esc_html__( 'Verified', 'eurocomply-cbam' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No imports recorded yet.', 'eurocomply-cbam' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['period'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['cn8'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $r['category'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['origin_country'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['quantity'] ) . ' ' . esc_html( (string) $r['unit'] ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) $r['direct_emissions'] + (float) $r['indirect_emissions'], 4 ) ) . '</td>';
			echo '<td>' . ( (int) $r['emissions_verified'] === 1 ? '<span class="eurocomply-cbam-pill ok">' . esc_html__( 'Verified', 'eurocomply-cbam' ) . '</span>' : '<span class="eurocomply-cbam-pill warn">' . esc_html__( 'Default', 'eurocomply-cbam' ) . '</span>' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add import', 'eurocomply-cbam' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_cbam_import" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_IMPORT );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label>' . esc_html__( 'Period', 'eurocomply-cbam' ) . '</label></th><td><input type="text" name="row[period]" value="' . esc_attr( (string) $s['reporting_period'] ) . '" pattern="\d{4}-Q[1-4]" /></td></tr>';
		echo '<tr><th><label>CN-8</label></th><td><input type="text" name="row[cn8]" maxlength="8" pattern="[0-9]{8}" required /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Origin (ISO-2)', 'eurocomply-cbam' ) . '</label></th><td><input type="text" name="row[origin_country]" maxlength="2" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Supplier', 'eurocomply-cbam' ) . '</label></th><td><input type="text" name="row[supplier]" class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Production route', 'eurocomply-cbam' ) . '</label></th><td><input type="text" name="row[production_route]" class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Quantity', 'eurocomply-cbam' ) . '</label></th><td><input type="number" step="0.0001" min="0" name="row[quantity]" required /> <input type="text" name="row[unit]" value="t" maxlength="8" style="width:60px" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Direct (tCO₂e/unit)', 'eurocomply-cbam' ) . '</label></th><td><input type="number" step="0.0001" min="0" name="row[direct_emissions]" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Indirect (tCO₂e/unit)', 'eurocomply-cbam' ) . '</label></th><td><input type="number" step="0.0001" min="0" name="row[indirect_emissions]" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Data source', 'eurocomply-cbam' ) . '</label></th><td><select name="row[data_source]"><option value="default">' . esc_html__( 'Default values (Annex IV)', 'eurocomply-cbam' ) . '</option><option value="estimate">' . esc_html__( 'Estimate', 'eurocomply-cbam' ) . '</option><option value="verified">' . esc_html__( 'Verified', 'eurocomply-cbam' ) . '</option></select></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Emissions verified?', 'eurocomply-cbam' ) . '</label></th><td><label><input type="checkbox" name="row[emissions_verified]" value="1" /> ' . esc_html__( 'Yes (accredited verifier)', 'eurocomply-cbam' ) . '</label></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add import', 'eurocomply-cbam' ) );
		echo '</form>';
	}

	private function render_reports() : void {
		$s     = Settings::get();
		$rows  = ReportStore::recent( 30 );
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<h2>' . esc_html__( 'Build Q-report', 'eurocomply-cbam' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_cbam_report" />';
		echo '<input type="hidden" name="op"     value="run" />';
		wp_nonce_field( self::NONCE_REPORT );
		echo '<input type="text" name="period" value="' . esc_attr( (string) $s['reporting_period'] ) . '" pattern="\d{4}-Q[1-4]" /> ';
		submit_button( __( 'Build report', 'eurocomply-cbam' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Stored reports', 'eurocomply-cbam' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Generated', 'eurocomply-cbam' ) . '</th><th>' . esc_html__( 'Period', 'eurocomply-cbam' ) . '</th><th>' . esc_html__( 'Imports', 'eurocomply-cbam' ) . '</th><th>tCO₂e (D)</th><th>tCO₂e (I)</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No reports yet.', 'eurocomply-cbam' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['created_at'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['period'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['imports_count'] ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) $r['total_direct'], 4 ) ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) $r['total_indirect'], 4 ) ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline">';
			echo '<input type="hidden" name="action"    value="eurocomply_cbam_export_xml" />';
			echo '<input type="hidden" name="report_id" value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( 'eurocomply_cbam_export' );
			submit_button( __( 'XML', 'eurocomply-cbam' ), 'secondary small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_declarants() : void {
		$s = Settings::get();
		echo '<h2>' . esc_html__( 'Reporting declarant', 'eurocomply-cbam' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configure declarant identity in the Settings tab. From 1 January 2026 only the “authorised CBAM declarant” status (Art. 5 of Reg. 2023/956) permits import of CBAM goods.', 'eurocomply-cbam' ) . '</p>';
		echo '<table class="widefat striped"><tbody>';
		echo '<tr><th>' . esc_html__( 'Name', 'eurocomply-cbam' ) . '</th><td>' . esc_html( (string) $s['declarant_name'] ) . '</td></tr>';
		echo '<tr><th>EORI</th><td><code>' . esc_html( (string) $s['declarant_eori'] ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Country', 'eurocomply-cbam' ) . '</th><td>' . esc_html( (string) $s['declarant_country'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Authorised CBAM declarant ID', 'eurocomply-cbam' ) . '</th><td><code>' . esc_html( (string) $s['authorised_declarant_id'] ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Reporting officer email', 'eurocomply-cbam' ) . '</th><td>' . esc_html( (string) $s['reporting_officer_email'] ) . '</td></tr>';
		echo '</tbody></table>';
	}

	private function render_verifiers() : void {
		$rows  = VerifierStore::all();
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Country', 'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Name',    'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Accreditation', 'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Scope',   'eurocomply-cbam' ) . '</th>';
		echo '<th>' . esc_html__( 'Email',   'eurocomply-cbam' ) . '</th>';
		echo '<th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No verifiers in directory yet.', 'eurocomply-cbam' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . ( '' !== (string) $r['website'] ? ' <a href="' . esc_url( (string) $r['website'] ) . '" target="_blank" rel="noopener">↗</a>' : '' ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['accreditation_id'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $r['scope'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['contact_email'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Delete?', 'eurocomply-cbam' ) ) . '\');">';
			echo '<input type="hidden" name="action"    value="eurocomply_cbam_verifier" />';
			echo '<input type="hidden" name="op"        value="delete" />';
			echo '<input type="hidden" name="ver_id"    value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( self::NONCE_VERIFIER );
			submit_button( __( 'Delete', 'eurocomply-cbam' ), 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add verifier', 'eurocomply-cbam' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_cbam_verifier" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_VERIFIER );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label>' . esc_html__( 'Country (ISO-2)', 'eurocomply-cbam' ) . '</label></th><td><input type="text" name="ver[country]" maxlength="2" required /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Name', 'eurocomply-cbam' ) . '</label></th><td><input type="text" name="ver[name]" required class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Accreditation ID', 'eurocomply-cbam' ) . '</label></th><td><input type="text" name="ver[accreditation_id]" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Scope', 'eurocomply-cbam' ) . '</label></th><td><input type="text" name="ver[scope]" class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Contact email', 'eurocomply-cbam' ) . '</label></th><td><input type="email" name="ver[contact_email]" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Website', 'eurocomply-cbam' ) . '</label></th><td><input type="url" name="ver[website]" class="regular-text" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add verifier', 'eurocomply-cbam' ) );
		echo '</form>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_cbam_save" />';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="declarant_name">' . esc_html__( 'Declarant name', 'eurocomply-cbam' ) . '</label></th><td><input type="text" id="declarant_name" name="eurocomply_cbam[declarant_name]" value="' . esc_attr( (string) $s['declarant_name'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="declarant_eori">EORI</label></th><td><input type="text" id="declarant_eori" name="eurocomply_cbam[declarant_eori]" value="' . esc_attr( (string) $s['declarant_eori'] ) . '" /></td></tr>';
		echo '<tr><th><label for="declarant_country">' . esc_html__( 'Country (ISO-2)', 'eurocomply-cbam' ) . '</label></th><td><input type="text" id="declarant_country" name="eurocomply_cbam[declarant_country]" value="' . esc_attr( (string) $s['declarant_country'] ) . '" maxlength="2" /></td></tr>';
		echo '<tr><th><label for="authorised_declarant_id">' . esc_html__( 'Authorised CBAM declarant ID (from 2026)', 'eurocomply-cbam' ) . '</label></th><td><input type="text" id="authorised_declarant_id" name="eurocomply_cbam[authorised_declarant_id]" value="' . esc_attr( (string) $s['authorised_declarant_id'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="reporting_officer_email">' . esc_html__( 'Reporting officer email', 'eurocomply-cbam' ) . '</label></th><td><input type="email" id="reporting_officer_email" name="eurocomply_cbam[reporting_officer_email]" value="' . esc_attr( (string) $s['reporting_officer_email'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="reporting_period">' . esc_html__( 'Active reporting period', 'eurocomply-cbam' ) . '</label></th><td><input type="text" id="reporting_period" name="eurocomply_cbam[reporting_period]" value="' . esc_attr( (string) $s['reporting_period'] ) . '" pattern="\d{4}-Q[1-4]" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Frontend integration', 'eurocomply-cbam' ) . '</label></th><td>';
		echo '<label><input type="checkbox" name="eurocomply_cbam[enable_product_meta]" value="1"' . checked( ! empty( $s['enable_product_meta'] ), true, false ) . ' /> ' . esc_html__( 'Add CBAM metabox to products.', 'eurocomply-cbam' ) . '</label><br />';
		echo '<label><input type="checkbox" name="eurocomply_cbam[show_emissions_on_frontend]" value="1"' . checked( ! empty( $s['show_emissions_on_frontend'] ), true, false ) . ' /> ' . esc_html__( 'Surface embedded emissions on the product page.', 'eurocomply-cbam' ) . '</label><br />';
		echo '<label><input type="checkbox" name="eurocomply_cbam[use_default_values]" value="1"' . checked( ! empty( $s['use_default_values'] ), true, false ) . ' /> ' . esc_html__( 'Fall back to Annex IV defaults when product data is missing.', 'eurocomply-cbam' ) . '</label></td></tr>';
		echo '<tr><th><label for="default_emissions_factor">' . esc_html__( 'Override default tCO₂e/unit (0 = use Annex IV)', 'eurocomply-cbam' ) . '</label></th><td><input type="number" step="0.0001" id="default_emissions_factor" name="eurocomply_cbam[default_emissions_factor]" value="' . esc_attr( (string) $s['default_emissions_factor'] ) . '" /></td></tr>';
		echo '<tr><th><label for="currency">' . esc_html__( 'Currency', 'eurocomply-cbam' ) . '</label></th><td><input type="text" id="currency" name="eurocomply_cbam[currency]" value="' . esc_attr( (string) $s['currency'] ) . '" maxlength="3" /></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-cbam' ) . '</h2>';
		echo '<ul class="ul-disc" style="margin-left:1.4em;">';
		foreach ( array(
			__( 'Full TARIC sync — every CBAM CN-8 sub-heading + automatic monthly updates',  'eurocomply-cbam' ),
			__( 'EU CBAM Registry / Trader Portal API submission (Q-report + annual decl.)',   'eurocomply-cbam' ),
			__( 'Signed PDF Q-report (DPO + customs-ready)',                                   'eurocomply-cbam' ),
			__( 'Supplier portal — installation operators upload verified emissions data',     'eurocomply-cbam' ),
			__( 'WooCommerce import-line auto-create on order paid (manufacturer SKU mapping)','eurocomply-cbam' ),
			__( 'CBAM-certificate price tracker + cost calculator (definitive period 2026+)',  'eurocomply-cbam' ),
			__( 'REST API: /eurocomply/v1/cbam/{imports,reports,verifiers}',                   'eurocomply-cbam' ),
			__( 'Slack / Teams alerts on quarterly deadline (T-7 days)',                       'eurocomply-cbam' ),
			__( 'WPML / Polylang frontend translations',                                       'eurocomply-cbam' ),
			__( '5,000-row CSV export cap',                                                     'eurocomply-cbam' ),
			__( 'Multi-site network aggregator',                                               'eurocomply-cbam' ),
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
		echo '<input type="hidden" name="action" value="eurocomply_cbam_license" />';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-cbam' ) . '</label></th>';
		echo '<td><input type="text" id="license_key" name="license_key" value="' . esc_attr( $key ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-cbam' ) . '</th><td><code>' . esc_html( $status ) . '</code></td></tr>';
		echo '</tbody></table>';
		submit_button( License::is_pro() ? __( 'Deactivate', 'eurocomply-cbam' ) : __( 'Activate', 'eurocomply-cbam' ) );
		echo '</form>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cbam' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST['eurocomply_cbam'] ) && is_array( $_POST['eurocomply_cbam'] ) ? wp_unslash( (array) $_POST['eurocomply_cbam'] ) : array();
		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );
		add_settings_error( 'eurocomply_cbam', 'saved', __( 'Saved.', 'eurocomply-cbam' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cbam' ), 403 );
		}
		check_admin_referer( self::NONCE_LICENSE );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		if ( License::is_pro() ) {
			License::deactivate();
			add_settings_error( 'eurocomply_cbam', 'lic-off', __( 'License deactivated.', 'eurocomply-cbam' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_cbam', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_import() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cbam' ), 403 );
		}
		check_admin_referer( self::NONCE_IMPORT );
		$op  = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
		if ( 'create' === $op ) {
			$id = ImportStore::create( $row );
			add_settings_error( 'eurocomply_cbam', 'imp-ok', sprintf( /* translators: %d: id */ __( 'Import #%d added.', 'eurocomply-cbam' ), $id ), 'updated' );
			$persisted = $id > 0 ? ImportStore::get( $id ) : null;
			/**
			 * Fired after a CBAM import row is recorded. Sister
			 * plugins (e.g. EuroComply CSRD/ESRS #19) listen on
			 * this to refresh aggregated sustainability datapoints.
			 *
			 * @param int                 $import_id Import row id.
			 * @param array<string,mixed> $row       Persisted import row.
			 */
			do_action( 'eurocomply_cbam_import_recorded', $id, is_array( $persisted ) ? $persisted : array() );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'imports', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_verifier() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cbam' ), 403 );
		}
		check_admin_referer( self::NONCE_VERIFIER );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['ver_id'] ) ? (int) $_POST['ver_id'] : 0;
			if ( $id > 0 ) {
				VerifierStore::delete( $id );
				add_settings_error( 'eurocomply_cbam', 'ver-del', __( 'Verifier deleted.', 'eurocomply-cbam' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['ver'] ) && is_array( $_POST['ver'] ) ? wp_unslash( (array) $_POST['ver'] ) : array();
			VerifierStore::create( $row );
			add_settings_error( 'eurocomply_cbam', 'ver-ok', __( 'Verifier added.', 'eurocomply-cbam' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'verifiers', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_report() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-cbam' ), 403 );
		}
		check_admin_referer( self::NONCE_REPORT );
		$period = isset( $_POST['period'] ) ? strtoupper( preg_replace( '/[^0-9Q-]/', '', (string) wp_unslash( $_POST['period'] ) ) ) : Settings::current_period();
		if ( ! preg_match( '/^\d{4}-Q[1-4]$/', $period ) ) {
			$period = Settings::current_period();
		}
		$res = ReportBuilder::build( $period );
		ReportStore::create(
			array(
				'period'         => $period,
				'imports_count'  => (int) $res['imports_count'],
				'total_quantity' => (float) $res['total_quantity'],
				'total_direct'   => (float) $res['total_direct'],
				'total_indirect' => (float) $res['total_indirect'],
				'xml_envelope'   => (string) $res['xml'],
			)
		);
		add_settings_error(
			'eurocomply_cbam',
			'rep-ok',
			sprintf(
				/* translators: 1: period, 2: imports count */
				esc_html__( 'Q-report built for %1$s with %2$d imports.', 'eurocomply-cbam' ),
				$period,
				(int) $res['imports_count']
			),
			'updated'
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'reports', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
