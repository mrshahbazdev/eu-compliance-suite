<?php
/**
 * Admin — menu, settings pages, form rendering, publish handlers.
 *
 * @package EuroComply\LegalPages
 */

namespace EuroComply\LegalPages;

defined( 'ABSPATH' ) || exit;

class Admin {

	const MENU_SLUG     = 'eurocomply-legal';
	const NONCE_SETTINGS = 'eurocomply_legal_settings_nonce';
	const NONCE_PUBLISH  = 'eurocomply_legal_publish_nonce';
	const NONCE_LICENSE  = 'eurocomply_legal_license_nonce';

	/** @var Settings */
	private $settings;
	/** @var Templates */
	private $templates;
	/** @var Generator */
	private $generator;
	/** @var Publisher */
	private $publisher;
	/** @var License */
	private $license;

	public function __construct( Settings $settings, Templates $templates, Generator $generator, Publisher $publisher, License $license ) {
		$this->settings  = $settings;
		$this->templates = $templates;
		$this->generator = $generator;
		$this->publisher = $publisher;
		$this->license   = $license;
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . EUROCOMPLY_LEGAL_BASENAME, array( $this, 'plugin_row_links' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'eurocomply-legal-admin',
			EUROCOMPLY_LEGAL_URL . 'assets/css/admin.css',
			array(),
			EUROCOMPLY_LEGAL_VERSION
		);
		wp_enqueue_script(
			'eurocomply-legal-admin',
			EUROCOMPLY_LEGAL_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			EUROCOMPLY_LEGAL_VERSION,
			true
		);
	}

	public function plugin_row_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		$upgrade_url  = 'https://eurocomply.eu/pricing';
		array_unshift(
			$links,
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'eurocomply-legal' ) . '</a>',
			'<a href="' . esc_url( $upgrade_url ) . '" style="color:#b54708;font-weight:600">' . esc_html__( 'Go Pro', 'eurocomply-legal' ) . '</a>'
		);
		return $links;
	}

	public function register_menu() {
		add_menu_page(
			__( 'EuroComply — Legal Pages', 'eurocomply-legal' ),
			__( 'EuroComply', 'eurocomply-legal' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield-alt',
			80
		);
	}

	public function handle_post() {
		if ( empty( $_POST['eurocomply_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['eurocomply_action'] ) );

		if ( 'save_settings' === $action ) {
			check_admin_referer( self::NONCE_SETTINGS );
			$raw = isset( $_POST['eurocomply_settings'] ) ? wp_unslash( $_POST['eurocomply_settings'] ) : array();
			if ( isset( $_POST['footer_links_enabled'] ) ) {
				$raw['footer_links_enabled'] = '1';
			}
			$this->settings->save( is_array( $raw ) ? $raw : array() );
			$this->redirect_with( 'saved' );
			return;
		}

		if ( 'publish_page' === $action ) {
			check_admin_referer( self::NONCE_PUBLISH );
			$type   = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
			$result = $this->publisher->publish( $type );
			if ( ! empty( $result['ok'] ) ) {
				$this->redirect_with( 'published', $type );
			} else {
				$this->redirect_with( ! empty( $result['paywall'] ) ? 'paywall' : 'error', $type, isset( $result['error'] ) ? $result['error'] : '' );
			}
			return;
		}

		if ( 'save_license' === $action ) {
			check_admin_referer( self::NONCE_LICENSE );
			$key = isset( $_POST['eurocomply_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['eurocomply_license_key'] ) ) : '';
			$this->license->save_key( $key );
			$this->redirect_with( 'license_' . $this->license->status() );
			return;
		}
	}

	private function redirect_with( $msg, $type = '', $error = '' ) {
		$args = array( 'page' => self::MENU_SLUG, 'm' => $msg );
		if ( $type ) {
			$args['t'] = $type;
		}
		if ( $error ) {
			$args['e'] = rawurlencode( $error );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'business';
		if ( ! in_array( $tab, array( 'business', 'publish', 'license' ), true ) ) {
			$tab = 'business';
		}

		$base_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

		echo '<div class="wrap eurocomply-wrap">';
		echo '<h1><span class="eurocomply-brand">EuroComply</span> <span class="eurocomply-subtitle">' . esc_html__( 'Legal Pages', 'eurocomply-legal' ) . '</span></h1>';

		$this->render_notices();

		echo '<h2 class="nav-tab-wrapper">';
		foreach (
			array(
				'business' => __( '1. Business info', 'eurocomply-legal' ),
				'publish'  => __( '2. Generate & publish', 'eurocomply-legal' ),
				'license'  => __( '3. License / Pro', 'eurocomply-legal' ),
			) as $slug => $label
		) {
			$url   = add_query_arg( 'tab', $slug, $base_url );
			$class = ( $slug === $tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		echo '<div class="eurocomply-tab-content">';
		switch ( $tab ) {
			case 'business':
				$this->render_business_tab();
				break;
			case 'publish':
				$this->render_publish_tab();
				break;
			case 'license':
				$this->render_license_tab();
				break;
		}
		echo '</div>';

		echo '</div>';
	}

	private function render_notices() {
		if ( empty( $_GET['m'] ) ) {
			return;
		}
		$msg  = sanitize_key( wp_unslash( $_GET['m'] ) );
		$type = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : '';
		$err  = isset( $_GET['e'] ) ? wp_kses_post( rawurldecode( wp_unslash( $_GET['e'] ) ) ) : '';

		$map = array(
			'saved'           => array( 'success', __( 'Settings saved.', 'eurocomply-legal' ) ),
			'published'       => array( 'success', __( 'Page published.', 'eurocomply-legal' ) ),
			'paywall'         => array( 'warning', __( 'Pro feature — upgrade to unlock this template.', 'eurocomply-legal' ) ),
			'error'           => array( 'error', $err ? $err : __( 'Something went wrong.', 'eurocomply-legal' ) ),
			'license_active'  => array( 'success', __( 'Pro license activated.', 'eurocomply-legal' ) ),
			'license_invalid' => array( 'error', __( 'License key is not valid. Expected format: EC-XXXXXX.', 'eurocomply-legal' ) ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return;
		}
		list( $level, $text ) = $map[ $msg ];
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $level ),
			esc_html( $text )
		);
	}

	// -----------------------------------------------------------------------
	// Tab: Business info
	// -----------------------------------------------------------------------

	private function render_business_tab() {
		$values = $this->settings->get_all();
		$fields = $this->settings->fields();
		$groups = $this->settings->groups();
		$footer_on = ! empty( $values['footer_links_enabled'] );

		echo '<form method="post" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_SETTINGS );
		echo '<input type="hidden" name="eurocomply_action" value="save_settings" />';

		foreach ( $groups as $gkey => $glabel ) {
			echo '<fieldset class="eurocomply-group"><legend>' . esc_html( $glabel ) . '</legend><table class="form-table">';
			foreach ( $fields as $key => $def ) {
				if ( $def['group'] !== $gkey ) {
					continue;
				}
				$value = isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
				$req   = ! empty( $def['required'] );
				echo '<tr><th scope="row"><label for="ec-' . esc_attr( $key ) . '">' .
					esc_html( $def['label'] ) . ( $req ? ' <span class="required">*</span>' : '' ) .
					'</label></th><td>';

				$name = 'eurocomply_settings[' . $key . ']';

				switch ( $def['type'] ) {
					case 'textarea':
						echo '<textarea id="ec-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
						break;
					case 'select':
						echo '<select id="ec-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
						echo '<option value="">' . esc_html__( '— choose —', 'eurocomply-legal' ) . '</option>';
						foreach ( (array) $def['options'] as $opt_k => $opt_v ) {
							printf(
								'<option value="%1$s"%2$s>%3$s</option>',
								esc_attr( $opt_k ),
								selected( $value, $opt_k, false ),
								esc_html( $opt_v )
							);
						}
						echo '</select>';
						break;
					default:
						echo '<input id="ec-' . esc_attr( $key ) . '" type="' . esc_attr( $def['type'] ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
				}

				if ( ! empty( $def['help'] ) ) {
					echo '<p class="description">' . esc_html( $def['help'] ) . '</p>';
				}
				echo '</td></tr>';
			}
			echo '</table></fieldset>';
		}

		// Footer links toggle (stored in same option).
		echo '<fieldset class="eurocomply-group"><legend>' . esc_html__( 'Site integration', 'eurocomply-legal' ) . '</legend>';
		echo '<label><input type="checkbox" name="footer_links_enabled" value="1" ' . checked( $footer_on, true, false ) . ' /> ';
		echo esc_html__( 'Automatically add legal-page links to the site footer.', 'eurocomply-legal' ) . '</label>';
		echo '</fieldset>';

		submit_button( __( 'Save business info', 'eurocomply-legal' ) );
		echo '</form>';
	}

	// -----------------------------------------------------------------------
	// Tab: Generate & publish
	// -----------------------------------------------------------------------

	private function render_publish_tab() {
		$missing = $this->settings->missing_required();
		if ( ! empty( $missing ) ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'Please complete required business info before publishing pages.', 'eurocomply-legal' ) .
				'</p></div>';
		}

		$country = strtoupper( (string) $this->settings->get( 'country' ) );
		$is_free_country = $this->templates->is_free_country( $country );

		echo '<p class="description">' . esc_html__( 'Each legal document can be generated as a new WordPress page (or updated if it already exists). Content regenerates each time you save business info and click publish.', 'eurocomply-legal' ) . '</p>';

		echo '<table class="widefat striped eurocomply-publish-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Document', 'eurocomply-legal' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'eurocomply-legal' ) . '</th>';
		echo '<th>' . esc_html__( 'Availability', 'eurocomply-legal' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'eurocomply-legal' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $this->templates->types() as $type => $label ) {
			$page_id  = $this->publisher->get_page_id( $type );
			$url      = $page_id ? get_permalink( $page_id ) : '';
			$is_free  = $this->templates->is_free_type( $type ) && $is_free_country;
			$is_pro   = $this->license->is_pro();
			$can_pub  = $is_free || $is_pro;

			echo '<tr>';
			echo '<td><strong>' . esc_html( $label ) . '</strong><br/><code>[' . esc_html( 'eurocomply_' . $type ) . ']</code></td>';
			if ( $page_id && $url ) {
				echo '<td><a href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( 'View page', 'eurocomply-legal' ) . ' →</a></td>';
			} else {
				echo '<td>' . esc_html__( 'Not published yet', 'eurocomply-legal' ) . '</td>';
			}

			if ( $is_free ) {
				echo '<td><span class="eurocomply-badge eurocomply-badge-free">' . esc_html__( 'Free', 'eurocomply-legal' ) . '</span></td>';
			} elseif ( $is_pro ) {
				echo '<td><span class="eurocomply-badge eurocomply-badge-pro">' . esc_html__( 'Pro active', 'eurocomply-legal' ) . '</span></td>';
			} else {
				echo '<td><span class="eurocomply-badge eurocomply-badge-locked">' . esc_html__( 'Pro required', 'eurocomply-legal' ) . '</span></td>';
			}

			echo '<td>';
			if ( $can_pub && empty( $missing ) ) {
				echo '<form method="post" style="display:inline">';
				wp_nonce_field( self::NONCE_PUBLISH );
				echo '<input type="hidden" name="eurocomply_action" value="publish_page" />';
				echo '<input type="hidden" name="type" value="' . esc_attr( $type ) . '" />';
				submit_button(
					$page_id ? __( 'Regenerate', 'eurocomply-legal' ) : __( 'Generate & publish', 'eurocomply-legal' ),
					'secondary',
					'submit',
					false
				);
				echo '</form>';
			} elseif ( ! $can_pub ) {
				echo '<a class="button button-primary" href="https://eurocomply.eu/pricing" target="_blank">' . esc_html__( 'Unlock in Pro →', 'eurocomply-legal' ) . '</a>';
			} else {
				echo '<em>' . esc_html__( 'Fill required fields', 'eurocomply-legal' ) . '</em>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<div class="eurocomply-upsell"><h3>' . esc_html__( 'Unlock EuroComply Pro', 'eurocomply-legal' ) . '</h3>';
		echo '<ul><li>' . esc_html__( 'All 27 EU country variants', 'eurocomply-legal' ) . '</li>';
		echo '<li>' . esc_html__( 'AGB + Widerrufsbelehrung with lawyer-reviewed clauses', 'eurocomply-legal' ) . '</li>';
		echo '<li>' . esc_html__( 'Automatic content updates when laws change', 'eurocomply-legal' ) . '</li>';
		echo '<li>' . esc_html__( 'WooCommerce checkout integration', 'eurocomply-legal' ) . '</li>';
		echo '<li>' . esc_html__( 'Priority email support', 'eurocomply-legal' ) . '</li></ul>';
		echo '<a class="button button-primary button-hero" href="https://eurocomply.eu/pricing" target="_blank">' . esc_html__( 'See pricing — from €9/month', 'eurocomply-legal' ) . '</a></div>';
	}

	// -----------------------------------------------------------------------
	// Tab: License
	// -----------------------------------------------------------------------

	private function render_license_tab() {
		$key    = $this->license->get_key();
		$status = $this->license->status();

		echo '<form method="post" class="eurocomply-form">';
		wp_nonce_field( self::NONCE_LICENSE );
		echo '<input type="hidden" name="eurocomply_action" value="save_license" />';
		echo '<table class="form-table">';
		echo '<tr><th scope="row"><label for="ec-license">' . esc_html__( 'EuroComply Pro license key', 'eurocomply-legal' ) . '</label></th><td>';
		echo '<input id="ec-license" type="text" name="eurocomply_license_key" value="' . esc_attr( $key ) . '" class="regular-text" placeholder="EC-XXXXXXXX" />';
		echo '<p class="description">' . esc_html__( 'Enter the license key you received after purchasing EuroComply Pro.', 'eurocomply-legal' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Current status', 'eurocomply-legal' ) . '</th><td>';
		switch ( $status ) {
			case 'active':
				echo '<span class="eurocomply-badge eurocomply-badge-pro">' . esc_html__( 'Pro active', 'eurocomply-legal' ) . '</span>';
				break;
			case 'invalid':
				echo '<span class="eurocomply-badge eurocomply-badge-locked">' . esc_html__( 'Invalid key', 'eurocomply-legal' ) . '</span>';
				break;
			default:
				echo '<span class="eurocomply-badge eurocomply-badge-free">' . esc_html__( 'Free tier', 'eurocomply-legal' ) . '</span>';
		}
		echo '</td></tr></table>';
		submit_button( __( 'Save license', 'eurocomply-legal' ) );
		echo '</form>';

		echo '<div class="eurocomply-upsell">';
		echo '<p>' . esc_html__( 'Do not have a license yet?', 'eurocomply-legal' ) . ' <a href="https://eurocomply.eu/pricing" target="_blank">' . esc_html__( 'Buy EuroComply Pro →', 'eurocomply-legal' ) . '</a></p>';
		echo '</div>';
	}
}
