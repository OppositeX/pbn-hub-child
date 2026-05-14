<?php
/**
 * Plugin Name: PBN Hub Child
 * Plugin URI:  https://github.com/OppositeX/pbn-hub-child
 * Description: Lightweight REST endpoint for sites managed by PBN Hub. Receives content + media from the Hub, exposes whoami / categories / analytics. Authenticated by per-site bearer token.
 * Version:     1.0.11
 * Author:      OppositeX
 * License:     GPL-2.0+
 * Text Domain: pbn-hub-child
 * Requires PHP: 7.0
 */

defined( 'ABSPATH' ) || exit;

// v1.0.11: PHP 7.0 is now supported. The single nullable return type that
// previously required 7.1 has been dropped from swap_scheme().
if ( false ) {  // disabled — kept for diff continuity
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>PBN Hub Child</strong> requires PHP 7.1 or higher (you are on PHP ' . esc_html( PHP_VERSION ) . '). Please contact your hosting provider to upgrade.</p></div>';
    });
    return;
}

define( 'PBN_HUB_CHILD_VERSION', '1.0.11' );
define( 'PBN_HUB_CHILD_FILE',    __FILE__ );
define( 'PBN_HUB_CHILD_PATH',    plugin_dir_path( __FILE__ ) );
define( 'PBN_HUB_CHILD_URL',     plugin_dir_url( __FILE__ ) );
define( 'PBN_HUB_CHILD_REST_NS', 'pbn-hub-child/v1' );

// v1.0.9: shared HMAC secret used ONLY to verify one-click enrollment URLs
// minted by the Hub. Never stored in an option — used in-memory only, then
// thrown away. Overridable via filter for tests / staging Hubs.
define( 'PBN_HUB_CHILD_ENROLL_SECRET', 'pbn-master-2024-x9k2' );

require_once PBN_HUB_CHILD_PATH . 'includes/class-auth.php';
require_once PBN_HUB_CHILD_PATH . 'includes/class-rest.php';
require_once PBN_HUB_CHILD_PATH . 'includes/class-settings.php';
require_once PBN_HUB_CHILD_PATH . 'includes/class-updater.php';

add_action( 'plugins_loaded', function() {
    new PBN_Hub_Child_Settings();
    new PBN_Hub_Child_Rest();
    new PBN_Hub_Child_Updater();
} );

register_activation_hook( __FILE__, function() {
    if ( ! get_option( 'pbn_hub_child_token' ) ) update_option( 'pbn_hub_child_token', '' );
    if ( ! get_option( 'pbn_hub_child_hub_url' ) ) update_option( 'pbn_hub_child_hub_url', 'http://hub.d3v.co.il' );
} );

/**
 * v1.0.9: FORCE auto-update for this plugin, bypassing the user's wp-admin opt-in.
 * Every future tag push to pbn-hub-child-releases auto-applies silently across
 * the fleet. This filter is intentionally outside any conditional — it MUST be
 * registered on every load, not just admin / cron / etc.
 *
 * The plugin file under check is matched on the trailing `pbn-hub-child.php`
 * basename so this still works if the plugin folder is renamed at install time.
 */
add_filter( 'auto_update_plugin', function( $update, $item ) {
    $file = is_object( $item ) && isset( $item->plugin ) ? (string) $item->plugin : '';
    if ( $file === '' && is_array( $item ) && isset( $item['plugin'] ) ) {
        $file = (string) $item['plugin'];
    }
    if ( $file === '' ) return $update;
    // Match "pbn-hub-child/pbn-hub-child.php" OR any folder/pbn-hub-child.php
    if ( substr( $file, -strlen( '/pbn-hub-child.php' ) ) === '/pbn-hub-child.php'
         || $file === 'pbn-hub-child.php' ) {
        return true;
    }
    return $update;
}, 10, 2 );
