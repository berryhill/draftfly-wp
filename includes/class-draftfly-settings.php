<?php
/**
 * DraftFly Settings Page
 *
 * @package DraftFly
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DraftFly Settings Class
 */
class DraftFly_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_draftfly_generate_api_key', array( $this, 'generate_api_key' ) );
		add_action( 'admin_post_draftfly_revoke_api_key', array( $this, 'revoke_api_key' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add settings page to WordPress admin
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'DraftFly Settings', 'draftfly' ),
			__( 'DraftFly', 'draftfly' ),
			'manage_options',
			'draftfly-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-edit',
			30
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'draftfly_settings',
			'draftfly_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'toplevel_page_draftfly-settings' !== $hook ) {
			return;
		}

		wp_add_inline_style(
			'wp-admin',
			'
			.draftfly-card {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 20px;
				margin-bottom: 20px;
			}
			.draftfly-key-display {
				background: #f6f7f7;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				padding: 15px;
				font-family: monospace;
				font-size: 14px;
				word-break: break-all;
				margin: 15px 0;
			}
			.draftfly-endpoint {
				background: #f6f7f7;
				border-left: 4px solid #2271b1;
				padding: 12px;
				margin: 10px 0;
				font-family: monospace;
				font-size: 13px;
			}
			.draftfly-status-healthy {
				color: #00a32a;
			}
			.draftfly-status-warning {
				color: #dba617;
			}
			.draftfly-copy-btn {
				margin-left: 10px;
			}
			'
		);
	}

	/**
	 * Generate new API key
	 */
	public function generate_api_key() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'draftfly_generate_api_key' ) ) {
			wp_die( __( 'Security check failed', 'draftfly' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'draftfly' ) );
		}

		// Generate cryptographically secure API key
		$api_key = $this->create_api_key();

		// Store API key
		update_option( 'draftfly_api_key', $api_key );

		// Redirect back with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'draftfly-settings',
					'message' => 'key_generated',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Revoke API key
	 */
	public function revoke_api_key() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'draftfly_revoke_api_key' ) ) {
			wp_die( __( 'Security check failed', 'draftfly' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'draftfly' ) );
		}

		// Delete API key
		delete_option( 'draftfly_api_key' );

		// Redirect back with success message
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'draftfly-settings',
					'message' => 'key_revoked',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Create a cryptographically secure API key
	 *
	 * @return string
	 */
	private function create_api_key() {
		return 'dfwp_' . bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		$api_key     = get_option( 'draftfly_api_key' );
		$has_api_key = ! empty( $api_key );
		$site_url    = get_site_url();

		// Show success messages
		if ( isset( $_GET['message'] ) ) {
			$message = sanitize_text_field( $_GET['message'] );
			if ( 'key_generated' === $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'API key generated successfully!', 'draftfly' ) . '</p></div>';
			} elseif ( 'key_revoked' === $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'API key revoked successfully!', 'draftfly' ) . '</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Configure DraftFly to accept posts from external platforms.', 'draftfly' ); ?></p>

			<!-- API Key Section -->
			<div class="draftfly-card">
				<h2><?php esc_html_e( 'API Key', 'draftfly' ); ?></h2>
				<p><?php esc_html_e( 'Generate an API key to authenticate requests from DraftFly or other platforms.', 'draftfly' ); ?></p>

				<?php if ( $has_api_key ) : ?>
					<div class="draftfly-key-display">
						<strong><?php esc_html_e( 'Current API Key:', 'draftfly' ); ?></strong><br>
						<span id="draftfly-api-key"><?php echo esc_html( $api_key ); ?></span>
						<button type="button" class="button draftfly-copy-btn" onclick="draftflyCopyApiKey()">
							<?php esc_html_e( 'Copy', 'draftfly' ); ?>
						</button>
					</div>

					<p class="description">
						<span class="draftfly-status-warning">⚠️</span>
						<?php esc_html_e( 'Keep this key secure. Anyone with this key can create and modify posts on your site.', 'draftfly' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
						<input type="hidden" name="action" value="draftfly_revoke_api_key">
						<?php wp_nonce_field( 'draftfly_revoke_api_key' ); ?>
						<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure? This will immediately invalidate the current API key.', 'draftfly' ); ?>')">
							<?php esc_html_e( 'Revoke API Key', 'draftfly' ); ?>
						</button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
						<input type="hidden" name="action" value="draftfly_generate_api_key">
						<?php wp_nonce_field( 'draftfly_generate_api_key' ); ?>
						<button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'Generate a new API key? The old key will stop working.', 'draftfly' ); ?>')">
							<?php esc_html_e( 'Regenerate API Key', 'draftfly' ); ?>
						</button>
					</form>
				<?php else : ?>
					<p class="description">
						<span class="draftfly-status-warning">⚠️</span>
						<?php esc_html_e( 'No API key has been generated yet. Click the button below to create one.', 'draftfly' ); ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="draftfly_generate_api_key">
						<?php wp_nonce_field( 'draftfly_generate_api_key' ); ?>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Generate API Key', 'draftfly' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<!-- API Endpoints Documentation -->
			<div class="draftfly-card">
				<h2><?php esc_html_e( 'API Endpoints', 'draftfly' ); ?></h2>
				<p><?php esc_html_e( 'Use these endpoints to integrate with DraftFly or other platforms.', 'draftfly' ); ?></p>

				<h3><?php esc_html_e( '1. Health Check', 'draftfly' ); ?></h3>
				<div class="draftfly-endpoint">
					GET <?php echo esc_url( $site_url ); ?>/wp-json/draftfly/v1/health
				</div>

				<h3><?php esc_html_e( '2. Validate Credentials', 'draftfly' ); ?></h3>
				<div class="draftfly-endpoint">
					GET <?php echo esc_url( $site_url ); ?>/wp-json/draftfly/v1/auth/validate
				</div>

				<h3><?php esc_html_e( '3. Create Post', 'draftfly' ); ?></h3>
				<div class="draftfly-endpoint">
					POST <?php echo esc_url( $site_url ); ?>/wp-json/draftfly/v1/posts
				</div>

				<h3><?php esc_html_e( '4. Update Post', 'draftfly' ); ?></h3>
				<div class="draftfly-endpoint">
					PATCH <?php echo esc_url( $site_url ); ?>/wp-json/draftfly/v1/posts/{id}
				</div>

				<p class="description">
					<?php esc_html_e( 'All requests must include the header:', 'draftfly' ); ?>
					<code>x-api-key: YOUR_API_KEY</code>
				</p>
			</div>

			<!-- Test Connection -->
			<?php if ( $has_api_key ) : ?>
			<div class="draftfly-card">
				<h2><?php esc_html_e( 'Test Connection', 'draftfly' ); ?></h2>
				<p><?php esc_html_e( 'Test your API connection using curl:', 'draftfly' ); ?></p>
				<div class="draftfly-endpoint">
curl -X GET "<?php echo esc_url( $site_url ); ?>/wp-json/draftfly/v1/health" \<br>
&nbsp;&nbsp;-H "x-api-key: <?php echo esc_html( $api_key ); ?>"
				</div>
				<p class="description">
					<?php esc_html_e( 'Expected response:', 'draftfly' ); ?>
					<code>{"status":"healthy"}</code>
				</p>
			</div>
			<?php endif; ?>
		</div>

		<script>
		function draftflyCopyApiKey() {
			const apiKey = document.getElementById('draftfly-api-key').textContent;
			navigator.clipboard.writeText(apiKey).then(function() {
				alert('<?php esc_html_e( 'API key copied to clipboard!', 'draftfly' ); ?>');
			}, function() {
				alert('<?php esc_html_e( 'Failed to copy API key', 'draftfly' ); ?>');
			});
		}
		</script>
		<?php
	}
}
