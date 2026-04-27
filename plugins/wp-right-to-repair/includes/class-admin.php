<?php
/**
 * Admin UI.
 *
 * @package EuroComply\R2R
 */

declare( strict_types = 1 );

namespace EuroComply\R2R;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG        = 'eurocomply-r2r';
	public const NONCE_SAVE       = 'eurocomply_r2r_save';
	public const NONCE_LIC        = 'eurocomply_r2r_license';
	public const NONCE_SUPPLIER   = 'eurocomply_r2r_supplier';
	public const NONCE_REPAIRER   = 'eurocomply_r2r_repairer';
	public const ACTION_SAVE      = 'eurocomply_r2r_save';
	public const ACTION_LIC       = 'eurocomply_r2r_license';
	public const ACTION_SUPPLIER  = 'eurocomply_r2r_supplier';
	public const ACTION_REPAIRER  = 'eurocomply_r2r_repairer';

	private static ?Admin $instance = null;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_LIC, array( $this, 'handle_license' ) );
		add_action( 'admin_post_' . self::ACTION_SUPPLIER, array( $this, 'handle_supplier' ) );
		add_action( 'admin_post_' . self::ACTION_REPAIRER, array( $this, 'handle_repairer' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply R2R', 'eurocomply-r2r' ),
			__( 'EuroComply R2R', 'eurocomply-r2r' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-hammer',
			82
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) && false === strpos( $hook, 'post.php' ) && false === strpos( $hook, 'post-new.php' ) ) {
			return;
		}
		wp_enqueue_style( self::MENU_SLUG . '-admin', EUROCOMPLY_R2R_URL . 'assets/css/admin.css', array(), EUROCOMPLY_R2R_VERSION );
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab  = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'eurocomply-r2r' ),
			'products'  => __( 'Products', 'eurocomply-r2r' ),
			'spares'    => __( 'Spare parts', 'eurocomply-r2r' ),
			'repairers' => __( 'Repairers', 'eurocomply-r2r' ),
			'settings'  => __( 'Settings', 'eurocomply-r2r' ),
			'pro'       => __( 'Pro', 'eurocomply-r2r' ),
			'license'   => __( 'License', 'eurocomply-r2r' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		echo '<div class="wrap eurocomply-r2r-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply Right-to-Repair & Energy', 'eurocomply-r2r' ) . '</h1>';

		if ( isset( $_GET['settings-updated'] ) && 'true' === (string) $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			settings_errors( 'eurocomply_r2r' );
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => $slug ),
				admin_url( 'admin.php' )
			);
			printf(
				'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
				$tab === $slug ? ' nav-tab-active' : '',
				esc_url( $url ),
				esc_html( $label )
			);
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'products':
				$this->render_products();
				break;
			case 'spares':
				$this->render_spares();
				break;
			case 'repairers':
				$this->render_repairers();
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
		$product_ids = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => 500,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		$total     = count( $product_ids );
		$with_cat  = 0;
		$with_class = 0;
		$with_score = 0;
		foreach ( $product_ids as $pid ) {
			if ( '' !== (string) get_post_meta( (int) $pid, '_eurocomply_r2r_category', true ) ) {
				$with_cat++;
			}
			if ( '' !== (string) get_post_meta( (int) $pid, '_eurocomply_r2r_energy_class', true ) ) {
				$with_class++;
			}
			if ( '' !== (string) get_post_meta( (int) $pid, '_eurocomply_r2r_repair_index', true ) ) {
				$with_score++;
			}
		}
		$spares    = count( SparePartsStore::all() );
		$repairers = count( RepairerStore::all() );

		echo '<div class="eurocomply-r2r-cards">';
		$cards = array(
			array( __( 'WC products', 'eurocomply-r2r' ), $total ),
			array( __( 'With ESPR category', 'eurocomply-r2r' ), $with_cat ),
			array( __( 'With energy class', 'eurocomply-r2r' ), $with_class ),
			array( __( 'With reparability score', 'eurocomply-r2r' ), $with_score ),
			array( __( 'Spare-parts suppliers', 'eurocomply-r2r' ), $spares ),
			array( __( 'Listed repairers', 'eurocomply-r2r' ), $repairers ),
		);
		foreach ( $cards as $card ) {
			printf(
				'<div class="eurocomply-r2r-card"><div class="eurocomply-r2r-card__value">%1$s</div><div class="eurocomply-r2r-card__label">%2$s</div></div>',
				esc_html( (string) $card[1] ),
				esc_html( (string) $card[0] )
			);
		}
		echo '</div>';

		echo '<h2>' . esc_html__( 'Public shortcodes', 'eurocomply-r2r' ) . '</h2>';
		echo '<ul class="eurocomply-r2r-shortlist">';
		echo '<li><code>[eurocomply_r2r_info]</code> — ' . esc_html__( 'Repair & energy spec sheet for the current product (or id="123").', 'eurocomply-r2r' ) . '</li>';
		echo '<li><code>[eurocomply_r2r_spares]</code> — ' . esc_html__( 'Spare-parts supplier directory.', 'eurocomply-r2r' ) . '</li>';
		echo '<li><code>[eurocomply_r2r_repairers]</code> — ' . esc_html__( 'Authorised-repairer directory.', 'eurocomply-r2r' ) . '</li>';
		echo '</ul>';
	}

	private function render_products() : void {
		$products = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => 50,
			'post_status'    => 'any',
			'no_found_rows'  => true,
		) );

		echo '<p><a class="button" href="' . esc_url( CsvExport::url( 'products' ) ) . '">' . esc_html__( 'Export products CSV', 'eurocomply-r2r' ) . '</a></p>';
		if ( empty( $products ) ) {
			echo '<p>' . esc_html__( 'No products found. WooCommerce must be installed to edit product-level meta.', 'eurocomply-r2r' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Product', 'eurocomply-r2r' ), __( 'Category', 'eurocomply-r2r' ), __( 'Energy', 'eurocomply-r2r' ), __( 'Repair score', 'eurocomply-r2r' ), __( 'Spare-parts years', 'eurocomply-r2r' ), __( 'EPREL', 'eurocomply-r2r' ), '' ) as $label ) {
			echo '<th>' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $products as $p ) {
			$m = ProductMeta::get_for_product( (int) $p->ID );
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $p->ID );
			printf( '<td><a href="%1$s">%2$s</a></td>', esc_url( (string) get_edit_post_link( $p->ID ) ), esc_html( (string) get_the_title( $p->ID ) ) );
			printf( '<td>%s</td>', esc_html( (string) $m['category_label'] ) );
			printf( '<td>%s</td>', '' !== $m['energy_class'] ? ProductDisplay::energy_badge_html( (string) $m['energy_class'] ) : '—' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf( '<td>%s</td>', '' !== $m['repair_index'] ? ProductDisplay::score_badge_html( (string) $m['repair_index'] ) : '—' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf( '<td>%d</td>', (int) $m['spare_parts_years'] );
			printf( '<td>%s</td>', esc_html( (string) $m['eprel_id'] ) );
			printf( '<td><a class="button" href="%s">%s</a></td>', esc_url( (string) get_edit_post_link( $p->ID ) ), esc_html__( 'Edit', 'eurocomply-r2r' ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_spares() : void {
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_new  = isset( $_GET['edit'] ) && 'new' === (string) $_GET['edit']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $is_new || $edit_id > 0 ) {
			$row = $edit_id > 0 ? (array) SparePartsStore::get( $edit_id ) : array();
			$row = wp_parse_args( $row, array(
				'name'               => '',
				'product_category'   => 'not_applicable',
				'country'            => '',
				'website'            => '',
				'email'              => '',
				'phone'              => '',
				'availability_years' => 0,
				'notes'              => '',
			) );

			echo '<h2>' . esc_html( $is_new ? __( 'Add spare-parts supplier', 'eurocomply-r2r' ) : __( 'Edit supplier', 'eurocomply-r2r' ) ) . '</h2>';
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_SUPPLIER, '_wpnonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SUPPLIER ); ?>" />
				<input type="hidden" name="op" value="save" />
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />

				<table class="form-table" role="presentation">
					<tr><th><label><?php esc_html_e( 'Supplier name', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" name="name" class="regular-text" value="<?php echo esc_attr( (string) $row['name'] ); ?>" required="required" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Product category', 'eurocomply-r2r' ); ?></label></th>
					<td><select name="product_category"><?php foreach ( Settings::product_categories() as $slug => $info ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) $row['product_category'], $slug ); ?>><?php echo esc_html( (string) $info['label'] ); ?></option>
					<?php endforeach; ?></select></td></tr>

					<tr><th><label><?php esc_html_e( 'Country (ISO alpha-2)', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" name="country" class="small-text" maxlength="2" value="<?php echo esc_attr( (string) $row['country'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Availability (years)', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="number" name="availability_years" min="0" max="15" value="<?php echo esc_attr( (string) $row['availability_years'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Website', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="url" name="website" class="regular-text" value="<?php echo esc_attr( (string) $row['website'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Email', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="email" name="email" class="regular-text" value="<?php echo esc_attr( (string) $row['email'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Phone', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr( (string) $row['phone'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Notes', 'eurocomply-r2r' ); ?></label></th>
					<td><textarea name="notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $row['notes'] ); ?></textarea></td></tr>
				</table>
				<?php submit_button( $is_new ? __( 'Create supplier', 'eurocomply-r2r' ) : __( 'Save supplier', 'eurocomply-r2r' ) ); ?>
			</form>
			<?php
			return;
		}

		$rows = SparePartsStore::all();
		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'spares', 'edit' => 'new' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Add supplier', 'eurocomply-r2r' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( CsvExport::url( 'suppliers' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-r2r' ) . '</a>';
		echo '</p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No spare-parts suppliers yet.', 'eurocomply-r2r' ) . '</p>';
			return;
		}

		$cats = Settings::product_categories();
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Name', 'eurocomply-r2r' ), __( 'Category', 'eurocomply-r2r' ), __( 'Country', 'eurocomply-r2r' ), __( 'Parts years', 'eurocomply-r2r' ), __( 'Website', 'eurocomply-r2r' ), '' ) as $l ) {
			echo '<th>' . esc_html( $l ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$cat = isset( $cats[ $r['product_category'] ] ) ? (string) $cats[ $r['product_category'] ]['label'] : (string) $r['product_category'];
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $r['id'] );
			printf( '<td>%s</td>', esc_html( (string) $r['name'] ) );
			printf( '<td>%s</td>', esc_html( $cat ) );
			printf( '<td>%s</td>', esc_html( (string) $r['country'] ) );
			printf( '<td>%d</td>', (int) $r['availability_years'] );
			printf( '<td>%s</td>', '' !== $r['website'] ? '<a href="' . esc_url( (string) $r['website'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open', 'eurocomply-r2r' ) . '</a>' : '—' );

			$edit_url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'spares', 'edit' => (int) $r['id'] ), admin_url( 'admin.php' ) );
			$del_url  = wp_nonce_url( add_query_arg( array( 'op' => 'delete', 'id' => (int) $r['id'] ), admin_url( 'admin-post.php?action=' . self::ACTION_SUPPLIER ) ), self::NONCE_SUPPLIER, '_wpnonce' );
			printf( '<td><a class="button" href="%s">%s</a> <a class="button button-link-delete" href="%s" onclick="return confirm(\'%s\');">%s</a></td>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'eurocomply-r2r' ),
				esc_url( $del_url ),
				esc_js( __( 'Delete this supplier?', 'eurocomply-r2r' ) ),
				esc_html__( 'Delete', 'eurocomply-r2r' )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_repairers() : void {
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_new  = isset( $_GET['edit'] ) && 'new' === (string) $_GET['edit']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $is_new || $edit_id > 0 ) {
			$row = $edit_id > 0 ? (array) RepairerStore::get( $edit_id ) : array();
			$row = wp_parse_args( $row, array(
				'name'             => '',
				'product_category' => 'not_applicable',
				'country'          => '',
				'city'             => '',
				'address'          => '',
				'website'          => '',
				'email'            => '',
				'phone'            => '',
				'certification'    => '',
				'notes'            => '',
			) );

			echo '<h2>' . esc_html( $is_new ? __( 'Add repairer', 'eurocomply-r2r' ) : __( 'Edit repairer', 'eurocomply-r2r' ) ) . '</h2>';
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_REPAIRER, '_wpnonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REPAIRER ); ?>" />
				<input type="hidden" name="op" value="save" />
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />

				<table class="form-table" role="presentation">
					<tr><th><label><?php esc_html_e( 'Repairer name', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" name="name" class="regular-text" value="<?php echo esc_attr( (string) $row['name'] ); ?>" required="required" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Product category', 'eurocomply-r2r' ); ?></label></th>
					<td><select name="product_category"><?php foreach ( Settings::product_categories() as $slug => $info ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) $row['product_category'], $slug ); ?>><?php echo esc_html( (string) $info['label'] ); ?></option>
					<?php endforeach; ?></select></td></tr>

					<tr><th><label><?php esc_html_e( 'Country / City', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" name="country" class="small-text" maxlength="2" value="<?php echo esc_attr( (string) $row['country'] ); ?>" placeholder="DE" />
					    <input type="text" name="city" class="regular-text" value="<?php echo esc_attr( (string) $row['city'] ); ?>" placeholder="<?php esc_attr_e( 'City', 'eurocomply-r2r' ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Address', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" name="address" class="large-text" value="<?php echo esc_attr( (string) $row['address'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Certification', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" name="certification" class="regular-text" value="<?php echo esc_attr( (string) $row['certification'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. QualiRépar, Meister-Betrieb', 'eurocomply-r2r' ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Website', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="url" name="website" class="regular-text" value="<?php echo esc_attr( (string) $row['website'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Email', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="email" name="email" class="regular-text" value="<?php echo esc_attr( (string) $row['email'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Phone', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr( (string) $row['phone'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Notes', 'eurocomply-r2r' ); ?></label></th>
					<td><textarea name="notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $row['notes'] ); ?></textarea></td></tr>
				</table>
				<?php submit_button( $is_new ? __( 'Create repairer', 'eurocomply-r2r' ) : __( 'Save repairer', 'eurocomply-r2r' ) ); ?>
			</form>
			<?php
			return;
		}

		$rows = RepairerStore::all();
		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'repairers', 'edit' => 'new' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Add repairer', 'eurocomply-r2r' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( CsvExport::url( 'repairers' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-r2r' ) . '</a>';
		echo '</p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No repairers yet.', 'eurocomply-r2r' ) . '</p>';
			return;
		}

		$cats = Settings::product_categories();
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Name', 'eurocomply-r2r' ), __( 'Category', 'eurocomply-r2r' ), __( 'Location', 'eurocomply-r2r' ), __( 'Certification', 'eurocomply-r2r' ), '' ) as $l ) {
			echo '<th>' . esc_html( $l ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$cat = isset( $cats[ $r['product_category'] ] ) ? (string) $cats[ $r['product_category'] ]['label'] : (string) $r['product_category'];
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $r['id'] );
			printf( '<td>%s</td>', esc_html( (string) $r['name'] ) );
			printf( '<td>%s</td>', esc_html( $cat ) );
			printf( '<td>%s / %s</td>', esc_html( (string) $r['country'] ), esc_html( (string) $r['city'] ) );
			printf( '<td>%s</td>', esc_html( (string) $r['certification'] ) );
			$edit_url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'repairers', 'edit' => (int) $r['id'] ), admin_url( 'admin.php' ) );
			$del_url  = wp_nonce_url( add_query_arg( array( 'op' => 'delete', 'id' => (int) $r['id'] ), admin_url( 'admin-post.php?action=' . self::ACTION_REPAIRER ) ), self::NONCE_REPAIRER, '_wpnonce' );
			printf( '<td><a class="button" href="%s">%s</a> <a class="button button-link-delete" href="%s" onclick="return confirm(\'%s\');">%s</a></td>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'eurocomply-r2r' ),
				esc_url( $del_url ),
				esc_js( __( 'Delete this repairer?', 'eurocomply-r2r' ) ),
				esc_html__( 'Delete', 'eurocomply-r2r' )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />

			<h2><?php esc_html_e( 'Display', 'eurocomply-r2r' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Frontend badges', 'eurocomply-r2r' ); ?></th>
				<td>
					<label><input type="checkbox" name="eurocomply_r2r[show_energy_badge]" value="1" <?php checked( ! empty( $s['show_energy_badge'] ) ); ?> /> <?php esc_html_e( 'Energy class badge', 'eurocomply-r2r' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_r2r[show_repair_score_badge]" value="1" <?php checked( ! empty( $s['show_repair_score_badge'] ) ); ?> /> <?php esc_html_e( 'Reparability score badge', 'eurocomply-r2r' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_r2r[show_spare_parts_years]" value="1" <?php checked( ! empty( $s['show_spare_parts_years'] ) ); ?> /> <?php esc_html_e( 'Spare-parts years badge', 'eurocomply-r2r' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_r2r[show_repair_tab]" value="1" <?php checked( ! empty( $s['show_repair_tab'] ) ); ?> /> <?php esc_html_e( 'Repair & parts product tab', 'eurocomply-r2r' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_r2r[show_on_shop_grid]" value="1" <?php checked( ! empty( $s['show_on_shop_grid'] ) ); ?> /> <?php esc_html_e( 'Badges on shop grid', 'eurocomply-r2r' ); ?></label>
				</td></tr>
			</table>

			<h2><?php esc_html_e( 'Warranty', 'eurocomply-r2r' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label><?php esc_html_e( 'EU statutory warranty (years)', 'eurocomply-r2r' ); ?></label></th>
				<td><input type="number" name="eurocomply_r2r[default_warranty_years]" min="0" max="10" value="<?php echo esc_attr( (string) $s['default_warranty_years'] ); ?>" />
				<p class="description"><?php esc_html_e( 'EU minimum is 2 years. Per-product override available.', 'eurocomply-r2r' ); ?></p></td></tr>
				<tr><th><label><?php esc_html_e( 'Commercial extended warranty (years)', 'eurocomply-r2r' ); ?></label></th>
				<td><input type="number" name="eurocomply_r2r[commercial_warranty_years]" min="0" max="10" value="<?php echo esc_attr( (string) $s['commercial_warranty_years'] ); ?>" /></td></tr>
			</table>

			<h2><?php esc_html_e( 'Defaults', 'eurocomply-r2r' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label><?php esc_html_e( 'Default ESPR category', 'eurocomply-r2r' ); ?></label></th>
				<td><select name="eurocomply_r2r[default_product_category]"><?php foreach ( Settings::product_categories() as $slug => $info ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) $s['default_product_category'], $slug ); ?>><?php echo esc_html( (string) $info['label'] ); ?></option>
				<?php endforeach; ?></select></td></tr>

				<tr><th><label><?php esc_html_e( 'Primary country (ISO alpha-2)', 'eurocomply-r2r' ); ?></label></th>
				<td><input type="text" name="eurocomply_r2r[country]" class="small-text" maxlength="2" value="<?php echo esc_attr( (string) $s['country'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Repair support email', 'eurocomply-r2r' ); ?></label></th>
				<td><input type="email" name="eurocomply_r2r[repair_contact_email]" class="regular-text" value="<?php echo esc_attr( (string) $s['repair_contact_email'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Policy URL', 'eurocomply-r2r' ); ?></label></th>
				<td><input type="url" name="eurocomply_r2r[policy_url]" class="regular-text" value="<?php echo esc_attr( (string) $s['policy_url'] ); ?>" /></td></tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_pro() : void {
		$pro   = License::is_pro();
		$items = array(
			__( 'EPREL database sync (automatic energy-label fetch + image embed)', 'eurocomply-r2r' ),
			__( 'FR Indice de réparabilité auto-calculator (5 criteria)', 'eurocomply-r2r' ),
			__( 'German ReparaturIndex draft calculator (once finalised by BMUV)', 'eurocomply-r2r' ),
			__( 'Digital Product Passport (QR / datamatrix on invoices and labels)', 'eurocomply-r2r' ),
			__( 'Multi-country spare-parts cross-border availability matrix', 'eurocomply-r2r' ),
			__( 'Extended warranty tracker (per-sale, per-SKU)', 'eurocomply-r2r' ),
			__( 'Energy label image generator (A-G tricolour)', 'eurocomply-r2r' ),
			__( 'REST API for product catalogue sync', 'eurocomply-r2r' ),
			__( '5,000-row CSV cap (vs 500 free)', 'eurocomply-r2r' ),
			__( 'WPML / Polylang multilingual product info', 'eurocomply-r2r' ),
			__( 'EU R2R platform submission (once published by the Commission)', 'eurocomply-r2r' ),
		);
		echo '<p>' . esc_html__( 'Pro unlocks the enterprise R2R integrations:', 'eurocomply-r2r' ) . '</p>';
		echo '<ul class="eurocomply-r2r-pro-list">';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . ( $pro ? esc_html__( 'Pro is active.', 'eurocomply-r2r' ) : esc_html__( 'Activate a license in the License tab to unlock Pro stubs.', 'eurocomply-r2r' ) ) . '</p>';
	}

	private function render_license() : void {
		$license = License::get();
		$is_pro  = License::is_pro();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:520px;">
			<?php wp_nonce_field( self::NONCE_LIC, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_LIC ); ?>" />
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="license_key"><?php esc_html_e( 'License key', 'eurocomply-r2r' ); ?></label></th>
					<td><input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( (string) ( $license['key'] ?? '' ) ); ?>" class="regular-text" placeholder="EC-XXXXXX" />
					<p class="description"><?php echo $is_pro ? esc_html__( 'Pro is active.', 'eurocomply-r2r' ) : esc_html__( 'Enter a license key in the form EC-XXXXXX to activate Pro stubs.', 'eurocomply-r2r' ); ?></p></td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="license_op" value="activate" class="button button-primary"><?php esc_html_e( 'Activate', 'eurocomply-r2r' ); ?></button>
				<button type="submit" name="license_op" value="deactivate" class="button"><?php esc_html_e( 'Deactivate', 'eurocomply-r2r' ); ?></button>
			</p>
		</form>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-r2r' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );

		$input = isset( $_POST['eurocomply_r2r'] ) && is_array( $_POST['eurocomply_r2r'] )
			? wp_unslash( (array) $_POST['eurocomply_r2r'] )
			: array();

		$sanitized = Settings::sanitize( $input );
		update_option( Settings::OPTION_KEY, $sanitized, false );

		add_settings_error( 'eurocomply_r2r', 'saved', __( 'Settings saved.', 'eurocomply-r2r' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => 'settings', 'settings-updated' => 'true' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_license() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-r2r' ), 403 );
		}
		check_admin_referer( self::NONCE_LIC );

		$op  = isset( $_POST['license_op'] ) ? sanitize_key( (string) $_POST['license_op'] ) : '';
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';

		if ( 'deactivate' === $op ) {
			License::deactivate();
			add_settings_error( 'eurocomply_r2r', 'lic_off', __( 'License deactivated.', 'eurocomply-r2r' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_r2r', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => 'license', 'settings-updated' => 'true' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_supplier() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-r2r' ), 403 );
		}
		check_admin_referer( self::NONCE_SUPPLIER );

		$op = isset( $_REQUEST['op'] ) ? sanitize_key( (string) $_REQUEST['op'] ) : '';
		$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

		if ( 'delete' === $op && $id > 0 ) {
			SparePartsStore::delete( $id );
			add_settings_error( 'eurocomply_r2r', 'sup_del', __( 'Supplier deleted.', 'eurocomply-r2r' ), 'updated' );
		} elseif ( 'save' === $op ) {
			$data = array(
				'name'               => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '',
				'product_category'   => isset( $_POST['product_category'] ) ? sanitize_key( (string) $_POST['product_category'] ) : 'not_applicable',
				'country'            => isset( $_POST['country'] ) ? (string) $_POST['country'] : '',
				'website'            => isset( $_POST['website'] ) ? (string) $_POST['website'] : '',
				'email'              => isset( $_POST['email'] ) ? (string) $_POST['email'] : '',
				'phone'              => isset( $_POST['phone'] ) ? (string) $_POST['phone'] : '',
				'availability_years' => isset( $_POST['availability_years'] ) ? (int) $_POST['availability_years'] : 0,
				'notes'              => isset( $_POST['notes'] ) ? (string) $_POST['notes'] : '',
			);
			if ( $id > 0 ) {
				SparePartsStore::update( $id, $data );
				add_settings_error( 'eurocomply_r2r', 'sup_up', __( 'Supplier updated.', 'eurocomply-r2r' ), 'updated' );
			} else {
				SparePartsStore::create( $data );
				add_settings_error( 'eurocomply_r2r', 'sup_new', __( 'Supplier created.', 'eurocomply-r2r' ), 'updated' );
			}
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => 'spares', 'settings-updated' => 'true' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_repairer() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-r2r' ), 403 );
		}
		check_admin_referer( self::NONCE_REPAIRER );

		$op = isset( $_REQUEST['op'] ) ? sanitize_key( (string) $_REQUEST['op'] ) : '';
		$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

		if ( 'delete' === $op && $id > 0 ) {
			RepairerStore::delete( $id );
			add_settings_error( 'eurocomply_r2r', 'rep_del', __( 'Repairer deleted.', 'eurocomply-r2r' ), 'updated' );
		} elseif ( 'save' === $op ) {
			$data = array(
				'name'             => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '',
				'product_category' => isset( $_POST['product_category'] ) ? sanitize_key( (string) $_POST['product_category'] ) : 'not_applicable',
				'country'          => isset( $_POST['country'] ) ? (string) $_POST['country'] : '',
				'city'             => isset( $_POST['city'] ) ? (string) $_POST['city'] : '',
				'address'          => isset( $_POST['address'] ) ? (string) $_POST['address'] : '',
				'website'          => isset( $_POST['website'] ) ? (string) $_POST['website'] : '',
				'email'            => isset( $_POST['email'] ) ? (string) $_POST['email'] : '',
				'phone'            => isset( $_POST['phone'] ) ? (string) $_POST['phone'] : '',
				'certification'    => isset( $_POST['certification'] ) ? (string) $_POST['certification'] : '',
				'notes'            => isset( $_POST['notes'] ) ? (string) $_POST['notes'] : '',
			);
			if ( $id > 0 ) {
				RepairerStore::update( $id, $data );
				add_settings_error( 'eurocomply_r2r', 'rep_up', __( 'Repairer updated.', 'eurocomply-r2r' ), 'updated' );
			} else {
				RepairerStore::create( $data );
				add_settings_error( 'eurocomply_r2r', 'rep_new', __( 'Repairer created.', 'eurocomply-r2r' ), 'updated' );
			}
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => 'repairers', 'settings-updated' => 'true' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
