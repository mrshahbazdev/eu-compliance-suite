<?php
/**
 * Per-post AI Act transparency meta.
 *
 * Stored as post meta:
 *   _eurocomply_aiact_generated      bool 0|1
 *   _eurocomply_aiact_provider       provider slug (see Settings::ai_providers_known)
 *   _eurocomply_aiact_model          string
 *   _eurocomply_aiact_purpose        purpose slug (see Settings::ai_purposes)
 *   _eurocomply_aiact_human_edited   bool 0|1
 *   _eurocomply_aiact_deepfake       bool 0|1
 *   _eurocomply_aiact_prompt         text (short summary)
 *   _eurocomply_aiact_c2pa_url       url to C2PA manifest
 *
 * @package EuroComply\AIAct
 */

declare( strict_types = 1 );

namespace EuroComply\AIAct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostMeta {

	public const NONCE = 'eurocomply_aiact_post_meta';

	private static ?PostMeta $instance = null;

	public static function instance() : PostMeta {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
		return self::$instance;
	}

	private function register() : void {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	public function register_metabox() : void {
		$post_types = Settings::get()['public_post_types'];
		if ( ! is_array( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'eurocomply_aiact_meta',
				__( 'AI Act transparency (Art. 50)', 'eurocomply-ai-act' ),
				array( $this, 'render' ),
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render( \WP_Post $post ) : void {
		wp_nonce_field( self::NONCE, '_eurocomply_aiact_nonce' );

		$generated    = (string) get_post_meta( $post->ID, '_eurocomply_aiact_generated', true );
		$provider     = (string) get_post_meta( $post->ID, '_eurocomply_aiact_provider', true );
		$model        = (string) get_post_meta( $post->ID, '_eurocomply_aiact_model', true );
		$purpose      = (string) get_post_meta( $post->ID, '_eurocomply_aiact_purpose', true );
		$human_edited = (string) get_post_meta( $post->ID, '_eurocomply_aiact_human_edited', true );
		$deepfake     = (string) get_post_meta( $post->ID, '_eurocomply_aiact_deepfake', true );
		$prompt       = (string) get_post_meta( $post->ID, '_eurocomply_aiact_prompt', true );
		$c2pa         = (string) get_post_meta( $post->ID, '_eurocomply_aiact_c2pa_url', true );

		?>
		<p><label><input type="checkbox" name="eurocomply_aiact[generated]" value="1" <?php checked( '1', $generated ); ?> /> <strong><?php esc_html_e( 'AI-generated or AI-assisted content', 'eurocomply-ai-act' ); ?></strong></label></p>

		<p><label><?php esc_html_e( 'Provider', 'eurocomply-ai-act' ); ?><br />
			<select name="eurocomply_aiact[provider]" style="width:100%;">
				<option value=""><?php esc_html_e( '— select —', 'eurocomply-ai-act' ); ?></option>
				<?php foreach ( Settings::ai_providers_known() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $provider, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label></p>

		<p><label><?php esc_html_e( 'Model name', 'eurocomply-ai-act' ); ?><br />
			<input type="text" name="eurocomply_aiact[model]" class="widefat" value="<?php echo esc_attr( $model ); ?>" placeholder="e.g. gpt-4o-2024-08-06" />
		</label></p>

		<p><label><?php esc_html_e( 'Purpose', 'eurocomply-ai-act' ); ?><br />
			<select name="eurocomply_aiact[purpose]" style="width:100%;">
				<option value=""><?php esc_html_e( '— select —', 'eurocomply-ai-act' ); ?></option>
				<?php foreach ( Settings::ai_purposes() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $purpose, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label></p>

		<p><label><input type="checkbox" name="eurocomply_aiact[human_edited]" value="1" <?php checked( '1', $human_edited ); ?> /> <?php esc_html_e( 'Human edited / reviewed', 'eurocomply-ai-act' ); ?></label></p>

		<p><label><input type="checkbox" name="eurocomply_aiact[deepfake]" value="1" <?php checked( '1', $deepfake ); ?> /> <?php esc_html_e( 'Contains deepfake / synthetic media (Art. 50(3))', 'eurocomply-ai-act' ); ?></label></p>

		<p><label><?php esc_html_e( 'Prompt summary (optional, internal)', 'eurocomply-ai-act' ); ?><br />
			<textarea name="eurocomply_aiact[prompt]" rows="3" class="widefat"><?php echo esc_textarea( $prompt ); ?></textarea>
		</label></p>

		<p><label><?php esc_html_e( 'C2PA manifest URL (optional)', 'eurocomply-ai-act' ); ?><br />
			<input type="url" name="eurocomply_aiact[c2pa_url]" class="widefat" value="<?php echo esc_attr( $c2pa ); ?>" placeholder="https://..." />
		</label></p>

		<p class="description"><?php esc_html_e( 'Per Art. 50(2) Regulation (EU) 2024/1689, providers and deployers must disclose AI-generated content in machine-readable form. This metadata feeds the public label and the JSON-LD schema on the rendered page.', 'eurocomply-ai-act' ); ?></p>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ) : void {
		if ( ! isset( $_POST['_eurocomply_aiact_nonce'] ) || ! wp_verify_nonce( (string) $_POST['_eurocomply_aiact_nonce'], self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$input = isset( $_POST['eurocomply_aiact'] ) && is_array( $_POST['eurocomply_aiact'] )
			? wp_unslash( (array) $_POST['eurocomply_aiact'] )
			: array();

		$prev_generated = (string) get_post_meta( $post_id, '_eurocomply_aiact_generated', true );

		$generated    = ! empty( $input['generated'] ) ? '1' : '0';
		$human_edited = ! empty( $input['human_edited'] ) ? '1' : '0';
		$deepfake     = ! empty( $input['deepfake'] ) ? '1' : '0';
		$provider     = isset( $input['provider'] ) ? sanitize_key( (string) $input['provider'] ) : '';
		$model        = isset( $input['model'] ) ? substr( sanitize_text_field( (string) $input['model'] ), 0, 191 ) : '';
		$purpose      = isset( $input['purpose'] ) ? sanitize_key( (string) $input['purpose'] ) : '';
		$prompt       = isset( $input['prompt'] ) ? substr( sanitize_textarea_field( (string) $input['prompt'] ), 0, 2000 ) : '';
		$c2pa         = isset( $input['c2pa_url'] ) ? esc_url_raw( (string) $input['c2pa_url'] ) : '';

		if ( ! isset( Settings::ai_providers_known()[ $provider ] ) ) {
			$provider = '';
		}
		if ( ! isset( Settings::ai_purposes()[ $purpose ] ) ) {
			$purpose = '';
		}

		$set = function ( string $key, string $value ) use ( $post_id ) : void {
			if ( '' === $value || '0' === $value && in_array( $key, array( '_eurocomply_aiact_generated', '_eurocomply_aiact_human_edited', '_eurocomply_aiact_deepfake' ), true ) ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		};

		$set( '_eurocomply_aiact_generated',    $generated );
		$set( '_eurocomply_aiact_provider',     $provider );
		$set( '_eurocomply_aiact_model',        $model );
		$set( '_eurocomply_aiact_purpose',      $purpose );
		$set( '_eurocomply_aiact_human_edited', $human_edited );
		$set( '_eurocomply_aiact_deepfake',     $deepfake );
		$set( '_eurocomply_aiact_prompt',       $prompt );
		$set( '_eurocomply_aiact_c2pa_url',     $c2pa );

		$settings = Settings::get();
		if ( ! empty( $settings['log_post_changes'] ) && $prev_generated !== $generated ) {
			DisclosureLog::record(
				array(
					'post_id'  => $post_id,
					'action'   => '1' === $generated ? 'marked_ai' : 'unmarked_ai',
					'provider' => $provider,
					'purpose'  => $purpose,
					'user_id'  => get_current_user_id(),
				)
			);
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_for_post( int $post_id ) : array {
		return array(
			'generated'    => '1' === (string) get_post_meta( $post_id, '_eurocomply_aiact_generated', true ),
			'provider'     => (string) get_post_meta( $post_id, '_eurocomply_aiact_provider', true ),
			'model'        => (string) get_post_meta( $post_id, '_eurocomply_aiact_model', true ),
			'purpose'      => (string) get_post_meta( $post_id, '_eurocomply_aiact_purpose', true ),
			'human_edited' => '1' === (string) get_post_meta( $post_id, '_eurocomply_aiact_human_edited', true ),
			'deepfake'     => '1' === (string) get_post_meta( $post_id, '_eurocomply_aiact_deepfake', true ),
			'prompt'       => (string) get_post_meta( $post_id, '_eurocomply_aiact_prompt', true ),
			'c2pa_url'     => (string) get_post_meta( $post_id, '_eurocomply_aiact_c2pa_url', true ),
		);
	}

	/**
	 * Iterate AI-marked posts.
	 *
	 * @return array<int,int> array of post IDs
	 */
	public static function ai_marked_post_ids( int $limit = 200 ) : array {
		$q = new \WP_Query( array(
			'post_type'      => 'any',
			'posts_per_page' => max( 1, min( 5000, $limit ) ),
			'post_status'    => 'any',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_eurocomply_aiact_generated',
					'value'   => '1',
					'compare' => '=',
				),
			),
		) );
		return array_map( 'intval', (array) $q->posts );
	}
}
