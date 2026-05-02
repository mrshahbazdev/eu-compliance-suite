<?php
/**
 * Admin UI.
 *
 * @package EuroComply\ProductLiability
 */

declare( strict_types = 1 );

namespace EuroComply\ProductLiability;

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
		add_action( 'admin_post_eurocomply_pl_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_eurocomply_pl_save_product', array( $this, 'save_product' ) );
		add_action( 'admin_post_eurocomply_pl_delete_product', array( $this, 'delete_product' ) );
		add_action( 'admin_post_eurocomply_pl_save_claim', array( $this, 'save_claim' ) );
		add_action( 'admin_post_eurocomply_pl_set_claim_status', array( $this, 'set_claim_status' ) );
		add_action( 'admin_post_eurocomply_pl_delete_claim', array( $this, 'delete_claim' ) );
		add_action( 'admin_post_eurocomply_pl_save_disclosure', array( $this, 'save_disclosure' ) );
		add_action( 'admin_post_eurocomply_pl_delete_disclosure', array( $this, 'delete_disclosure' ) );
		add_action( 'admin_post_eurocomply_pl_set_defect_status', array( $this, 'set_defect_status' ) );
		add_action( 'admin_post_eurocomply_pl_delete_defect', array( $this, 'delete_defect' ) );
		add_action( 'admin_post_eurocomply_pl_save_license', array( $this, 'save_license' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'Product Liability', 'eurocomply-product-liability' ),
			__( 'Product Liability', 'eurocomply-product-liability' ),
			'manage_options',
			'eurocomply-product-liability',
			array( $this, 'render' ),
			'dashicons-warning',
			60
		);
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'dashboard';
		$tabs = array(
			'dashboard'   => __( 'Dashboard', 'eurocomply-product-liability' ),
			'products'    => __( 'Products', 'eurocomply-product-liability' ),
			'defects'     => __( 'Defect reports', 'eurocomply-product-liability' ),
			'claims'      => __( 'Claims', 'eurocomply-product-liability' ),
			'disclosures' => __( 'Disclosures', 'eurocomply-product-liability' ),
			'settings'    => __( 'Settings', 'eurocomply-product-liability' ),
			'pro'         => __( 'Pro', 'eurocomply-product-liability' ),
			'license'     => __( 'License', 'eurocomply-product-liability' ),
		);
		echo '<div class="wrap"><h1>' . esc_html__( 'EuroComply Product Liability', 'eurocomply-product-liability' ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = admin_url( 'admin.php?page=eurocomply-product-liability&tab=' . $slug );
			$class = 'nav-tab' . ( $tab === $slug ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';
		if ( ! empty( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'eurocomply-product-liability' ) . '</p></div>';
		}
		switch ( $tab ) {
			case 'products':
				$this->render_products();
				break;
			case 'defects':
				$this->render_defects();
				break;
			case 'claims':
				$this->render_claims();
				break;
			case 'disclosures':
				$this->render_disclosures();
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
		$cards = array(
			array( __( 'Products / components', 'eurocomply-product-liability' ), ProductStore::count() ),
			array( __( 'Nearing 10y limitation', 'eurocomply-product-liability' ), ProductStore::nearing_limitation_count( 365 ) ),
			array( __( 'Defect reports', 'eurocomply-product-liability' ), DefectStore::count() ),
			array( __( 'Open defects', 'eurocomply-product-liability' ), DefectStore::open_count() ),
			array( __( 'Critical defects', 'eurocomply-product-liability' ), DefectStore::critical_count() ),
			array( __( 'Claims', 'eurocomply-product-liability' ), ClaimStore::count() ),
			array( __( 'Open claims', 'eurocomply-product-liability' ), ClaimStore::open_count() ),
			array( __( 'Claims near 3y window', 'eurocomply-product-liability' ), ClaimStore::nearing_limitation_count( 90 ) ),
			array( __( 'Total settled (€)', 'eurocomply-product-liability' ), number_format_i18n( ClaimStore::total_paid_eur(), 2 ) ),
			array( __( 'Disclosures (Art. 9)', 'eurocomply-product-liability' ), DisclosureStore::count() ),
			array( __( 'Open disclosures', 'eurocomply-product-liability' ), DisclosureStore::open_count() ),
		);
		echo '<h2>' . esc_html__( 'Liability overview', 'eurocomply-product-liability' ) . '</h2>';
		echo '<p>' . esc_html__( 'Dir. (EU) 2024/2853 modernises the 1985 Product Liability Directive. Software, AI systems and related digital services now fall within scope. Limitation periods: 3 years from awareness (Art. 17(1)); general 10 years from placing on market; extended to 25 years for latent personal injury.', 'eurocomply-product-liability' ) . '</p>';
		echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:12px;">';
		foreach ( $cards as $c ) {
			echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px 18px;min-width:170px;">';
			echo '<div style="font-size:11px;text-transform:uppercase;color:#646970;">' . esc_html( $c[0] ) . '</div>';
			echo '<div style="font-size:24px;font-weight:600;">' . esc_html( (string) $c[1] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	private function render_products() : void {
		$rows  = ProductStore::all();
		$types = Settings::product_types();
		echo '<h2>' . esc_html__( 'Product / component register', 'eurocomply-product-liability' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_pl_save_product' );
		echo '<input type="hidden" name="action" value="eurocomply_pl_save_product" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Name', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="name" required class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'SKU', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="sku" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'WooCommerce product ID', 'eurocomply-product-liability' ) . '</th><td><input type="number" name="wc_product_id" min="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Type', 'eurocomply-product-liability' ) . '</th><td><select name="type">';
		foreach ( $types as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'AI system?', 'eurocomply-product-liability' ) . '</th><td><label><input type="checkbox" name="ai_system" value="1" /> ' . esc_html__( 'Yes', 'eurocomply-product-liability' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'Manufacturer', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="manufacturer" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Manufacturer country', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="manufacturer_country" maxlength="8" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Importer', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="importer" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'EU representative', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="eu_representative" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Substantial modifier', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="substantial_modifier" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Placed on market', 'eurocomply-product-liability' ) . '</th><td><input type="date" name="placed_on_market" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Withdrawn at', 'eurocomply-product-liability' ) . '</th><td><input type="date" name="withdrawn_at" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Software updates until', 'eurocomply-product-liability' ) . '</th><td><input type="date" name="software_update_until" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Documentation URL', 'eurocomply-product-liability' ) . '</th><td><input type="url" name="documentation_url" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Notes', 'eurocomply-product-liability' ) . '</th><td><textarea name="notes" rows="3" cols="60"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add product', 'eurocomply-product-liability' ) );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Products', 'eurocomply-product-liability' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Type</th><th>AI</th><th>Manufacturer</th><th>Placed</th><th>10y until</th><th>25y until</th><th></th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No products yet.', 'eurocomply-product-liability' ) . '</td></tr>';
		} else {
			foreach ( $rows as $r ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_pl_delete_product&id=' . (int) $r['id'] ), 'eurocomply_pl_delete_product_' . (int) $r['id'] );
				echo '<tr>';
				echo '<td>' . (int) $r['id'] . '</td>';
				echo '<td>' . esc_html( (string) $r['name'] ) . ( $r['sku'] ? ' <code>' . esc_html( (string) $r['sku'] ) . '</code>' : '' ) . '</td>';
				echo '<td>' . esc_html( (string) $r['type'] ) . '</td>';
				echo '<td>' . ( (int) $r['ai_system'] ? '✓' : '' ) . '</td>';
				echo '<td>' . esc_html( (string) $r['manufacturer'] ) . '</td>';
				echo '<td>' . esc_html( (string) ( $r['placed_on_market'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $r['limitation_until'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $r['extended_limitation_until'] ?? '' ) ) . '</td>';
				echo '<td><a href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete?\')">' . esc_html__( 'Delete', 'eurocomply-product-liability' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_defects() : void {
		$rows = DefectStore::all();
		$st   = Settings::defect_status();
		echo '<h2>' . esc_html__( 'Consumer defect reports', 'eurocomply-product-liability' ) . '</h2>';
		echo '<p>' . esc_html__( 'Embed [eurocomply_pl_defect_report] on a public page to receive consumer defect reports.', 'eurocomply-product-liability' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Created</th><th>Anon</th><th>Product</th><th>Damage</th><th>Severity</th><th>Status</th><th>' . esc_html__( 'Set status', 'eurocomply-product-liability' ) . '</th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No defect reports yet.', 'eurocomply-product-liability' ) . '</td></tr>';
		} else {
			foreach ( $rows as $r ) {
				echo '<tr>';
				echo '<td>' . (int) $r['id'] . '</td>';
				echo '<td>' . esc_html( (string) $r['created_at'] ) . '</td>';
				echo '<td>' . ( (int) $r['reporter_anonymous'] ? '✓' : '' ) . '</td>';
				echo '<td>' . (int) $r['product_id'] . '</td>';
				echo '<td>' . esc_html( (string) $r['damage_type'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['severity'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
				echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-flex;gap:4px;">';
				wp_nonce_field( 'eurocomply_pl_set_defect_status_' . (int) $r['id'] );
				echo '<input type="hidden" name="action" value="eurocomply_pl_set_defect_status" />';
				echo '<input type="hidden" name="id" value="' . (int) $r['id'] . '" />';
				echo '<select name="status">';
				foreach ( $st as $k => $l ) {
					$sel = $k === $r['status'] ? ' selected' : '';
					echo '<option value="' . esc_attr( $k ) . '"' . $sel . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select> <button class="button button-small">' . esc_html__( 'Set', 'eurocomply-product-liability' ) . '</button></form></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_claims() : void {
		$rows    = ClaimStore::all();
		$status  = Settings::claim_status();
		$damages = Settings::damage_types();
		echo '<h2>' . esc_html__( 'Liability claims', 'eurocomply-product-liability' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_pl_save_claim' );
		echo '<input type="hidden" name="action" value="eurocomply_pl_save_claim" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Product ID', 'eurocomply-product-liability' ) . '</th><td><input type="number" name="product_id" min="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Defect ID', 'eurocomply-product-liability' ) . '</th><td><input type="number" name="defect_id" min="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Claimant ref', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="claimant_ref" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Jurisdiction', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="jurisdiction" maxlength="8" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Damage type', 'eurocomply-product-liability' ) . '</th><td><select name="damage_type">';
		foreach ( $damages as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Damage value (EUR)', 'eurocomply-product-liability' ) . '</th><td><input type="number" name="damage_value_eur" min="0" step="0.01" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Occurred on', 'eurocomply-product-liability' ) . '</th><td><input type="date" name="occurred_on" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Became aware on', 'eurocomply-product-liability' ) . '</th><td><input type="date" name="became_aware_on" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Counsel', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="counsel" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Notes', 'eurocomply-product-liability' ) . '</th><td><textarea name="notes" rows="3" cols="60"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Open claim', 'eurocomply-product-liability' ) );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Claims', 'eurocomply-product-liability' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Product</th><th>Damage</th><th>Value €</th><th>Aware on</th><th>3y until</th><th>Status</th><th>' . esc_html__( 'Set status', 'eurocomply-product-liability' ) . '</th><th></th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No claims yet.', 'eurocomply-product-liability' ) . '</td></tr>';
		} else {
			foreach ( $rows as $r ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_pl_delete_claim&id=' . (int) $r['id'] ), 'eurocomply_pl_delete_claim_' . (int) $r['id'] );
				echo '<tr>';
				echo '<td>' . (int) $r['id'] . '</td>';
				echo '<td>' . (int) $r['product_id'] . '</td>';
				echo '<td>' . esc_html( (string) $r['damage_type'] ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $r['damage_value_eur'], 2 ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $r['became_aware_on'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $r['limitation_until'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
				echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-flex;gap:4px;">';
				wp_nonce_field( 'eurocomply_pl_set_claim_status_' . (int) $r['id'] );
				echo '<input type="hidden" name="action" value="eurocomply_pl_set_claim_status" />';
				echo '<input type="hidden" name="id" value="' . (int) $r['id'] . '" />';
				echo '<select name="status">';
				foreach ( $status as $k => $l ) {
					$sel = $k === $r['status'] ? ' selected' : '';
					echo '<option value="' . esc_attr( $k ) . '"' . $sel . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select><input type="number" name="settled_amount_eur" placeholder="€" step="0.01" style="width:90px;" />';
				echo '<button class="button button-small">' . esc_html__( 'Set', 'eurocomply-product-liability' ) . '</button></form></td>';
				echo '<td><a href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete?\')">' . esc_html__( 'Delete', 'eurocomply-product-liability' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_disclosures() : void {
		$rows = DisclosureStore::all();
		$st   = Settings::disclosure_status();
		echo '<h2>' . esc_html__( 'Art. 9 disclosure-of-evidence log', 'eurocomply-product-liability' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_pl_save_disclosure' );
		echo '<input type="hidden" name="action" value="eurocomply_pl_save_disclosure" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Claim ID', 'eurocomply-product-liability' ) . '</th><td><input type="number" name="claim_id" min="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Product ID', 'eurocomply-product-liability' ) . '</th><td><input type="number" name="product_id" min="0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-product-liability' ) . '</th><td><select name="status">';
		foreach ( $st as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Evidence category', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="evidence_category" class="regular-text" placeholder="design dossier / test reports / source code / audit trail…" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Confidentiality', 'eurocomply-product-liability' ) . '</th><td><select name="confidentiality">';
		foreach ( array( 'standard' => 'Standard', 'restricted' => 'Restricted', 'attorney_eyes_only' => 'Attorneys-only', 'sealed' => 'Sealed' ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Court ref', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="court_ref" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Counsel', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="counsel" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Description', 'eurocomply-product-liability' ) . '</th><td><textarea name="description" rows="3" cols="60"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record disclosure', 'eurocomply-product-liability' ) );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Disclosures', 'eurocomply-product-liability' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Claim</th><th>Status</th><th>Category</th><th>Confidentiality</th><th>Court</th><th></th></tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No disclosures yet.', 'eurocomply-product-liability' ) . '</td></tr>';
		} else {
			foreach ( $rows as $r ) {
				$del = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_pl_delete_disclosure&id=' . (int) $r['id'] ), 'eurocomply_pl_delete_disclosure_' . (int) $r['id'] );
				echo '<tr>';
				echo '<td>' . (int) $r['id'] . '</td>';
				echo '<td>' . (int) $r['claim_id'] . '</td>';
				echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['evidence_category'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['confidentiality'] ) . '</td>';
				echo '<td>' . esc_html( (string) $r['court_ref'] ) . '</td>';
				echo '<td><a href="' . esc_url( $del ) . '" onclick="return confirm(\'Delete?\')">' . esc_html__( 'Delete', 'eurocomply-product-liability' ) . '</a></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_pl_save_settings' );
		echo '<input type="hidden" name="action" value="eurocomply_pl_save_settings" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Manufacturer name', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="manufacturer_name" class="regular-text" value="' . esc_attr( (string) $s['manufacturer_name'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Manufacturer address', 'eurocomply-product-liability' ) . '</th><td><textarea name="manufacturer_address" rows="3" cols="60">' . esc_textarea( (string) $s['manufacturer_address'] ) . '</textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Manufacturer email', 'eurocomply-product-liability' ) . '</th><td><input type="email" name="manufacturer_email" class="regular-text" value="' . esc_attr( (string) $s['manufacturer_email'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'EU representative', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="eu_representative" class="regular-text" value="' . esc_attr( (string) $s['eu_representative'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'EU rep address', 'eurocomply-product-liability' ) . '</th><td><textarea name="eu_rep_address" rows="3" cols="60">' . esc_textarea( (string) $s['eu_rep_address'] ) . '</textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Importer', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="importer_name" class="regular-text" value="' . esc_attr( (string) $s['importer_name'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Importer address', 'eurocomply-product-liability' ) . '</th><td><textarea name="importer_address" rows="3" cols="60">' . esc_textarea( (string) $s['importer_address'] ) . '</textarea></td></tr>';
		echo '<tr><th>' . esc_html__( 'Liability officer', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="liability_officer" class="regular-text" value="' . esc_attr( (string) $s['liability_officer'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Defect inbox', 'eurocomply-product-liability' ) . '</th><td><input type="email" name="defect_inbox" class="regular-text" value="' . esc_attr( (string) $s['defect_inbox'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'General limitation (years)', 'eurocomply-product-liability' ) . '</th><td><input type="number" name="limitation_years" min="1" value="' . esc_attr( (string) $s['limitation_years'] ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Latent-injury limitation (years)', 'eurocomply-product-liability' ) . '</th><td><input type="number" name="latent_injury_years" min="1" value="' . esc_attr( (string) $s['latent_injury_years'] ) . '" /></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-product-liability' ) . '</h2>';
		echo '<p>' . esc_html__( 'Roadmap (stubs in this scaffold):', 'eurocomply-product-liability' ) . '</p>';
		echo '<ul style="list-style:disc;margin-left:20px;">';
		foreach ( array(
			'Liability insurance auto-syndication (CSV / XML pack)',
			'Signed PDF claim dossier with evidence index',
			'Court-portal export (e.g. e-Justice, beA, RPVA, PolyJur)',
			'REST API for legal-tech integrations',
			'Slack / Teams alert on critical defect or new claim',
			'Bulk CSV import for product / SKU register',
			'GPSR + Toy Safety bridges (auto-clone product register)',
			'AI-Act bridge (auto-mark AI-system products & high-risk)',
			'5,000-row CSV cap (free tier 500)',
			'Software-update obligation reminder cron (Art. 11)',
		) as $stub ) {
			echo '<li>' . esc_html( $stub ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_license() : void {
		$lic = License::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'eurocomply_pl_save_license' );
		echo '<input type="hidden" name="action" value="eurocomply_pl_save_license" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'License key', 'eurocomply-product-liability' ) . '</th><td><input type="text" name="key" class="regular-text" placeholder="EC-XXXXXX" value="' . esc_attr( (string) ( $lic['key'] ?? '' ) ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-product-liability' ) . '</th><td>' . esc_html( (string) ( $lic['status'] ?? 'inactive' ) ) . '</td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Save license', 'eurocomply-product-liability' ) );
		echo '</form>';
	}

	private function redirect( string $tab ) : void {
		wp_safe_redirect( add_query_arg( array( 'page' => 'eurocomply-product-liability', 'tab' => $tab, 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_settings() : void {
		check_admin_referer( 'eurocomply_pl_save_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		Settings::update(
			array(
				'manufacturer_name'    => sanitize_text_field( wp_unslash( (string) ( $_POST['manufacturer_name'] ?? '' ) ) ),
				'manufacturer_address' => sanitize_textarea_field( wp_unslash( (string) ( $_POST['manufacturer_address'] ?? '' ) ) ),
				'manufacturer_email'   => sanitize_email( wp_unslash( (string) ( $_POST['manufacturer_email'] ?? '' ) ) ),
				'eu_representative'    => sanitize_text_field( wp_unslash( (string) ( $_POST['eu_representative'] ?? '' ) ) ),
				'eu_rep_address'       => sanitize_textarea_field( wp_unslash( (string) ( $_POST['eu_rep_address'] ?? '' ) ) ),
				'importer_name'        => sanitize_text_field( wp_unslash( (string) ( $_POST['importer_name'] ?? '' ) ) ),
				'importer_address'     => sanitize_textarea_field( wp_unslash( (string) ( $_POST['importer_address'] ?? '' ) ) ),
				'liability_officer'    => sanitize_text_field( wp_unslash( (string) ( $_POST['liability_officer'] ?? '' ) ) ),
				'defect_inbox'         => sanitize_email( wp_unslash( (string) ( $_POST['defect_inbox'] ?? '' ) ) ),
				'limitation_years'     => max( 1, (int) ( $_POST['limitation_years'] ?? 10 ) ),
				'latent_injury_years'  => max( 1, (int) ( $_POST['latent_injury_years'] ?? 25 ) ),
			)
		);
		$this->redirect( 'settings' );
	}

	public function save_product() : void {
		check_admin_referer( 'eurocomply_pl_save_product' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		ProductStore::insert( wp_unslash( $_POST ) );
		$this->redirect( 'products' );
	}

	public function delete_product() : void {
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'eurocomply_pl_delete_product_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		ProductStore::delete( $id );
		$this->redirect( 'products' );
	}

	public function save_claim() : void {
		check_admin_referer( 'eurocomply_pl_save_claim' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		ClaimStore::insert( wp_unslash( $_POST ) );
		$this->redirect( 'claims' );
	}

	public function set_claim_status() : void {
		$id = (int) ( $_POST['id'] ?? 0 );
		check_admin_referer( 'eurocomply_pl_set_claim_status_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		ClaimStore::set_status(
			$id,
			sanitize_key( wp_unslash( (string) ( $_POST['status'] ?? '' ) ) ),
			array(
				'settled_amount_eur' => (float) ( $_POST['settled_amount_eur'] ?? 0 ),
			)
		);
		$this->redirect( 'claims' );
	}

	public function delete_claim() : void {
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'eurocomply_pl_delete_claim_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		ClaimStore::delete( $id );
		$this->redirect( 'claims' );
	}

	public function save_disclosure() : void {
		check_admin_referer( 'eurocomply_pl_save_disclosure' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		DisclosureStore::insert( wp_unslash( $_POST ) );
		$this->redirect( 'disclosures' );
	}

	public function delete_disclosure() : void {
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'eurocomply_pl_delete_disclosure_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		DisclosureStore::delete( $id );
		$this->redirect( 'disclosures' );
	}

	public function set_defect_status() : void {
		$id = (int) ( $_POST['id'] ?? 0 );
		check_admin_referer( 'eurocomply_pl_set_defect_status_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		DefectStore::set_status( $id, sanitize_key( wp_unslash( (string) ( $_POST['status'] ?? '' ) ) ) );
		$this->redirect( 'defects' );
	}

	public function delete_defect() : void {
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'eurocomply_pl_delete_defect_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		DefectStore::delete( $id );
		$this->redirect( 'defects' );
	}

	public function save_license() : void {
		check_admin_referer( 'eurocomply_pl_save_license' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '' );
		}
		$key = sanitize_text_field( wp_unslash( (string) ( $_POST['key'] ?? '' ) ) );
		if ( '' === $key ) {
			License::deactivate();
		} else {
			License::activate( $key );
		}
		$this->redirect( 'license' );
	}
}
