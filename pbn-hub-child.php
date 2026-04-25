<?php
/**
 * Plugin Name: PBN Hub Child
 * Plugin URI:  https://github.com/OppositeX/pbn-hub-child
 * Description: Lightweight REST endpoint for sites managed by PBN Hub. Receives content + media from the Hub, exposes whoami / categories / analytics. Authenticated by per-site bearer token.
 * Version:     1.0.2
 * Author:      OppositeX
 * License:     GPL-2.0+
 * Text Domain: pbn-hub-child
 */

defined( 'ABSPATH' ) || exit;

define( 'PBN_HUB_CHILD_VERSION', '1.0.2' );
define( 'PBN_HUB_CHILD_FILE',    __FILE__ );
define( 'PBN_HUB_CHILD_PATH',    plugin_dir_path( __FILE__ ) );
define( 'PBN_HUB_CHILD_URL',     plugin_dir_url( __FILE__ ) );
define( 'PBN_HUB_CHILD_REST_NS', 'pbn-hub-child/v1' );

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
    if ( ! get_option( 'pbn_hub_child_hub_url' ) ) update_option( 'pbn_hub_child_hub_url', 'https://pbn.d3v.co.il' );
} );
