<?php
/**
 * Admin settings page for WP Agent Memory.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use WPAM\WordPress\Memory\Response_Shaper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	/**
	 * Handle the migrate-content admin-post action.
	 */
	public function handle_migrate_content(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		check_admin_referer( 'wpam_migrate_content' );

		$entries = get_posts( array(
			'post_type'      => 'memory_entry',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );

		$migrated = 0;

		foreach ( $entries as $entry ) {
			if ( ! str_contains( $entry->post_content, '<!-- wp:wpam/markdown' ) ) {
				continue;
			}

			$plain = Response_Shaper::extract_content( $entry->post_content );

			kses_remove_filters();
			wp_update_post( array(
				'ID'           => $entry->ID,
				'post_content' => $plain,
			) );
			kses_init_filters();

			$migrated++;
		}

		wp_safe_redirect( add_query_arg(
			array(
				'page'            => 'wp-agent-memory',
				'wpam_migrated'   => $migrated,
			),
			admin_url( 'options-general.php' )
		) );
		exit;
	}

	/**
	 * Handle the migrate-keywords-to-topic admin-post action.
	 */
	public function handle_migrate_keywords_to_topic(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		check_admin_referer( 'wpam_migrate_keywords_to_topic' );

		$entries = get_posts( array(
			'post_type'      => 'memory_entry',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );

		$migrated = 0;

		foreach ( $entries as $entry ) {
			$keywords_raw = (string) get_post_meta( $entry->ID, 'keywords', true );
			if ( '' === $keywords_raw ) {
				continue;
			}

			$terms = array_values( array_filter( array_map( 'trim', explode( ',', $keywords_raw ) ) ) );
			if ( empty( $terms ) ) {
				continue;
			}

			wp_set_post_terms( $entry->ID, $terms, 'memory_topic', true );
			delete_post_meta( $entry->ID, 'keywords' );
			$migrated++;
		}

		wp_safe_redirect( add_query_arg(
			array(
				'page'              => 'wp-agent-memory',
				'wpam_kw_migrated'  => $migrated,
			),
			admin_url( 'options-general.php' )
		) );
		exit;
	}

	/**
	 * Register the Agent Memory settings page under Settings.
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'Agent Memory', 'wp-agent-memory' ),
			__( 'Agent Memory', 'wp-agent-memory' ),
			'manage_options',
			'wp-agent-memory',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register plugin settings, sections, and admin fields.
	 */
	public function register_settings(): void {
		register_setting(
			'wpam_settings',
			'wpam_github_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_section(
			'wpam_external',
			__( 'External APIs', 'wp-agent-memory' ),
			'__return_null',
			'wp-agent-memory'
		);

		add_settings_field(
			'wpam_github_token',
			__( 'GitHub Token', 'wp-agent-memory' ),
			array( $this, 'render_github_token_field' ),
			'wp-agent-memory',
			'wpam_external',
			array( 'label_for' => 'wpam_github_token' )
		);
	}

	/**
	 * Render the masked GitHub token input field.
	 */
	public function render_github_token_field(): void {
		$value = (string) get_option( 'wpam_github_token', '' );
		?>
		<input
			type="password"
			id="wpam_github_token"
			name="wpam_github_token"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			autocomplete="new-password"
		/>
		<p class="description">
			<?php esc_html_e( 'Optional. Increases GitHub API rate limits for the search-github-issues ability. Generate a token at github.com/settings/tokens (no scopes required for public repos).', 'wp-agent-memory' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the plugin settings page contents.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php if ( isset( $_GET['wpam_migrated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible">
					<p><?php printf( esc_html__( 'Migrated %d memory entries to the new block format.', 'wp-agent-memory' ), (int) $_GET['wpam_migrated'] ); // phpcs:ignore WordPress.Security.NonceVerification ?></p>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['wpam_kw_migrated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible">
					<p><?php printf( esc_html__( 'Migrated keywords to topics for %d memory entries.', 'wp-agent-memory' ), (int) $_GET['wpam_kw_migrated'] ); // phpcs:ignore WordPress.Security.NonceVerification ?></p>
				</div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpam_settings' );
				do_settings_sections( 'wp-agent-memory' );
				submit_button();
				?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'Maintenance', 'wp-agent-memory' ); ?></h2>
			<p><?php esc_html_e( 'Convert memory entries from the legacy block format (raw Markdown between block comment delimiters) to the current format (Markdown in block comment JSON attributes). Run once after updating to a version that includes this fix.', 'wp-agent-memory' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpam_migrate_content" />
				<?php wp_nonce_field( 'wpam_migrate_content' ); ?>
				<?php submit_button( __( 'Migrate Content Format', 'wp-agent-memory' ), 'secondary', 'submit', false ); ?>
			</form>
			<br />
			<p><?php esc_html_e( 'Move keyword meta values into the Topic taxonomy so all search terms are first-class taxonomy members. Run once after updating.', 'wp-agent-memory' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpam_migrate_keywords_to_topic" />
				<?php wp_nonce_field( 'wpam_migrate_keywords_to_topic' ); ?>
				<?php submit_button( __( 'Migrate Keywords to Topics', 'wp-agent-memory' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add the memory count column to the users list table.
	 *
	 * @param array<string, string> $columns Existing users list columns.
	 * @return array<string, string>
	 */
	public function add_memories_column( array $columns ): array {
		$columns['memory_count'] = __( 'Memories', 'wp-agent-memory' );
		return $columns;
	}

	/**
	 * Render the custom memory count cell value for a user row.
	 *
	 * @param string $output  Existing rendered output.
	 * @param string $column  Column key currently being rendered.
	 * @param int    $user_id Current user ID.
	 * @return string
	 */
	public function render_memories_column( string $output, string $column, int $user_id ): string {
		if ( 'memory_count' !== $column ) {
			return $output;
		}
		return (string) count_user_posts( $user_id, 'memory_entry', true );
	}

	/**
	 * Return the GitHub token, preferring the server env var for backwards compat.
	 */
	public static function get_github_token(): ?string {
		$env = getenv( 'GITHUB_TOKEN' );
		if ( false !== $env && '' !== $env ) {
			return $env;
		}

		$option = (string) get_option( 'wpam_github_token', '' );

		return '' !== $option ? $option : null;
	}
}
