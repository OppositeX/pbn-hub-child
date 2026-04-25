<?php
/**
 * Auto-update via plugin-update-checker. Replaces the v1.0.x home-rolled
 * /releases/latest updater. Uses the same OPPOSITEX_DEPLOY_TOKEN pattern
 * as the Hub plugin — embedded fine-grained read-only PAT for the
 * private OppositeX/pbn-hub-child repo.
 */
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'OPPOSITEX_DEPLOY_TOKEN' ) ) {
    define( 'OPPOSITEX_DEPLOY_TOKEN', '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' );
}

class PBN_Hub_Child_Updater {
    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ], 5 );
    }

    public function init(): void {
        $puc_path = PBN_HUB_CHILD_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
        if ( ! file_exists( $puc_path ) ) return;
        require_once $puc_path;
        if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) return;

        $updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/OppositeX/pbn-hub-child/',
            PBN_HUB_CHILD_FILE,
            'pbn-hub-child'
        );
        $updater->setBranch( 'main' );

        $vcs_api = $updater->getVcsApi();
        if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
            $vcs_api->enableReleaseAssets( '/^pbn-hub-child\.zip$/' );
        }

        $token = OPPOSITEX_DEPLOY_TOKEN;
        if ( $token === '' ) {
            $token = trim( (string) get_option( 'pbn_hub_child_github_token', '' ) );
        }
        if ( $token !== '' ) {
            $updater->setAuthentication( $token );
        }
    }
}
