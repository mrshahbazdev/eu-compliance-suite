<?php
/**
 * Admin UI.
 *
 * @package EuroComply\ForcedLabour
 */

declare( strict_types = 1 );

namespace EuroComply\ForcedLabour;

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
		add_action( 'admin_post_eurocomply_fl_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_eurocomply_fl_save_supplier', array( $this, 'save_supplier' ) );
		add_action( 'admin_post_eurocomply_fl_delete_supplier', array( $this, 'delete_supplier' ) );
		add_action( 'admin_post_eurocomply_fl_save_risk', array( $this, 'save_risk' ) );
		add_action( 'admin_post_eurocomply_fl_delete_risk', array( $this, 'delete_risk' ) );
		add_action( 'admin_post_eurocomply_fl_save_audit', array( $this, 'save_audit' ) );
		add_action( 'admin_post_eurocomply_fl_delete_audit', array( $this, 'delete_audit' ) );
		add_action( 'admin_post_eurocomply_fl_save_withdrawal', array( $this, 'save_withdrawal' ) );
		add_action( 'admin_post_eurocomply_fl_delete_withdrawal', array( $this, 'delete_withdrawal' ) );
		add_action( 'admin_post_eurocomply_fl_set_submission_status', array( $this, 'set_submission_status' ) );
		add_action( 'admin_post_eurocomply_fl_save_license', array( $this, 'save_license' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'Forced Labour', 'eurocomply-forced-labour' ),
			__( 'Forced Labour', 'eurocomply-forced-labour' ),
			'manage_options',
			'eurocomply-forced-labour',
			array( $this, 'render' ),
			'dashicons-shield-alt',
			59
		);
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'dashboard';
		$tabs = array(
			'dashboard'    => __( 'Dashboard', 'eurocomply-forced-labour' ),
			'suppliers'    => __( 'Suppliers', 'eurocomply-forced-labour' ),
			'risks'        => __( 'Risk register', 'eurocomply-forced-labour' ),
			'audits'       => __( 'Audits', 'eurocomply-forced-labour' ),
			'submissions'  => __( 'Submissions', 'eurocomply-forced-labour' ),
			'withdrawals'  => __( 'Withdrawals', 'eurocomply-forced-labour' ),
			'settings'     => __( 'Settings', 'eurocomply-forced-labour' ),
			'pro'          => __( 'Pro', 'eurocomply-forced-labour' ),
			'license'      => __( 'License', 'eurocomply-forced-labour' ),
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'EuroComply Forced Labour', 'eurocomply-forced-labour' ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = admin_url( 'admin.php?page=eurocomply-forced-labour&tab=' . $slug );
			$class = 'nav-tab' . ( $tab === $slug ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
		if ( ! empty( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'eurocomply-forced-labour' ) . '</p></div>';
		}
		switch ( $tab ) {
			case 'suppliers':
				$this->render_suppliers();
				break;
			case 'risks':
				$this->render_risks();
				break;
			case 'audits':
				$this->render_audits();
				break;
			case 'submissions':
				$this->render_submissions();
				break;
			case 'withdrawals':
				$this->render_withdrawals();
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
			default:
				$this->render_dashboard();
		}
		echo '</div>';
	}

	private function render_dashboard() : void {
		$suppliers   = SupplierStore::count();
		$high_risk   = SupplierStore::high_risk_count();
		$risks       = RiskStore::count();
		$unresolved  = RiskStore::unresolved_count();
		$critical    = RiskStore::critical_count();
		$audits      = AuditStore::count();
		$expired     = AuditStore::expired_count();
		$submissions = SubmissionStore::count();
		$overdue_ack = SubmissionStore::overdue_ack_count( 30 );
		$withdrawals = WithdrawalStore::count();
		$active_with = WithdrawalStore::active_count();

		echo '<h2>' . esc_html__( 'Compliance overview', 'eurocomply-forced-labour' ) . '</h2>';
		echo '<p>' . esc_html__( 'Reg. (EU) 2024/3015 prohibits placing or making available on the EU market — and exporting from it — products made with forced labour. Operators must conduct due-diligence proportionate to their size and the risk of forced labour in their supply chain.', 'eurocomply-forced-labour' ) . '</p>';

		$cards = array(
			array( __( 'Suppliers', 'eurocomply-forced-labour' ), (int) $suppliers ),
			array( __( 'High-risk suppliers', 'eurocomply-forced-labour' ), (int) $high_risk ),
			array( __( 'Risk findings', 'eurocomply-forced-labour' ), (int) $risks ),
			array( __( 'Unresolved findings', 'eurocomply-forced-labour' ), (int) $unresolved ),
			array( __( 'Critical findings', 'eurocomply-forced-labour' ), (int) $critical ),
			array( __( 'Audits / certifications', 'eurocomply-forced-labour' ), (int) $audits ),
			array( __( 'Expired certificates', 'eurocomply-forced-labour' ), (int) $expired ),
			array( __( 'Public submissions', 'eurocomply-forced-labour' ), (int) $submissions ),
			array( __( 'Submissions overdue (30d)', 'eurocomply-forced-labour' ), (int) $overdue_ack ),
			array( __( 'Withdrawal procedures', 'eurocomply-forced-labour' ), (int) $withdrawals ),
			array( __( 'Active withdrawals', 'eurocomply-forced-labour' ), (int) $active_with ),
		);
		echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:12px;">';
		foreach ( $cards as $c ) {
			echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px 18px;min-width:170px;">';
			echo '<div style="font-size:11px;text-transform:uppercase;color:#646970;">' . esc_html( $c[0] ) . '</div>';
			echo '<div style="font-size:24px;font-weight:600;">' . esc_html( (string) $c[1] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';

		echo '<h3 style="margin-top:24px;">' . esc_html__( 'Indicator coverage', 'eurocomply-forced-labour' ) . '</h3>';
		echo '<p>' . esc_html__( 'The risk register uses the 11 ILO indicators of forced labour as the canonical taxonomy.', 'eurocomply-forced-labour' ) . '</p>';
		echo '<ul style="columns:2;-webkit-columns:2;-moz-columns:2;">';
		foreach ( Settings::indicators() as $label ) {
			echo '<li>' . esc_html( $label ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_suppliers() : void {
		$suppliers = SupplierStore::all();
		$sectors   = Settings::high_risk_sectors();
		echo '<h2>' . esc_html__( 'Supplier register', 'eurocomply-forced-labour' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_fl_save_supplier' );
		echo '<input type="hidden" name="action" value="eurocomply_fl_save_supplier" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Name', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="name" required class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'External ref', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="external_ref" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Country (ISO-3166)', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="country" maxlength="8" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Region', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="region" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Sector', 'eurocomply-forced-labour' ) . '</th><td><select name="sector">';
		foreach ( $sectors as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Tier', 'eurocomply-forced-labour' ) . '</th><td><select name="tier">';
		foreach ( array( 'tier_1', 'tier_2', 'tier_3_plus' ) as $t ) {
			echo '<option value="' . esc_attr( $t ) . '">' . esc_html( $t ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Risk score (0–100)', 'eurocomply-forced-labour' ) . '</th><td><input type="number" name="risk_score" min="0" max="100" value="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Last audited', 'eurocomply-forced-labour' ) . '</th><td><input type="date" name="last_audited" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Contact email', 'eurocomply-forced-labour' ) . '</th><td><input type="email" name="contact_email" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Notes', 'eurocomply-forced-labour' ) . '</th><td><textarea name="notes" rows="3" cols="60"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add supplier', 'eurocomply-forced-labour' ) );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Suppliers', 'eurocomply-forced-labour' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>ID</th><th>Name</th><th>Country</th><th>Sector</th><th>Tier</th><th>Risk</th><th>Last audited</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( empty( $suppliers ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No suppliers yet.', 'eurocomply-forced-labour' ) . '</td></tr>';
		} else {
			foreach ( $suppliers as $s ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_fl_delete_supplier&id=' . (int) $s['id'] ), 'eurocomply_fl_delete_supplier_' . (int) $s['id'] );
				echo '<tr>';
				echo '<td>' . (int) $s['id'] . '</td>';
				echo '<td>' . esc_html( (string) $s['name'] ) . '</td>';
				echo '<td>' . esc_html( (string) $s['country'] ) . '</td>';
				echo '<td>' . esc_html( (string) $s['sector'] ) . '</td>';
				echo '<td>' . esc_html( (string) $s['tier'] ) . '</td>';
				echo '<td>' . (int) $s['risk_score'] . '</td>';
				echo '<td>' . esc_html( (string) ( $s['last_audited'] ?? '' ) ) . '</td>';
				echo '<td><a href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete?\')">' . esc_html__( 'Delete', 'eurocomply-forced-labour' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_risks() : void {
		$risks      = RiskStore::all();
		$indicators = Settings::indicators();
		$severity   = Settings::severity_levels();
		$status     = Settings::risk_status();
		$sectors    = Settings::high_risk_sectors();
		echo '<h2>' . esc_html__( 'Risk register', 'eurocomply-forced-labour' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_fl_save_risk' );
		echo '<input type="hidden" name="action" value="eurocomply_fl_save_risk" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Supplier ID', 'eurocomply-forced-labour' ) . '</th><td><input type="number" name="supplier_id" min="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'ILO indicator', 'eurocomply-forced-labour' ) . '</th><td><select name="indicator" required>';
		foreach ( $indicators as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Severity', 'eurocomply-forced-labour' ) . '</th><td><select name="severity">';
		foreach ( $severity as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-forced-labour' ) . '</th><td><select name="status">';
		foreach ( $status as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Country', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="country" maxlength="8" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Sector', 'eurocomply-forced-labour' ) . '</th><td><select name="sector">';
		foreach ( $sectors as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Source', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="source" class="regular-text" placeholder="ILO / Walk Free / SOMO / news…" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Description', 'eurocomply-forced-labour' ) . '</th><td><textarea name="description" rows="3" cols="60"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record finding', 'eurocomply-forced-labour' ) );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Findings', 'eurocomply-forced-labour' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Supplier</th><th>Indicator</th><th>Severity</th><th>Status</th><th>Country</th><th>Identified</th><th></th></tr></thead><tbody>';
		if ( empty( $risks ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No findings yet.', 'eurocomply-forced-labour' ) . '</td></tr>';
		} else {
			foreach ( $risks as $r ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_fl_delete_risk&id=' . (int) $r['id'] ), 'eurocomply_fl_delete_risk_' . (int) $r['id'] );
				echo '<tr>';
				echo '<td>' . (int) $r['id'] . '</td>';
				echo '<td>' . (int) $r['supplier_id'] . '</td>';
				echo '<td>' . esc_html( (string) $r['indicator'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['severity'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['identified_at'] ) . '</td>';
				echo '<td><a href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete?\')">' . esc_html__( 'Delete', 'eurocomply-forced-labour' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_audits() : void {
		$audits  = AuditStore::all();
		$schemes = Settings::audit_schemes();
		echo '<h2>' . esc_html__( 'Audit & certification log', 'eurocomply-forced-labour' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_fl_save_audit' );
		echo '<input type="hidden" name="action" value="eurocomply_fl_save_audit" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Supplier ID', 'eurocomply-forced-labour' ) . '</th><td><input type="number" name="supplier_id" min="0" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Scheme', 'eurocomply-forced-labour' ) . '</th><td><select name="scheme">';
		foreach ( $schemes as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Audit date', 'eurocomply-forced-labour' ) . '</th><td><input type="date" name="audit_date" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Expires', 'eurocomply-forced-labour' ) . '</th><td><input type="date" name="expires_at" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Certificate no.', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="certificate_no" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Certificate URL', 'eurocomply-forced-labour' ) . '</th><td><input type="url" name="certificate_url" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Findings', 'eurocomply-forced-labour' ) . '</th><td><textarea name="findings" rows="3" cols="60"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record audit', 'eurocomply-forced-labour' ) );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Audits', 'eurocomply-forced-labour' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Supplier</th><th>Scheme</th><th>Audit date</th><th>Expires</th><th>Cert no.</th><th></th></tr></thead><tbody>';
		if ( empty( $audits ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No audits yet.', 'eurocomply-forced-labour' ) . '</td></tr>';
		} else {
			foreach ( $audits as $a ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_fl_delete_audit&id=' . (int) $a['id'] ), 'eurocomply_fl_delete_audit_' . (int) $a['id'] );
				echo '<tr>';
				echo '<td>' . (int) $a['id'] . '</td>';
				echo '<td>' . (int) $a['supplier_id'] . '</td>';
				echo '<td>' . esc_html( (string) $a['scheme'] ) . '</td>';
				echo '<td>' . esc_html( (string) ( $a['audit_date'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $a['expires_at'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) $a['certificate_no'] ) . '</td>';
				echo '<td><a href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete?\')">' . esc_html__( 'Delete', 'eurocomply-forced-labour' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_submissions() : void {
		$rows = SubmissionStore::all();
		$st   = Settings::submission_status();
		echo '<h2>' . esc_html__( 'Public submissions (Art. 9)', 'eurocomply-forced-labour' ) . '</h2>';
		echo '<p>' . esc_html__( 'Place the [eurocomply_fl_submit] shortcode on a public page so any natural or legal person can submit information about suspected forced-labour products.', 'eurocomply-forced-labour' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Created</th><th>Anonymous</th><th>Country</th><th>Sector</th><th>Indicator</th><th>Status</th><th>' . esc_html__( 'Set status', 'eurocomply-forced-labour' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No submissions yet.', 'eurocomply-forced-labour' ) . '</td></tr>';
		} else {
			foreach ( $rows as $r ) {
				echo '<tr>';
				echo '<td>' . (int) $r['id'] . '</td>';
				echo '<td>' . esc_html( (string) $r['created_at'] ) . '</td>';
				echo '<td>' . ( (int) $r['submitter_anonymous'] ? '✓' : '' ) . '</td>';
				echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['sector'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['indicator'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
				echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-flex;gap:4px;">';
				wp_nonce_field( 'eurocomply_fl_set_submission_status_' . (int) $r['id'] );
				echo '<input type="hidden" name="action" value="eurocomply_fl_set_submission_status" />';
				echo '<input type="hidden" name="id" value="' . (int) $r['id'] . '" />';
				echo '<select name="status">';
				foreach ( $st as $k => $l ) {
					$sel = $k === $r['status'] ? ' selected' : '';
					echo '<option value="' . esc_attr( $k ) . '"' . $sel . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select> <button class="button button-small">' . esc_html__( 'Set', 'eurocomply-forced-labour' ) . '</button></form></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_withdrawals() : void {
		$rows = WithdrawalStore::all();
		$st   = Settings::withdrawal_status();
		echo '<h2>' . esc_html__( 'Withdrawal procedures', 'eurocomply-forced-labour' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_fl_save_withdrawal' );
		echo '<input type="hidden" name="action" value="eurocomply_fl_save_withdrawal" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Risk ID', 'eurocomply-forced-labour' ) . '</th><td><input type="number" name="risk_id" min="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Supplier ID', 'eurocomply-forced-labour' ) . '</th><td><input type="number" name="supplier_id" min="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Decision ref', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="decision_ref" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Decision date', 'eurocomply-forced-labour' ) . '</th><td><input type="date" name="decision_date" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-forced-labour' ) . '</th><td><select name="status">';
		foreach ( $st as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Channels', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="channels" class="regular-text" placeholder="retail, online, B2B…" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Units recalled', 'eurocomply-forced-labour' ) . '</th><td><input type="number" name="units_recalled" min="0" value="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Disposal method', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="disposal_method" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Notes', 'eurocomply-forced-labour' ) . '</th><td><textarea name="notes" rows="3" cols="60"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record withdrawal', 'eurocomply-forced-labour' ) );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Procedures', 'eurocomply-forced-labour' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Decision</th><th>Status</th><th>Channels</th><th>Units</th><th></th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No withdrawal procedures yet.', 'eurocomply-forced-labour' ) . '</td></tr>';
		} else {
			foreach ( $rows as $w ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_fl_delete_withdrawal&id=' . (int) $w['id'] ), 'eurocomply_fl_delete_withdrawal_' . (int) $w['id'] );
				echo '<tr>';
				echo '<td>' . (int) $w['id'] . '</td>';
				echo '<td>' . esc_html( (string) $w['decision_ref'] ) . '</td>';
				echo '<td>' . esc_html( (string) $w['status'] ) . '</td>';
				echo '<td>' . esc_html( (string) $w['channels'] ) . '</td>';
				echo '<td>' . (int) $w['units_recalled'] . '</td>';
				echo '<td><a href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete?\')">' . esc_html__( 'Delete', 'eurocomply-forced-labour' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_fl_save_settings' );
		echo '<input type="hidden" name="action" value="eurocomply_fl_save_settings" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Company name', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="company_name" class="regular-text" value="' . esc_attr( (string) $s['company_name'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Compliance officer', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="compliance_officer" class="regular-text" value="' . esc_attr( (string) $s['compliance_officer'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Submission inbox', 'eurocomply-forced-labour' ) . '</th><td><input type="email" name="submission_email" class="regular-text" value="' . esc_attr( (string) $s['submission_email'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Reporting year', 'eurocomply-forced-labour' ) . '</th><td><input type="number" name="reporting_year" value="' . esc_attr( (string) $s['reporting_year'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'High-risk threshold (0–100)', 'eurocomply-forced-labour' ) . '</th><td><input type="number" name="high_risk_threshold" min="0" max="100" value="' . esc_attr( (string) $s['high_risk_threshold'] ) . '" /></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-forced-labour' ) . '</h2>';
		echo '<p>' . esc_html__( 'Roadmap (stubs in this scaffold):', 'eurocomply-forced-labour' ) . '</p>';
		echo '<ul style="list-style:disc;margin-left:20px;">';
		foreach ( array(
			'EU "single information submission point" forwarder',
			'Walk Free Global Slavery Index country-risk auto-sync',
			'Sedex / amfori / SAI API ingestion of audit reports',
			'Signed PDF supplier-due-diligence dossier',
			'REST API for ESG dashboards',
			'CSDDD plugin bridge (re-use chain-of-activities)',
			'WPML / Polylang multilingual public submission form',
			'Slack / Teams alert on new submission',
			'5,000-row CSV cap (free tier 500)',
			'Bulk CSV import for supplier register',
			'Withdrawal-procedure signed evidence packet (Art. 20 fulfilment)',
		) as $stub ) {
			echo '<li>' . esc_html( $stub ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_license() : void {
		$lic = License::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_fl_save_license' );
		echo '<input type="hidden" name="action" value="eurocomply_fl_save_license" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'License key', 'eurocomply-forced-labour' ) . '</th><td><input type="text" name="key" class="regular-text" placeholder="EC-XXXXXX" value="' . esc_attr( (string) ( $lic['key'] ?? '' ) ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-forced-labour' ) . '</th><td>' . esc_html( (string) ( $lic['status'] ?? 'inactive' ) ) . '</td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Save license', 'eurocomply-forced-labour' ) );
		echo '</form>';
	}

	public function save_settings() : void {
		check_admin_referer( 'eurocomply_fl_save_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		Settings::update(
			array(
				'company_name'        => sanitize_text_field( wp_unslash( (string) ( $_POST['company_name'] ?? '' ) ) ),
				'compliance_officer'  => sanitize_text_field( wp_unslash( (string) ( $_POST['compliance_officer'] ?? '' ) ) ),
				'submission_email'    => sanitize_email( wp_unslash( (string) ( $_POST['submission_email'] ?? '' ) ) ),
				'reporting_year'      => (int) ( $_POST['reporting_year'] ?? gmdate( 'Y' ) ),
				'high_risk_threshold' => max( 0, min( 100, (int) ( $_POST['high_risk_threshold'] ?? 70 ) ) ),
			)
		);
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_supplier() : void {
		check_admin_referer( 'eurocomply_fl_save_supplier' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		SupplierStore::insert( wp_unslash( $_POST ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'suppliers', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_supplier() : void {
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'eurocomply_fl_delete_supplier_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		SupplierStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'suppliers', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_risk() : void {
		check_admin_referer( 'eurocomply_fl_save_risk' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		RiskStore::insert( wp_unslash( $_POST ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'risks', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_risk() : void {
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'eurocomply_fl_delete_risk_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		RiskStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'risks', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_audit() : void {
		check_admin_referer( 'eurocomply_fl_save_audit' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		AuditStore::insert( wp_unslash( $_POST ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'audits', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_audit() : void {
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'eurocomply_fl_delete_audit_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		AuditStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'audits', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_withdrawal() : void {
		check_admin_referer( 'eurocomply_fl_save_withdrawal' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		WithdrawalStore::insert( wp_unslash( $_POST ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'withdrawals', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_withdrawal() : void {
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'eurocomply_fl_delete_withdrawal_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		WithdrawalStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'withdrawals', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function set_submission_status() : void {
		$id = (int) ( $_POST['id'] ?? 0 );
		check_admin_referer( 'eurocomply_fl_set_submission_status_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		SubmissionStore::set_status( $id, sanitize_key( wp_unslash( (string) ( $_POST['status'] ?? '' ) ) ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'submissions', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_license() : void {
		check_admin_referer( 'eurocomply_fl_save_license' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		$key = sanitize_text_field( wp_unslash( (string) ( $_POST['key'] ?? '' ) ) );
		if ( '' === $key ) {
			License::deactivate();
		} else {
			License::activate( $key );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-forced-labour', 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
