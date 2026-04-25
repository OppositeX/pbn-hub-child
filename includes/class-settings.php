<?php
/**
 * Settings page for the child plugin.
 * User pastes the Hub URL + the per-site token issued by the Hub. On save,
 * we POST to the Hub's /handshake endpoint so it flips this site to "online".
 */
defined( 'ABSPATH' ) || exit;

class PBN_Hub_Child_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_pbn_hub_child_save', [ $this, 'save' ] );
    }

    public function menu(): void {
        add_options_page(
            'PBN Hub Child',
            'PBN Hub Child',
            'manage_options',
            'pbn-hub-child',
            [ $this, 'render' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'pbn_hub_child', 'pbn_hub_child_token' );
        register_setting( 'pbn_hub_child', 'pbn_hub_child_hub_url' );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $token   = get_option( 'pbn_hub_child_token', '' );
        $hub_url = get_option( 'pbn_hub_child_hub_url', 'https://pbn.d3v.co.il' );
        $last_handshake = get_option( 'pbn_hub_child_last_handshake', '' );
        $last_error     = get_option( 'pbn_hub_child_last_error', '' );
        ?>
        <div class="wrap">
            <h1>PBN Hub Child</h1>
            <p>This site is a child of the central PBN Hub. Paste the Hub URL and the per-site token issued by the Hub when you added this domain.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'pbn_hub_child_save' ); ?>
                <input type="hidden" name="action" value="pbn_hub_child_save">

                <table class="form-table">
                    <tr>
                        <th><label for="hub_url">Hub URL</label></th>
                        <td>
                            <input type="url" id="hub_url" name="hub_url" value="<?php echo esc_attr( $hub_url ); ?>" class="regular-text" placeholder="https://pbn.d3v.co.il">
                            <p class="description">The Hub WordPress site (no trailing slash).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="token">Site Token</label></th>
                        <td>
                            <input type="text" id="token" name="token" value="<?php echo esc_attr( $token ); ?>" class="regular-text code" placeholder="Paste from Hub → Domains → Add Site">
                            <p class="description">Per-site bearer token. The Hub uses this for every request — keep it secret.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Save & Handshake</button>
                </p>
            </form>

            <hr>
            <h2>Status</h2>
            <table class="form-table">
                <tr>
                    <th>REST endpoint</th>
                    <td><code><?php echo esc_html( rest_url( PBN_HUB_CHILD_REST_NS . '/whoami' ) ); ?></code></td>
                </tr>
                <tr>
                    <th>Plugin version</th>
                    <td><code><?php echo esc_html( PBN_HUB_CHILD_VERSION ); ?></code></td>
                </tr>
                <tr>
                    <th>Last handshake</th>
                    <td><?php echo $last_handshake ? esc_html( $last_handshake ) : '—'; ?></td>
                </tr>
                <?php if ( $last_error ) : ?>
                <tr>
                    <th>Last error</th>
                    <td><span style="color:#b91c1c"><?php echo esc_html( $last_error ); ?></span></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }

    public function save(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'pbn_hub_child_save' );

        $hub_url = esc_url_raw( wp_unslash( $_POST['hub_url'] ?? '' ) );
        $token   = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

        $hub_url = rtrim( $hub_url, '/' );

        update_option( 'pbn_hub_child_hub_url', $hub_url );
        update_option( 'pbn_hub_child_token',   $token );

        $msg = $this->try_handshake( $hub_url, $token );

        if ( strpos( $msg, 'OK' ) === 0 ) {
            update_option( 'pbn_hub_child_last_handshake', current_time( 'mysql' ) );
            update_option( 'pbn_hub_child_last_error', '' );
            wp_safe_redirect( add_query_arg( 'pbn_msg', urlencode( $msg ), admin_url( 'options-general.php?page=pbn-hub-child' ) ) );
        } else {
            update_option( 'pbn_hub_child_last_error', $msg );
            wp_safe_redirect( add_query_arg( 'pbn_msg', urlencode( $msg ), admin_url( 'options-general.php?page=pbn-hub-child' ) ) );
        }
        exit;
    }

    private function try_handshake( string $hub_url, string $token ): string {
        if ( ! $hub_url || ! $token ) return 'Hub URL and token both required.';
        global $wp_version;
        $url = trailingslashit( $hub_url ) . 'wp-json/pbn-hub/v1/handshake';
        $r = wp_remote_post( $url, [
            'timeout'   => 15,
            'sslverify' => apply_filters( 'pbn_hub_child_sslverify', true ),
            'headers'   => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body'      => wp_json_encode( [
                'token'                 => $token,
                'domain'                => preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( home_url() ) ),
                'wp_version'            => $wp_version,
                'php_version'           => PHP_VERSION,
                'pbn_hub_child_version' => PBN_HUB_CHILD_VERSION,
            ] ),
        ] );
        if ( is_wp_error( $r ) ) return 'Handshake error: ' . $r->get_error_message();
        $code = wp_remote_retrieve_response_code( $r );
        $body = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( $code !== 200 || empty( $body['ok'] ) ) {
            return 'Handshake failed: ' . ( $body['message'] ?? "HTTP {$code}" );
        }
        return 'OK · Handshake confirmed by Hub v' . ( $body['hub_version'] ?? '?' );
    }
}
