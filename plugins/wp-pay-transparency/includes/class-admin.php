<?php
/**
 * Admin UI: 9-tab dashboard.
 *
 * @package EuroComply\PayTransparency
 */

declare( strict_types = 1 );

namespace EuroComply\PayTransparency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG  = 'eurocomply-pay-transparency';
	private const NONCE_SAVE     = 'eurocomply_pt_save';
	private const NONCE_LICENSE  = 'eurocomply_pt_license';
	private const NONCE_CATEGORY = 'eurocomply_pt_category';
	private const NONCE_REPORT   = 'eurocomply_pt_report';
	private const NONCE_REQUEST  = 'eurocomply_pt_request';
	private const NONCE_IMPORT   = 'eurocomply_pt_import';

	private static ?Admin $instance = null;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_menu',          array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_eurocomply_pt_save',     array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_pt_license',  array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_pt_category', array( $this, 'handle_category' ) );
		add_action( 'admin_post_eurocomply_pt_report',   array( $this, 'handle_report' ) );
		add_action( 'admin_post_eurocomply_pt_request',  array( $this, 'handle_request' ) );
		add_action( 'admin_post_eurocomply_pt_import',   array( $this, 'handle_import' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'Pay Transparency', 'eurocomply-pay-transparency' ),
			__( 'Pay Transparency', 'eurocomply-pay-transparency' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-money-alt',
			79
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-pt-admin',
			EUROCOMPLY_PT_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_PT_VERSION
		);
	}

	public function render() : void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard';
		echo '<div class="wrap eurocomply-pt-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply Pay Transparency', 'eurocomply-pay-transparency' ) . '</h1>';
		settings_errors();
		$this->tabs( $tab );
		switch ( $tab ) {
			case 'jobads':     $this->render_jobads();     break;
			case 'categories': $this->render_categories(); break;
			case 'paydata':    $this->render_paydata();    break;
			case 'reports':    $this->render_reports();    break;
			case 'requests':   $this->render_requests();   break;
			case 'settings':   $this->render_settings();   break;
			case 'pro':        $this->render_pro();        break;
			case 'license':    $this->render_license();    break;
			case 'dashboard':
			default:           $this->render_dashboard();  break;
		}
		echo '</div>';
	}

	private function tabs( string $current ) : void {
		$tabs = array(
			'dashboard'  => __( 'Dashboard',  'eurocomply-pay-transparency' ),
			'jobads'     => __( 'Job ads',    'eurocomply-pay-transparency' ),
			'categories' => __( 'Categories', 'eurocomply-pay-transparency' ),
			'paydata'    => __( 'Pay data',   'eurocomply-pay-transparency' ),
			'reports'    => __( 'Reports',    'eurocomply-pay-transparency' ),
			'requests'   => __( 'Requests',   'eurocomply-pay-transparency' ),
			'settings'   => __( 'Settings',   'eurocomply-pay-transparency' ),
			'pro'        => __( 'Pro',        'eurocomply-pay-transparency' ),
			'license'    => __( 'License',    'eurocomply-pay-transparency' ),
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
		$cls = 'eurocomply-pt-card' . ( '' !== $tone ? ' eurocomply-pt-card-' . $tone : '' );
		echo '<div class="' . esc_attr( $cls ) . '">';
		echo '<div class="eurocomply-pt-card-value">' . esc_html( $value ) . '</div>';
		echo '<div class="eurocomply-pt-card-label">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	private function render_dashboard() : void {
		$s          = Settings::get();
		$obligation = Settings::reporting_obligation();
		$cat_n      = CategoryStore::count_total();
		$emp_n      = EmployeeStore::count_for_year( (int) $s['reporting_year'] );
		$req_n      = RequestStore::count_total();
		$open_n     = RequestStore::count_open();
		$overdue_n  = RequestStore::count_overdue( (int) $s['request_response_days'] );
		$rep_n      = ReportStore::count_total();

		echo '<div class="eurocomply-pt-cards">';
		$this->card( __( 'Reporting year',           'eurocomply-pay-transparency' ), (string) $s['reporting_year'] );
		$this->card( __( 'Employees declared',       'eurocomply-pay-transparency' ), (string) $s['employees_total'], $s['employees_total'] > 0 ? 'ok' : 'warn' );
		$this->card( __( 'Categories',               'eurocomply-pay-transparency' ), (string) $cat_n, $cat_n > 0 ? 'ok' : 'warn' );
		$this->card( __( 'Employees this year',      'eurocomply-pay-transparency' ), (string) $emp_n );
		$this->card( __( 'Requests open',            'eurocomply-pay-transparency' ), (string) $open_n, $open_n > 0 ? 'warn' : 'ok' );
		$this->card( __( 'Requests overdue',         'eurocomply-pay-transparency' ), (string) $overdue_n, $overdue_n > 0 ? 'crit' : 'ok' );
		$this->card( __( 'Requests total',           'eurocomply-pay-transparency' ), (string) $req_n );
		$this->card( __( 'Reports stored',           'eurocomply-pay-transparency' ), (string) $rep_n );
		echo '</div>';

		echo '<div class="eurocomply-pt-info">';
		echo '<p><strong>' . esc_html__( 'Reporting obligation', 'eurocomply-pay-transparency' ) . ':</strong> ';
		echo '<span class="eurocomply-pt-pill ' . ( $obligation['required'] ? 'crit' : 'ok' ) . '">' . esc_html( $obligation['frequency'] ) . '</span> — ';
		echo esc_html( $obligation['note'] );
		echo '</p>';
		echo '</div>';
	}

	private function render_jobads() : void {
		$s = Settings::get();
		$types = (array) $s['job_post_types'];
		echo '<p>' . esc_html__( 'Posts whose post-type matches the list below get an Art. 5 pay-range badge automatically. Set the range in the post sidebar metabox.', 'eurocomply-pay-transparency' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Active post types:', 'eurocomply-pay-transparency' ) . '</strong> <code>' . esc_html( implode( ', ', $types ) ) . '</code></p>';
		echo '<p>' . esc_html__( 'Recent posts of these types:', 'eurocomply-pay-transparency' ) . '</p>';
		$posts = get_posts(
			array(
				'post_type'   => $types,
				'numberposts' => 30,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			)
		);
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th>';
		echo '<th>' . esc_html__( 'Title',    'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Type',     'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Range',    'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Period',   'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Category', 'eurocomply-pay-transparency' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $posts ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No posts found.', 'eurocomply-pay-transparency' ) . '</td></tr>';
		}
		foreach ( $posts as $p ) {
			$min  = (float) get_post_meta( $p->ID, '_eurocomply_pt_pay_min', true );
			$max  = (float) get_post_meta( $p->ID, '_eurocomply_pt_pay_max', true );
			$cur  = (string) get_post_meta( $p->ID, '_eurocomply_pt_pay_currency', true );
			$per  = (string) get_post_meta( $p->ID, '_eurocomply_pt_pay_period', true );
			$cat  = (string) get_post_meta( $p->ID, '_eurocomply_pt_category', true );
			$range = $min > 0 && $max > 0 ? number_format( $min, 2 ) . '–' . number_format( $max, 2 ) . ' ' . $cur : __( 'not set', 'eurocomply-pay-transparency' );
			echo '<tr>';
			echo '<td>' . esc_html( (string) $p->ID ) . '</td>';
			echo '<td><a href="' . esc_url( (string) get_edit_post_link( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></td>';
			echo '<td>' . esc_html( (string) $p->post_type ) . '</td>';
			echo '<td>' . esc_html( $range ) . '</td>';
			echo '<td>' . esc_html( $per ) . '</td>';
			echo '<td>' . esc_html( $cat ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_categories() : void {
		$rows  = CategoryStore::all();
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Slug', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Skills', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Effort', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Responsibility', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Conditions', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Pay range', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No categories yet.', 'eurocomply-pay-transparency' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( (string) $r['slug'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['skills_level'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['effort_level'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['responsibility_level'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['working_conditions_level'] ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) $r['pay_min'], 2 ) . '–' . number_format( (float) $r['pay_max'], 2 ) ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Delete this category?', 'eurocomply-pay-transparency' ) ) . '\');">';
			echo '<input type="hidden" name="action"  value="eurocomply_pt_category" />';
			echo '<input type="hidden" name="op"      value="delete" />';
			echo '<input type="hidden" name="cat_id"  value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( self::NONCE_CATEGORY );
			submit_button( __( 'Delete', 'eurocomply-pay-transparency' ), 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add / update category', 'eurocomply-pay-transparency' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_pt_category" />';
		echo '<input type="hidden" name="op" value="upsert" />';
		wp_nonce_field( self::NONCE_CATEGORY );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label>' . esc_html__( 'Slug', 'eurocomply-pay-transparency' ) . '</label></th><td><input type="text" name="cat[slug]" required class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Name', 'eurocomply-pay-transparency' ) . '</label></th><td><input type="text" name="cat[name]" required class="regular-text" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Skills (0–10)', 'eurocomply-pay-transparency' ) . '</label></th><td><input type="number" name="cat[skills_level]" min="0" max="10" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Effort (0–10)', 'eurocomply-pay-transparency' ) . '</label></th><td><input type="number" name="cat[effort_level]" min="0" max="10" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Responsibility (0–10)', 'eurocomply-pay-transparency' ) . '</label></th><td><input type="number" name="cat[responsibility_level]" min="0" max="10" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Conditions (0–10)', 'eurocomply-pay-transparency' ) . '</label></th><td><input type="number" name="cat[working_conditions_level]" min="0" max="10" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Pay min', 'eurocomply-pay-transparency' ) . '</label></th><td><input type="number" step="0.01" name="cat[pay_min]" min="0" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Pay max', 'eurocomply-pay-transparency' ) . '</label></th><td><input type="number" step="0.01" name="cat[pay_max]" min="0" /></td></tr>';
		echo '<tr><th><label>' . esc_html__( 'Description', 'eurocomply-pay-transparency' ) . '</label></th><td><textarea name="cat[description]" rows="3" class="large-text"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Save category', 'eurocomply-pay-transparency' ) );
		echo '</form>';
	}

	private function render_paydata() : void {
		$s     = Settings::get();
		$year  = (int) $s['reporting_year'];
		$count = EmployeeStore::count_for_year( $year );
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<p>' . sprintf(
			/* translators: 1: count, 2: year */
			esc_html__( 'Year %2$d: %1$d employee records on file. Pay data is stored as HMAC-keyed external_ref + (category, gender, total_comp, hours_per_week). No names, emails or national IDs are retained.', 'eurocomply-pay-transparency' ),
			(int) $count,
			(int) $year
		) . '</p>';

		echo '<h2>' . esc_html__( 'Import employees CSV', 'eurocomply-pay-transparency' ) . '</h2>';
		echo '<p>' . esc_html__( 'Required columns: external_ref, category_slug, gender (w/m/x/u), total_comp, hours_per_week. Optional: currency.', 'eurocomply-pay-transparency' ) . '</p>';
		echo '<form method="post" action="' . $action . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="eurocomply_pt_import" />';
		echo '<input type="hidden" name="year"   value="' . esc_attr( (string) $year ) . '" />';
		wp_nonce_field( self::NONCE_IMPORT );
		echo '<input type="file" name="csv" accept=".csv,text/csv" required /> ';
		submit_button( __( 'Import', 'eurocomply-pay-transparency' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Export employees CSV', 'eurocomply-pay-transparency' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action"  value="eurocomply_pt_export" />';
		echo '<input type="hidden" name="dataset" value="employees" />';
		wp_nonce_field( 'eurocomply_pt_export' );
		submit_button( __( 'Export', 'eurocomply-pay-transparency' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	private function render_reports() : void {
		$s    = Settings::get();
		$year = (int) $s['reporting_year'];
		$emp  = EmployeeStore::count_for_year( $year );
		$rows = ReportStore::recent( 30 );
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<h2>' . sprintf(
			/* translators: %d: reporting year */
			esc_html__( 'Generate report — %d', 'eurocomply-pay-transparency' ),
			(int) $year
		) . '</h2>';
		echo '<p>' . sprintf(
			/* translators: %d: number of employee records on file */
			esc_html__( 'Computes mean and median gender pay gap overall and per category from the %d employee records on file for this year. Stores a snapshot you can export.', 'eurocomply-pay-transparency' ),
			(int) $emp
		) . '</p>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_pt_report" />';
		echo '<input type="hidden" name="op"     value="run" />';
		echo '<input type="hidden" name="year"   value="' . esc_attr( (string) $year ) . '" />';
		wp_nonce_field( self::NONCE_REPORT );
		submit_button( __( 'Run report', 'eurocomply-pay-transparency' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Stored snapshots', 'eurocomply-pay-transparency' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th>';
		echo '<th>' . esc_html__( 'Generated', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Year',      'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Employees', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Mean gap %',   'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Median gap %', 'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Joint assessment?', 'eurocomply-pay-transparency' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No reports yet.', 'eurocomply-pay-transparency' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['created_at'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['year'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['employees_count'] ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) $r['gap_overall_pct'], 2 ) ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) $r['gap_overall_median_pct'], 2 ) ) . '</td>';
			echo '<td>' . ( (int) $r['joint_assessment_required'] === 1 ? '<span class="eurocomply-pt-pill crit">' . esc_html__( 'Required', 'eurocomply-pay-transparency' ) . '</span>' : '<span class="eurocomply-pt-pill ok">' . esc_html__( 'No', 'eurocomply-pay-transparency' ) . '</span>' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<form method="post" action="' . $action . '" style="margin-top:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_pt_export" />';
		echo '<input type="hidden" name="dataset" value="reports" />';
		wp_nonce_field( 'eurocomply_pt_export' );
		submit_button( __( 'Export reports CSV', 'eurocomply-pay-transparency' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	private function render_requests() : void {
		$s   = Settings::get();
		$rows = RequestStore::recent( 100 );
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_pt_export" />';
		echo '<input type="hidden" name="dataset" value="requests" />';
		wp_nonce_field( 'eurocomply_pt_export' );
		submit_button( __( 'Export requests CSV', 'eurocomply-pay-transparency' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th>';
		echo '<th>' . esc_html__( 'Created',       'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Email',         'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Scope',         'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Category',      'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Status',        'eurocomply-pay-transparency' ) . '</th>';
		echo '<th>' . esc_html__( 'Action',        'eurocomply-pay-transparency' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No requests yet.', 'eurocomply-pay-transparency' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$days_old = max( 0, (int) floor( ( time() - strtotime( (string) $r['created_at'] ) ) / DAY_IN_SECONDS ) );
			$overdue  = ( $days_old > (int) $s['request_response_days'] ) && ! in_array( (string) $r['status'], array( 'responded', 'rejected' ), true );
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['created_at'] ) . ' (' . esc_html( (string) $days_old ) . 'd)' . '</td>';
			echo '<td>' . esc_html( (string) $r['contact_email'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['scope'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['category_slug'] ) . '</td>';
			echo '<td>';
			echo '<span class="eurocomply-pt-pill ' . esc_attr( $overdue ? 'crit' : 'ok' ) . '">' . esc_html( (string) $r['status'] ) . '</span>';
			if ( $overdue ) {
				echo ' <span class="eurocomply-pt-pill crit">' . esc_html__( 'Overdue', 'eurocomply-pay-transparency' ) . '</span>';
			}
			echo '</td>';
			echo '<td>';
			if ( ! in_array( (string) $r['status'], array( 'responded', 'rejected' ), true ) ) {
				echo '<form method="post" action="' . $action . '" style="display:inline">';
				echo '<input type="hidden" name="action"  value="eurocomply_pt_request" />';
				echo '<input type="hidden" name="op"      value="respond" />';
				echo '<input type="hidden" name="req_id"  value="' . esc_attr( (string) $r['id'] ) . '" />';
				wp_nonce_field( self::NONCE_REQUEST );
				submit_button( __( 'Mark responded', 'eurocomply-pay-transparency' ), 'secondary small', 'submit', false );
				echo '</form>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_pt_save" />';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th><label for="organisation_name">' . esc_html__( 'Organisation name', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="text" id="organisation_name" name="eurocomply_pt[organisation_name]" value="' . esc_attr( (string) $s['organisation_name'] ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="organisation_country">' . esc_html__( 'Country (ISO 2)', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="text" id="organisation_country" name="eurocomply_pt[organisation_country]" value="' . esc_attr( (string) $s['organisation_country'] ) . '" maxlength="2" /></td></tr>';

		echo '<tr><th><label for="employees_total">' . esc_html__( 'Employees (total, headcount)', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="number" id="employees_total" name="eurocomply_pt[employees_total]" value="' . esc_attr( (string) $s['employees_total'] ) . '" min="0" /></td></tr>';

		echo '<tr><th><label for="currency">' . esc_html__( 'Currency (ISO 4217)', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="text" id="currency" name="eurocomply_pt[currency]" value="' . esc_attr( (string) $s['currency'] ) . '" maxlength="3" /></td></tr>';

		echo '<tr><th><label for="reporting_year">' . esc_html__( 'Reporting year', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="number" id="reporting_year" name="eurocomply_pt[reporting_year]" value="' . esc_attr( (string) $s['reporting_year'] ) . '" min="2020" max="2099" /></td></tr>';

		echo '<tr><th><label>' . esc_html__( 'Job-ad filter', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><label><input type="checkbox" name="eurocomply_pt[enable_job_ad_filter]" value="1"' . checked( ! empty( $s['enable_job_ad_filter'] ), true, false ) . ' /> ' . esc_html__( 'Inject Art. 5 pay-range badge into job posts.', 'eurocomply-pay-transparency' ) . '</label><br />';
		echo '<label><input type="checkbox" name="eurocomply_pt[pay_range_required]" value="1"' . checked( ! empty( $s['pay_range_required'] ), true, false ) . ' /> ' . esc_html__( 'Show editor warning on job posts missing a range.', 'eurocomply-pay-transparency' ) . '</label></td></tr>';

		echo '<tr><th><label for="job_post_types">' . esc_html__( 'Job post types', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="text" id="job_post_types" name="eurocomply_pt[job_post_types]" value="' . esc_attr( implode( ', ', (array) $s['job_post_types'] ) ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Comma-separated post-type slugs (e.g. post, job_position, gigs).', 'eurocomply-pay-transparency' ) . '</p></td></tr>';

		echo '<tr><th><label for="pay_setting_criteria">' . esc_html__( 'Pay-setting criteria (Art. 6)', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><textarea id="pay_setting_criteria" name="eurocomply_pt[pay_setting_criteria]" rows="6" class="large-text">' . esc_textarea( (string) $s['pay_setting_criteria'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Rendered by [eurocomply_pay_setting_criteria]. Plain HTML allowed.', 'eurocomply-pay-transparency' ) . '</p></td></tr>';

		echo '<tr><th><label for="progression_criteria">' . esc_html__( 'Progression criteria (Art. 6)', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><textarea id="progression_criteria" name="eurocomply_pt[progression_criteria]" rows="6" class="large-text">' . esc_textarea( (string) $s['progression_criteria'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Rendered by [eurocomply_pay_progression]. Plain HTML allowed.', 'eurocomply-pay-transparency' ) . '</p></td></tr>';

		echo '<tr><th><label for="request_response_days">' . esc_html__( 'Response window (days)', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="number" id="request_response_days" name="eurocomply_pt[request_response_days]" value="' . esc_attr( (string) $s['request_response_days'] ) . '" min="1" max="120" />';
		echo '<p class="description">' . esc_html__( 'Art. 7(1): 2 months max (default 60 days).', 'eurocomply-pay-transparency' ) . '</p></td></tr>';

		echo '<tr><th><label for="rate_limit_per_hour">' . esc_html__( 'Rate limit per hour', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="number" id="rate_limit_per_hour" name="eurocomply_pt[rate_limit_per_hour]" value="' . esc_attr( (string) $s['rate_limit_per_hour'] ) . '" min="1" max="100" /></td></tr>';

		echo '<tr><th><label for="joint_assessment_threshold">' . esc_html__( 'Joint-assessment threshold (%)', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="number" step="0.1" id="joint_assessment_threshold" name="eurocomply_pt[joint_assessment_threshold]" value="' . esc_attr( (string) $s['joint_assessment_threshold'] ) . '" min="0" max="100" />';
		echo '<p class="description">' . esc_html__( 'Art. 10: any category gap above this triggers a joint pay assessment (default 5%).', 'eurocomply-pay-transparency' ) . '</p></td></tr>';

		echo '<tr><th><label for="compliance_email">' . esc_html__( 'Compliance email', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="email" id="compliance_email" name="eurocomply_pt[compliance_email]" value="' . esc_attr( (string) $s['compliance_email'] ) . '" class="regular-text" /></td></tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-pay-transparency' ) . '</h2>';
		echo '<ul class="ul-disc" style="margin-left:1.4em;">';
		foreach ( array(
			__( 'Payroll integrations: DATEV / SAP SuccessFactors / Personio / BambooHR / HiBob / Workday connectors',  'eurocomply-pay-transparency' ),
			__( 'Eurostat NACE Rev.2 classifier auto-suggesting category groupings',                                    'eurocomply-pay-transparency' ),
			__( 'Signed PDF Art. 9 report (DPO-ready, includes joint-assessment workflow)',                             'eurocomply-pay-transparency' ),
			__( 'REST API: /eurocomply/v1/pay/{requests,reports,categories}',                                            'eurocomply-pay-transparency' ),
			__( 'Slack / Teams alerts on every new Art. 7 request',                                                     'eurocomply-pay-transparency' ),
			__( 'Joint pay assessment workflow with worker-rep approval (Art. 10)',                                     'eurocomply-pay-transparency' ),
			__( 'WPML / Polylang multi-language pay-range translations',                                                'eurocomply-pay-transparency' ),
			__( 'Schema.org JobPosting structured data with `baseSalary` (Google Jobs)',                                'eurocomply-pay-transparency' ),
			__( '5,000-row CSV export cap',                                                                              'eurocomply-pay-transparency' ),
			__( 'Multi-site network aggregator',                                                                        'eurocomply-pay-transparency' ),
			__( 'EU monitoring-body submission helper (national portal pre-fill)',                                       'eurocomply-pay-transparency' ),
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
		echo '<input type="hidden" name="action" value="eurocomply_pt_license" />';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-pay-transparency' ) . '</label></th>';
		echo '<td><input type="text" id="license_key" name="license_key" value="' . esc_attr( $key ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-pay-transparency' ) . '</th><td><code>' . esc_html( $status ) . '</code></td></tr>';
		echo '</tbody></table>';
		submit_button( License::is_pro() ? __( 'Deactivate', 'eurocomply-pay-transparency' ) : __( 'Activate', 'eurocomply-pay-transparency' ) );
		echo '</form>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-pay-transparency' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST['eurocomply_pt'] ) && is_array( $_POST['eurocomply_pt'] ) ? wp_unslash( (array) $_POST['eurocomply_pt'] ) : array();
		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );
		add_settings_error( 'eurocomply_pt', 'saved', __( 'Saved.', 'eurocomply-pay-transparency' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-pay-transparency' ), 403 );
		}
		check_admin_referer( self::NONCE_LICENSE );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		if ( License::is_pro() ) {
			License::deactivate();
			add_settings_error( 'eurocomply_pt', 'lic-off', __( 'License deactivated.', 'eurocomply-pay-transparency' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_pt', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_category() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-pay-transparency' ), 403 );
		}
		check_admin_referer( self::NONCE_CATEGORY );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'upsert';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['cat_id'] ) ? (int) $_POST['cat_id'] : 0;
			if ( $id > 0 ) {
				CategoryStore::delete( $id );
				add_settings_error( 'eurocomply_pt', 'cat-del', __( 'Category deleted.', 'eurocomply-pay-transparency' ), 'updated' );
			}
		} else {
			$cat = isset( $_POST['cat'] ) && is_array( $_POST['cat'] ) ? wp_unslash( (array) $_POST['cat'] ) : array();
			$id  = CategoryStore::upsert( $cat );
			if ( $id > 0 ) {
				add_settings_error( 'eurocomply_pt', 'cat-saved', __( 'Category saved.', 'eurocomply-pay-transparency' ), 'updated' );
			} else {
				add_settings_error( 'eurocomply_pt', 'cat-fail', __( 'Could not save category (slug missing?).', 'eurocomply-pay-transparency' ), 'error' );
			}
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'categories', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_report() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-pay-transparency' ), 403 );
		}
		check_admin_referer( self::NONCE_REPORT );
		$year = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) Settings::get()['reporting_year'];
		$res  = GapCalculator::run( $year );
		ReportStore::create(
			array(
				'year'                       => $year,
				'gap_overall_pct'            => (float) $res['gap_overall_pct'],
				'gap_overall_median_pct'     => (float) $res['gap_overall_median_pct'],
				'employees_count'            => (int) $res['employees_count'],
				'joint_assessment_required'  => $res['joint_assessment_required'] ? 1 : 0,
				'payload'                    => $res['payload'],
			)
		);
		add_settings_error(
			'eurocomply_pt',
			'rep-run',
			sprintf(
				/* translators: 1: count, 2: mean gap, 3: median gap */
				esc_html__( 'Report generated: %1$d employees, mean gap %2$.2f%%, median gap %3$.2f%%.', 'eurocomply-pay-transparency' ),
				(int) $res['employees_count'],
				(float) $res['gap_overall_pct'],
				(float) $res['gap_overall_median_pct']
			),
			'updated'
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'reports', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_request() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-pay-transparency' ), 403 );
		}
		check_admin_referer( self::NONCE_REQUEST );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : '';
		$id = isset( $_POST['req_id'] ) ? (int) $_POST['req_id'] : 0;
		if ( 'respond' === $op && $id > 0 ) {
			RequestStore::update(
				$id,
				array(
					'status'        => 'responded',
					'responded_at'  => current_time( 'mysql' ),
				)
			);
			add_settings_error( 'eurocomply_pt', 'req', __( 'Request marked responded.', 'eurocomply-pay-transparency' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'requests', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_import() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-pay-transparency' ), 403 );
		}
		check_admin_referer( self::NONCE_IMPORT );
		$year = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) Settings::get()['reporting_year'];
		if ( empty( $_FILES['csv'] ) || empty( $_FILES['csv']['tmp_name'] ) ) {
			add_settings_error( 'eurocomply_pt', 'imp-fail', __( 'No file uploaded.', 'eurocomply-pay-transparency' ), 'error' );
		} else {
			$path = (string) $_FILES['csv']['tmp_name'];
			$res  = CsvImport::import_employees( $path, $year );
			if ( $res['ok'] ) {
				add_settings_error(
					'eurocomply_pt',
					'imp-ok',
					sprintf(
						/* translators: %d: rows */
						esc_html__( 'Imported %d employee records.', 'eurocomply-pay-transparency' ),
						(int) $res['inserted']
					),
					'updated'
				);
			} else {
				add_settings_error( 'eurocomply_pt', 'imp-fail', esc_html( implode( ' | ', $res['errors'] ) ), 'error' );
			}
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'paydata', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
