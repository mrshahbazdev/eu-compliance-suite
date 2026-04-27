<?php
/**
 * Admin UI.
 *
 * @package EuroComply\EUDR
 */

declare( strict_types = 1 );

namespace EuroComply\EUDR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG       = 'eurocomply-eudr';
	private const NONCE_SAVE     = 'eurocomply_eudr_save';
	private const NONCE_LICENSE  = 'eurocomply_eudr_license';
	private const NONCE_SUPPLIER = 'eurocomply_eudr_supplier';
	private const NONCE_PLOT     = 'eurocomply_eudr_plot';
	private const NONCE_SHIPMENT = 'eurocomply_eudr_shipment';
	private const NONCE_RISK     = 'eurocomply_eudr_risk';
	private const NONCE_RISK_OV  = 'eurocomply_eudr_risk_override';

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
		add_action( 'admin_post_eurocomply_eudr_save',          array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_eudr_license',       array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_eudr_supplier',      array( $this, 'handle_supplier' ) );
		add_action( 'admin_post_eurocomply_eudr_plot',          array( $this, 'handle_plot' ) );
		add_action( 'admin_post_eurocomply_eudr_shipment',      array( $this, 'handle_shipment' ) );
		add_action( 'admin_post_eurocomply_eudr_risk',          array( $this, 'handle_risk' ) );
		add_action( 'admin_post_eurocomply_eudr_risk_override', array( $this, 'handle_risk_override' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'EUDR', 'eurocomply-eudr' ),
			__( 'EUDR', 'eurocomply-eudr' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-palmtree',
			83
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-eudr-admin',
			EUROCOMPLY_EUDR_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_EUDR_VERSION
		);
	}

	public function render() : void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard';
		echo '<div class="wrap eurocomply-eudr-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply EUDR', 'eurocomply-eudr' ) . '</h1>';
		settings_errors();
		$this->tabs( $tab );
		switch ( $tab ) {
			case 'commodities': $this->render_commodities(); break;
			case 'suppliers':   $this->render_suppliers();   break;
			case 'plots':       $this->render_plots();       break;
			case 'shipments':   $this->render_shipments();   break;
			case 'risk':        $this->render_risk();        break;
			case 'countries':   $this->render_countries();   break;
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
			'dashboard'   => __( 'Dashboard',   'eurocomply-eudr' ),
			'commodities' => __( 'Commodities', 'eurocomply-eudr' ),
			'suppliers'   => __( 'Suppliers',   'eurocomply-eudr' ),
			'plots'       => __( 'Plots',       'eurocomply-eudr' ),
			'shipments'   => __( 'Shipments',   'eurocomply-eudr' ),
			'risk'        => __( 'Risk',        'eurocomply-eudr' ),
			'countries'   => __( 'Countries',   'eurocomply-eudr' ),
			'settings'    => __( 'Settings',    'eurocomply-eudr' ),
			'pro'         => __( 'Pro',         'eurocomply-eudr' ),
			'license'     => __( 'License',     'eurocomply-eudr' ),
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
		$cls = 'eurocomply-eudr-card-stat' . ( '' !== $tone ? ' eurocomply-eudr-card-' . $tone : '' );
		echo '<div class="' . esc_attr( $cls ) . '">';
		echo '<div class="val">' . esc_html( $value ) . '</div>';
		echo '<div class="lbl">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	private function render_dashboard() : void {
		$s         = Settings::get();
		$suppliers = SupplierStore::count_total();
		$plots     = PlotStore::count_total();
		$failed    = PlotStore::count_failed();
		$total     = ShipmentStore::count_total();
		$drafts    = ShipmentStore::count_by_status( 'draft' );
		$submitted = ShipmentStore::count_by_status( 'submitted' );
		$accepted  = ShipmentStore::count_by_status( 'accepted' );
		$rejected  = ShipmentStore::count_by_status( 'rejected' );
		$high      = ShipmentStore::count_high_risk();
		$nn        = RiskStore::count_non_negligible();

		echo '<div class="eurocomply-eudr-cards">';
		$this->card( __( 'Reporting year',  'eurocomply-eudr' ), (string) $s['reporting_year'] );
		$this->card( __( 'Operator role',   'eurocomply-eudr' ), (string) $s['operator_role'] );
		$this->card( __( 'Suppliers',       'eurocomply-eudr' ), (string) $suppliers );
		$this->card( __( 'Plots tracked',   'eurocomply-eudr' ), (string) $plots );
		$this->card( __( 'Failed defor. checks', 'eurocomply-eudr' ), (string) $failed, 0 === $failed ? 'ok' : 'crit' );
		$this->card( __( 'Shipments',       'eurocomply-eudr' ), (string) $total );
		$this->card( __( 'Draft DDS',       'eurocomply-eudr' ), (string) $drafts, 0 === $drafts ? 'ok' : 'warn' );
		$this->card( __( 'Submitted',       'eurocomply-eudr' ), (string) $submitted, 'ok' );
		$this->card( __( 'Accepted',        'eurocomply-eudr' ), (string) $accepted, 'ok' );
		$this->card( __( 'Rejected',        'eurocomply-eudr' ), (string) $rejected, 0 === $rejected ? '' : 'crit' );
		$this->card( __( 'High-risk shipments', 'eurocomply-eudr' ), (string) $high, 0 === $high ? 'ok' : 'warn' );
		$this->card( __( 'Non-negligible risks', 'eurocomply-eudr' ), (string) $nn, 0 === $nn ? 'ok' : 'crit' );
		echo '</div>';

		echo '<div class="eurocomply-eudr-info">';
		echo '<p>' . esc_html__( 'EuroComply EUDR is a compliance tracking layer for Reg. (EU) 2023/1115. The plugin builds a TRACES NT-style XML envelope for every shipment, but does NOT submit on your behalf in the free tier — Pro adds live TRACES NT submission, satellite-imagery deforestation checks against the 31 Dec 2020 cut-off, and signed PDF DDS export.', 'eurocomply-eudr' ) . '</p>';
		echo '</div>';
	}

	private function render_commodities() : void {
		echo '<h2>' . esc_html__( 'In-scope commodities (Annex I)', 'eurocomply-eudr' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Code',        'eurocomply-eudr' ) . '</th>';
		echo '<th>' . esc_html__( 'Name',        'eurocomply-eudr' ) . '</th>';
		echo '<th>' . esc_html__( 'HS chapters', 'eurocomply-eudr' ) . '</th>';
		echo '<th>' . esc_html__( 'Examples',    'eurocomply-eudr' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( CommodityRegistry::commodities() as $code => $info ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $code ) . '</code></td>';
			echo '<td>' . esc_html( (string) $info['name'] ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $info['hs'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $info['examples'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_suppliers() : void {
		$rows   = SupplierStore::all();
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_eudr_export" />';
		echo '<input type="hidden" name="dataset" value="suppliers" />';
		wp_nonce_field( 'eurocomply_eudr_export' );
		submit_button( __( 'Export suppliers CSV', 'eurocomply-eudr' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Name', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Country', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Role', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Tax ID', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Email', 'eurocomply-eudr' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No suppliers yet.', 'eurocomply-eudr' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['role'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $r['tax_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['contact_email'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Delete supplier?', 'eurocomply-eudr' ) ) . '\');">';
			echo '<input type="hidden" name="action" value="eurocomply_eudr_supplier" />';
			echo '<input type="hidden" name="op"     value="delete" />';
			echo '<input type="hidden" name="sid"    value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( self::NONCE_SUPPLIER );
			submit_button( __( 'Delete', 'eurocomply-eudr' ), 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add supplier', 'eurocomply-eudr' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eudr_supplier" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_SUPPLIER );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Name', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[name]" class="regular-text" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Country (ISO-2)', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[country]" maxlength="2" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Role', 'eurocomply-eudr' ) . '</th><td><select name="row[role]"><option value="producer">producer</option><option value="trader">trader</option><option value="cooperative">cooperative</option><option value="aggregator">aggregator</option><option value="broker">broker</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Address', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[address]" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Tax ID', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[tax_id]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Contact email', 'eurocomply-eudr' ) . '</th><td><input type="email" name="row[contact_email]" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add supplier', 'eurocomply-eudr' ) );
		echo '</form>';
	}

	private function render_plots() : void {
		$rows      = PlotStore::all();
		$suppliers = SupplierStore::all();
		$action    = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_eudr_export" />';
		echo '<input type="hidden" name="dataset" value="plots" />';
		wp_nonce_field( 'eurocomply_eudr_export' );
		submit_button( __( 'Export plots CSV', 'eurocomply-eudr' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Supplier', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Country', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Label', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Geometry', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Area (ha)', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Defor. check', 'eurocomply-eudr' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No plots yet.', 'eurocomply-eudr' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$pill = 'pass' === $r['deforestation_check']
				? array( 'ok', 'pass' )
				: ( 'fail' === $r['deforestation_check']
					? array( 'crit', 'fail' )
					: ( 'inconclusive' === $r['deforestation_check'] ? array( 'warn', 'inconclusive' ) : array( 'warn', 'pending' ) ) );
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['supplier_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['label'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['geom_type'] === 'polygon' ? 'polygon' : 'point ' . (string) $r['lat'] . ',' . (string) $r['lng'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['area_ha'] ) . '</td>';
			echo '<td><span class="eurocomply-eudr-pill ' . esc_attr( $pill[0] ) . '">' . esc_html( $pill[1] ) . '</span></td>';
			echo '<td>';
			foreach ( array( 'pass', 'fail', 'inconclusive' ) as $st ) {
				echo '<form method="post" action="' . $action . '" style="display:inline">';
				echo '<input type="hidden" name="action" value="eurocomply_eudr_plot" />';
				echo '<input type="hidden" name="op"     value="check" />';
				echo '<input type="hidden" name="pid"    value="' . esc_attr( (string) $r['id'] ) . '" />';
				echo '<input type="hidden" name="status" value="' . esc_attr( $st ) . '" />';
				wp_nonce_field( self::NONCE_PLOT );
				submit_button( ucfirst( $st ), 'small', 'submit', false );
				echo '</form> ';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add plot', 'eurocomply-eudr' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eudr_plot" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_PLOT );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Supplier', 'eurocomply-eudr' ) . '</th><td><select name="row[supplier_id]" required><option value="">—</option>';
		foreach ( $suppliers as $sup ) {
			echo '<option value="' . esc_attr( (string) $sup['id'] ) . '">' . esc_html( (string) $sup['name'] . ' (' . $sup['country'] . ')' ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Country (ISO-2)', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[country]" maxlength="2" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Label', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[label]" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Geometry type', 'eurocomply-eudr' ) . '</th><td><select name="row[geom_type]"><option value="point">point</option><option value="polygon">polygon</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Latitude', 'eurocomply-eudr' ) . '</th><td><input type="number" step="0.0000001" name="row[lat]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Longitude', 'eurocomply-eudr' ) . '</th><td><input type="number" step="0.0000001" name="row[lng]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Polygon (GeoJSON)', 'eurocomply-eudr' ) . '</th><td><textarea name="row[polygon]" rows="4" class="large-text" placeholder=\'{"type":"Polygon","coordinates":[[[lng,lat],...]]}\'></textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Area (ha)', 'eurocomply-eudr' ) . '</th><td><input type="number" step="0.0001" name="row[area_ha]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Production from', 'eurocomply-eudr' ) . '</th><td><input type="date" name="row[production_from]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Production to',   'eurocomply-eudr' ) . '</th><td><input type="date" name="row[production_to]" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add plot', 'eurocomply-eudr' ) );
		echo '</form>';
	}

	private function render_shipments() : void {
		$rows      = ShipmentStore::all( 100 );
		$suppliers = SupplierStore::all();
		$plots     = PlotStore::all();
		$action    = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_eudr_export" />';
		echo '<input type="hidden" name="dataset" value="shipments" />';
		wp_nonce_field( 'eurocomply_eudr_export' );
		submit_button( __( 'Export shipments CSV', 'eurocomply-eudr' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Year', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Commodity', 'eurocomply-eudr' ) . '</th><th>HS</th><th>' . esc_html__( 'Qty', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Country', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Risk', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'DDS', 'eurocomply-eudr' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No shipments yet.', 'eurocomply-eudr' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$risk_pill = ! empty( $r['risk_level'] ) ? array(
				'low'      => 'ok',
				'standard' => 'warn',
				'high'     => 'crit',
			)[ (string) $r['risk_level'] ] ?? '' : '';
			$status_pill = array(
				'draft'     => 'warn',
				'submitted' => 'ok',
				'accepted'  => 'ok',
				'rejected'  => 'crit',
			)[ (string) $r['dds_status'] ] ?? '';
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['year'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['commodity'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['hs_code'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['quantity'] . ' ' . $r['unit'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country_origin'] ) . '</td>';
			echo '<td>' . ( '' !== $risk_pill ? '<span class="eurocomply-eudr-pill ' . esc_attr( $risk_pill ) . '">' . esc_html( (string) $r['risk_level'] ) . '</span>' : '—' ) . '</td>';
			echo '<td><span class="eurocomply-eudr-pill ' . esc_attr( $status_pill ) . '">' . esc_html( (string) $r['dds_status'] ) . '</span> ' . esc_html( (string) $r['dds_reference'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline">';
			echo '<input type="hidden" name="action" value="eurocomply_eudr_export_xml" />';
			echo '<input type="hidden" name="shipment_id" value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( 'eurocomply_eudr_export' );
			submit_button( __( 'XML', 'eurocomply-eudr' ), 'small', 'submit', false );
			echo '</form> ';
			echo '<form method="post" action="' . $action . '" style="display:inline">';
			echo '<input type="hidden" name="action" value="eurocomply_eudr_export_json" />';
			echo '<input type="hidden" name="shipment_id" value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( 'eurocomply_eudr_export' );
			submit_button( __( 'JSON', 'eurocomply-eudr' ), 'small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add shipment', 'eurocomply-eudr' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eudr_shipment" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_SHIPMENT );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Commodity', 'eurocomply-eudr' ) . '</th><td><select name="row[commodity]" required>';
		foreach ( CommodityRegistry::options() as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'HS code', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[hs_code]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Description', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[description]" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Quantity', 'eurocomply-eudr' ) . '</th><td><input type="number" step="0.0001" name="row[quantity]" /> <input type="text" name="row[unit]" maxlength="8" value="kg" style="width:60px" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Year', 'eurocomply-eudr' ) . '</th><td><input type="number" name="row[year]" min="2024" max="2099" value="' . esc_attr( (string) gmdate( 'Y' ) ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Supplier', 'eurocomply-eudr' ) . '</th><td><select name="row[supplier_id]"><option value="">—</option>';
		foreach ( $suppliers as $sup ) {
			echo '<option value="' . esc_attr( (string) $sup['id'] ) . '">' . esc_html( (string) $sup['name'] . ' (' . $sup['country'] . ')' ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Country of origin (ISO-2)', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[country_origin]" maxlength="2" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Plot IDs (comma-separated)', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[plot_ids]" placeholder="1,2,3" /> <small>' . esc_html__( 'Available:', 'eurocomply-eudr' ) . ' ';
		foreach ( array_slice( $plots, 0, 20 ) as $p ) {
			echo esc_html( '#' . $p['id'] . ' ' . ( $p['label'] ?: $p['country'] ) ) . ', ';
		}
		echo '</small></td></tr>';
		echo '<tr><th>' . esc_html__( 'Upstream DDS reference', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[upstream_dds]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'DDS reference (TRACES)', 'eurocomply-eudr' ) . '</th><td><input type="text" name="row[dds_reference]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'DDS status', 'eurocomply-eudr' ) . '</th><td><select name="row[dds_status]"><option value="draft">draft</option><option value="submitted">submitted</option><option value="accepted">accepted</option><option value="rejected">rejected</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Risk level (override)', 'eurocomply-eudr' ) . '</th><td><select name="row[risk_level]"><option value="">' . esc_html__( '(auto)', 'eurocomply-eudr' ) . '</option><option value="low">low</option><option value="standard">standard</option><option value="high">high</option></select></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add shipment', 'eurocomply-eudr' ) );
		echo '</form>';
	}

	private function render_risk() : void {
		$rows      = RiskStore::recent( 100 );
		$shipments = ShipmentStore::all( 200 );
		$action    = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_eudr_export" />';
		echo '<input type="hidden" name="dataset" value="risk" />';
		wp_nonce_field( 'eurocomply_eudr_export' );
		submit_button( __( 'Export risk CSV', 'eurocomply-eudr' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Shipment', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Factor', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Level', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Conclusion', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Finding', 'eurocomply-eudr' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No risk assessments yet.', 'eurocomply-eudr' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$pill_lvl = array(
				'low'      => 'ok',
				'standard' => 'warn',
				'high'     => 'crit',
			)[ (string) $r['level'] ] ?? '';
			$pill_con = 'non_negligible' === $r['conclusion'] ? 'crit' : ( 'negligible' === $r['conclusion'] ? 'ok' : '' );
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['shipment_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['factor'] ) . '</td>';
			echo '<td><span class="eurocomply-eudr-pill ' . esc_attr( $pill_lvl ) . '">' . esc_html( (string) $r['level'] ) . '</span></td>';
			echo '<td><span class="eurocomply-eudr-pill ' . esc_attr( $pill_con ) . '">' . esc_html( (string) $r['conclusion'] ) . '</span></td>';
			echo '<td>' . esc_html( (string) $r['finding'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Record risk assessment', 'eurocomply-eudr' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eudr_risk" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_RISK );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Shipment', 'eurocomply-eudr' ) . '</th><td><select name="row[shipment_id]" required><option value="">—</option>';
		foreach ( $shipments as $shp ) {
			echo '<option value="' . esc_attr( (string) $shp['id'] ) . '">#' . esc_html( (string) $shp['id'] . ' ' . $shp['commodity'] . ' / ' . $shp['country_origin'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Factor', 'eurocomply-eudr' ) . '</th><td><select name="row[factor]"><option value="country_risk">country_risk</option><option value="indigenous_rights">indigenous_rights</option><option value="land_tenure">land_tenure</option><option value="legality">legality</option><option value="supply_chain_complexity">supply_chain_complexity</option><option value="corruption">corruption</option><option value="conflict">conflict</option><option value="deforestation_recent">deforestation_recent</option><option value="general">general</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Level', 'eurocomply-eudr' ) . '</th><td><select name="row[level]"><option value="low">low</option><option value="standard" selected>standard</option><option value="high">high</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Conclusion', 'eurocomply-eudr' ) . '</th><td><select name="row[conclusion]"><option value="">—</option><option value="negligible">negligible</option><option value="non_negligible">non_negligible</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Finding', 'eurocomply-eudr' ) . '</th><td><textarea name="row[finding]" rows="3" class="large-text"></textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Mitigation', 'eurocomply-eudr' ) . '</th><td><textarea name="row[mitigation]" rows="3" class="large-text"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record assessment', 'eurocomply-eudr' ) );
		echo '</form>';
	}

	private function render_countries() : void {
		$action    = esc_url( admin_url( 'admin-post.php' ) );
		$overrides = CountryRisk::overrides();

		echo '<h2>' . esc_html__( 'Country risk overrides', 'eurocomply-eudr' ) . '</h2>';
		echo '<p>' . esc_html__( 'The Commission publishes the official country-risk list per Art. 29 implementing acts. Until Pro syncs that list, override resolved levels per country here.', 'eurocomply-eudr' ) . '</p>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Country', 'eurocomply-eudr' ) . '</th><th>' . esc_html__( 'Override level', 'eurocomply-eudr' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $overrides ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No overrides yet.', 'eurocomply-eudr' ) . '</td></tr>';
		}
		foreach ( $overrides as $cc => $level ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $cc ) . '</td>';
			echo '<td><span class="eurocomply-eudr-pill ' . esc_attr( ( 'high' === $level ? 'crit' : ( 'low' === $level ? 'ok' : 'warn' ) ) ) . '">' . esc_html( (string) $level ) . '</span></td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline">';
			echo '<input type="hidden" name="action"  value="eurocomply_eudr_risk_override" />';
			echo '<input type="hidden" name="op"      value="clear" />';
			echo '<input type="hidden" name="country" value="' . esc_attr( (string) $cc ) . '" />';
			wp_nonce_field( self::NONCE_RISK_OV );
			submit_button( __( 'Clear', 'eurocomply-eudr' ), 'small', 'submit', false );
			echo '</form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add override', 'eurocomply-eudr' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eudr_risk_override" />';
		echo '<input type="hidden" name="op"     value="set" />';
		wp_nonce_field( self::NONCE_RISK_OV );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Country (ISO-2)', 'eurocomply-eudr' ) . '</th><td><input type="text" name="country" maxlength="2" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Level', 'eurocomply-eudr' ) . '</th><td><select name="level"><option value="low">low</option><option value="standard">standard</option><option value="high">high</option></select></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Set override', 'eurocomply-eudr' ) );
		echo '</form>';
	}

	private function render_settings() : void {
		$s      = Settings::get();
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_eudr_save" />';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="operator_name">' . esc_html__( 'Operator name', 'eurocomply-eudr' ) . '</label></th><td><input type="text" id="operator_name" name="eurocomply_eudr[operator_name]" value="' . esc_attr( (string) $s['operator_name'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="operator_eori">' . esc_html__( 'EORI', 'eurocomply-eudr' ) . '</label></th><td><input type="text" id="operator_eori" name="eurocomply_eudr[operator_eori]" value="' . esc_attr( (string) $s['operator_eori'] ) . '" /></td></tr>';
		echo '<tr><th><label for="operator_country">' . esc_html__( 'Country (ISO-2)', 'eurocomply-eudr' ) . '</label></th><td><input type="text" id="operator_country" name="eurocomply_eudr[operator_country]" value="' . esc_attr( (string) $s['operator_country'] ) . '" maxlength="2" /></td></tr>';
		echo '<tr><th><label for="operator_address">' . esc_html__( 'Address', 'eurocomply-eudr' ) . '</label></th><td><textarea id="operator_address" name="eurocomply_eudr[operator_address]" rows="3" class="large-text">' . esc_textarea( (string) $s['operator_address'] ) . '</textarea></td></tr>';
		echo '<tr><th><label for="operator_role">' . esc_html__( 'Role', 'eurocomply-eudr' ) . '</label></th><td><select id="operator_role" name="eurocomply_eudr[operator_role]">';
		foreach ( array(
			'operator'     => __( 'Operator',           'eurocomply-eudr' ),
			'trader'       => __( 'Trader',             'eurocomply-eudr' ),
			'sme_operator' => __( 'SME operator',       'eurocomply-eudr' ),
			'sme_trader'   => __( 'SME trader',         'eurocomply-eudr' ),
		) as $val => $lab ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( (string) $s['operator_role'], $val, false ) . '>' . esc_html( $lab ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="compliance_officer">' . esc_html__( 'Compliance officer email', 'eurocomply-eudr' ) . '</label></th><td><input type="email" id="compliance_officer" name="eurocomply_eudr[compliance_officer]" value="' . esc_attr( (string) $s['compliance_officer'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="cutoff_date">' . esc_html__( 'Deforestation cut-off date (Art. 2(13))', 'eurocomply-eudr' ) . '</label></th><td><input type="date" id="cutoff_date" name="eurocomply_eudr[cutoff_date]" value="' . esc_attr( (string) $s['cutoff_date'] ) . '" /></td></tr>';
		echo '<tr><th><label for="reporting_year">' . esc_html__( 'Reporting year', 'eurocomply-eudr' ) . '</label></th><td><input type="number" id="reporting_year" name="eurocomply_eudr[reporting_year]" min="2024" max="2099" value="' . esc_attr( (string) $s['reporting_year'] ) . '" /></td></tr>';
		echo '<tr><th><label for="default_country_risk">' . esc_html__( 'Default country risk', 'eurocomply-eudr' ) . '</label></th><td><select id="default_country_risk" name="eurocomply_eudr[default_country_risk]"><option value="low"' . selected( (string) $s['default_country_risk'], 'low', false ) . '>low</option><option value="standard"' . selected( (string) $s['default_country_risk'], 'standard', false ) . '>standard</option><option value="high"' . selected( (string) $s['default_country_risk'], 'high', false ) . '>high</option></select></td></tr>';
		echo '<tr><th><label for="currency">' . esc_html__( 'Currency', 'eurocomply-eudr' ) . '</label></th><td><input type="text" id="currency" name="eurocomply_eudr[currency]" value="' . esc_attr( (string) $s['currency'] ) . '" maxlength="3" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Attach EUDR meta to WC products', 'eurocomply-eudr' ) . '</th><td><input type="checkbox" name="eurocomply_eudr[enable_woo_meta]" value="1"' . checked( ! empty( $s['enable_woo_meta'] ), true, false ) . ' /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Capture geolocation on plot creation', 'eurocomply-eudr' ) . '</th><td><input type="checkbox" name="eurocomply_eudr[enable_geo_capture]" value="1"' . checked( ! empty( $s['enable_geo_capture'] ), true, false ) . ' /></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-eudr' ) . '</h2>';
		echo '<ul class="ul-disc" style="margin-left:1.4em;">';
		foreach ( array(
			__( 'Live TRACES NT submission (operator role)',                       'eurocomply-eudr' ),
			__( 'Satellite-imagery deforestation check vs. 31 Dec 2020 cut-off',    'eurocomply-eudr' ),
			__( 'Commission country-risk-list sync (Art. 29)',                       'eurocomply-eudr' ),
			__( 'Signed PDF Due Diligence Statement',                                 'eurocomply-eudr' ),
			__( 'WooCommerce per-product EUDR meta (HS code + supplier + plot link)', 'eurocomply-eudr' ),
			__( 'Polygon ingest (KML / Shapefile / GeoJSON FeatureCollection)',         'eurocomply-eudr' ),
			__( 'Map view (OpenLayers) with deforestation overlay',                       'eurocomply-eudr' ),
			__( 'Supplier portal (third-party uploads geolocation + tax docs)',             'eurocomply-eudr' ),
			__( 'REST API: /eurocomply/v1/eudr/{shipments,plots,suppliers}',                 'eurocomply-eudr' ),
			__( 'Slack / Teams alerts on rejected DDS or non-negligible risk',                 'eurocomply-eudr' ),
			__( 'WPML / Polylang supplier directory translations',                              'eurocomply-eudr' ),
			__( 'Multi-site network aggregator (group consolidation)',                            'eurocomply-eudr' ),
			__( '5,000-row CSV export cap',                                                         'eurocomply-eudr' ),
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
		echo '<input type="hidden" name="action" value="eurocomply_eudr_license" />';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-eudr' ) . '</label></th>';
		echo '<td><input type="text" id="license_key" name="license_key" value="' . esc_attr( $key ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-eudr' ) . '</th><td><code>' . esc_html( $status ) . '</code></td></tr>';
		echo '</tbody></table>';
		submit_button( License::is_pro() ? __( 'Deactivate', 'eurocomply-eudr' ) : __( 'Activate', 'eurocomply-eudr' ) );
		echo '</form>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST['eurocomply_eudr'] ) && is_array( $_POST['eurocomply_eudr'] ) ? wp_unslash( (array) $_POST['eurocomply_eudr'] ) : array();
		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );
		add_settings_error( 'eurocomply_eudr', 'saved', __( 'Saved.', 'eurocomply-eudr' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE_LICENSE );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		if ( License::is_pro() ) {
			License::deactivate();
			add_settings_error( 'eurocomply_eudr', 'lic-off', __( 'License deactivated.', 'eurocomply-eudr' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_eudr', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_supplier() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE_SUPPLIER );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['sid'] ) ? (int) $_POST['sid'] : 0;
			if ( $id > 0 ) {
				SupplierStore::delete( $id );
				add_settings_error( 'eurocomply_eudr', 'sup-del', __( 'Supplier deleted.', 'eurocomply-eudr' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			SupplierStore::create( $row );
			add_settings_error( 'eurocomply_eudr', 'sup-ok', __( 'Supplier added.', 'eurocomply-eudr' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'suppliers', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_plot() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE_PLOT );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'check' === $op ) {
			$id     = isset( $_POST['pid'] )    ? (int) $_POST['pid']    : 0;
			$status = isset( $_POST['status'] ) ? sanitize_key( (string) $_POST['status'] ) : 'pending';
			if ( $id > 0 ) {
				PlotStore::set_check( $id, $status );
				add_settings_error( 'eurocomply_eudr', 'plot-st', __( 'Plot check updated.', 'eurocomply-eudr' ), 'updated' );
			}
		} elseif ( 'delete' === $op ) {
			$id = isset( $_POST['pid'] ) ? (int) $_POST['pid'] : 0;
			if ( $id > 0 ) {
				PlotStore::delete( $id );
				add_settings_error( 'eurocomply_eudr', 'plot-del', __( 'Plot deleted.', 'eurocomply-eudr' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			PlotStore::create( $row );
			add_settings_error( 'eurocomply_eudr', 'plot-ok', __( 'Plot added.', 'eurocomply-eudr' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'plots', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_shipment() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE_SHIPMENT );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['sh_id'] ) ? (int) $_POST['sh_id'] : 0;
			if ( $id > 0 ) {
				ShipmentStore::delete( $id );
				add_settings_error( 'eurocomply_eudr', 'sh-del', __( 'Shipment deleted.', 'eurocomply-eudr' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			$id  = ShipmentStore::create( $row );
			add_settings_error( 'eurocomply_eudr', 'sh-ok', sprintf( /* translators: %d: id */ __( 'Shipment #%d added.', 'eurocomply-eudr' ), $id ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'shipments', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_risk() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE_RISK );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['r_id'] ) ? (int) $_POST['r_id'] : 0;
			if ( $id > 0 ) {
				RiskStore::delete( $id );
				add_settings_error( 'eurocomply_eudr', 'r-del', __( 'Risk record deleted.', 'eurocomply-eudr' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			RiskStore::create( $row );
			add_settings_error( 'eurocomply_eudr', 'r-ok', __( 'Risk assessment recorded.', 'eurocomply-eudr' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'risk', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_risk_override() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-eudr' ), 403 );
		}
		check_admin_referer( self::NONCE_RISK_OV );
		$op      = isset( $_POST['op'] )      ? sanitize_key( (string) $_POST['op'] )       : 'set';
		$country = isset( $_POST['country'] ) ? sanitize_text_field( (string) $_POST['country'] ) : '';
		$level   = isset( $_POST['level'] )   ? sanitize_key( (string) $_POST['level'] )    : 'standard';
		if ( 'clear' === $op ) {
			CountryRisk::clear_override( $country );
			add_settings_error( 'eurocomply_eudr', 'co-clr', __( 'Override cleared.', 'eurocomply-eudr' ), 'updated' );
		} else {
			CountryRisk::set_override( $country, $level );
			add_settings_error( 'eurocomply_eudr', 'co-set', __( 'Override saved.', 'eurocomply-eudr' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'countries', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
