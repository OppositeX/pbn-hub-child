<?php
/**
 * Auto-update via plugin-update-checker, reading from the PUBLIC release-proxy repo.
 *
 * Source code lives privately in OppositeX/pbn-hub-child. The private repo's GitHub Action
 * mirrors each tag to OppositeX/pbn-hub-child-releases (public) via the RELEASES_PAT secret.
 * PUC reads anonymously from the public repo — no tokens in source, no secret-scan revoke
 * cycle, no manual setup on each install.
 */
defined( 'ABSPATH' ) || exit;

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
            'https://github.com/OppositeX/pbn-hub-child-releases/',
            PBN_HUB_CHILD_FILE,
            'pbn-hub-child'
        );
        $updater->setBranch( 'main' );

        $vcs_api = $updater->getVcsApi();
        if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
            $vcs_api->enableReleaseAssets( '/^pbn-hub-child\.zip$/' );
        }
    }
}
