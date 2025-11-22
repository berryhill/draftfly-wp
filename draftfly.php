<?php
/**
 * Plugin Name: DraftFly
 * Plugin URI: https://github.com/yourusername/draftfly-wp
 * Description: A simple WordPress plugin for DraftFly functionality
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: draftfly
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'DRAFTFLY_VERSION', '1.0.0' );
define( 'DRAFTFLY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRAFTFLY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DRAFTFLY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main DraftFly class
 */
class DraftFly {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Initialize plugin
        add_action( 'plugins_loaded', array( $this, 'init' ) );

        // Admin hooks
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            add_action( 'admin_init', array( $this, 'admin_init' ) );
        }

        // Frontend hooks
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Run on plugin activation
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Run on plugin deactivation
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain( 'draftfly', false, dirname( DRAFTFLY_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Admin menu
     */
    public function admin_menu() {
        add_menu_page(
            __( 'DraftFly', 'draftfly' ),           // Page title
            __( 'DraftFly', 'draftfly' ),           // Menu title
            'manage_options',                        // Capability
            'draftfly',                              // Menu slug
            array( $this, 'admin_page' ),           // Callback
            'dashicons-edit',                        // Icon
            30                                       // Position
        );
    }

    /**
     * Admin init
     */
    public function admin_init() {
        // Register settings here
        register_setting( 'draftfly_settings', 'draftfly_options' );
    }

    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php esc_html_e( 'Welcome to DraftFly! Configure your settings below.', 'draftfly' ); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'draftfly_settings' );
                do_settings_sections( 'draftfly' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Enqueue CSS
        // wp_enqueue_style( 'draftfly-style', DRAFTFLY_PLUGIN_URL . 'assets/css/style.css', array(), DRAFTFLY_VERSION );

        // Enqueue JS
        // wp_enqueue_script( 'draftfly-script', DRAFTFLY_PLUGIN_URL . 'assets/js/script.js', array( 'jquery' ), DRAFTFLY_VERSION, true );
    }
}

// Initialize the plugin
DraftFly::get_instance();
