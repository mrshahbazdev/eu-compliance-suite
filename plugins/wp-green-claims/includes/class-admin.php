<?php
/**
 * Admin UI.
 *
 * @package EuroComply\GreenClaims
 */

declare( strict_types = 1 );

namespace EuroComply\GreenClaims;

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
		add_action( 'admin_post_eurocomply_gc_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_eurocomply_gc_save_claim', array( $this, 'save_claim' ) );
		add_action( 'admin_post_eurocomply_gc_delete_claim', array( $this, 'delete_claim' ) );
		add_action( 'admin_post_eurocomply_gc_save_label', array( $this, 'save_label' ) );
		add_action( 'admin_post_eurocomply_gc_delete_label', array( $this, 'delete_label' ) );
		add_action( 'admin_post_eurocomply_gc_save_license', array( $this, 'save_license' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'EuroComply Green Claims', 'eurocomply-green-claims' ),
			__( 'Green Claims', 'eurocomply-green-claims' ),
			'manage_options',
			EUROCOMPLY_GC_SLUG,
			array( $this, 'render' ),
			'dashicons-palmtree',
			60
		);
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'dashboard';
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'eurocomply-green-claims' ),
			'claims'    => __( 'Claims', 'eurocomply-green-claims' ),
			'labels'    => __( 'Labels', 'eurocomply-green-claims' ),
			'scanner'   => __( 'Scanner', 'eurocomply-green-claims' ),
			'durability'=> __( 'Durability', 'eurocomply-green-claims' ),
			'settings'  => __( 'Settings', 'eurocomply-green-claims' ),
			'pro'       => __( 'Pro', 'eurocomply-green-claims' ),
			'license'   => __( 'License', 'eurocomply-green-claims' ),
		);

		echo '<div class="wrap"><h1>' . esc_html__( 'EuroComply Green Claims', 'eurocomply-green-claims' ) . '</h1>';
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'eurocomply-green-claims' ) . '</p></div>';
		}
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg( array( 'page' => EUROCOMPLY_GC_SLUG, 'tab' => $key ), admin_url( 'admin.php' ) );
			$cls = $tab === $key ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $cls ), esc_html( $label ) );
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'claims':     $this->render_claims();     break;
			case 'labels':     $this->render_labels();     break;
			case 'scanner':    $this->render_scanner();    break;
			case 'durability': $this->render_durability(); break;
			case 'settings':   $this->render_settings();   break;
			case 'pro':        $this->render_pro();        break;
			case 'license':    $this->render_license();    break;
			default:           $this->render_dashboard();  break;
		}
		echo '</div>';
	}

	private function render_dashboard() : void {
		echo '<table class="widefat striped"><tbody>';
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Total claims registered', 'eurocomply-green-claims' ), ClaimStore::count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Pending verification', 'eurocomply-green-claims' ), ClaimStore::pending_count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Expired evidence', 'eurocomply-green-claims' ), ClaimStore::expired_count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Sustainability labels in registry', 'eurocomply-green-claims' ), LabelStore::count() );
		printf( '<tr><th>%s</th><td>%d</td></tr>', esc_html__( 'Labels lacking third-party verification', 'eurocomply-green-claims' ), LabelStore::unverified_count() );
		echo '</tbody></table>';
		echo '<p>' . esc_html__( 'Add the [eurocomply_gc_disclaimer] shortcode to product / consumer-info pages and edit per-product CRD Art. 5a fields via the side metabox on each post.', 'eurocomply-green-claims' ) . '</p>';
	}

	private function render_claims() : void {
		$rows = ClaimStore::all();
		$this->form_open( 'eurocomply_gc_save_claim', 'eurocomply_gc_save_claim' );
		echo '<h3>' . esc_html__( 'Register claim', 'eurocomply-green-claims' ) . '</h3>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Product / post ID', 'eurocomply-green-claims' ) . '</th><td><input type="number" name="product_id" min="0"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Claim text', 'eurocomply-green-claims' ) . '</th><td><input type="text" name="claim_text" class="regular-text" required></td></tr>';
		echo '<tr><th>' . esc_html__( 'Evidence type', 'eurocomply-green-claims' ) . '</th><td><select name="evidence_type">';
		foreach ( Settings::evidence_types() as $k => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $label ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Evidence URL', 'eurocomply-green-claims' ) . '</th><td><input type="url" name="evidence_url" class="regular-text"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Verifier', 'eurocomply-green-claims' ) . '</th><td><input type="text" name="verifier" class="regular-text"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Verified at', 'eurocomply-green-claims' ) . '</th><td><input type="date" name="verified_at"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Expires at', 'eurocomply-green-claims' ) . '</th><td><input type="date" name="expires_at"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-green-claims' ) . '</th><td><select name="status">';
		foreach ( Settings::claim_status() as $k => $label ) {
			printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $label ) );
		}
		echo '</select></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Register claim', 'eurocomply-green-claims' ) );
		echo '</form>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No claims registered yet.', 'eurocomply-green-claims' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Product', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'Claim', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'Evidence', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'Status', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'Expires', 'eurocomply-green-claims' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_gc_delete_claim&id=' . (int) $r['id'] ), 'eurocomply_gc_delete_claim_' . (int) $r['id'] );
			printf(
				'<tr><td>%d</td><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s" onclick="return confirm(\'%s\')">%s</a></td></tr>',
				(int) $r['id'],
				(int) $r['product_id'],
				esc_html( (string) $r['claim_text'] ),
				esc_html( (string) $r['evidence_type'] ),
				esc_html( (string) $r['status'] ),
				esc_html( (string) ( $r['expires_at'] ?? '' ) ),
				esc_url( $delete ),
				esc_js( __( 'Delete?', 'eurocomply-green-claims' ) ),
				esc_html__( 'Delete', 'eurocomply-green-claims' )
			);
		}
		echo '</tbody></table>';
	}

	private function render_labels() : void {
		$rows = LabelStore::all();
		$this->form_open( 'eurocomply_gc_save_label', 'eurocomply_gc_save_label' );
		echo '<h3>' . esc_html__( 'Add sustainability label', 'eurocomply-green-claims' ) . '</h3>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th>' . esc_html__( 'Label name', 'eurocomply-green-claims' ) . '</th><td><input type="text" name="label_name" class="regular-text" required></td></tr>';
		echo '<tr><th>' . esc_html__( 'Scheme owner', 'eurocomply-green-claims' ) . '</th><td><input type="text" name="scheme_owner" class="regular-text"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Scheme URL', 'eurocomply-green-claims' ) . '</th><td><input type="url" name="scheme_url" class="regular-text"></td></tr>';
		echo '<tr><th>' . esc_html__( 'Recognised by EU', 'eurocomply-green-claims' ) . '</th><td><label><input type="checkbox" name="recognized_eu" value="1"> ' . esc_html__( 'Yes', 'eurocomply-green-claims' ) . '</label></td></tr>';
		echo '<tr><th>' . esc_html__( 'Third-party verified', 'eurocomply-green-claims' ) . '</th><td><label><input type="checkbox" name="third_party_verified" value="1" checked> ' . esc_html__( 'Yes', 'eurocomply-green-claims' ) . '</label></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add label', 'eurocomply-green-claims' ) );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Label', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'Owner', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( 'EU', 'eurocomply-green-claims' ) . '</th><th>' . esc_html__( '3rd-party', 'eurocomply-green-claims' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=eurocomply_gc_delete_label&id=' . (int) $r['id'] ), 'eurocomply_gc_delete_label_' . (int) $r['id'] );
			printf(
				'<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s" onclick="return confirm(\'%s\')">%s</a></td></tr>',
				(int) $r['id'],
				esc_html( (string) $r['label_name'] ),
				esc_html( (string) $r['scheme_owner'] ),
				$r['recognized_eu'] ? '✓' : '–',
				$r['third_party_verified'] ? '✓' : '–',
				esc_url( $delete ),
				esc_js( __( 'Delete?', 'eurocomply-green-claims' ) ),
				esc_html__( 'Delete', 'eurocomply-green-claims' )
			);
		}
		echo '</tbody></table>';
	}

	private function render_scanner() : void {
		echo '<p>' . esc_html__( 'The scanner inspects every singular front-end view for unsubstantiated generic claims (e.g. "eco-friendly", "climate neutral", "biodegradable") and either appends a disclaimer or replaces the offending phrases depending on settings.', 'eurocomply-green-claims' ) . '</p>';
		echo '<h3>' . esc_html__( 'Banned generic phrases', 'eurocomply-green-claims' ) . '</h3>';
		echo '<p><code>' . esc_html( implode( ' · ', Settings::banned_phrases() ) ) . '</code></p>';
		echo '<p>' . esc_html__( 'Phrases are matched case-insensitively. Add a verified ClaimStore row whose claim text contains the phrase to permit it on the linked product/post.', 'eurocomply-green-claims' ) . '</p>';
	}

	private function render_durability() : void {
		echo '<p>' . esc_html__( 'CRD Art. 5a (added by Dir. (EU) 2024/825) requires pre-contractual information on durability, software/security update period, and repairability. Configure defaults in Settings, then override per-product via the "EuroComply Green Claims" metabox on each post.', 'eurocomply-green-claims' ) . '</p>';
		echo '<p>' . esc_html__( 'Front-end shortcode:', 'eurocomply-green-claims' ) . ' <code>[eurocomply_gc_durability post_id="123"]</code></p>';
	}

	private function render_settings() : void {
		$d = Settings::get();
		$this->form_open( 'eurocomply_gc_save_settings', 'eurocomply_gc_save_settings' );
		echo '<table class="form-table"><tbody>';
		printf( '<tr><th>%s</th><td><input type="text" name="company_name" class="regular-text" value="%s"></td></tr>', esc_html__( 'Company name', 'eurocomply-green-claims' ), esc_attr( (string) $d['company_name'] ) );
		printf( '<tr><th>%s</th><td><label><input type="checkbox" name="enable_scanner" value="1" %s> %s</label></td></tr>', esc_html__( 'Scanner', 'eurocomply-green-claims' ), checked( ! empty( $d['enable_scanner'] ), true, false ), esc_html__( 'Enable banned-claim scanner', 'eurocomply-green-claims' ) );
		printf( '<tr><th>%s</th><td><label><input type="checkbox" name="block_unverified" value="1" %s> %s</label></td></tr>', esc_html__( 'Hard block', 'eurocomply-green-claims' ), checked( ! empty( $d['block_unverified'] ), true, false ), esc_html__( 'Replace unverified phrases with placeholder dots (instead of soft disclaimer)', 'eurocomply-green-claims' ) );
		printf( '<tr><th>%s</th><td><input type="number" name="default_durability_m" min="0" value="%d"></td></tr>', esc_html__( 'Default durability (months)', 'eurocomply-green-claims' ), (int) $d['default_durability_m'] );
		printf( '<tr><th>%s</th><td><input type="number" name="default_software_y" min="0" value="%d"></td></tr>', esc_html__( 'Default software-update period (years)', 'eurocomply-green-claims' ), (int) $d['default_software_y'] );
		printf( '<tr><th>%s</th><td><input type="number" name="default_repair_score" min="0" max="10" value="%d"></td></tr>', esc_html__( 'Default repairability score (0–10)', 'eurocomply-green-claims' ), (int) $d['default_repair_score'] );
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		$active = License::is_pro() ? __( 'Active', 'eurocomply-green-claims' ) : __( 'Inactive', 'eurocomply-green-claims' );
		echo '<p>' . esc_html__( 'License status:', 'eurocomply-green-claims' ) . ' <strong>' . esc_html( $active ) . '</strong></p>';
		echo '<h3>' . esc_html__( 'Pro roadmap (stubs)', 'eurocomply-green-claims' ) . '</h3>';
		echo '<ul class="ul-disc">';
		foreach ( array(
			__( 'Third-party verification API (Bureau Veritas / TÜV / SGS / DEKRA)', 'eurocomply-green-claims' ),
			__( 'EPREL bridge: pull energy-label data from EU registry', 'eurocomply-green-claims' ),
			__( 'Signed PDF substantiation file per product / per claim', 'eurocomply-green-claims' ),
			__( 'PEF / OEF method import (Recommendation 2013/179/EU)', 'eurocomply-green-claims' ),
			__( 'REST API for compliance dashboards', 'eurocomply-green-claims' ),
			__( 'WPML / Polylang for multi-language disclosures', 'eurocomply-green-claims' ),
			__( 'Slack / Teams alert on expired evidence', 'eurocomply-green-claims' ),
			__( '5,000-row CSV cap (free tier 500)', 'eurocomply-green-claims' ),
			__( 'Bulk CSV import for product-level CRD Art. 5a fields', 'eurocomply-green-claims' ),
			__( 'CSDDD bridge: link evidence to chain-of-activities suppliers', 'eurocomply-green-claims' ),
			__( 'EU Commission "Green Claims" registry submission helper', 'eurocomply-green-claims' ),
		) as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';
	}

	private function render_license() : void {
		$d = License::get();
		$this->form_open( 'eurocomply_gc_save_license', 'eurocomply_gc_save_license' );
		echo '<table class="form-table"><tbody>';
		printf( '<tr><th>%s</th><td><input type="text" name="license_key" class="regular-text" value="%s"></td></tr>', esc_html__( 'License key', 'eurocomply-green-claims' ), esc_attr( (string) ( $d['key'] ?? '' ) ) );
		echo '</tbody></table>';
		submit_button( __( 'Save license', 'eurocomply-green-claims' ) );
		echo '</form>';
	}

	private function form_open( string $action, string $nonce_action ) : void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $nonce_action );
	}

	public function save_settings() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_gc_save_settings' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-green-claims' ) );
		}
		$input = array();
		foreach ( array( 'company_name', 'enable_scanner', 'block_unverified', 'default_durability_m', 'default_software_y', 'default_repair_score' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$input[ $k ] = wp_unslash( $_POST[ $k ] );
			}
		}
		Settings::save( $input );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_GC_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_claim() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_gc_save_claim' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-green-claims' ) );
		}
		$data = array();
		foreach ( array( 'product_id', 'claim_text', 'evidence_type', 'evidence_url', 'verifier', 'verified_at', 'expires_at', 'status' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$data[ $k ] = wp_unslash( $_POST[ $k ] );
			}
		}
		ClaimStore::insert( $data );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_GC_SLUG, 'tab' => 'claims', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_claim() : void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'eurocomply_gc_delete_claim_' . $id ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-green-claims' ) );
		}
		ClaimStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_GC_SLUG, 'tab' => 'claims', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_label() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_gc_save_label' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-green-claims' ) );
		}
		$data = array();
		foreach ( array( 'label_name', 'scheme_owner', 'scheme_url', 'recognized_eu', 'third_party_verified' ) as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$data[ $k ] = wp_unslash( $_POST[ $k ] );
			}
		}
		LabelStore::insert( $data );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_GC_SLUG, 'tab' => 'labels', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function delete_label() : void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'manage_options' ) || ! $id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ), 'eurocomply_gc_delete_label_' . $id ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-green-claims' ) );
		}
		LabelStore::delete( $id );
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_GC_SLUG, 'tab' => 'labels', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_license() : void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ), 'eurocomply_gc_save_license' ) ) {
			wp_die( esc_html__( 'Forbidden', 'eurocomply-green-claims' ) );
		}
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		if ( '' === $key ) {
			License::deactivate();
		} else {
			License::activate( $key );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => EUROCOMPLY_GC_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
