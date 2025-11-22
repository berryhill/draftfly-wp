<?php
/**
 * Uninstall DraftFly
 *
 * @package DraftFly
 */

// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'draftfly_options' );

// For multisite
delete_site_option( 'draftfly_options' );

// Clean up any custom database tables (if you created any)
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}draftfly_table" );

// Clear any cached data
wp_cache_flush();
