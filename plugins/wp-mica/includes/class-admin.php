<?php
/**
 * Admin UI.
 *
 * @package EuroComply\MiCA
 */

declare( strict_types = 1 );

namespace EuroComply\MiCA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG       = 'eurocomply-mica';
	public const NONCE_SAVE      = 'eurocomply_mica_save';
	public const NONCE_LICENSE   = 'eurocomply_mica_license';
	public const NONCE_ASSET     = 'eurocomply_mica_asset';
	public const NONCE_WP        = 'eurocomply_mica_wp';
	public const NONCE_COMM      = 'eurocomply_mica_comm';
	public const NONCE_COMP      = 'eurocomply_mica_comp';
	public const NONCE_DISC      = 'eurocomply_mica_disc';
	public const NONCE_PUB       = 'eurocomply_mica_pub';
	public const NONCE_STEP      = 'eurocomply_mica_step';

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
		add_action( 'admin_post_eurocomply_mica_save',    array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_mica_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_mica_asset',   array( $this, 'handle_asset' ) );
		add_action( 'admin_post_eurocomply_mica_wp',      array( $this, 'handle_whitepaper' ) );
		add_action( 'admin_post_eurocomply_mica_comm',    array( $this, 'handle_comm' ) );
		add_action( 'admin_post_eurocomply_mica_comp',    array( $this, 'handle_complaint' ) );
		add_action( 'admin_post_eurocomply_mica_disc',    array( $this, 'handle_disclosure' ) );
		add_action( 'admin_post_eurocomply_mica_pub',     array( $this, 'handle_publish' ) );
		add_action( 'admin_post_eurocomply_mica_step',    array( $this, 'handle_step' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'MiCA', 'eurocomply-mica' ),
			__( 'MiCA', 'eurocomply-mica' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-money-alt',
			86
		);
	}

	public function enqueue( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'eurocomply-mica-admin', EUROCOMPLY_MICA_URL . 'assets/css/admin.css', array(), EUROCOMPLY_MICA_VERSION );
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap eurocomply-mica">';
		echo '<h1>' . esc_html__( 'EuroComply MiCA', 'eurocomply-mica' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Markets in Crypto-Assets Regulation (EU) 2023/1114. ART/EMT in force from 30 June 2024; rest from 30 December 2024.', 'eurocomply-mica' ) . '</p>';
		settings_errors( 'eurocomply_mica' );

		$tabs = array(
			'dashboard'  => __( 'Dashboard', 'eurocomply-mica' ),
			'assets'     => __( 'Assets',     'eurocomply-mica' ),
			'whitepapers'=> __( 'White papers','eurocomply-mica' ),
			'marketing'  => __( 'Marketing',     'eurocomply-mica' ),
			'complaints' => __( 'Complaints',     'eurocomply-mica' ),
			'insider'    => __( 'Insider info',     'eurocomply-mica' ),
			'reports'    => __( 'Reports',           'eurocomply-mica' ),
			'settings'   => __( 'Settings',          'eurocomply-mica' ),
			'pro'        => __( 'Pro',                'eurocomply-mica' ),
			'license'    => __( 'License',             'eurocomply-mica' ),
		);

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$active = ( $tab === $key ) ? ' nav-tab-active' : '';
			$url    = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $key ), admin_url( 'admin.php' ) );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'assets':      $this->tab_assets();      break;
			case 'whitepapers': $this->tab_whitepapers(); break;
			case 'marketing':   $this->tab_marketing();   break;
			case 'complaints':  $this->tab_complaints();  break;
			case 'insider':     $this->tab_insider();     break;
			case 'reports':     $this->tab_reports();     break;
			case 'settings':    $this->tab_settings();    break;
			case 'pro':         $this->tab_pro();         break;
			case 'license':     $this->tab_license();     break;
			default:            $this->tab_dashboard();
		}
		echo '</div>';
	}

	private function tab_dashboard() : void {
		$s        = Settings::get();
		$a_total  = AssetStore::count_total();
		$a_art    = AssetStore::count_category( 'art' );
		$a_emt    = AssetStore::count_category( 'emt' );
		$a_other  = AssetStore::count_category( 'other' ) + AssetStore::count_category( 'utility' );
		$a_sig    = AssetStore::count_significant();
		$wp_total = WhitepaperStore::count_total();
		$wp_pub   = WhitepaperStore::count_status( 'published' );
		$wp_stand = count( WhitepaperStore::standstill_elapsed( (int) $s['standstill_days'] ) );
		$c_total  = CommunicationStore::count_total();
		$c_unflag = CommunicationStore::count_unflagged();
		$cp_total = ComplaintStore::count_total();
		$cp_open  = ComplaintStore::count_open();
		$cp_ack   = ComplaintStore::ack_overdue( (int) $s['ack_days'] );
		$cp_res   = ComplaintStore::resolution_overdue( (int) $s['resolution_days'] );
		$d_total  = DisclosureStore::count_total();
		$d_pend   = DisclosureStore::count_pending();

		echo '<div class="eurocomply-mica-grid">';
		$this->card( __( 'Entity', 'eurocomply-mica' ), esc_html( (string) $s['entity_name'] ), Settings::entity_type_label( (string) $s['entity_type'] ) );
		$this->card( __( 'Crypto-assets', 'eurocomply-mica' ), (string) $a_total, sprintf( /* translators: %1$d ART, %2$d EMT, %3$d other */ __( 'ART %1$d · EMT %2$d · other %3$d', 'eurocomply-mica' ), $a_art, $a_emt, $a_other ) );
		$this->card( __( 'Significant tokens', 'eurocomply-mica' ), (string) $a_sig, $a_sig > 0 ? __( 'EBA supervisory regime applies', 'eurocomply-mica' ) : '', $a_sig > 0 ? 'warn' : 'plain' );
		$this->card( __( 'White papers', 'eurocomply-mica' ), (string) $wp_total, sprintf( /* translators: %d: published */ __( '%d published', 'eurocomply-mica' ), $wp_pub ) );
		$this->card( __( 'Standstill elapsed (Art. 8(1))', 'eurocomply-mica' ), (string) $wp_stand, $wp_stand > 0 ? __( 'Ready to publish', 'eurocomply-mica' ) : '', $wp_stand > 0 ? 'ok' : 'plain' );
		$this->card( __( 'Marketing comms', 'eurocomply-mica' ), (string) $c_total, sprintf( /* translators: %d: unflagged */ __( '%d missing risk-warning / fair-clear', 'eurocomply-mica' ), $c_unflag ), $c_unflag > 0 ? 'crit' : 'ok' );
		$this->card( __( 'Open complaints', 'eurocomply-mica' ), (string) $cp_open, sprintf( /* translators: %d: total */ __( '%d total', 'eurocomply-mica' ), $cp_total ) );
		$this->card( __( 'Complaints ack overdue', 'eurocomply-mica' ), (string) $cp_ack, $cp_ack > 0 ? sprintf( /* translators: %d: days */ __( '> %d days', 'eurocomply-mica' ), (int) $s['ack_days'] ) : __( 'On schedule', 'eurocomply-mica' ), $cp_ack > 0 ? 'crit' : 'ok' );
		$this->card( __( 'Complaint resolution overdue', 'eurocomply-mica' ), (string) $cp_res, $cp_res > 0 ? sprintf( /* translators: %d: days */ __( '> %d days', 'eurocomply-mica' ), (int) $s['resolution_days'] ) : __( 'On schedule', 'eurocomply-mica' ), $cp_res > 0 ? 'warn' : 'ok' );
		$this->card( __( 'Insider disclosures pending', 'eurocomply-mica' ), (string) $d_pend, sprintf( /* translators: %d: total */ __( '%d total', 'eurocomply-mica' ), $d_total ), $d_pend > 0 ? 'warn' : 'ok' );
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

	private function tab_assets() : void {
		echo '<h2>' . esc_html__( 'Add crypto-asset', 'eurocomply-mica' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_ASSET );
		echo '<input type="hidden" name="action" value="eurocomply_mica_asset" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'name', __( 'Name', 'eurocomply-mica' ), 'text' );
		$this->row( 'ticker', __( 'Ticker', 'eurocomply-mica' ), 'text' );
		$this->select( 'category', __( 'Category', 'eurocomply-mica' ), array( 'art', 'emt', 'utility', 'other' ), 'other' );
		$this->checkbox( 'significant', __( 'Significant (EBA supervision applies)', 'eurocomply-mica' ) );
		$this->row( 'network', __( 'Network', 'eurocomply-mica' ), 'text' );
		$this->row( 'contract_address', __( 'Contract address', 'eurocomply-mica' ), 'text' );
		$this->row( 'isin', __( 'ISIN', 'eurocomply-mica' ), 'text' );
		$this->row( 'pegged_to', __( 'Pegged to', 'eurocomply-mica' ), 'text' );
		$this->row( 'max_supply', __( 'Max supply', 'eurocomply-mica' ), 'text' );
		$this->row( 'circulating', __( 'Circulating supply', 'eurocomply-mica' ), 'text' );
		$this->select( 'status', __( 'Status', 'eurocomply-mica' ), array( 'draft', 'notified', 'live', 'suspended', 'redeemed' ), 'draft' );
		$this->textarea( 'reserve_assets', __( 'Reserve assets composition (ART/EMT)', 'eurocomply-mica' ) );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-mica' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add asset', 'eurocomply-mica' ) );
		echo '</form>';

		$rows = AssetStore::all();
		echo '<h2>' . esc_html__( 'Crypto-asset register', 'eurocomply-mica' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No assets recorded.', 'eurocomply-mica' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Name</th><th>Ticker</th><th>Category</th><th>Significant</th><th>Network</th><th>Status</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['ticker'] ) . '</code></td>';
			echo '<td>' . esc_html( strtoupper( (string) $r['category'] ) ) . '</td>';
			echo '<td>' . ( $r['significant'] ? '⚠' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) $r['network'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_whitepapers() : void {
		$assets = AssetStore::all();
		echo '<h2>' . esc_html__( 'Add white paper', 'eurocomply-mica' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_WP );
		echo '<input type="hidden" name="action" value="eurocomply_mica_wp" />';
		echo '<table class="form-table"><tbody>';
		$this->asset_select( $assets );
		$this->row( 'version', __( 'Version', 'eurocomply-mica' ), 'text', '1.0' );
		$this->select( 'article', __( 'MiCA article', 'eurocomply-mica' ), array( '6', '17', '19', '51' ), '6' );
		$this->row( 'document_url', __( 'Document URL', 'eurocomply-mica' ), 'url' );
		$this->row( 'notified_at',  __( 'Notified to NCA at', 'eurocomply-mica' ), 'datetime-local' );
		$this->row( 'expires_at',   __( 'Expires at',          'eurocomply-mica' ), 'datetime-local' );
		$this->select( 'status', __( 'Status', 'eurocomply-mica' ), array( 'draft', 'notified', 'standstill', 'published', 'withdrawn' ), 'draft' );
		$this->textarea( 'summary', __( 'Summary', 'eurocomply-mica' ) );
		$this->textarea( 'risks',   __( 'Risk warnings', 'eurocomply-mica' ) );
		$this->textarea( 'rights',  __( 'Rights & obligations', 'eurocomply-mica' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add white paper', 'eurocomply-mica' ) );
		echo '</form>';

		$rows = WhitepaperStore::all();
		echo '<h2>' . esc_html__( 'White-paper register', 'eurocomply-mica' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No white papers.', 'eurocomply-mica' ) . '</p>';
			return;
		}
		$s = Settings::get();
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Asset</th><th>Article</th><th>Version</th><th>Notified</th><th>Standstill</th><th>Published</th><th>Status</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$elapsed = '';
			if ( ! empty( $r['notified_at'] ) && empty( $r['published_at'] ) ) {
				$days = (int) floor( ( strtotime( current_time( 'mysql' ) ) - strtotime( (string) $r['notified_at'] ) ) / 86400 );
				$elapsed = sprintf( '%d / %d', $days, (int) $s['standstill_days'] );
			}
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['asset_id'] . '</td>';
			echo '<td>Art. ' . esc_html( (string) $r['article'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['version'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['notified_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( $elapsed ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['published_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '<td>';
			if ( empty( $r['published_at'] ) ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
				wp_nonce_field( self::NONCE_PUB );
				echo '<input type="hidden" name="action" value="eurocomply_mica_pub" />';
				echo '<input type="hidden" name="whitepaper_id" value="' . (int) $r['id'] . '" />';
				submit_button( __( 'Mark published', 'eurocomply-mica' ), 'small', 'submit', false );
				echo '</form>';
			}
			echo ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'eurocomply_mica_export' );
			echo '<input type="hidden" name="action" value="eurocomply_mica_export_xml" />';
			echo '<input type="hidden" name="whitepaper_id" value="' . (int) $r['id'] . '" />';
			submit_button( __( 'XML', 'eurocomply-mica' ), 'small', 'submit', false );
			echo '</form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_marketing() : void {
		$assets = AssetStore::all();
		echo '<h2>' . esc_html__( 'Log marketing communication (Art. 7)', 'eurocomply-mica' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_COMM );
		echo '<input type="hidden" name="action" value="eurocomply_mica_comm" />';
		echo '<table class="form-table"><tbody>';
		$this->asset_select( $assets );
		$this->select( 'channel', __( 'Channel', 'eurocomply-mica' ), array( 'website', 'social', 'email', 'press', 'paid_ads', 'influencer', 'event', 'tv_radio', 'other' ), 'website' );
		$this->row( 'audience', __( 'Audience',  'eurocomply-mica' ), 'text', 'general' );
		$this->row( 'country',  __( 'Country',   'eurocomply-mica' ), 'text' );
		$this->row( 'language', __( 'Language',  'eurocomply-mica' ), 'text' );
		$this->row( 'published_at', __( 'Published at', 'eurocomply-mica' ), 'datetime-local' );
		$this->row( 'withdrawn_at', __( 'Withdrawn at',  'eurocomply-mica' ), 'datetime-local' );
		$this->checkbox( 'risk_warning', __( 'Includes Art. 7 risk warning', 'eurocomply-mica' ) );
		$this->checkbox( 'fair_clear',   __( 'Fair, clear, not misleading',     'eurocomply-mica' ) );
		$this->row( 'content_url', __( 'Content URL / archive', 'eurocomply-mica' ), 'url' );
		$this->textarea( 'notes', __( 'Notes', 'eurocomply-mica' ) );
		echo '</tbody></table>';
		submit_button( __( 'Log communication', 'eurocomply-mica' ) );
		echo '</form>';

		$rows = CommunicationStore::all();
		echo '<h2>' . esc_html__( 'Marketing communications', 'eurocomply-mica' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No communications recorded.', 'eurocomply-mica' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Channel</th><th>Country</th><th>Lang</th><th>Risk</th><th>Fair</th><th>Published</th><th>Withdrawn</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['channel'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['language'] ) . '</td>';
			echo '<td>' . ( $r['risk_warning'] ? '✓' : '✗' ) . '</td>';
			echo '<td>' . ( $r['fair_clear']   ? '✓' : '✗' ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['published_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['withdrawn_at'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_complaints() : void {
		$assets = AssetStore::all();
		echo '<h2>' . esc_html__( 'Record complaint (Art. 31)', 'eurocomply-mica' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_COMP );
		echo '<input type="hidden" name="action" value="eurocomply_mica_comp" />';
		echo '<table class="form-table"><tbody>';
		$this->asset_select( $assets );
		$this->row( 'received_at', __( 'Received at', 'eurocomply-mica' ), 'datetime-local' );
		$this->row( 'category',    __( 'Category',     'eurocomply-mica' ), 'text', 'service_quality' );
		$this->row( 'country',     __( 'Country',      'eurocomply-mica' ), 'text' );
		$this->row( 'complainant_ref', __( 'Complainant reference (hashed)', 'eurocomply-mica' ), 'text' );
		$this->select( 'status', __( 'Status', 'eurocomply-mica' ), array( 'received', 'investigating', 'resolved', 'rejected', 'escalated' ), 'received' );
		$this->textarea( 'summary', __( 'Summary', 'eurocomply-mica' ) );
		$this->textarea( 'outcome', __( 'Outcome', 'eurocomply-mica' ) );
		echo '</tbody></table>';
		submit_button( __( 'Add complaint', 'eurocomply-mica' ) );
		echo '</form>';

		$rows = ComplaintStore::all();
		echo '<h2>' . esc_html__( 'Complaint register', 'eurocomply-mica' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No complaints recorded.', 'eurocomply-mica' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Received</th><th>Category</th><th>Country</th><th>Status</th><th>Ack</th><th>Resolved</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . esc_html( (string) ( $r['received_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $r['category'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['status'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['ack_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['resolved_at'] ?? '' ) ) . '</td>';
			echo '<td>';
			foreach ( array( 'ack' => __( 'Mark ack', 'eurocomply-mica' ), 'resolved' => __( 'Mark resolved', 'eurocomply-mica' ) ) as $step => $label ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
				wp_nonce_field( self::NONCE_STEP );
				echo '<input type="hidden" name="action" value="eurocomply_mica_step" />';
				echo '<input type="hidden" name="complaint_id" value="' . (int) $r['id'] . '" />';
				echo '<input type="hidden" name="step" value="' . esc_attr( $step ) . '" />';
				submit_button( $label, 'small', 'submit', false );
				echo '</form> ';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_insider() : void {
		$assets = AssetStore::all();
		echo '<h2>' . esc_html__( 'Log disclosure (Art. 87–88)', 'eurocomply-mica' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_DISC );
		echo '<input type="hidden" name="action" value="eurocomply_mica_disc" />';
		echo '<table class="form-table"><tbody>';
		$this->asset_select( $assets );
		$this->select( 'kind', __( 'Kind', 'eurocomply-mica' ), array( 'insider', 'market_abuse', 'suspicious_order', 'self_dealing', 'other' ), 'insider' );
		$this->row( 'occurred_at',   __( 'Occurred at',  'eurocomply-mica' ), 'datetime-local' );
		$this->row( 'disclosed_at',  __( 'Disclosed at', 'eurocomply-mica' ), 'datetime-local' );
		$this->row( 'delayed_until', __( 'Delayed until (Art. 88)', 'eurocomply-mica' ), 'datetime-local' );
		$this->row( 'channel', __( 'Channel', 'eurocomply-mica' ), 'text', 'website' );
		$this->checkbox( 'notified_nca', __( 'Notified NCA', 'eurocomply-mica' ) );
		$this->textarea( 'summary',       __( 'Summary',       'eurocomply-mica' ) );
		$this->textarea( 'justification', __( 'Justification (delay)', 'eurocomply-mica' ) );
		echo '</tbody></table>';
		submit_button( __( 'Log disclosure', 'eurocomply-mica' ) );
		echo '</form>';

		$rows = DisclosureStore::all();
		echo '<h2>' . esc_html__( 'Disclosure log', 'eurocomply-mica' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No disclosures.', 'eurocomply-mica' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>Asset</th><th>Kind</th><th>Occurred</th><th>Disclosed</th><th>Delayed until</th><th>NCA</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . (int) $r['id'] . '</td>';
			echo '<td>' . (int) $r['asset_id'] . '</td>';
			echo '<td>' . esc_html( (string) $r['kind'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['occurred_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['disclosed_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['delayed_until'] ?? '' ) ) . '</td>';
			echo '<td>' . ( $r['notified_nca'] ? '✓' : '' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function tab_reports() : void {
		echo '<h2>' . esc_html__( 'Export', 'eurocomply-mica' ) . '</h2>';
		foreach ( array(
			'assets'      => __( 'Assets',      'eurocomply-mica' ),
			'whitepapers' => __( 'White papers', 'eurocomply-mica' ),
			'comms'       => __( 'Marketing',     'eurocomply-mica' ),
			'complaints'  => __( 'Complaints',     'eurocomply-mica' ),
			'disclosures' => __( 'Disclosures',     'eurocomply-mica' ),
		) as $ds => $label ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;margin-bottom:8px">';
			wp_nonce_field( 'eurocomply_mica_export' );
			echo '<input type="hidden" name="action"  value="eurocomply_mica_export" />';
			echo '<input type="hidden" name="dataset" value="' . esc_attr( $ds ) . '" />';
			submit_button( sprintf( /* translators: %s: dataset */ __( 'CSV: %s', 'eurocomply-mica' ), $label ), 'secondary', 'submit', false );
			echo '</form>';
		}
	}

	private function tab_settings() : void {
		$s = Settings::get();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<input type="hidden" name="action" value="eurocomply_mica_save" />';
		echo '<table class="form-table"><tbody>';
		$this->row( 'entity_name',        __( 'Entity name', 'eurocomply-mica' ), 'text', (string) $s['entity_name'] );
		$this->row( 'entity_lei',         __( 'LEI',          'eurocomply-mica' ), 'text', (string) $s['entity_lei'] );
		$this->row( 'entity_country',     __( 'Country (ISO-2)', 'eurocomply-mica' ), 'text', (string) $s['entity_country'] );
		echo '<tr><th><label for="entity_type">' . esc_html__( 'Entity type', 'eurocomply-mica' ) . '</label></th><td><select name="entity_type" id="entity_type">';
		foreach ( Settings::entity_types() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['entity_type'], $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->row( 'home_nca',           __( 'Home NCA', 'eurocomply-mica' ),       'text',  (string) $s['home_nca'] );
		$this->row( 'home_nca_email',     __( 'Home NCA email', 'eurocomply-mica' ), 'email', (string) $s['home_nca_email'] );
		$this->row( 'compliance_officer', __( 'Compliance officer email', 'eurocomply-mica' ), 'email', (string) $s['compliance_officer'] );
		$this->row( 'standstill_days',    __( 'Standstill (days, Art. 8)', 'eurocomply-mica' ), 'number', (string) $s['standstill_days'] );
		$this->row( 'ack_days',           __( 'Complaint ack (days)',          'eurocomply-mica' ), 'number', (string) $s['ack_days'] );
		$this->row( 'resolution_days',    __( 'Complaint resolution (days)',     'eurocomply-mica' ), 'number', (string) $s['resolution_days'] );
		$this->row( 'reporting_year',     __( 'Reporting year',                    'eurocomply-mica' ), 'number', (string) $s['reporting_year'] );
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function tab_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-mica' ) . '</h2>';
		echo '<ul style="list-style:disc;padding-left:18px">';
		foreach ( array(
			__( 'Live NCA submission of white papers + standstill timer',                                'eurocomply-mica' ),
			__( 'Reserve-of-assets snapshot scheduling for ART/EMT issuers (Art. 36–38)',                  'eurocomply-mica' ),
			__( 'Authorisation register sync (CASP / ART / EMT) — auto-pull ESMA register',                  'eurocomply-mica' ),
			__( 'Signed PDF white papers + audit bundle',                                                       'eurocomply-mica' ),
			__( 'REST + webhooks for SIEM / portfolio-manager forwarding',                                       'eurocomply-mica' ),
			__( 'Marketing-comms compliance scanner (Art. 7 risk-warning + fair-clear-misleading detection)',     'eurocomply-mica' ),
			__( 'Complaint-handling SLAs with Slack / Teams alerts on ack overdue',                                'eurocomply-mica' ),
			__( 'Insider-information delay-justification workflow (Art. 88)',                                       'eurocomply-mica' ),
			__( 'Suspicious-order / market-abuse rule engine (TFR-style transaction monitoring)',                     'eurocomply-mica' ),
			__( 'EBA significant-token threshold tracker (Art. 43)',                                                   'eurocomply-mica' ),
			__( 'WPML / Polylang for multi-language white papers',                                                       'eurocomply-mica' ),
			__( 'Multi-site network aggregator',                                                                            'eurocomply-mica' ),
			__( '5,000-row CSV cap',                                                                                          'eurocomply-mica' ),
		) as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}

	private function tab_license() : void {
		$d      = License::get();
		$active = License::is_pro();
		echo '<h2>' . esc_html__( 'License', 'eurocomply-mica' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<input type="hidden" name="action" value="eurocomply_mica_license" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-mica' ) . '</label></th><td><input type="text" id="license_key" name="license_key" value="' . esc_attr( (string) ( $d['key'] ?? '' ) ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-mica' ) . '</th><td>' . ( $active ? '<span class="eurocomply-pill eurocomply-pill-ok">' . esc_html__( 'Active', 'eurocomply-mica' ) . '</span>' : '<span class="eurocomply-pill eurocomply-pill-warn">' . esc_html__( 'Inactive', 'eurocomply-mica' ) . '</span>' ) . '</td></tr>';
		echo '</tbody></table>';
		submit_button( $active ? __( 'Deactivate', 'eurocomply-mica' ) : __( 'Activate', 'eurocomply-mica' ), 'primary', $active ? 'deactivate' : 'activate' );
		echo '</form>';
	}

	// --- POST handlers ----------------------------------------------------

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_SAVE );
		$clean = Settings::sanitize( wp_unslash( $_POST ) );
		update_option( Settings::OPTION_KEY, $clean, false );
		add_settings_error( 'eurocomply_mica', 'eurocomply_mica_saved', __( 'Settings saved.', 'eurocomply-mica' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_LICENSE );
		if ( isset( $_POST['deactivate'] ) ) {
			License::deactivate();
			add_settings_error( 'eurocomply_mica', 'eurocomply_mica_lic', __( 'License deactivated.', 'eurocomply-mica' ), 'updated' );
		} else {
			$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_mica', 'eurocomply_mica_lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_asset() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_ASSET );
		AssetStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_mica', 'eurocomply_mica_a', __( 'Asset recorded.', 'eurocomply-mica' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'assets', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_whitepaper() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_WP );
		WhitepaperStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_mica', 'eurocomply_mica_wp', __( 'White paper recorded.', 'eurocomply-mica' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'whitepapers', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_comm() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_COMM );
		CommunicationStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_mica', 'eurocomply_mica_c', __( 'Communication logged.', 'eurocomply-mica' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'marketing', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_complaint() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_COMP );
		ComplaintStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_mica', 'eurocomply_mica_cp', __( 'Complaint recorded.', 'eurocomply-mica' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'complaints', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_disclosure() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_DISC );
		DisclosureStore::create( wp_unslash( $_POST ) );
		add_settings_error( 'eurocomply_mica', 'eurocomply_mica_d', __( 'Disclosure logged.', 'eurocomply-mica' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'insider', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_publish() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_PUB );
		$id = isset( $_POST['whitepaper_id'] ) ? (int) $_POST['whitepaper_id'] : 0;
		WhitepaperStore::mark_published( $id );
		add_settings_error( 'eurocomply_mica', 'eurocomply_mica_pub', __( 'White paper marked published.', 'eurocomply-mica' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'whitepapers', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_step() : void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-mica' ), 403 ); }
		check_admin_referer( self::NONCE_STEP );
		$id   = isset( $_POST['complaint_id'] ) ? (int) $_POST['complaint_id'] : 0;
		$step = isset( $_POST['step'] )         ? sanitize_key( (string) $_POST['step'] ) : '';
		ComplaintStore::mark_step( $id, $step );
		add_settings_error( 'eurocomply_mica', 'eurocomply_mica_st', __( 'Complaint step marked.', 'eurocomply-mica' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'complaints', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// --- helpers ----------------------------------------------------------

	private function asset_select( array $assets ) : void {
		echo '<tr><th><label for="asset_id">' . esc_html__( 'Linked asset', 'eurocomply-mica' ) . '</label></th><td><select name="asset_id" id="asset_id"><option value="0">' . esc_html__( '— none —', 'eurocomply-mica' ) . '</option>';
		foreach ( $assets as $a ) {
			echo '<option value="' . (int) $a['id'] . '">#' . (int) $a['id'] . ' · ' . esc_html( (string) $a['name'] ) . ' (' . esc_html( strtoupper( (string) $a['category'] ) ) . ')</option>';
		}
		echo '</select></td></tr>';
	}

	private function row( string $name, string $label, string $type = 'text', string $default = '' ) : void {
		echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" class="regular-text" value="' . esc_attr( $default ) . '" /></td></tr>';
	}

	private function checkbox( string $name, string $label, bool $checked = false ) : void {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . ' /> ' . esc_html__( 'Yes', 'eurocomply-mica' ) . '</label></td></tr>';
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
