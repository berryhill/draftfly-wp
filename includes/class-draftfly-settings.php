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
		add_action( 'admin_post_draftfly_clear_logs', array( $this, 'clear_logs' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_draftfly_get_logs', array( $this, 'ajax_get_logs' ) );
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
			.draftfly-logs {
				background: #1e1e1e;
				color: #d4d4d4;
				font-family: "Courier New", Courier, monospace;
				font-size: 12px;
				padding: 15px;
				border-radius: 4px;
				height: 400px;
				overflow-y: auto;
				white-space: pre-wrap;
				word-wrap: break-word;
			}
			.draftfly-logs .log-error {
				color: #f48771;
			}
			.draftfly-logs .log-success {
				color: #89d185;
			}
			.draftfly-logs .log-info {
				color: #6cb6ff;
			}
			.draftfly-log-actions {
				margin-top: 10px;
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
	 * Clear debug logs
	 */
	public function clear_logs() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'draftfly_clear_logs' ) ) {
			wp_die( __( 'Security check failed', 'draftfly' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'draftfly' ) );
		}

		// Clear the debug log file
		$log_file = WP_CONTENT_DIR . '/debug.log';
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
		}

		// Redirect back
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'draftfly-settings',
					'message' => 'logs_cleared',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Get logs via AJAX
	 */
	public function ajax_get_logs() {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$logs = $this->get_draftfly_logs();
		wp_send_json_success( array( 'logs' => $logs ) );
	}

	/**
	 * Get DraftFly logs from debug.log
	 *
	 * @param int $lines Number of lines to retrieve.
	 * @return string
	 */
	private function get_draftfly_logs( $lines = 200 ) {
		$log_file = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_file ) ) {
			return __( 'No debug log file found. Make sure WP_DEBUG_LOG is enabled in wp-config.php', 'draftfly' );
		}

		// Read the file
		$file_contents = file_get_contents( $log_file );
		if ( empty( $file_contents ) ) {
			return __( 'Debug log is empty.', 'draftfly' );
		}

		// Split into lines and filter for DraftFly entries
		$all_lines     = explode( "\n", $file_contents );
		$draftfly_logs = array();

		foreach ( $all_lines as $line ) {
			if ( strpos( $line, 'DraftFly:' ) !== false ) {
				$draftfly_logs[] = $line;
			}
		}

		if ( empty( $draftfly_logs ) ) {
			return __( 'No DraftFly logs found in debug.log', 'draftfly' );
		}

		// Get last N lines
		$recent_logs = array_slice( $draftfly_logs, -$lines );

		return implode( "\n", $recent_logs );
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		$api_key     = get_option( 'draftfly_api_key' );
		$has_api_key = ! empty( $api_key );
		$site_url    = get_site_url();
		$api_base_url = $site_url . '/wp-json/draftfly/v1';

		// Show success messages
		if ( isset( $_GET['message'] ) ) {
			$message = sanitize_text_field( $_GET['message'] );
			if ( 'key_generated' === $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'API key generated successfully!', 'draftfly' ) . '</p></div>';
			} elseif ( 'key_revoked' === $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'API key revoked successfully!', 'draftfly' ) . '</p></div>';
			} elseif ( 'logs_cleared' === $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Debug logs cleared successfully!', 'draftfly' ) . '</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Configure DraftFly to accept posts from external platforms.', 'draftfly' ); ?></p>

			<!-- Base URL Section -->
			<div class="draftfly-card">
				<h2><?php esc_html_e( 'Base URL', 'draftfly' ); ?></h2>
				<p><?php esc_html_e( 'This is your DraftFly API base URL. Use this when configuring DraftFly.', 'draftfly' ); ?></p>

				<div class="draftfly-key-display">
					<strong><?php esc_html_e( 'Base URL:', 'draftfly' ); ?></strong><br>
					<span id="draftfly-base-url"><?php echo esc_url( $api_base_url ); ?></span>
					<button type="button" class="button draftfly-copy-btn" onclick="draftflyCopyBaseUrl()">
						<?php esc_html_e( 'Copy', 'draftfly' ); ?>
					</button>
				</div>

				<p class="description">
					<?php esc_html_e( 'All API endpoints will be relative to this base URL.', 'draftfly' ); ?>
				</p>
			</div>

			<!-- API Key Section -->
			<div class="draftfly-card">
				<h2><?php esc_html_e( 'API Key', 'draftfly' ); ?></h2>
				<p><?php esc_html_e( 'Generate an API key to authenticate requests from DraftFly or other platforms.', 'draftfly' ); ?></p>

				<?php if ( $has_api_key ) : ?>
					<div class="draftfly-key-display">
						<strong><?php esc_html_e( 'Current API Key:', 'draftfly' ); ?></strong><br>
						<span id="draftfly-api-key" data-key="<?php echo esc_attr( $api_key ); ?>">••••••••••••••••••••••••••••••••••••••••</span>
						<button type="button" class="button draftfly-copy-btn" onclick="draftflyToggleApiKey()" id="draftfly-toggle-btn">
							<?php esc_html_e( 'Show', 'draftfly' ); ?>
						</button>
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

			<!-- Debug Logs -->
			<div class="draftfly-card">
				<h2><?php esc_html_e( 'Debug Logs', 'draftfly' ); ?></h2>
				<p><?php esc_html_e( 'View DraftFly debug logs in real-time. Logs auto-refresh every 5 seconds.', 'draftfly' ); ?></p>

				<div id="draftfly-logs-container" class="draftfly-logs">
					<?php echo esc_html( $this->get_draftfly_logs() ); ?>
				</div>

				<div class="draftfly-log-actions">
					<button type="button" class="button" id="draftfly-refresh-logs">
						<?php esc_html_e( 'Refresh Now', 'draftfly' ); ?>
					</button>

					<button type="button" class="button" id="draftfly-toggle-auto-refresh">
						<?php esc_html_e( 'Pause Auto-Refresh', 'draftfly' ); ?>
					</button>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
						<input type="hidden" name="action" value="draftfly_clear_logs">
						<?php wp_nonce_field( 'draftfly_clear_logs' ); ?>
						<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Clear all debug logs? This cannot be undone.', 'draftfly' ); ?>')">
							<?php esc_html_e( 'Clear Logs', 'draftfly' ); ?>
						</button>
					</form>
				</div>

				<p class="description" style="margin-top: 10px;">
					<?php esc_html_e( 'Showing the last 200 DraftFly log entries from wp-content/debug.log', 'draftfly' ); ?>
				</p>
			</div>
		</div>

		<script>
		let apiKeyVisible = false;
		let autoRefreshEnabled = true;
		let refreshInterval;

		// Auto-refresh logs
		function draftflyRefreshLogs() {
			jQuery.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'draftfly_get_logs'
				},
				success: function(response) {
					if (response.success && response.data.logs) {
						const logsContainer = document.getElementById('draftfly-logs-container');
						const wasScrolledToBottom = logsContainer.scrollHeight - logsContainer.scrollTop === logsContainer.clientHeight;

						logsContainer.textContent = response.data.logs;

						// Auto-scroll to bottom if already at bottom
						if (wasScrolledToBottom) {
							logsContainer.scrollTop = logsContainer.scrollHeight;
						}
					}
				}
			});
		}

		// Start auto-refresh
		function startAutoRefresh() {
			if (!refreshInterval) {
				refreshInterval = setInterval(draftflyRefreshLogs, 5000);
			}
		}

		// Stop auto-refresh
		function stopAutoRefresh() {
			if (refreshInterval) {
				clearInterval(refreshInterval);
				refreshInterval = null;
			}
		}

		// Initialize on page load
		jQuery(document).ready(function($) {
			// Start auto-refresh
			startAutoRefresh();

			// Refresh button
			$('#draftfly-refresh-logs').on('click', function() {
				draftflyRefreshLogs();
			});

			// Toggle auto-refresh
			$('#draftfly-toggle-auto-refresh').on('click', function() {
				autoRefreshEnabled = !autoRefreshEnabled;
				if (autoRefreshEnabled) {
					$(this).text('<?php esc_html_e( 'Pause Auto-Refresh', 'draftfly' ); ?>');
					startAutoRefresh();
				} else {
					$(this).text('<?php esc_html_e( 'Resume Auto-Refresh', 'draftfly' ); ?>');
					stopAutoRefresh();
				}
			});

			// Scroll to bottom on load
			const logsContainer = document.getElementById('draftfly-logs-container');
			logsContainer.scrollTop = logsContainer.scrollHeight;
		});

		function draftflyCopyBaseUrl() {
			const baseUrl = document.getElementById('draftfly-base-url').textContent;
			navigator.clipboard.writeText(baseUrl).then(function() {
				alert('<?php esc_html_e( 'Base URL copied to clipboard!', 'draftfly' ); ?>');
			}, function() {
				alert('<?php esc_html_e( 'Failed to copy base URL', 'draftfly' ); ?>');
			});
		}

		function draftflyToggleApiKey() {
			const apiKeyElement = document.getElementById('draftfly-api-key');
			const toggleBtn = document.getElementById('draftfly-toggle-btn');
			const actualKey = apiKeyElement.getAttribute('data-key');

			if (apiKeyVisible) {
				apiKeyElement.textContent = '••••••••••••••••••••••••••••••••••••••••';
				toggleBtn.textContent = '<?php esc_html_e( 'Show', 'draftfly' ); ?>';
				apiKeyVisible = false;
			} else {
				apiKeyElement.textContent = actualKey;
				toggleBtn.textContent = '<?php esc_html_e( 'Hide', 'draftfly' ); ?>';
				apiKeyVisible = true;
			}
		}

		function draftflyCopyApiKey() {
			const apiKeyElement = document.getElementById('draftfly-api-key');
			const actualKey = apiKeyElement.getAttribute('data-key');
			navigator.clipboard.writeText(actualKey).then(function() {
				alert('<?php esc_html_e( 'API key copied to clipboard!', 'draftfly' ); ?>');
			}, function() {
				alert('<?php esc_html_e( 'Failed to copy API key', 'draftfly' ); ?>');
			});
		}
		</script>
		<?php
	}
}
