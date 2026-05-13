<?php
/**
 * Auto-update via plugin-update-checker, reading from the PUBLIC release-proxy repo.
 *
 * Source code lives privately in the canonical source repo. The private repo's GitHub
 * Action mirrors each tag to the public release-proxy via the RELEASES_PAT secret. PUC
 * reads anonymously from the public repo — no tokens in source, no secret-scan revoke
 * cycle, no manual setup on each install.
 *
 * v1.0.7: init() runs from the constructor directly. Earlier we scheduled it on
 * plugins_loaded priority 5, but the class itself was instantiated from a
 * plugins_loaded priority-10 callback — the priority-5 hook had already fired
 * by the time we registered it, so PUC was never initialized and the
 * "Check for updates" link never appeared on the plugin row.
 */
defined( 'ABSPATH' ) || exit;

class PBN_Hub_Child_Updater {
    public function __construct() {
        $this->init();
    }

    public function init() {
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
