<?php
/**
 * GitHub releases auto-update for PBN Hub Child.
 * Mirror of the Hub's existing pattern. Optional GitHub token from option pbn_hub_child_github_token.
 */
defined( 'ABSPATH' ) || exit;

class PBN_Hub_Child_Updater {

    private const REPO = 'OppositeX/pbn-hub-child';

    public function __construct() {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_updates' ] );
        add_filter( 'plugins_api',                            [ $this, 'plugin_info' ], 10, 3 );
        add_action( 'upgrader_process_complete',              fn() => delete_transient( 'pbn_hub_child_release' ), 10, 0 );
    }

    private function github_request( string $url ) {
        $args = [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'PBN-Hub-Child/' . PBN_HUB_CHILD_VERSION,
                'Accept'     => 'application/vnd.github+json',
            ],
        ];
        $token = trim( (string) get_option( 'pbn_hub_child_github_token', '' ) );
        if ( $token !== '' ) $args['headers']['Authorization'] = 'Bearer ' . $token;
        return wp_remote_get( $url, $args );
    }

    private function latest_release(): ?array {
        $cached = get_transient( 'pbn_hub_child_release' );
        if ( $cached !== false ) return $cached ?: null;

        $r = $this->github_request( 'https://api.github.com/repos/' . self::REPO . '/releases/latest' );
        if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) {
            set_transient( 'pbn_hub_child_release', false, HOUR_IN_SECONDS );
            return null;
        }
        $rel = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( empty( $rel['tag_name'] ) ) {
            set_transient( 'pbn_hub_child_release', false, HOUR_IN_SECONDS );
            return null;
        }
        $version = ltrim( $rel['tag_name'], 'v' );
        $zip = $rel['zipball_url'] ?? '';
        foreach ( $rel['assets'] ?? [] as $asset ) {
            if ( substr( $asset['name'], -4 ) === '.zip' ) { $zip = $asset['browser_download_url']; break; }
        }
        $data = [
            'version' => $version,
            'zipball' => $zip,
            'url'     => $rel['html_url'] ?? 'https://github.com/' . self::REPO,
            'notes'   => $rel['body'] ?? '',
        ];
        set_transient( 'pbn_hub_child_release', $data, HOUR_IN_SECONDS );
        return $data;
    }

    public function check_updates( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;
        $info = $this->latest_release();
        $slug = plugin_basename( PBN_HUB_CHILD_FILE );
        if ( $info && version_compare( PBN_HUB_CHILD_VERSION, $info['version'], '<' ) ) {
            $transient->response[ $slug ] = (object) [
                'id'          => 'w.org/plugins/pbn-hub-child',
                'slug'        => 'pbn-hub-child',
                'plugin'      => $slug,
                'new_version' => $info['version'],
                'url'         => $info['url'],
                'package'     => $info['zipball'],
                'icons'       => [],
                'banners'     => [],
            ];
        }
        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== 'pbn-hub-child' ) return $result;
        $info = $this->latest_release();
        if ( ! $info ) return $result;
        return (object) [
            'name'          => 'PBN Hub Child',
            'slug'          => 'pbn-hub-child',
            'version'       => $info['version'],
            'author'        => '<a href="https://github.com/OppositeX">OppositeX</a>',
            'homepage'      => 'https://github.com/' . self::REPO,
            'download_link' => $info['zipball'],
            'last_updated'  => date( 'Y-m-d' ),
            'sections'      => [
                'description' => 'Receives content + media from PBN Hub. Per-site bearer token authentication.',
                'changelog'   => nl2br( esc_html( $info['notes'] ) ),
            ],
        ];
    }
}
