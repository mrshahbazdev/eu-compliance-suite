<?php
/**
 * Admin UI.
 *
 * @package EuroComply\PSD2
 */

declare( strict_types = 1 );

namespace EuroComply\PSD2;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG          = 'eurocomply-psd2-sca';
	private const NONCE_SAVE        = 'eurocomply_psd2_save';
	private const NONCE_LICENSE     = 'eurocomply_psd2_license';
	private const NONCE_DECIDE      = 'eurocomply_psd2_decide';
	private const NONCE_TRANSACTION = 'eurocomply_psd2_transaction';
	private const NONCE_CONSENT     = 'eurocomply_psd2_consent';
	private const NONCE_TPP         = 'eurocomply_psd2_tpp';
	private const NONCE_FRAUD       = 'eurocomply_psd2_fraud';

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
		add_action( 'admin_post_eurocomply_psd2_save',        array( $this, 'handle_save' ) );
		add_action( 'admin_post_eurocomply_psd2_license',     array( $this, 'handle_license' ) );
		add_action( 'admin_post_eurocomply_psd2_decide',      array( $this, 'handle_decide' ) );
		add_action( 'admin_post_eurocomply_psd2_transaction', array( $this, 'handle_transaction' ) );
		add_action( 'admin_post_eurocomply_psd2_consent',     array( $this, 'handle_consent' ) );
		add_action( 'admin_post_eurocomply_psd2_tpp',         array( $this, 'handle_tpp' ) );
		add_action( 'admin_post_eurocomply_psd2_fraud',       array( $this, 'handle_fraud' ) );
	}

	public function menu() : void {
		add_menu_page(
			__( 'PSD2 / SCA', 'eurocomply-psd2-sca' ),
			__( 'PSD2 / SCA', 'eurocomply-psd2-sca' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-shield-alt',
			82
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-psd2-admin',
			EUROCOMPLY_PSD2_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_PSD2_VERSION
		);
	}

	public function render() : void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard';
		echo '<div class="wrap eurocomply-psd2-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply PSD2 / SCA', 'eurocomply-psd2-sca' ) . '</h1>';
		settings_errors();
		$this->tabs( $tab );
		switch ( $tab ) {
			case 'sca':          $this->render_sca();          break;
			case 'transactions': $this->render_transactions(); break;
			case 'consents':     $this->render_consents();     break;
			case 'tpps':         $this->render_tpps();         break;
			case 'fraud':        $this->render_fraud();        break;
			case 'reports':      $this->render_reports();      break;
			case 'settings':     $this->render_settings();     break;
			case 'pro':          $this->render_pro();          break;
			case 'license':      $this->render_license();      break;
			case 'dashboard':
			default:             $this->render_dashboard();    break;
		}
		echo '</div>';
	}

	private function tabs( string $current ) : void {
		$tabs = array(
			'dashboard'    => __( 'Dashboard',    'eurocomply-psd2-sca' ),
			'sca'          => __( 'SCA decision', 'eurocomply-psd2-sca' ),
			'transactions' => __( 'Transactions', 'eurocomply-psd2-sca' ),
			'consents'     => __( 'Consents',     'eurocomply-psd2-sca' ),
			'tpps'         => __( 'TPPs',         'eurocomply-psd2-sca' ),
			'fraud'        => __( 'Fraud',        'eurocomply-psd2-sca' ),
			'reports'      => __( 'Reports',      'eurocomply-psd2-sca' ),
			'settings'     => __( 'Settings',     'eurocomply-psd2-sca' ),
			'pro'          => __( 'Pro',          'eurocomply-psd2-sca' ),
			'license'      => __( 'License',      'eurocomply-psd2-sca' ),
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
		$cls = 'eurocomply-psd2-card-stat' . ( '' !== $tone ? ' eurocomply-psd2-card-' . $tone : '' );
		echo '<div class="' . esc_attr( $cls ) . '">';
		echo '<div class="val">' . esc_html( $value ) . '</div>';
		echo '<div class="lbl">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	private function render_dashboard() : void {
		$s        = Settings::get();
		$period   = (string) $s['reporting_period'];
		$tx_count = TransactionStore::count_for_period( $period );
		$fail     = TransactionStore::challenge_failure_rate( $period );
		$exempts  = TransactionStore::exemption_breakdown( $period );
		$fraud_n  = FraudStore::count_for_period( $period );
		$fraud_v  = FraudStore::fraud_value( $period );
		$refund   = FraudStore::refund_compliance( $period );
		$consents = ConsentStore::count_active();
		$expired  = ConsentStore::count_expired();
		$tpps     = TppStore::count_total();

		echo '<div class="eurocomply-psd2-cards">';
		$this->card( __( 'Reporting period', 'eurocomply-psd2-sca' ), $period );
		$this->card( __( 'Transactions',     'eurocomply-psd2-sca' ), (string) $tx_count, $tx_count > 0 ? 'ok' : 'warn' );
		$this->card( __( 'Challenge failure rate', 'eurocomply-psd2-sca' ), ( $fail * 100 ) . '%', $fail < 0.05 ? 'ok' : ( $fail < 0.10 ? 'warn' : 'crit' ) );
		$this->card( __( 'TRA-exempt txns', 'eurocomply-psd2-sca' ), (string) ( $exempts['tra']['count'] ?? 0 ) );
		$this->card( __( 'Fraud events',    'eurocomply-psd2-sca' ), (string) $fraud_n, $fraud_n === 0 ? 'ok' : ( $fraud_n < 5 ? 'warn' : 'crit' ) );
		$this->card( __( 'Fraud value (€)', 'eurocomply-psd2-sca' ), number_format( $fraud_v, 2 ) );
		$this->card( __( 'Refund on-time (Art. 73)', 'eurocomply-psd2-sca' ), ( $refund * 100 ) . '%', $refund >= 0.95 ? 'ok' : 'crit' );
		$this->card( __( 'Active PSU consents', 'eurocomply-psd2-sca' ), (string) $consents );
		$this->card( __( 'Expired consents', 'eurocomply-psd2-sca' ), (string) $expired, 0 === $expired ? 'ok' : 'warn' );
		$this->card( __( 'TPPs registered', 'eurocomply-psd2-sca' ), (string) $tpps );
		echo '</div>';

		echo '<div class="eurocomply-psd2-info">';
		echo '<p>' . esc_html__( 'EuroComply PSD2 is a compliance toolkit, not a payment processor. Configure your PSP (Stripe / Adyen / Mollie / etc.) to forward SCA + 3-DS2 + fraud events to this plugin via webhooks (Pro) or via custom WC payment gateway hooks.', 'eurocomply-psd2-sca' ) . '</p>';
		echo '</div>';
	}

	private function render_sca() : void {
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<h2>' . esc_html__( 'SCA decision sandbox', 'eurocomply-psd2-sca' ) . '</h2>';
		echo '<p>' . esc_html__( 'Try a hypothetical transaction against the SCA RTS exemption library — see whether SCA is required and which exemption applies.', 'eurocomply-psd2-sca' ) . '</p>';

		$decision = isset( $_POST['decision'] ) && is_array( $_POST['decision'] ) ? (array) $_POST['decision'] : null;

		echo '<form method="post">';
		wp_nonce_field( self::NONCE_DECIDE );
		echo '<input type="hidden" name="action" value="eurocomply_psd2_decide_inline" />';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Amount (EUR)', 'eurocomply-psd2-sca' ) . '</th><td><input type="number" step="0.01" min="0" name="tx[amount]" value="' . esc_attr( (string) ( $_POST['tx']['amount'] ?? '50' ) ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Cumulative since last SCA (EUR)', 'eurocomply-psd2-sca' ) . '</th><td><input type="number" step="0.01" min="0" name="tx[cumulative_since_sca]" value="' . esc_attr( (string) ( $_POST['tx']['cumulative_since_sca'] ?? '0' ) ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Payments since last SCA', 'eurocomply-psd2-sca' ) . '</th><td><input type="number" min="0" name="tx[payments_since_sca]" value="' . esc_attr( (string) ( $_POST['tx']['payments_since_sca'] ?? '0' ) ) . '" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Real-time TRA fraud rate (decimal, e.g. 0.0005)', 'eurocomply-psd2-sca' ) . '</th><td><input type="number" step="0.00001" min="0" name="tx[tra_score]" value="' . esc_attr( (string) ( $_POST['tx']['tra_score'] ?? '0.0005' ) ) . '" /></td></tr>';
		foreach ( array(
			'recurring'           => __( 'Recurring of same amount', 'eurocomply-psd2-sca' ),
			'merchant_initiated'  => __( 'Merchant-initiated',         'eurocomply-psd2-sca' ),
			'trusted_beneficiary' => __( 'Trusted beneficiary',        'eurocomply-psd2-sca' ),
			'corporate'           => __( 'Secure corporate payment',   'eurocomply-psd2-sca' ),
		) as $k => $lab ) {
			echo '<tr><th>' . esc_html( $lab ) . '</th><td><label><input type="checkbox" name="tx[' . esc_attr( $k ) . ']" value="1"' . checked( ! empty( $_POST['tx'][ $k ] ), true, false ) . ' /></label></td></tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Decide', 'eurocomply-psd2-sca' ) );
		echo '</form>';

		if ( $decision ) {
			$class = ! empty( $decision['required'] ) ? 'crit' : 'ok';
			echo '<div class="eurocomply-psd2-info">';
			echo '<p><span class="eurocomply-psd2-pill ' . esc_attr( $class ) . '">' . ( ! empty( $decision['required'] ) ? esc_html__( 'SCA REQUIRED', 'eurocomply-psd2-sca' ) : esc_html__( 'EXEMPT', 'eurocomply-psd2-sca' ) ) . '</span> ';
			echo esc_html( (string) $decision['reason'] ) . '</p>';
			if ( ! empty( $decision['exemption'] ) ) {
				$ex = ScaRules::exemptions()[ (string) $decision['exemption'] ] ?? null;
				if ( $ex ) {
					echo '<p><strong>' . esc_html( $ex['name'] ) . '</strong> — ' . esc_html( $ex['description'] ) . '</p>';
				}
			}
			echo '</div>';
		}

		echo '<h2>' . esc_html__( 'Exemption library', 'eurocomply-psd2-sca' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Code',    'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Article', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Name',    'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Description', 'eurocomply-psd2-sca' ) . '</th></tr></thead><tbody>';
		foreach ( ScaRules::exemptions() as $code => $info ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $code ) . '</code></td>';
			echo '<td>' . esc_html( (string) $info['article'] ) . '</td>';
			echo '<td>' . esc_html( (string) $info['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $info['description'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_transactions() : void {
		$rows   = TransactionStore::recent( 100 );
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_psd2_export" />';
		echo '<input type="hidden" name="dataset" value="transactions" />';
		wp_nonce_field( 'eurocomply_psd2_export' );
		submit_button( __( 'Export transactions CSV', 'eurocomply-psd2-sca' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Period', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Order', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Amount', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'SCA', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Exemption', 'eurocomply-psd2-sca' ) . '</th><th>3DS</th><th>' . esc_html__( 'Outcome', 'eurocomply-psd2-sca' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No transactions yet.', 'eurocomply-psd2-sca' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['period'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['order_ref'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['amount'] . ' ' . $r['currency'] ) . '</td>';
			echo '<td>' . ( (int) $r['sca_required'] === 1 ? '<span class="eurocomply-psd2-pill crit">' . esc_html__( 'required', 'eurocomply-psd2-sca' ) . '</span>' : '<span class="eurocomply-psd2-pill ok">' . esc_html__( 'exempt', 'eurocomply-psd2-sca' ) . '</span>' ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['exemption'] ?: '—' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $r['three_ds_status'] ?: '—' ) ) . ' ' . esc_html( (string) $r['three_ds_version'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['outcome'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add transaction', 'eurocomply-psd2-sca' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_psd2_transaction" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_TRANSACTION );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Order reference', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[order_ref]" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Amount', 'eurocomply-psd2-sca' ) . '</th><td><input type="number" step="0.01" name="row[amount]" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Currency', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[currency]" maxlength="3" value="EUR" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Provider', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[provider]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'SCA required?', 'eurocomply-psd2-sca' ) . '</th><td><input type="checkbox" name="row[sca_required]" value="1" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Exemption code', 'eurocomply-psd2-sca' ) . '</th><td><select name="row[exemption]"><option value="">—</option>';
		foreach ( ScaRules::exemptions() as $code => $info ) {
			echo '<option value="' . esc_attr( $code ) . '">' . esc_html( (string) $info['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>3DS</th><td><select name="row[three_ds_status]"><option value="">—</option><option value="frictionless">frictionless</option><option value="challenge_passed">challenge_passed</option><option value="challenge_failed">challenge_failed</option><option value="abandoned">abandoned</option><option value="not_attempted">not_attempted</option></select> <input type="text" name="row[three_ds_version]" placeholder="2.2.0" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Outcome', 'eurocomply-psd2-sca' ) . '</th><td><select name="row[outcome]"><option value="authorised">authorised</option><option value="declined">declined</option><option value="failed">failed</option><option value="abandoned">abandoned</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Country', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[country]" maxlength="2" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Risk score', 'eurocomply-psd2-sca' ) . '</th><td><input type="number" step="0.0001" min="0" name="row[risk_score]" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add transaction', 'eurocomply-psd2-sca' ) );
		echo '</form>';
	}

	private function render_consents() : void {
		$rows   = ConsentStore::recent( 100 );
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_psd2_export" />';
		echo '<input type="hidden" name="dataset" value="consents" />';
		wp_nonce_field( 'eurocomply_psd2_export' );
		submit_button( __( 'Export consents CSV', 'eurocomply-psd2-sca' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Subject', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'TPP', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Scope', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Created', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Expires', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Status', 'eurocomply-psd2-sca' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No consents yet.', 'eurocomply-psd2-sca' ) . '</td></tr>';
		}
		$now = current_time( 'mysql' );
		foreach ( $rows as $r ) {
			$expired = strtotime( (string) $r['expires_at'] ) < strtotime( $now );
			$pill    = (int) $r['revoked'] === 1 ? array( 'crit', 'revoked' ) : ( $expired ? array( 'warn', 'expired' ) : array( 'ok', 'active' ) );
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['subject'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['tpp_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['scope'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['created_at'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['expires_at'] ) . '</td>';
			echo '<td><span class="eurocomply-psd2-pill ' . esc_attr( $pill[0] ) . '">' . esc_html( $pill[1] ) . '</span></td>';
			echo '<td>';
			if ( (int) $r['revoked'] === 0 ) {
				echo '<form method="post" action="' . $action . '" style="display:inline">';
				echo '<input type="hidden" name="action" value="eurocomply_psd2_consent" />';
				echo '<input type="hidden" name="op"     value="revoke" />';
				echo '<input type="hidden" name="cid"    value="' . esc_attr( (string) $r['id'] ) . '" />';
				wp_nonce_field( self::NONCE_CONSENT );
				submit_button( __( 'Revoke', 'eurocomply-psd2-sca' ), 'small', 'submit', false );
				echo '</form>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Issue PSU consent', 'eurocomply-psd2-sca' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_psd2_consent" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_CONSENT );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Subject (PSU id / customer ref)', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[subject]" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'TPP id', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[tpp_id]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Scope', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[scope]" placeholder="accounts:read,balances,transactions" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'TTL (days, 1–180; default 90 per Art. 10 RTS)', 'eurocomply-psd2-sca' ) . '</th><td><input type="number" min="1" max="180" name="row[ttl_days]" value="90" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Issue consent', 'eurocomply-psd2-sca' ) );
		echo '</form>';
	}

	private function render_tpps() : void {
		$rows   = TppStore::all();
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_psd2_export" />';
		echo '<input type="hidden" name="dataset" value="tpps" />';
		wp_nonce_field( 'eurocomply_psd2_export' );
		submit_button( __( 'Export TPPs CSV', 'eurocomply-psd2-sca' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Country', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Name', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Role', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Auth ID', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Authority', 'eurocomply-psd2-sca' ) . '</th><th></th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No TPPs in directory yet.', 'eurocomply-psd2-sca' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['country'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['name'] ) . ( '' !== (string) $r['website'] ? ' <a href="' . esc_url( (string) $r['website'] ) . '" target="_blank" rel="noopener">↗</a>' : '' ) . '</td>';
			echo '<td><code>' . esc_html( (string) $r['role'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $r['authorisation_id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['competent_authority'] ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . $action . '" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Delete TPP?', 'eurocomply-psd2-sca' ) ) . '\');">';
			echo '<input type="hidden" name="action" value="eurocomply_psd2_tpp" />';
			echo '<input type="hidden" name="op"     value="delete" />';
			echo '<input type="hidden" name="tpp_id" value="' . esc_attr( (string) $r['id'] ) . '" />';
			wp_nonce_field( self::NONCE_TPP );
			submit_button( __( 'Delete', 'eurocomply-psd2-sca' ), 'delete small', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Add TPP', 'eurocomply-psd2-sca' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_psd2_tpp" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_TPP );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Country (ISO-2)', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[country]" maxlength="2" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Name', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[name]" class="regular-text" required /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Role', 'eurocomply-psd2-sca' ) . '</th><td><select name="row[role]"><option value="AISP">AISP</option><option value="PISP">PISP</option><option value="CBPII">CBPII</option><option value="PSP">PSP</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Authorisation ID', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[authorisation_id]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Competent authority', 'eurocomply-psd2-sca' ) . '</th><td><input type="text" name="row[competent_authority]" class="regular-text" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Contact email', 'eurocomply-psd2-sca' ) . '</th><td><input type="email" name="row[contact_email]" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Website', 'eurocomply-psd2-sca' ) . '</th><td><input type="url" name="row[website]" class="regular-text" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Add TPP', 'eurocomply-psd2-sca' ) );
		echo '</form>';
	}

	private function render_fraud() : void {
		$rows   = FraudStore::recent( 100 );
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<form method="post" action="' . $action . '" style="margin-bottom:1em;">';
		echo '<input type="hidden" name="action"  value="eurocomply_psd2_export" />';
		echo '<input type="hidden" name="dataset" value="fraud" />';
		wp_nonce_field( 'eurocomply_psd2_export' );
		submit_button( __( 'Export fraud CSV', 'eurocomply-psd2-sca' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Period', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Category', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Channel', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Amount', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'Reimbursed', 'eurocomply-psd2-sca' ) . '</th><th>' . esc_html__( 'On-time?', 'eurocomply-psd2-sca' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No fraud events yet.', 'eurocomply-psd2-sca' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $r['id'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['period'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['category'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['channel'] ) . '</td>';
			echo '<td>' . esc_html( (string) $r['amount'] . ' ' . $r['currency'] ) . '</td>';
			echo '<td>' . ( (int) $r['reimbursed'] === 1 ? esc_html__( 'yes', 'eurocomply-psd2-sca' ) : '—' ) . '</td>';
			echo '<td>' . ( (int) $r['refunded_within_window'] === 1 ? '<span class="eurocomply-psd2-pill ok">' . esc_html__( 'on-time', 'eurocomply-psd2-sca' ) . '</span>' : '<span class="eurocomply-psd2-pill warn">' . esc_html__( 'late', 'eurocomply-psd2-sca' ) . '</span>' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Record fraud event', 'eurocomply-psd2-sca' ) . '</h2>';
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_psd2_fraud" />';
		echo '<input type="hidden" name="op"     value="create" />';
		wp_nonce_field( self::NONCE_FRAUD );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Category', 'eurocomply-psd2-sca' ) . '</th><td><select name="row[category]"><option value="card_lost_stolen">card_lost_stolen</option><option value="counterfeit">counterfeit</option><option value="not_received">not_received</option><option value="cnp_fraud">cnp_fraud</option><option value="manipulation">manipulation</option><option value="phishing">phishing</option><option value="other">other</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Channel', 'eurocomply-psd2-sca' ) . '</th><td><select name="row[channel]"><option value="remote_card">remote_card</option><option value="card_present">card_present</option><option value="credit_transfer">credit_transfer</option><option value="direct_debit">direct_debit</option><option value="emoney">emoney</option></select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Amount', 'eurocomply-psd2-sca' ) . '</th><td><input type="number" step="0.01" name="row[amount]" required /> <input type="text" name="row[currency]" maxlength="3" value="EUR" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Reimbursed', 'eurocomply-psd2-sca' ) . '</th><td><input type="checkbox" name="row[reimbursed]" value="1" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Refunded within Art. 73 window?', 'eurocomply-psd2-sca' ) . '</th><td><input type="checkbox" name="row[refunded_within_window]" value="1" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Notes', 'eurocomply-psd2-sca' ) . '</th><td><textarea name="row[notes]" rows="3" class="large-text"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Record fraud event', 'eurocomply-psd2-sca' ) );
		echo '</form>';
	}

	private function render_reports() : void {
		$s      = Settings::get();
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<h2>' . esc_html__( 'Quarterly fraud report (Art. 96(6) PSD2)', 'eurocomply-psd2-sca' ) . '</h2>';
		echo '<p>' . esc_html__( 'Aggregates transactions + fraud events for a YYYY-Qn period and produces an XML envelope (urn:psd2:eurocomply:0.1) plus a JSON payload.', 'eurocomply-psd2-sca' ) . '</p>';

		echo '<form method="post" action="' . $action . '" style="margin-right:8px;display:inline">';
		echo '<input type="hidden" name="action" value="eurocomply_psd2_export_xml" />';
		echo '<input type="text" name="period" value="' . esc_attr( (string) $s['reporting_period'] ) . '" pattern="\\d{4}-Q[1-4]" /> ';
		wp_nonce_field( 'eurocomply_psd2_export' );
		submit_button( __( 'Download XML', 'eurocomply-psd2-sca' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . $action . '" style="display:inline">';
		echo '<input type="hidden" name="action" value="eurocomply_psd2_export_json" />';
		echo '<input type="hidden" name="period" value="' . esc_attr( (string) $s['reporting_period'] ) . '" />';
		wp_nonce_field( 'eurocomply_psd2_export' );
		submit_button( __( 'Download JSON (current period)', 'eurocomply-psd2-sca' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		$action = esc_url( admin_url( 'admin-post.php' ) );
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="action" value="eurocomply_psd2_save" />';
		wp_nonce_field( self::NONCE_SAVE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="psp_name">' . esc_html__( 'PSP / merchant name', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="text" id="psp_name" name="eurocomply_psd2[psp_name]" value="' . esc_attr( (string) $s['psp_name'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="psp_country">' . esc_html__( 'Country (ISO-2)', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="text" id="psp_country" name="eurocomply_psd2[psp_country]" value="' . esc_attr( (string) $s['psp_country'] ) . '" maxlength="2" /></td></tr>';
		echo '<tr><th><label for="psp_bic">' . esc_html__( 'BIC (8 or 11)', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="text" id="psp_bic" name="eurocomply_psd2[psp_bic]" value="' . esc_attr( (string) $s['psp_bic'] ) . '" maxlength="11" /></td></tr>';
		echo '<tr><th><label for="reporting_officer">' . esc_html__( 'Reporting officer email', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="email" id="reporting_officer" name="eurocomply_psd2[reporting_officer]" value="' . esc_attr( (string) $s['reporting_officer'] ) . '" class="regular-text" /></td></tr>';
		echo '<tr><th><label for="sca_provider">' . esc_html__( 'SCA provider', 'eurocomply-psd2-sca' ) . '</label></th><td><select id="sca_provider" name="eurocomply_psd2[sca_provider]">';
		foreach ( array( 'stripe', 'adyen', 'mollie', 'wirecard', 'paypal', 'other' ) as $p ) {
			echo '<option value="' . esc_attr( $p ) . '"' . selected( (string) $s['sca_provider'], $p, false ) . '>' . esc_html( $p ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="low_value_threshold">' . esc_html__( 'Low-value threshold (EUR, Art. 16 RTS)', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="number" min="0" id="low_value_threshold" name="eurocomply_psd2[low_value_threshold]" value="' . esc_attr( (string) $s['low_value_threshold'] ) . '" /></td></tr>';
		echo '<tr><th><label for="cumulative_cap">' . esc_html__( 'Cumulative cap (EUR)', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="number" min="0" id="cumulative_cap" name="eurocomply_psd2[cumulative_cap]" value="' . esc_attr( (string) $s['cumulative_cap'] ) . '" /></td></tr>';
		echo '<tr><th><label for="tra_fraud_threshold">' . esc_html__( 'TRA fraud-rate threshold (decimal, e.g. 0.0013 = 13 bps)', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="number" step="0.00001" min="0" id="tra_fraud_threshold" name="eurocomply_psd2[tra_fraud_threshold]" value="' . esc_attr( (string) $s['tra_fraud_threshold'] ) . '" /></td></tr>';
		foreach ( array(
			'tra_enabled'         => __( 'Enable TRA exemption', 'eurocomply-psd2-sca' ),
			'recurring_exempt'    => __( 'Allow recurring same-amount exemption', 'eurocomply-psd2-sca' ),
			'mit_exempt'          => __( 'Treat MIT as out-of-scope', 'eurocomply-psd2-sca' ),
			'trusted_beneficiary' => __( 'Honour trusted-beneficiary list', 'eurocomply-psd2-sca' ),
			'enable_3ds_log'      => __( 'Log 3-DS2 challenge events', 'eurocomply-psd2-sca' ),
			'enable_woo_meta'     => __( 'Attach SCA decision to WC orders (when WC active)', 'eurocomply-psd2-sca' ),
		) as $k => $lab ) {
			echo '<tr><th>' . esc_html( $lab ) . '</th><td><label><input type="checkbox" name="eurocomply_psd2[' . esc_attr( $k ) . ']" value="1"' . checked( ! empty( $s[ $k ] ), true, false ) . ' /></label></td></tr>';
		}
		echo '<tr><th><label for="refund_window_days">' . esc_html__( 'Refund window (days, Art. 73(1))', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="number" min="0" id="refund_window_days" name="eurocomply_psd2[refund_window_days]" value="' . esc_attr( (string) $s['refund_window_days'] ) . '" /></td></tr>';
		echo '<tr><th><label for="reporting_period">' . esc_html__( 'Reporting period (YYYY-Qn)', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="text" id="reporting_period" name="eurocomply_psd2[reporting_period]" value="' . esc_attr( (string) $s['reporting_period'] ) . '" pattern="\\d{4}-Q[1-4]" /></td></tr>';
		echo '<tr><th><label for="currency">' . esc_html__( 'Currency', 'eurocomply-psd2-sca' ) . '</label></th><td><input type="text" id="currency" name="eurocomply_psd2[currency]" value="' . esc_attr( (string) $s['currency'] ) . '" maxlength="3" /></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	private function render_pro() : void {
		echo '<h2>' . esc_html__( 'Pro features', 'eurocomply-psd2-sca' ) . '</h2>';
		echo '<ul class="ul-disc" style="margin-left:1.4em;">';
		foreach ( array(
			__( 'Live EBA TPP-register sync (daily cron)',                          'eurocomply-psd2-sca' ),
			__( 'Stripe / Adyen / Mollie webhook adapters → auto-log transactions', 'eurocomply-psd2-sca' ),
			__( 'WooCommerce gateway hooks (auto SCA decision on every order)',     'eurocomply-psd2-sca' ),
			__( 'Signed PDF Art. 96(6) report (NCA-ready)',                          'eurocomply-psd2-sca' ),
			__( 'EBA GL/2018/05 fraud reporting CSV (full schema)',                  'eurocomply-psd2-sca' ),
			__( 'REST API: /eurocomply/v1/psd2/{transactions,consents,tpps}',        'eurocomply-psd2-sca' ),
			__( 'Webhook out — push events to SIEM / fraud team',                     'eurocomply-psd2-sca' ),
			__( 'Slack / Teams alerts on Art. 73 refund-window breach',               'eurocomply-psd2-sca' ),
			__( 'Multi-PSP consolidation (group fraud rate calculation)',             'eurocomply-psd2-sca' ),
			__( 'WPML / Polylang TPP directory translations',                          'eurocomply-psd2-sca' ),
			__( '5,000-row CSV export cap',                                              'eurocomply-psd2-sca' ),
			__( 'Multi-site network aggregator',                                          'eurocomply-psd2-sca' ),
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
		echo '<input type="hidden" name="action" value="eurocomply_psd2_license" />';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th><label for="license_key">' . esc_html__( 'License key', 'eurocomply-psd2-sca' ) . '</label></th>';
		echo '<td><input type="text" id="license_key" name="license_key" value="' . esc_attr( $key ) . '" class="regular-text" placeholder="EC-XXXXXX" /></td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'eurocomply-psd2-sca' ) . '</th><td><code>' . esc_html( $status ) . '</code></td></tr>';
		echo '</tbody></table>';
		submit_button( License::is_pro() ? __( 'Deactivate', 'eurocomply-psd2-sca' ) : __( 'Activate', 'eurocomply-psd2-sca' ) );
		echo '</form>';
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );
		$input = isset( $_POST['eurocomply_psd2'] ) && is_array( $_POST['eurocomply_psd2'] ) ? wp_unslash( (array) $_POST['eurocomply_psd2'] ) : array();
		update_option( Settings::OPTION_KEY, Settings::sanitize( $input ), false );
		add_settings_error( 'eurocomply_psd2', 'saved', __( 'Saved.', 'eurocomply-psd2-sca' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE_LICENSE );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';
		if ( License::is_pro() ) {
			License::deactivate();
			add_settings_error( 'eurocomply_psd2', 'lic-off', __( 'License deactivated.', 'eurocomply-psd2-sca' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_psd2', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_decide() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE_DECIDE );
		// Re-render SCA tab inline; render() picks up POST.
		$_REQUEST['tab'] = 'sca';
		$_GET['tab']     = 'sca';
		$tx              = isset( $_POST['tx'] ) && is_array( $_POST['tx'] ) ? wp_unslash( (array) $_POST['tx'] ) : array();
		// Coerce amount fields.
		$norm = array(
			'amount'               => isset( $tx['amount'] )               ? (float) $tx['amount']               : 0,
			'cumulative_since_sca' => isset( $tx['cumulative_since_sca'] ) ? (float) $tx['cumulative_since_sca'] : 0,
			'payments_since_sca'   => isset( $tx['payments_since_sca'] )   ? (int) $tx['payments_since_sca']     : 0,
			'tra_score'            => isset( $tx['tra_score'] )            ? (float) $tx['tra_score']            : 0,
			'recurring'            => ! empty( $tx['recurring'] ),
			'merchant_initiated'   => ! empty( $tx['merchant_initiated'] ),
			'trusted_beneficiary'  => ! empty( $tx['trusted_beneficiary'] ),
			'corporate'            => ! empty( $tx['corporate'] ),
		);
		$_POST['decision'] = ScaRules::decide( $norm );
		$_POST['tx']       = $tx;
		$this->render();
		exit;
	}

	public function handle_transaction() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE_TRANSACTION );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['tx_id'] ) ? (int) $_POST['tx_id'] : 0;
			if ( $id > 0 ) {
				TransactionStore::delete( $id );
				add_settings_error( 'eurocomply_psd2', 'tx-del', __( 'Transaction deleted.', 'eurocomply-psd2-sca' ), 'updated' );
			}
		} else {
			$row    = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			$row['period'] = Settings::current_period();
			$id = TransactionStore::create( $row );
			add_settings_error( 'eurocomply_psd2', 'tx-ok', sprintf( /* translators: %d: id */ __( 'Transaction #%d added.', 'eurocomply-psd2-sca' ), $id ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'transactions', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_consent() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE_CONSENT );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'revoke' === $op ) {
			$id = isset( $_POST['cid'] ) ? (int) $_POST['cid'] : 0;
			if ( $id > 0 ) {
				ConsentStore::revoke( $id );
				add_settings_error( 'eurocomply_psd2', 'c-rev', __( 'Consent revoked.', 'eurocomply-psd2-sca' ), 'updated' );
			}
		} elseif ( 'delete' === $op ) {
			$id = isset( $_POST['cid'] ) ? (int) $_POST['cid'] : 0;
			if ( $id > 0 ) {
				ConsentStore::delete( $id );
				add_settings_error( 'eurocomply_psd2', 'c-del', __( 'Consent deleted.', 'eurocomply-psd2-sca' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			ConsentStore::create( $row );
			add_settings_error( 'eurocomply_psd2', 'c-ok', __( 'Consent issued.', 'eurocomply-psd2-sca' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'consents', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_tpp() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE_TPP );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['tpp_id'] ) ? (int) $_POST['tpp_id'] : 0;
			if ( $id > 0 ) {
				TppStore::delete( $id );
				add_settings_error( 'eurocomply_psd2', 'tpp-del', __( 'TPP deleted.', 'eurocomply-psd2-sca' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			TppStore::create( $row );
			add_settings_error( 'eurocomply_psd2', 'tpp-ok', __( 'TPP added.', 'eurocomply-psd2-sca' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'tpps', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_fraud() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-psd2-sca' ), 403 );
		}
		check_admin_referer( self::NONCE_FRAUD );
		$op = isset( $_POST['op'] ) ? sanitize_key( (string) $_POST['op'] ) : 'create';
		if ( 'delete' === $op ) {
			$id = isset( $_POST['fr_id'] ) ? (int) $_POST['fr_id'] : 0;
			if ( $id > 0 ) {
				FraudStore::delete( $id );
				add_settings_error( 'eurocomply_psd2', 'f-del', __( 'Fraud event deleted.', 'eurocomply-psd2-sca' ), 'updated' );
			}
		} else {
			$row = isset( $_POST['row'] ) && is_array( $_POST['row'] ) ? wp_unslash( (array) $_POST['row'] ) : array();
			$row['period'] = Settings::current_period();
			FraudStore::create( $row );
			add_settings_error( 'eurocomply_psd2', 'f-ok', __( 'Fraud event recorded.', 'eurocomply-psd2-sca' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'fraud', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
