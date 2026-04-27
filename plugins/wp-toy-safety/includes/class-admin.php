<?php
/**
 * Admin UI.
 *
 * @package EuroComply\ToySafety
 */

declare( strict_types = 1 );

namespace EuroComply\ToySafety;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG     = 'eurocomply-toy-safety';
	public const NONCE_SAVE    = 'eurocomply_toy_save';
	public const NONCE_LICENSE = 'eurocomply_toy_license';
	public const NONCE_TOY     = 'eurocomply_toy_toy';
	public const NONCE_SUB     = 'eurocomply_toy_sub';
	public const NONCE_ASS     = 'eurocomply_toy_ass';
	public const NONCE_INC     = 'eurocomply_toy_inc';
	public const NONCE_OP      = 'eurocomply_toy_op';
	public const NONCE_STEP    = 'eurocomply_toy_step';

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
		foreach ( array(
			'save'    => 'handle_save',
			'license' => 'handle_license',
			'toy'     => 'handle_toy',
			'sub'     => 'handle_substance',
			'ass'     => 'handle_assessment',
			'inc'     => 'handle_incident',
			'op'      => 'handle_operator',
			'step'    => 'handle_step',
		) as $a => $cb ) {
			add_action( 'admin_post_eurocomply_toy_' . $a, array( $this, $cb ) );
		}
	}

	public function menu() : void {
		add_menu_page(
			__( 'Toy Safety', 'eurocomply-toy-safety' ),
			__( 'Toy Safety', 'eurocomply-toy-safety' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-shield',
			87
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'eurocomply-toy-admin', EUROCOMPLY_TOY_URL . 'assets/css/admin.css', array(), EUROCOMPLY_TOY_VERSION );
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap eurocomply-toy">';
		echo '<h1>' . esc_html__( 'EuroComply Toy Safety', 'eurocomply-toy-safety' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Toy Safety Regulation (revising Directive 2009/48/EC) — toy register, restricted substances, conformity assessment, Digital Product Passport, RAPEX/Safety Gate.', 'eurocomply-toy-safety' ) . '</p>';
		settings_errors( 'eurocomply_toy' );

		$tabs = array(
			'dashboard'   => __( 'Dashboard',   'eurocomply-toy-safety' ),
			'toys'        => __( 'Toys',         'eurocomply-toy-safety' ),
			'substances'  => __( 'Substances',     'eurocomply-toy-safety' ),
			'conformity'  => __( 'Conformity',     'eurocomply-toy-safety' ),
			'incidents'   => __( 'Incidents',       'eurocomply-toy-safety' ),
			'operators'   => __( 'Operators',         'eurocomply-toy-safety' ),
			'dpp'         => __( 'DPP',                'eurocomply-toy-safety' ),
			'settings'    => __( 'Settings',           'eurocomply-toy-safety' ),
			'pro'         => __( 'Pro',                'eurocomply-toy-safety' ),
			'license'     => __( 'License',             'eurocomply-toy-safety' ),
		);

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$active = ( $tab === $key ) ? ' nav-tab-active' : '';
			$url    = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $key ), admin_url( 'admin.php' ) );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'toys':       $this->tab_toys();       break;
			case 'substances': $this->tab_substances(); break;
			case 'conformity': $this->tab_conformity(); break;
			case 'incidents':  $this->tab_incidents();  break;
			case 'operators':  $this->tab_operators();  break;
			case 'dpp':        $this->tab_dpp();        break;
			case 'settings':   $this->tab_settings();   break;
			case 'pro':        $this->tab_pro();        break;
			case 'license':    $this->tab_license();    break;
			default:           $this->tab_dashboard();
		}
		echo '</div>';
	}

	private function tab_dashboard() : void {
		$s = Settings::get();
		echo '<div class="eurocomply-toy-grid">';
		$this->card( __( 'Entity', 'eurocomply-toy-safety' ), esc_html( (string) $s['entity_name'] ), Settings::role_label( (string) $s['role'] ) );
		$this->card( __( 'Toys on register', 'eurocomply-toy-safety' ), (string) ToyStore::count_total() );
		$this->card( __( 'Toys for ≤ 36 months', 'eurocomply-toy-safety' ), (string) ToyStore::count_under_36(), __( 'Higher-risk safety regime', 'eurocomply-toy-safety' ), 'warn' );

		$no_ce = ToyStore::count_no_ce();
		$this->card( __( 'On market without CE', 'eurocomply-toy-safety' ), (string) $no_ce, $no_ce > 0 ? __( 'Art. 4 breach risk', 'eurocomply-toy-safety' ) : __( 'OK', 'eurocomply-toy-safety' ), $no_ce > 0 ? 'crit' : 'ok' );

		$sub_fail = SubstanceStore::count_failures();
		$this->card( __( 'Substance failures', 'eurocomply-toy-safety' ), (string) $sub_fail, $sub_fail > 0 ? __( 'Annex II non-conformity', 'eurocomply-toy-safety' ) : __( 'No failures', 'eurocomply-toy-safety' ), $sub_fail > 0 ? 'crit' : 'ok' );

		$exp = AssessmentStore::count_expired();
		$this->card( __( 'Expired certificates', 'eurocomply-toy-safety' ), (string) $exp, $exp > 0 ? __( 'Renewal required', 'eurocomply-toy-safety' ) : __( 'None', 'eurocomply-toy-safety' ), $exp > 0 ? 'warn' : 'ok' );

		$this->card( __( 'Open incidents', 'eurocomply-toy-safety' ), (string) IncidentStore::count_open(), sprintf( /* translators: %d: total */ __( '%d total recorded', 'eurocomply-toy-safety' ), IncidentStore::count_total() ) );

		$nover = IncidentStore::notify_overdue( (int) $s['incident_initial_h'] );
		$this->card( __( 'Safety Gate overdue', 'eurocomply-toy-safety' ), (string) $nover, $nover > 0 ? sprintf( /* translators: %d: hours */ __( '> %d h since detection', 'eurocomply-toy-safety' ), (int) $s['incident_initial_h'] ) : __( 'On schedule', 'eurocomply-toy-safety' ), $nover > 0 ? 'crit' : 'ok' );

		$fover = IncidentStore::followup_overdue( (int) $s['incident_followup_d'] );
		$this->card( __( 'Follow-up overdue', 'eurocomply-toy-safety' ), (string) $fover, $fover > 0 ? sprintf( /* translators: %d: days */ __( '> %d days', 'eurocomply-toy-safety' ), (int) $s['incident_followup_d'] ) : __( 'On schedule', 'eurocomply-toy-safety' ), $fover > 0 ? 'warn' : 'ok' );

		$this->card( __( 'Operators on file', 'eurocomply-toy-safety' ), (string) OperatorStore::count_total() );
		echo '</div>';
	}

	private function card( string $label, string $value, string $sub = '', string $tone = 'plain' ) : void {
		echo '<div class="eurocomply-card eurocomply-tone-' . esc_attr( $tone ) . '">';
		echo '<div class="eurocomply-card-label">' . esc_html( $label ) . '</div>';
		echo '<div class="eurocomply-card-value">' . esc_html( $value ) . '</div>';
		if ( '' !== $sub ) {
			echo '<div class="eurocomply-card-sub">' . esc_html( $sub ) . '</div>';
		}
		echo '</div>';
	}

	private function tab_toys() : void {
		echo '<h2>' . esc_html__( 'Add toy', 'eurocomply-toy-safety' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_TOY );
		echo '<input type="hidden" name="action" value="eurocomply_toy_toy" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'name',     __( 'Name', 'eurocomply-toy-safety' ),     'text' );
		$this->row( 'model',    __( 'Model', 'eurocomply-toy-safety' ),     'text' );
		$this->row( 'gtin',     __( 'GTIN / EAN', 'eurocomply-toy-safety' ), 'text' );
		$this->row( 'batch',    __( 'Batch / lot',  'eurocomply-toy-safety' ), 'text' );
		$this->select( 'age_range', __( 'Age range', 'eurocomply-toy-safety' ), array_keys( Settings::age_ranges() ), '36-72' );
		$this->checkbox( 'under_36', __( 'Intended for children ≤ 36 months', 'eurocomply-toy-safety' ) );
		$this->row( 'category',       __( 'Category',          'eurocomply-toy-safety' ), 'text' );
		$this->row( 'origin_country', __( 'Country of origin', 'eurocomply-toy-safety' ), 'text' );
		$this->checkbox( 'ce_marked', __( 'CE marked',          'eurocomply-toy-safety' ) );
		$this->row( 'doc_url',   __( 'EU Declaration of Conformity URL', 'eurocomply-toy-safety' ), 'url' );
		$this->row( 'dpp_url',   __( 'External DPP URL (optional)',         'eurocomply-toy-safety' ), 'url' );
		$this->row( 'image_url', __( 'Image URL',                              'eurocomply-toy-safety' ), 'url' );
		$this->select( 'status', __( 'Status', 'eurocomply-toy-safety' ), array( 'draft', 'on_market', 'recalled', 'withdrawn' ), 'on_market' );
		$this->textarea( 'materials', __( 'Materials',           'eurocomply-toy-safety' ) );
		$this->textarea( 'warnings',  __( 'Warnings (Art. 11)',     'eurocomply-toy-safety' ) );
		$this->textarea( 'notes',     __( 'Notes',                    'eurocomply-toy-safety' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add toy', 'eurocomply-toy-safety' ) );
		echo '</form>';

		$rows = ToyStore::all();
		echo '<h2>' . esc_html__( 'Toy register', 'eurocomply-toy-safety' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No toys recorded.', 'eurocomply-toy-safety' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Name</th><th>GTIN</th><th>Age</th><th>≤36m</th><th>CE</th><th>Status</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . ' <span style="color:#646970">' . esc_html( (string) $r['model'] ) . '</span></td>';
			echo '<td><code>' . esc_html( (string) $r['gtin'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $r['age_range'] ) . '</td>';
			echo '<td>' . ( $r['under_36'] ? '⚠' : '' ) . '</td>';
			echo '<td>' . ( $r['ce_marked'] ? '✓' : '✗' ) . '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_substances() : void {
		$toys = ToyStore::all();
		echo '<h2>' . esc_html__( 'Log restricted substance', 'eurocomply-toy-safety' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_SUB );
		echo '<input type="hidden" name="action" value="eurocomply_toy_sub" />';
		echo '<table class="form-table"><tbody>';
		$this->toy_select( $toys );
		$this->row( 'name',      __( 'Substance name', 'eurocomply-toy-safety' ), 'text' );
		$this->row( 'cas',       __( 'CAS no',          'eurocomply-toy-safety' ), 'text' );
		$this->row( 'ec_number', __( 'EC no',            'eurocomply-toy-safety' ), 'text' );
		echo '<tr><th><label for="classification">' . esc_html__( 'Classification', 'eurocomply-toy-safety' ) . '</label></th><td><select name="classification" id="classification">';
		foreach ( SubstanceStore::classifications() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'limit_value',    __( 'Limit value',     'eurocomply-toy-safety' ), 'text' );
		$this->row( 'measured_value', __( 'Measured value',   'eurocomply-toy-safety' ), 'text' );
		$this->select( 'pass_fail', __( 'Pass / fail', 'eurocomply-toy-safety' ), array( 'pass', 'fail', 'na' ), 'pass' );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-toy-safety' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add substance', 'eurocomply-toy-safety' ) );
		echo '</form>';

		$rows = SubstanceStore::all();
		echo '<h2>' . esc_html__( 'Substance register', 'eurocomply-toy-safety' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No substances recorded.', 'eurocomply-toy-safety' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Toy</th><th>Substance</th><th>CAS</th><th>Class</th><th>Limit</th><th>Measured</th><th>Result</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$tone = 'fail' === $r['pass_fail'] ? 'eurocomply-pill-crit' : ( 'pass' === $r['pass_fail'] ? 'eurocomply-pill-ok' : 'eurocomply-pill-warn' );
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['toy_id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['cas'] ) . '</code></td>';
			echo '<td>' . esc_html( SubstanceStore::classification_label( (string) $r['classification'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['limit_value'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['measured_value'] ) . '</td>';
			echo '<td><span class="eurocomply-pill ' . esc_attr( $tone ) . '">' . esc_html( strtoupper( (string) $r['pass_fail'] ) ) . '</span></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_conformity() : void {
		$toys = ToyStore::all();
		echo '<h2>' . esc_html__( 'Add conformity assessment', 'eurocomply-toy-safety' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_ASS );
		echo '<input type="hidden" name="action" value="eurocomply_toy_ass" />';
		echo '<table class="form-table"><tbody>';
		$this->toy_select( $toys );
		echo '<tr><th><label for="module">' . esc_html__( 'Module', 'eurocomply-toy-safety' ) . '</label></th><td><select name="module" id="module">';
		foreach ( Settings::modules() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'notified_body',    __( 'Notified body name', 'eurocomply-toy-safety' ), 'text' );
		$this->row( 'notified_body_id', __( 'NB number',           'eurocomply-toy-safety' ), 'text' );
		$this->row( 'certificate_no',   __( 'Certificate no',       'eurocomply-toy-safety' ), 'text' );
		$this->row( 'issued_at',   __( 'Issued at',   'eurocomply-toy-safety' ), 'date' );
		$this->row( 'valid_until', __( 'Valid until', 'eurocomply-toy-safety' ), 'date' );
		$this->row( 'report_url',  __( 'Test-report URL', 'eurocomply-toy-safety' ), 'url' );
		$this->textarea( 'standards', __( 'Standards (e.g. EN 71-1, EN 71-2, EN 71-3, EN IEC 62115)', 'eurocomply-toy-safety' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add assessment', 'eurocomply-toy-safety' ) );
		echo '</form>';

		$rows = AssessmentStore::all();
		echo '<h2>' . esc_html__( 'Assessment register', 'eurocomply-toy-safety' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No assessments recorded.', 'eurocomply-toy-safety' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Toy</th><th>Module</th><th>Notified body</th><th>Cert no</th><th>Issued</th><th>Valid until</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['toy_id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['module'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['notified_body'] ) . ' <code>' . esc_html( (string) $r['notified_body_id'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $r['certificate_no'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['issued_at']   ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['valid_until'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_incidents() : void {
		$toys = ToyStore::all();
		$s    = Settings::get();
		echo '<h2>' . esc_html__( 'Log Safety Gate incident', 'eurocomply-toy-safety' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_INC );
		echo '<input type="hidden" name="action" value="eurocomply_toy_inc" />';
		echo '<table class="form-table"><tbody>';
		$this->toy_select( $toys );
		$this->row( 'occurred_at', __( 'Occurred at', 'eurocomply-toy-safety' ), 'datetime-local' );
		$this->row( 'detected_at', __( 'Detected at', 'eurocomply-toy-safety' ), 'datetime-local' );
		echo '<tr><th><label for="hazard">' . esc_html__( 'Hazard', 'eurocomply-toy-safety' ) . '</label></th><td><select name="hazard" id="hazard">';
		foreach ( IncidentStore::hazards() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->select( 'severity', __( 'Severity', 'eurocomply-toy-safety' ), array( 'low', 'medium', 'serious', 'fatal' ), 'serious' );
		$this->row( 'country',    __( 'Country',    'eurocomply-toy-safety' ), 'text' );
		$this->row( 'injuries',   __( 'Injuries',   'eurocomply-toy-safety' ), 'number', '0' );
		$this->row( 'fatalities', __( 'Fatalities', 'eurocomply-toy-safety' ), 'number', '0' );
		$this->select( 'status', __( 'Status', 'eurocomply-toy-safety' ), array( 'open', 'investigating', 'resolved', 'recall' ), 'open' );
		$this->textarea( 'summary',           __( 'Summary',           'eurocomply-toy-safety' ) );
		$this->textarea( 'corrective_action', __( 'Corrective action',   'eurocomply-toy-safety' ) );
		echo '</tbody></table>';
		submit_button( __( 'Log incident', 'eurocomply-toy-safety' ) );
		echo '</form>';

		$rows = IncidentStore::all();
		echo '<h2>' . esc_html__( 'Incident register', 'eurocomply-toy-safety' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No incidents.', 'eurocomply-toy-safety' ) . '</p>';
			return;
		}
		$h_init = (int) $s['incident_initial_h'];
		$d_fol  = (int) $s['incident_followup_d'];
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Toy</th><th>Hazard</th><th>Sev</th><th>Country</th><th>Inj/Fat</th><th>Initial</th><th>Follow-up</th><th>Status</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$initial_pill  = '<span class="eurocomply-pill eurocomply-pill-warn">…</span>';
			$followup_pill = '<span class="eurocomply-pill eurocomply-pill-warn">…</span>';
			if ( ! empty( $r['notified_at'] ) ) {
				$initial_pill = '<span class="eurocomply-pill eurocomply-pill-ok">' . esc_html__( 'Sent', 'eurocomply-toy-safety' ) . '</span>';
			} elseif ( ! empty( $r['detected_at'] ) ) {
				$diff_h = ( strtotime( current_time( 'mysql' ) ) - strtotime( (string) $r['detected_at'] ) ) / 3600;
				$initial_pill = $diff_h > $h_init ? '<span class="eurocomply-pill eurocomply-pill-crit">' . sprintf( /* translators: %d: hours */ esc_html__( '%dh OVERDUE', 'eurocomply-toy-safety' ), (int) $diff_h ) . '</span>'
					: '<span class="eurocomply-pill eurocomply-pill-warn">' . sprintf( '%dh / %dh', (int) $diff_h, $h_init ) . '</span>';
			}
			if ( ! empty( $r['followup_at'] ) ) {
				$followup_pill = '<span class="eurocomply-pill eurocomply-pill-ok">' . esc_html__( 'Sent', 'eurocomply-toy-safety' ) . '</span>';
			} elseif ( ! empty( $r['detected_at'] ) ) {
				$diff_d = ( strtotime( current_time( 'mysql' ) ) - strtotime( (string) $r['detected_at'] ) ) / 86400;
				$followup_pill = $diff_d > $d_fol ? '<span class="eurocomply-pill eurocomply-pill-crit">' . sprintf( /* translators: %d: days */ esc_html__( '%dd OVERDUE', 'eurocomply-toy-safety' ), (int) $diff_d ) . '</span>'
					: '<span class="eurocomply-pill eurocomply-pill-warn">' . sprintf( '%dd / %dd', (int) $diff_d, $d_fol ) . '</span>';
			}
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['toy_id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['hazard'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['severity'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td>' . (int) $r['injuries'] . ' / ' . (int) $r['fatalities'] . '</td>';
			echo '<td>' . $initial_pill . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . $followup_pill . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '<td>';
			foreach ( array( 'notified' => __( 'Mark notified', 'eurocomply-toy-safety' ), 'followup' => __( 'Mark follow-up', 'eurocomply-toy-safety' ) ) as $step => $label ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
				wp_nonce_field( self::NONCE_STEP );
				echo '<input type="hidden" name="action" value="eurocomply_toy_step" />';
				echo '<input type="hidden" name="incident_id" value="' . (int) $r['id'] . '" />';
				echo '<input type="hidden" name="step" value="' . esc_attr( $step ) . '" />';
				submit_button( $label, 'small', 'submit', false );
				echo '</form> ';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_operators() : void {
		$toys = ToyStore::all();
		echo '<h2>' . esc_html__( 'Add operator', 'eurocomply-toy-safety' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_OP );
		echo '<input type="hidden" name="action" value="eurocomply_toy_op" />';
		echo '<table class="form-table"><tbody>';
		$this->toy_select( $toys );
		echo '<tr><th><label for="role">' . esc_html__( 'Role', 'eurocomply-toy-safety' ) . '</label></th><td><select name="role" id="role">';
		foreach ( Settings::roles() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'name',    __( 'Name',    'eurocomply-toy-safety' ), 'text' );
		$this->row( 'country', __( 'Country', 'eurocomply-toy-safety' ), 'text' );
		$this->row( 'address', __( 'Address', 'eurocomply-toy-safety' ), 'text' );
		$this->row( 'email',   __( 'Email',   'eurocomply-toy-safety' ), 'email' );
		$this->row( 'eori',    __( 'EORI',    'eurocomply-toy-safety' ), 'text' );
		$this->row( 'vat',     __( 'VAT',     'eurocomply-toy-safety' ), 'text' );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-toy-safety' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add operator', 'eurocomply-toy-safety' ) );
		echo '</form>';

		$rows = OperatorStore::all();
		echo '<h2>' . esc_html__( 'Operator chain', 'eurocomply-toy-safety' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No operators recorded.', 'eurocomply-toy-safety' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Toy</th><th>Role</th><th>Name</th><th>Country</th><th>EORI</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['toy_id'] . '</td>';
			echo '<td>' . esc_html( Settings::role_label( (string) $r['role'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['eori'] ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_dpp() : void {
		$rows = ToyStore::all();
		echo '<h2>' . esc_html__( 'Digital Product Passport', 'eurocomply-toy-safety' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Build the DPP per toy entry — bundles toy, operator chain, conformity assessments, and substance test results into XML / JSON.', 'eurocomply-toy-safety' ) . '</p>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No toys to passport.', 'eurocomply-toy-safety' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Toy</th><th>GTIN</th><th>Build</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['gtin'] ) . '</code></td>';
			echo '<td>';
			foreach ( array( 'eurocomply_toy_dpp_xml' => 'XML', 'eurocomply_toy_dpp_json' => 'JSON' ) as $action => $label ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
				wp_nonce_field( 'eurocomply_toy_export' );
				echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
				echo '<input type="hidden" name="toy_id" value="' . (int) $r['id'] . '" />';
				submit_button( esc_html( $label ), 'small', 'submit', false );
				echo '</form> ';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Bulk CSV', 'eurocomply-toy-safety' ) . '</h2>';
		foreach ( array(
			'toys'        => __( 'Toys',         'eurocomply-toy-safety' ),
			'substances'  => __( 'Substances',     'eurocomply-toy-safety' ),
			'assessments' => __( 'Assessments',     'eurocomply-toy-safety' ),
			'incidents'   => __( 'Incidents',       'eurocomply-toy-safety' ),
			'operators'   => __( 'Operators',         'eurocomply-toy-safety' ),
		) as $ds => $label ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;margin-bottom:8px">';
			wp_nonce_field( 'eurocomply_toy_export' );
			echo '<input type="hidden" name="action"  value="eurocomply_toy_export" />';
			echo '<input type="hidden" name="dataset" value="' . esc_attr( $ds ) . '" />';
			submit_button( sprintf( /* translators: %s: dataset */ __( 'CSV: %s', 'eurocomply-toy-safety' ), $label ), 'secondary', 'submit', false );
			echo '</form>';
		}
	}

	private function tab_settings() : void {
		$s = Settings::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<input type="hidden" name="action" value="eurocomply_toy_save" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'entity_name',    __( 'Entity name',     'eurocomply-toy-safety' ), 'text', (string) $s['entity_name'] );
		$this->row( 'entity_country', __( 'Country',          'eurocomply-toy-safety' ), 'text', (string) $s['entity_country'] );
		echo '<tr><th><label for="role">' . esc_html__( 'Role', 'eurocomply-toy-safety' ) . '</label></th><td><select name="role" id="role">';
		foreach ( Settings::roles() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['role'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'eori', __( 'EORI', 'eurocomply-toy-safety' ), 'text', (string) $s['eori'] );
		$this->row( 'safety_gate_email',  __( 'Safety Gate contact email', 'eurocomply-toy-safety' ), 'email',  (string) $s['safety_gate_email'] );
		$this->row( 'compliance_officer', __( 'Compliance officer email',    'eurocomply-toy-safety' ), 'email',  (string) $s['compliance_officer'] );
		$this->row( 'incident_initial_h',   __( 'Initial-notification window (h)', 'eurocomply-toy-safety' ), 'number', (string) $s['incident_initial_h'] );
		$this->row( 'incident_followup_d',  __( 'Follow-up window (days)',          'eurocomply-toy-safety' ), 'number', (string) $s['incident_followup_d'] );
		$this->row( 'reporting_year',       __( 'Reporting year',                     'eurocomply-toy-safety' ), 'number', (string) $s['reporting_year'] );
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function tab_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-toy-safety' ) . '</h2>';
		echo '<ul style="list-style:disc;padding-left:18px">';
		foreach ( array(
			__( 'Live Safety Gate / RAPEX submission with statutory deadline timer',       'eurocomply-toy-safety' ),
			__( 'Signed PDF EU Declaration of Conformity (XAdES / PAdES)',                  'eurocomply-toy-safety' ),
			__( 'DPP backbone registry sync (ESPR Digital Product Passport)',                'eurocomply-toy-safety' ),
			__( 'GTIN / EAN barcode + DataMatrix / QR generator on each DPP',                  'eurocomply-toy-safety' ),
			__( 'Notified-body NANDO directory cache with automatic expiry alerts',             'eurocomply-toy-safety' ),
			__( 'Annex II Appx. C 55-fragrance allergen scanner against ingredient lists',         'eurocomply-toy-safety' ),
			__( 'WooCommerce product meta — auto-stamp DPP on shop pages',                            'eurocomply-toy-safety' ),
			__( 'EU 2019/1020 fulfilment-service-provider duty checks',                                  'eurocomply-toy-safety' ),
			__( 'Bulk CSV import of toy register + per-row substance limits',                              'eurocomply-toy-safety' ),
			__( 'REST + webhooks for SIEM / PIM forwarding',                                                 'eurocomply-toy-safety' ),
			__( 'Recall-management workflow: customer notification + return-rate tracker',                     'eurocomply-toy-safety' ),
			__( 'WPML / Polylang for multi-language warnings (Art. 11 — sold in user’s national language)',     'eurocomply-toy-safety' ),
			__( 'Multi-site network aggregator',                                                                  'eurocomply-toy-safety' ),
			__( '5,000-row CSV cap',                                                                                'eurocomply-toy-safety' ),
		) as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}

	private function tab_license() : void {
		$d      = License::get();
		$active = License::is_pro();
		echo '<h2>' . esc_html__( 'License', 'eurocomply-toy-safety' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<input type="hidden" name="action" value="eurocomply_toy_license" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-toy-safety' ) . '</label></th><td><input type="text" id="license_key" name="license_key" value="' . esc_attr( (string) ( $d['key'] ?? '' ) ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-toy-safety' ) . '</th><td>' . ( $active ? '<span class="eurocomply-pill eurocomply-pill-ok">' . esc_html__( 'Active', 'eurocomply-toy-safety' ) . '</span>' : '<span class="eurocomply-pill eurocomply-pill-warn">' . esc_html__( 'Inactive', 'eurocomply-toy-safety' ) . '</span>' ) . '</td></tr>';
		echo '</tbody></table>';
		submit_button( $active ? __( 'Deactivate', 'eurocomply-toy-safety' ) : __( 'Activate', 'eurocomply-toy-safety' ), 'primary', $active ? 'deactivate' : 'activate' );
		echo '</form>';
	}

	// --- POST handlers ----------------------------------------------------

	private function redirect_with_notice( string $tab, string $message ) : void {
		add_settings_error( 'eurocomply_toy', 'eurocomply_toy_n', $message, 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $tab, 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 ); }
		check_admin_referer( self::NONCE_SAVE );
		update_option( Settings::OPTION_KEY, Settings::sanitize( wp_unslash( $_POST ) ), false );
		$this->redirect_with_notice( 'settings', __( 'Settings saved.', 'eurocomply-toy-safety' ) );
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 ); }
		check_admin_referer( self::NONCE_LICENSE );
		if ( isset( $_POST['deactivate'] ) ) {
			License::deactivate();
			$msg = __( 'License deactivated.', 'eurocomply-toy-safety' );
		} else {
			$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
			$res = License::activate( $key );
			$msg = $res['message'];
		}
		$this->redirect_with_notice( 'license', $msg );
	}

	public function handle_toy() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 ); }
		check_admin_referer( self::NONCE_TOY );
		ToyStore::create( wp_unslash( $_POST ) );
		$this->redirect_with_notice( 'toys', __( 'Toy recorded.', 'eurocomply-toy-safety' ) );
	}

	public function handle_substance() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 ); }
		check_admin_referer( self::NONCE_SUB );
		SubstanceStore::create( wp_unslash( $_POST ) );
		$this->redirect_with_notice( 'substances', __( 'Substance recorded.', 'eurocomply-toy-safety' ) );
	}

	public function handle_assessment() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 ); }
		check_admin_referer( self::NONCE_ASS );
		AssessmentStore::create( wp_unslash( $_POST ) );
		$this->redirect_with_notice( 'conformity', __( 'Assessment recorded.', 'eurocomply-toy-safety' ) );
	}

	public function handle_incident() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 ); }
		check_admin_referer( self::NONCE_INC );
		IncidentStore::create( wp_unslash( $_POST ) );
		$this->redirect_with_notice( 'incidents', __( 'Incident logged.', 'eurocomply-toy-safety' ) );
	}

	public function handle_operator() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 ); }
		check_admin_referer( self::NONCE_OP );
		OperatorStore::create( wp_unslash( $_POST ) );
		$this->redirect_with_notice( 'operators', __( 'Operator recorded.', 'eurocomply-toy-safety' ) );
	}

	public function handle_step() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-toy-safety' ), 403 ); }
		check_admin_referer( self::NONCE_STEP );
		$id   = isset( $_POST['incident_id'] ) ? (int) $_POST['incident_id'] : 0;
		$step = isset( $_POST['step'] )         ? sanitize_key( (string) $_POST['step'] ) : '';
		IncidentStore::mark_step( $id, $step );
		$this->redirect_with_notice( 'incidents', __( 'Incident step marked.', 'eurocomply-toy-safety' ) );
	}

	// --- helpers ----------------------------------------------------------

	private function toy_select( array $toys ) : void {
		echo '<tr><th><label for="toy_id">' . esc_html__( 'Toy', 'eurocomply-toy-safety' ) . '</label></th><td><select name="toy_id" id="toy_id"><option value="0">' . esc_html__( '— none —', 'eurocomply-toy-safety' ) . '</option>';
		foreach ( $toys as $t ) {
			echo '<option value="' . (int) $t['id'] . '">#' . (int) $t['id'] . ' · ' . esc_html( (string) $t['name'] ) . ' (' . esc_html( (string) $t['gtin'] ) . ')</option>';
		}
		echo '</select></td></tr>';
	}

	private function row( string $name, string $label, string $type = 'text', string $default = '' ) : void {
		echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="regular-text" value="' . esc_attr( $default ) . '" /></td></tr>';
	}

	private function checkbox( string $name, string $label, bool $checked = false ) : void {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . ' /> ' . esc_html__( 'Yes', 'eurocomply-toy-safety' ) . '</label></td></tr>';
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
