<?php
/**
 * Admin UI.
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const MENU_SLUG       = 'eurocomply-ai-act';
	public const NONCE_SAVE      = 'eurocomply_ai_act_save';
	public const NONCE_LIC       = 'eurocomply_ai_act_license';
	public const NONCE_PROVIDER  = 'eurocomply_ai_act_provider';
	public const NONCE_POLICY    = 'eurocomply_ai_act_policy';
	public const ACTION_SAVE     = 'eurocomply_ai_act_save';
	public const ACTION_LIC      = 'eurocomply_ai_act_license';
	public const ACTION_PROVIDER = 'eurocomply_ai_act_provider';
	public const ACTION_POLICY   = 'eurocomply_ai_act_policy';

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
		add_action( 'admin_post_' . self::ACTION_PROVIDER, array( $this, 'handle_provider' ) );
		add_action( 'admin_post_' . self::ACTION_POLICY, array( $this, 'handle_policy' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'EuroComply AI Act', 'eurocomply-ai-act' ),
			__( 'EuroComply AI Act', 'eurocomply-ai-act' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-buddicons-replies',
			83
		);
	}

	public function assets( string $hook ) : void {
		if ( false === strpos( $hook, self::MENU_SLUG ) && false === strpos( $hook, 'post.php' ) && false === strpos( $hook, 'post-new.php' ) ) {
			return;
		}
		wp_enqueue_style( self::MENU_SLUG . '-admin', EUROCOMPLY_AIACT_URL . 'assets/css/admin.css', array(), EUROCOMPLY_AIACT_VERSION );
	}

	public function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab  = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'eurocomply-ai-act' ),
			'posts'     => __( 'Marked posts', 'eurocomply-ai-act' ),
			'providers' => __( 'Providers', 'eurocomply-ai-act' ),
			'log'       => __( 'Disclosure log', 'eurocomply-ai-act' ),
			'policy'    => __( 'Policy', 'eurocomply-ai-act' ),
			'settings'  => __( 'Settings', 'eurocomply-ai-act' ),
			'pro'       => __( 'Pro', 'eurocomply-ai-act' ),
			'license'   => __( 'License', 'eurocomply-ai-act' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}

		echo '<div class="wrap eurocomply-aiact-wrap">';
		echo '<h1>' . esc_html__( 'EuroComply AI Act Transparency', 'eurocomply-ai-act' ) . '</h1>';

		if ( isset( $_GET['settings-updated'] ) && 'true' === (string) $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			settings_errors( 'eurocomply_ai_act' );
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
			printf(
				'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
				$tab === $slug ? ' nav-tab-active' : '',
				esc_url( $url ),
				esc_html( $label )
			);
		}
		echo '</h2>';

		switch ( $tab ) {
			case 'posts':     $this->render_posts();     break;
			case 'providers': $this->render_providers(); break;
			case 'log':       $this->render_log();       break;
			case 'policy':    $this->render_policy();    break;
			case 'settings':  $this->render_settings();  break;
			case 'pro':       $this->render_pro();       break;
			case 'license':   $this->render_license();   break;
			default:          $this->render_dashboard();
		}

		echo '</div>';
	}

	private function render_dashboard() : void {
		$ids   = PostMeta::ai_marked_post_ids( 5000 );
		$total = count( $ids );
		$deepfake = 0;
		$human    = 0;
		foreach ( $ids as $pid ) {
			if ( '1' === (string) get_post_meta( (int) $pid, '_eurocomply_aiact_deepfake', true ) ) {
				$deepfake++;
			}
			if ( '1' === (string) get_post_meta( (int) $pid, '_eurocomply_aiact_human_edited', true ) ) {
				$human++;
			}
		}
		$providers = count( ProviderStore::all() );
		$log       = DisclosureLog::count_total();

		echo '<div class="eurocomply-aiact-cards">';
		$cards = array(
			array( __( 'AI-marked posts', 'eurocomply-ai-act' ), $total ),
			array( __( 'Human-edited', 'eurocomply-ai-act' ), $human ),
			array( __( 'Deepfake-flagged', 'eurocomply-ai-act' ), $deepfake ),
			array( __( 'Providers registered', 'eurocomply-ai-act' ), $providers ),
			array( __( 'Disclosure log entries', 'eurocomply-ai-act' ), $log ),
		);
		foreach ( $cards as $card ) {
			printf(
				'<div class="eurocomply-aiact-card"><div class="eurocomply-aiact-card__value">%1$s</div><div class="eurocomply-aiact-card__label">%2$s</div></div>',
				esc_html( (string) $card[1] ),
				esc_html( (string) $card[0] )
			);
		}
		echo '</div>';

		echo '<h2>' . esc_html__( 'Public shortcodes', 'eurocomply-ai-act' ) . '</h2>';
		echo '<ul class="eurocomply-aiact-shortlist">';
		echo '<li><code>[eurocomply_ai_disclosure]</code> — ' . esc_html__( 'Chatbot / AI-interaction disclosure (Art. 50(1)).', 'eurocomply-ai-act' ) . '</li>';
		echo '<li><code>[eurocomply_ai_label]</code> — ' . esc_html__( 'Standalone AI label for a post (id="..." optional).', 'eurocomply-ai-act' ) . '</li>';
		echo '<li><code>[eurocomply_ai_provider_list]</code> — ' . esc_html__( 'Public registry of AI tools used on this site.', 'eurocomply-ai-act' ) . '</li>';
		echo '<li><code>[eurocomply_ai_policy]</code> — ' . esc_html__( 'Auto-generated AI transparency policy.', 'eurocomply-ai-act' ) . '</li>';
		echo '</ul>';
	}

	private function render_posts() : void {
		$ids = PostMeta::ai_marked_post_ids( 200 );
		echo '<p><a class="button" href="' . esc_url( CsvExport::url( 'posts' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-ai-act' ) . '</a></p>';
		if ( empty( $ids ) ) {
			echo '<p>' . esc_html__( 'No posts have been marked as AI-generated yet. Edit a post and tick the AI Act transparency checkbox in the sidebar.', 'eurocomply-ai-act' ) . '</p>';
			return;
		}
		$providers = Settings::ai_providers_known();
		$purposes  = Settings::ai_purposes();

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Title', 'eurocomply-ai-act' ), __( 'Type', 'eurocomply-ai-act' ), __( 'Status', 'eurocomply-ai-act' ), __( 'Provider', 'eurocomply-ai-act' ), __( 'Model', 'eurocomply-ai-act' ), __( 'Purpose', 'eurocomply-ai-act' ), __( 'Flags', 'eurocomply-ai-act' ), '' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $ids as $pid ) {
			$p = get_post( (int) $pid );
			if ( ! $p instanceof \WP_Post ) {
				continue;
			}
			$m = PostMeta::get_for_post( (int) $pid );
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $pid );
			printf( '<td><a href="%1$s">%2$s</a></td>', esc_url( (string) get_edit_post_link( $pid ) ), esc_html( (string) get_the_title( $pid ) ) );
			printf( '<td>%s</td>', esc_html( (string) $p->post_type ) );
			printf( '<td>%s</td>', esc_html( (string) $p->post_status ) );
			printf( '<td>%s</td>', esc_html( (string) ( $providers[ $m['provider'] ] ?? $m['provider'] ) ) );
			printf( '<td>%s</td>', esc_html( (string) $m['model'] ) );
			printf( '<td>%s</td>', esc_html( (string) ( $purposes[ $m['purpose'] ] ?? '' ) ) );
			$flags = array();
			if ( $m['human_edited'] ) {
				$flags[] = __( 'human-edited', 'eurocomply-ai-act' );
			}
			if ( $m['deepfake'] ) {
				$flags[] = __( 'deepfake', 'eurocomply-ai-act' );
			}
			printf( '<td>%s</td>', esc_html( implode( ', ', $flags ) ) );
			printf( '<td><a class="button" href="%s">%s</a></td>', esc_url( (string) get_edit_post_link( $pid ) ), esc_html__( 'Edit', 'eurocomply-ai-act' ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_providers() : void {
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_new  = isset( $_GET['edit'] ) && 'new' === (string) $_GET['edit']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $is_new || $edit_id > 0 ) {
			$row = $edit_id > 0 ? (array) ProviderStore::get( $edit_id ) : array();
			$row = wp_parse_args( $row, array(
				'label'             => '',
				'provider_slug'     => 'other',
				'model'             => '',
				'purpose'           => '',
				'country'           => '',
				'vendor_legal_name' => '',
				'gpai'              => 0,
				'high_risk'         => 0,
				'notes'             => '',
			) );
			?>
			<h2><?php echo esc_html( $is_new ? __( 'Add AI tool', 'eurocomply-ai-act' ) : __( 'Edit AI tool', 'eurocomply-ai-act' ) ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_PROVIDER, '_wpnonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_PROVIDER ); ?>" />
				<input type="hidden" name="op" value="save" />
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $edit_id ); ?>" />
				<table class="form-table" role="presentation">
					<tr><th><label><?php esc_html_e( 'Label', 'eurocomply-ai-act' ); ?></label></th>
					<td><input type="text" name="label" class="regular-text" value="<?php echo esc_attr( (string) $row['label'] ); ?>" required="required" placeholder="ChatGPT for blog drafting" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Provider', 'eurocomply-ai-act' ); ?></label></th>
					<td><select name="provider_slug"><?php foreach ( Settings::ai_providers_known() as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) $row['provider_slug'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?></select></td></tr>

					<tr><th><label><?php esc_html_e( 'Model', 'eurocomply-ai-act' ); ?></label></th>
					<td><input type="text" name="model" class="regular-text" value="<?php echo esc_attr( (string) $row['model'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Purpose', 'eurocomply-ai-act' ); ?></label></th>
					<td><select name="purpose"><option value=""><?php esc_html_e( '— select —', 'eurocomply-ai-act' ); ?></option><?php foreach ( Settings::ai_purposes() as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) $row['purpose'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?></select></td></tr>

					<tr><th><label><?php esc_html_e( 'Vendor legal name', 'eurocomply-ai-act' ); ?></label></th>
					<td><input type="text" name="vendor_legal_name" class="regular-text" value="<?php echo esc_attr( (string) $row['vendor_legal_name'] ); ?>" /></td></tr>

					<tr><th><label><?php esc_html_e( 'Country (ISO alpha-2)', 'eurocomply-ai-act' ); ?></label></th>
					<td><input type="text" name="country" class="small-text" maxlength="2" value="<?php echo esc_attr( (string) $row['country'] ); ?>" /></td></tr>

					<tr><th><?php esc_html_e( 'Flags', 'eurocomply-ai-act' ); ?></th>
					<td>
						<label><input type="checkbox" name="gpai" value="1" <?php checked( ! empty( $row['gpai'] ) ); ?> /> <?php esc_html_e( 'General-purpose AI model (GPAI, Art. 51)', 'eurocomply-ai-act' ); ?></label><br />
						<label><input type="checkbox" name="high_risk" value="1" <?php checked( ! empty( $row['high_risk'] ) ); ?> /> <?php esc_html_e( 'High-risk system (Annex III)', 'eurocomply-ai-act' ); ?></label>
					</td></tr>

					<tr><th><label><?php esc_html_e( 'Notes', 'eurocomply-ai-act' ); ?></label></th>
					<td><textarea name="notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $row['notes'] ); ?></textarea></td></tr>
				</table>
				<?php submit_button( $is_new ? __( 'Create entry', 'eurocomply-ai-act' ) : __( 'Save entry', 'eurocomply-ai-act' ) ); ?>
			</form>
			<?php
			return;
		}

		$rows = ProviderStore::all();
		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'providers', 'edit' => 'new' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Add AI tool', 'eurocomply-ai-act' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( CsvExport::url( 'providers' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-ai-act' ) . '</a>';
		echo '</p>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No AI tools registered yet.', 'eurocomply-ai-act' ) . '</p>';
			return;
		}
		$providers = Settings::ai_providers_known();
		$purposes  = Settings::ai_purposes();

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Label', 'eurocomply-ai-act' ), __( 'Provider', 'eurocomply-ai-act' ), __( 'Model', 'eurocomply-ai-act' ), __( 'Purpose', 'eurocomply-ai-act' ), __( 'Flags', 'eurocomply-ai-act' ), '' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $r['id'] );
			printf( '<td>%s</td>', esc_html( (string) $r['label'] ) );
			printf( '<td>%s</td>', esc_html( (string) ( $providers[ $r['provider_slug'] ] ?? $r['provider_slug'] ) ) );
			printf( '<td>%s</td>', esc_html( (string) $r['model'] ) );
			printf( '<td>%s</td>', esc_html( (string) ( $purposes[ $r['purpose'] ] ?? '' ) ) );
			$flags = array();
			if ( ! empty( $r['gpai'] ) ) {
				$flags[] = 'GPAI';
			}
			if ( ! empty( $r['high_risk'] ) ) {
				$flags[] = __( 'High-risk', 'eurocomply-ai-act' );
			}
			printf( '<td>%s</td>', esc_html( implode( ', ', $flags ) ) );

			$edit_url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'providers', 'edit' => (int) $r['id'] ), admin_url( 'admin.php' ) );
			$del_url  = wp_nonce_url( add_query_arg( array( 'op' => 'delete', 'id' => (int) $r['id'] ), admin_url( 'admin-post.php?action=' . self::ACTION_PROVIDER ) ), self::NONCE_PROVIDER, '_wpnonce' );
			printf( '<td><a class="button" href="%s">%s</a> <a class="button button-link-delete" href="%s" onclick="return confirm(\'%s\');">%s</a></td>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'eurocomply-ai-act' ),
				esc_url( $del_url ),
				esc_js( __( 'Delete this entry?', 'eurocomply-ai-act' ) ),
				esc_html__( 'Delete', 'eurocomply-ai-act' )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_log() : void {
		$rows = DisclosureLog::recent( 200 );
		echo '<p><a class="button" href="' . esc_url( CsvExport::url( 'log' ) ) . '">' . esc_html__( 'Export CSV', 'eurocomply-ai-act' ) . '</a></p>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No disclosure-log entries yet.', 'eurocomply-ai-act' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', __( 'Date', 'eurocomply-ai-act' ), __( 'Post', 'eurocomply-ai-act' ), __( 'Action', 'eurocomply-ai-act' ), __( 'Provider', 'eurocomply-ai-act' ), __( 'User', 'eurocomply-ai-act' ) ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>';
			printf( '<td>#%d</td>', (int) $r['id'] );
			printf( '<td>%s</td>', esc_html( (string) $r['occurred_at'] ) );
			$post_link = (int) $r['post_id'] > 0 ? sprintf( '<a href="%1$s">#%2$d</a>', esc_url( (string) get_edit_post_link( (int) $r['post_id'] ) ), (int) $r['post_id'] ) : '—';
			echo '<td>' . $post_link . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf( '<td><code>%s</code></td>', esc_html( (string) $r['action'] ) );
			printf( '<td>%s</td>', esc_html( (string) $r['provider'] ) );
			printf( '<td>%s</td>', esc_html( (string) $r['user_login'] ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_policy() : void {
		$s       = Settings::get();
		$page_id = (int) $s['public_policy_page_id'];

		echo '<p>' . esc_html__( 'The AI transparency policy is auto-generated from your settings and the registered providers. You can place the [eurocomply_ai_policy] shortcode on any page, or click below to create a dedicated draft page.', 'eurocomply-ai-act' ) . '</p>';

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:24px;">
			<?php wp_nonce_field( self::NONCE_POLICY, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_POLICY ); ?>" />
			<button type="submit" class="button button-primary"><?php echo $page_id > 0 ? esc_html__( 'Re-create policy page', 'eurocomply-ai-act' ) : esc_html__( 'Create policy page', 'eurocomply-ai-act' ); ?></button>
			<?php if ( $page_id > 0 ) : ?>
				<a class="button" href="<?php echo esc_url( (string) get_permalink( $page_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View on site', 'eurocomply-ai-act' ); ?></a>
				<a class="button" href="<?php echo esc_url( (string) get_edit_post_link( $page_id ) ); ?>"><?php esc_html_e( 'Edit page', 'eurocomply-ai-act' ); ?></a>
			<?php endif; ?>
		</form>
		<?php

		echo '<h2>' . esc_html__( 'Live preview', 'eurocomply-ai-act' ) . '</h2>';
		echo '<div class="eurocomply-aiact-preview">';
		echo PolicyPageGenerator::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	private function render_settings() : void {
		$s = Settings::get();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />

			<h2><?php esc_html_e( 'Visibility', 'eurocomply-ai-act' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Frontend signals', 'eurocomply-ai-act' ); ?></th>
				<td>
					<label><input type="checkbox" name="eurocomply_ai_act[show_post_label]" value="1" <?php checked( ! empty( $s['show_post_label'] ) ); ?> /> <?php esc_html_e( 'Visible label on AI-marked posts', 'eurocomply-ai-act' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_ai_act[show_image_label]" value="1" <?php checked( ! empty( $s['show_image_label'] ) ); ?> /> <?php esc_html_e( 'Visible label on AI-generated images', 'eurocomply-ai-act' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_ai_act[show_meta_tag]" value="1" <?php checked( ! empty( $s['show_meta_tag'] ) ); ?> /> <?php esc_html_e( 'Emit <meta name="ai-generated"> tag', 'eurocomply-ai-act' ); ?></label><br />
					<label><input type="checkbox" name="eurocomply_ai_act[show_jsonld]" value="1" <?php checked( ! empty( $s['show_jsonld'] ) ); ?> /> <?php esc_html_e( 'Emit JSON-LD CreativeWork schema with AI properties', 'eurocomply-ai-act' ); ?></label>
				</td></tr>

				<tr><th><label><?php esc_html_e( 'Label position', 'eurocomply-ai-act' ); ?></label></th>
				<td><select name="eurocomply_ai_act[auto_label_position]">
					<?php foreach ( array( 'top' => __( 'Top of post', 'eurocomply-ai-act' ), 'bottom' => __( 'Bottom of post', 'eurocomply-ai-act' ), 'both' => __( 'Both', 'eurocomply-ai-act' ), 'none' => __( 'No automatic injection', 'eurocomply-ai-act' ) ) as $slug => $lbl ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) $s['auto_label_position'], $slug ); ?>><?php echo esc_html( $lbl ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>

				<tr><th><label><?php esc_html_e( 'Public post types', 'eurocomply-ai-act' ); ?></label></th>
				<td><?php
				$selected_types = is_array( $s['public_post_types'] ) ? $s['public_post_types'] : array( 'post', 'page' );
				$known          = get_post_types( array( 'public' => true ), 'objects' );
				foreach ( $known as $pt => $obj ) :
					?>
					<label style="margin-right:14px;"><input type="checkbox" name="eurocomply_ai_act[public_post_types][]" value="<?php echo esc_attr( $pt ); ?>" <?php checked( in_array( $pt, $selected_types, true ) ); ?> /> <?php echo esc_html( $obj->labels->singular_name ?? $pt ); ?></label>
				<?php endforeach; ?></td></tr>
			</table>

			<h2><?php esc_html_e( 'Label text', 'eurocomply-ai-act' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label><?php esc_html_e( 'Post label', 'eurocomply-ai-act' ); ?></label></th>
				<td><textarea name="eurocomply_ai_act[label_text_post]" rows="2" class="large-text"><?php echo esc_textarea( (string) $s['label_text_post'] ); ?></textarea></td></tr>

				<tr><th><label><?php esc_html_e( 'Image label', 'eurocomply-ai-act' ); ?></label></th>
				<td><textarea name="eurocomply_ai_act[label_text_image]" rows="2" class="large-text"><?php echo esc_textarea( (string) $s['label_text_image'] ); ?></textarea></td></tr>

				<tr><th><label><?php esc_html_e( 'Deepfake label', 'eurocomply-ai-act' ); ?></label></th>
				<td><textarea name="eurocomply_ai_act[label_text_deepfake]" rows="2" class="large-text"><?php echo esc_textarea( (string) $s['label_text_deepfake'] ); ?></textarea></td></tr>

				<tr><th><label><?php esc_html_e( 'Chatbot disclosure', 'eurocomply-ai-act' ); ?></label></th>
				<td><textarea name="eurocomply_ai_act[chatbot_disclosure_text]" rows="3" class="large-text"><?php echo esc_textarea( (string) $s['chatbot_disclosure_text'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Used by the [eurocomply_ai_disclosure] shortcode for Art. 50(1) compliance.', 'eurocomply-ai-act' ); ?></p></td></tr>
			</table>

			<h2><?php esc_html_e( 'Organisation', 'eurocomply-ai-act' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label><?php esc_html_e( 'Organisation name', 'eurocomply-ai-act' ); ?></label></th>
				<td><input type="text" name="eurocomply_ai_act[organisation_name]" class="regular-text" value="<?php echo esc_attr( (string) $s['organisation_name'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Country (ISO alpha-2)', 'eurocomply-ai-act' ); ?></label></th>
				<td><input type="text" name="eurocomply_ai_act[organisation_country]" class="small-text" maxlength="2" value="<?php echo esc_attr( (string) $s['organisation_country'] ); ?>" /></td></tr>

				<tr><th><label><?php esc_html_e( 'Contact email', 'eurocomply-ai-act' ); ?></label></th>
				<td><input type="email" name="eurocomply_ai_act[contact_email]" class="regular-text" value="<?php echo esc_attr( (string) $s['contact_email'] ); ?>" /></td></tr>
			</table>

			<h2><?php esc_html_e( 'Behaviour', 'eurocomply-ai-act' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Audit log', 'eurocomply-ai-act' ); ?></th>
				<td><label><input type="checkbox" name="eurocomply_ai_act[log_post_changes]" value="1" <?php checked( ! empty( $s['log_post_changes'] ) ); ?> /> <?php esc_html_e( 'Record marking / unmarking events to the disclosure log', 'eurocomply-ai-act' ); ?></label></td></tr>

				<tr><th><?php esc_html_e( 'Default disclosure', 'eurocomply-ai-act' ); ?></th>
				<td><label><input type="checkbox" name="eurocomply_ai_act[enforce_default_disclosure]" value="1" <?php checked( ! empty( $s['enforce_default_disclosure'] ) ); ?> /> <?php esc_html_e( 'Display the chatbot disclosure on every page (Pro)', 'eurocomply-ai-act' ); ?></label></td></tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_pro() : void {
		$pro = License::is_pro();
		echo '<p>' . esc_html__( 'Pro unlocks the deeper AI Act compliance integrations:', 'eurocomply-ai-act' ) . '</p>';
		echo '<ul class="eurocomply-aiact-pro-list">';
		$items = array(
			__( 'C2PA manifest server-side verification (Coalition for Content Provenance and Authenticity)', 'eurocomply-ai-act' ),
			__( 'Watermark detection (Google SynthID-style invisible signal scan on uploads)', 'eurocomply-ai-act' ),
			__( 'GPAI provider compliance scorecard (Art. 53 / 55 obligations)', 'eurocomply-ai-act' ),
			__( 'High-risk system Annex III classifier wizard (Art. 6, 16, 17)', 'eurocomply-ai-act' ),
			__( 'Multi-language disclosure templates (24 EU languages)', 'eurocomply-ai-act' ),
			__( 'Automated chatbot detection on the live site (banner injection)', 'eurocomply-ai-act' ),
			__( 'Auto-mark posts published via OpenAI / Anthropic / Gemini API hooks', 'eurocomply-ai-act' ),
			__( 'REST API + webhook for SIEM ingestion', 'eurocomply-ai-act' ),
			__( '5,000-row CSV cap (vs 500 free)', 'eurocomply-ai-act' ),
			__( 'WPML / Polylang multilingual policy', 'eurocomply-ai-act' ),
			__( 'EU AI Office submission templates (Art. 52 incident notification)', 'eurocomply-ai-act' ),
		);
		foreach ( $items as $i ) {
			echo '<li>' . esc_html( $i ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . ( $pro ? esc_html__( 'Pro is active.', 'eurocomply-ai-act' ) : esc_html__( 'Activate a license in the License tab to unlock Pro stubs.', 'eurocomply-ai-act' ) ) . '</p>';
	}

	private function render_license() : void {
		$license = License::get();
		$is_pro  = License::is_pro();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:520px;">
			<?php wp_nonce_field( self::NONCE_LIC, '_wpnonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_LIC ); ?>" />
			<table class="form-table" role="presentation">
				<tr><th><label for="license_key"><?php esc_html_e( 'License key', 'eurocomply-ai-act' ); ?></label></th>
				<td><input type="text" id="license_key" name="license_key" value="<?php echo esc_attr( (string) ( $license['key'] ?? '' ) ); ?>" class="regular-text" placeholder="EC-XXXXXX" />
				<p class="description"><?php echo $is_pro ? esc_html__( 'Pro is active.', 'eurocomply-ai-act' ) : esc_html__( 'Enter a license key in the form EC-XXXXXX to activate Pro stubs.', 'eurocomply-ai-act' ); ?></p></td></tr>
			</table>
			<p class="submit">
				<button type="submit" name="license_op" value="activate" class="button button-primary"><?php esc_html_e( 'Activate', 'eurocomply-ai-act' ); ?></button>
				<button type="submit" name="license_op" value="deactivate" class="button"><?php esc_html_e( 'Deactivate', 'eurocomply-ai-act' ); ?></button>
			</p>
		</form>
		<?php
	}

	public function handle_save() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-ai-act' ), 403 );
		}
		check_admin_referer( self::NONCE_SAVE );

		$input     = isset( $_POST['eurocomply_ai_act'] ) && is_array( $_POST['eurocomply_ai_act'] )
			? wp_unslash( (array) $_POST['eurocomply_ai_act'] )
			: array();
		$sanitized = Settings::sanitize( $input );
		update_option( Settings::OPTION_KEY, $sanitized, false );

		add_settings_error( 'eurocomply_ai_act', 'saved', __( 'Settings saved.', 'eurocomply-ai-act' ), 'updated' );
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
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-ai-act' ), 403 );
		}
		check_admin_referer( self::NONCE_LIC );

		$op  = isset( $_POST['license_op'] ) ? sanitize_key( (string) $_POST['license_op'] ) : '';
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) ) : '';

		if ( 'deactivate' === $op ) {
			License::deactivate();
			add_settings_error( 'eurocomply_ai_act', 'lic_off', __( 'License deactivated.', 'eurocomply-ai-act' ), 'updated' );
		} else {
			$res = License::activate( $key );
			add_settings_error( 'eurocomply_ai_act', 'lic', $res['message'], $res['ok'] ? 'updated' : 'error' );
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

	public function handle_provider() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-ai-act' ), 403 );
		}
		check_admin_referer( self::NONCE_PROVIDER );

		$op = isset( $_REQUEST['op'] ) ? sanitize_key( (string) $_REQUEST['op'] ) : '';
		$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

		if ( 'delete' === $op && $id > 0 ) {
			ProviderStore::delete( $id );
			add_settings_error( 'eurocomply_ai_act', 'p_del', __( 'AI tool deleted.', 'eurocomply-ai-act' ), 'updated' );
		} elseif ( 'save' === $op ) {
			$data = array(
				'label'             => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['label'] ) ) : '',
				'provider_slug'     => isset( $_POST['provider_slug'] ) ? sanitize_key( (string) $_POST['provider_slug'] ) : 'other',
				'model'             => isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['model'] ) ) : '',
				'purpose'           => isset( $_POST['purpose'] ) ? sanitize_key( (string) $_POST['purpose'] ) : '',
				'country'           => isset( $_POST['country'] ) ? (string) $_POST['country'] : '',
				'vendor_legal_name' => isset( $_POST['vendor_legal_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vendor_legal_name'] ) ) : '',
				'gpai'              => ! empty( $_POST['gpai'] ) ? 1 : 0,
				'high_risk'         => ! empty( $_POST['high_risk'] ) ? 1 : 0,
				'notes'             => isset( $_POST['notes'] ) ? (string) $_POST['notes'] : '',
			);
			if ( $id > 0 ) {
				ProviderStore::update( $id, $data );
				add_settings_error( 'eurocomply_ai_act', 'p_up', __( 'AI tool updated.', 'eurocomply-ai-act' ), 'updated' );
			} else {
				ProviderStore::create( $data );
				add_settings_error( 'eurocomply_ai_act', 'p_new', __( 'AI tool created.', 'eurocomply-ai-act' ), 'updated' );
			}
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => 'providers', 'settings-updated' => 'true' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_policy() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'eurocomply-ai-act' ), 403 );
		}
		check_admin_referer( self::NONCE_POLICY );

		$id = PolicyPageGenerator::ensure_page();
		if ( $id > 0 ) {
			add_settings_error( 'eurocomply_ai_act', 'policy', __( 'Policy page created as draft. Edit and publish when ready.', 'eurocomply-ai-act' ), 'updated' );
		} else {
			add_settings_error( 'eurocomply_ai_act', 'policy_err', __( 'Could not create policy page.', 'eurocomply-ai-act' ), 'error' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'tab' => 'policy', 'settings-updated' => 'true' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
