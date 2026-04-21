<?php
/**
 * Admin settings page for WP Agent Memory.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public function register_menu(): void {
		add_options_page(
			__( 'Agent Memory', 'wp-agent-memory' ),
			__( 'Agent Memory', 'wp-agent-memory' ),
			'manage_options',
			'wp-agent-memory',
			array( $this, 'render_page' )
		);
	}

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

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpam_settings' );
				do_settings_sections( 'wp-agent-memory' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function add_memories_column( array $columns ): array {
		$columns['memory_count'] = __( 'Memories', 'wp-agent-memory' );
		return $columns;
	}

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
