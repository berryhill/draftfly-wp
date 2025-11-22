<?php
/**
 * DraftFly REST API Endpoints
 *
 * @package DraftFly
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Parsedown for Markdown conversion
require_once DRAFTFLY_PLUGIN_DIR . 'includes/vendor/Parsedown.php';

/**
 * DraftFly API Class
 */
class DraftFly_API {

	/**
	 * API namespace
	 */
	const NAMESPACE = 'draftfly/v1';

	/**
	 * Parsedown instance
	 */
	private $parsedown;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		$this->parsedown = new Parsedown();
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Health check endpoint
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => array( $this, 'verify_api_key' ),
			)
		);

		// Validate credentials endpoint
		register_rest_route(
			self::NAMESPACE,
			'/auth/validate',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'validate_credentials' ),
				'permission_callback' => array( $this, 'verify_api_key' ),
			)
		);

		// Create post endpoint
		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_post' ),
				'permission_callback' => array( $this, 'verify_api_key' ),
				'args'                => $this->get_post_args(),
			)
		);

		// Update post endpoint
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>[\d]+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_post' ),
				'permission_callback' => array( $this, 'verify_api_key' ),
				'args'                => $this->get_post_args( false ),
			)
		);
	}

	/**
	 * Verify API key from request header
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_api_key( $request ) {
		$api_key = $request->get_header( 'x-api-key' );

		if ( empty( $api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'API key is required', 'draftfly' ),
				array( 'status' => 401 )
			);
		}

		$stored_key = get_option( 'draftfly_api_key' );

		if ( empty( $stored_key ) ) {
			return new WP_Error(
				'api_not_configured',
				__( 'API key not configured', 'draftfly' ),
				array( 'status' => 500 )
			);
		}

		// Use hash_equals to prevent timing attacks
		if ( ! hash_equals( $stored_key, $api_key ) ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid API key', 'draftfly' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Health check endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function health_check( $request ) {
		return new WP_REST_Response(
			array( 'status' => 'healthy' ),
			200
		);
	}

	/**
	 * Validate credentials endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function validate_credentials( $request ) {
		return new WP_REST_Response(
			array( 'valid' => true ),
			200
		);
	}

	/**
	 * Create post endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_post( $request ) {
		$title          = $request->get_param( 'title' );
		$content        = $request->get_param( 'content' );
		$markdown       = $request->get_param( 'markdown' );
		$excerpt        = $request->get_param( 'excerpt' );
		$tags           = $request->get_param( 'tags' );
		$status         = $request->get_param( 'status' );
		$featured_image = $request->get_param( 'featured_image' );

		// Convert markdown to HTML if provided
		$from_markdown = false;
		if ( ! empty( $markdown ) ) {
			$content       = $this->parsedown->text( $markdown );
			$from_markdown = true;
		}

		// Map status to WordPress post status
		$post_status = $this->map_post_status( $status );

		// Sanitize content - use wp_kses_post only for user-provided HTML, not converted Markdown
		$sanitized_content = $from_markdown ? wp_filter_post_kses( $content ) : wp_kses_post( $content );

		// Create post
		$post_data = array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => $sanitized_content,
			'post_excerpt' => sanitize_text_field( $excerpt ),
			'post_status'  => $post_status,
			'post_type'    => 'post',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'post_creation_failed',
				$post_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		// Handle tags
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			wp_set_post_tags( $post_id, $tags, false );
		}

		// Handle featured image
		if ( ! empty( $featured_image ) ) {
			$this->set_featured_image_from_url( $post_id, $featured_image );
		}

		$post = get_post( $post_id );

		return new WP_REST_Response(
			array(
				'id'         => (string) $post_id,
				'title'      => $post->post_title,
				'created_at' => gmdate( 'c', strtotime( $post->post_date_gmt ) ),
			),
			201
		);
	}

	/**
	 * Update post endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_post( $request ) {
		$post_id = (int) $request->get_param( 'id' );

		// Check if post exists
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				__( 'Post not found', 'draftfly' ),
				array( 'status' => 404 )
			);
		}

		$post_data = array( 'ID' => $post_id );

		// Update title if provided
		$title = $request->get_param( 'title' );
		if ( ! is_null( $title ) ) {
			$post_data['post_title'] = sanitize_text_field( $title );
		}

		// Update content if provided (check markdown first)
		$markdown      = $request->get_param( 'markdown' );
		$content       = $request->get_param( 'content' );
		$from_markdown = false;

		if ( ! is_null( $markdown ) ) {
			$content       = $this->parsedown->text( $markdown );
			$from_markdown = true;
		}

		if ( ! is_null( $content ) ) {
			$sanitized_content           = $from_markdown ? wp_filter_post_kses( $content ) : wp_kses_post( $content );
			$post_data['post_content'] = $sanitized_content;
		}

		// Update excerpt if provided
		$excerpt = $request->get_param( 'excerpt' );
		if ( ! is_null( $excerpt ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $excerpt );
		}

		// Update status if provided
		$status = $request->get_param( 'status' );
		if ( ! is_null( $status ) ) {
			$post_data['post_status'] = $this->map_post_status( $status );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'post_update_failed',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		// Update tags if provided
		$tags = $request->get_param( 'tags' );
		if ( ! is_null( $tags ) && is_array( $tags ) ) {
			wp_set_post_tags( $post_id, $tags, false );
		}

		// Update featured image if provided
		$featured_image = $request->get_param( 'featured_image' );
		if ( ! is_null( $featured_image ) ) {
			$this->set_featured_image_from_url( $post_id, $featured_image );
		}

		$updated_post = get_post( $post_id );

		return new WP_REST_Response(
			array(
				'id'         => (string) $post_id,
				'title'      => $updated_post->post_title,
				'updated_at' => gmdate( 'c', strtotime( $updated_post->post_modified_gmt ) ),
			),
			200
		);
	}

	/**
	 * Get post arguments schema
	 *
	 * @param bool $required Whether fields are required.
	 * @return array
	 */
	private function get_post_args( $required = true ) {
		return array(
			'title'          => array(
				'required'          => $required,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'        => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			'markdown'       => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => 'Markdown content (will be converted to HTML). Takes precedence over content field.',
			),
			'excerpt'        => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'tags'           => array(
				'required' => false,
				'type'     => 'array',
				'items'    => array(
					'type' => 'string',
				),
			),
			'status'         => array(
				'required' => false,
				'type'     => 'string',
				'default'  => 'published',
				'enum'     => array( 'draft', 'published' ),
			),
			'featured_image' => array(
				'required' => false,
				'type'     => 'string',
				'format'   => 'uri',
			),
		);
	}

	/**
	 * Map DraftFly status to WordPress post status
	 *
	 * @param string $status DraftFly status.
	 * @return string WordPress post status.
	 */
	private function map_post_status( $status ) {
		$status_map = array(
			'published' => 'publish',
			'draft'     => 'draft',
		);

		return isset( $status_map[ $status ] ) ? $status_map[ $status ] : 'draft';
	}

	/**
	 * Set featured image from URL
	 *
	 * @param int    $post_id Post ID.
	 * @param string $image_url Image URL.
	 * @return int|false Attachment ID or false on failure.
	 */
	private function set_featured_image_from_url( $post_id, $image_url ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download image to temp file
		$temp_file = download_url( $image_url );

		if ( is_wp_error( $temp_file ) ) {
			return false;
		}

		// Get filename from URL
		$filename = basename( $image_url );

		// Prepare file array
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $temp_file,
		);

		// Upload and attach to post
		$attachment_id = media_handle_sideload( $file_array, $post_id );

		// Clean up temp file
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
			return $attachment_id;
		}

		return false;
	}
}
