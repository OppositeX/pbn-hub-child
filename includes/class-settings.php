<?php
/**
 * Settings page for the child plugin.
 * User pastes the Hub URL + the per-site token issued by the Hub. On save,
 * we POST to the Hub's /handshake endpoint so it flips this site to "online".
 *
 * v1.0.6: friendly cURL 60 (cert-expired) error + explicit, dangerous opt-in
 * to skip SSL verification on Hub requests as a temporary workaround during
 * a Hub-side cert renewal incident.
 */
defined( 'ABSPATH' ) || exit;

class PBN_Hub_Child_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_pbn_hub_child_save', [ $this, 'save' ] );
        add_action( 'admin_notices', [ $this, 'maybe_admin_notice' ] );
    }

    public function menu() {
        add_options_page(
            'PBN Hub Child',
            'PBN Hub Child',
            'manage_options',
            'pbn-hub-child',
            [ $this, 'render' ]
        );
    }

    public function register_settings() {
        register_setting( 'pbn_hub_child', 'pbn_hub_child_token' );
        register_setting( 'pbn_hub_child', 'pbn_hub_child_hub_url' );
        register_setting( 'pbn_hub_child', 'pbn_hub_child_skip_ssl' );
    }

    /** Persistent admin notice when handshake last failed for >24h. */
    public function maybe_admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $err = (string) get_option( 'pbn_hub_child_last_error', '' );
        if ( $err === '' ) return;
        // Only show on screens other than our own settings page (where the error is already prominent).
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && $screen->id === 'settings_page_pbn-hub-child' ) return;

        $is_cert = self::is_cert_expired_error( $err );
        $url = admin_url( 'options-general.php?page=pbn-hub-child' );
        $msg = $is_cert
            ? 'PBN Hub Child cannot reach the Hub: the Hub\'s SSL certificate is expired or invalid. Contact the Hub administrator to renew it.'
            : 'PBN Hub Child is having trouble talking to the Hub. Last error: ' . esc_html( $err );
        echo '<div class="notice notice-warning is-dismissible"><p><strong>PBN Hub Child:</strong> '
            . wp_kses_post( $msg )
            . ' <a href="' . esc_url( $url ) . '">Open settings</a>.</p></div>';
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $token          = get_option( 'pbn_hub_child_token', '' );
        $hub_url        = get_option( 'pbn_hub_child_hub_url', 'http://hub.d3v.co.il' );
        $last_handshake = get_option( 'pbn_hub_child_last_handshake', '' );
        $last_error     = (string) get_option( 'pbn_hub_child_last_error', '' );
        $skip_ssl       = (bool) get_option( 'pbn_hub_child_skip_ssl', false );

        $is_cert_err = $last_error && self::is_cert_expired_error( $last_error );
        ?>
        <div class="wrap">
            <h1>PBN Hub Child</h1>
            <p>This site is a child of the central PBN Hub. Paste the Hub URL and the per-site token issued by the Hub when you added this domain.</p>

            <?php if ( $is_cert_err ) : ?>
            <div style="margin:16px 0;padding:14px 16px;border-left:4px solid #dc2626;background:#fef2f2;color:#7f1d1d;border-radius:4px">
                <p style="margin:0 0 8px"><strong>Hub SSL certificate expired or invalid.</strong>
                The Hub at <code><?php echo esc_html( $hub_url ); ?></code> is currently presenting an invalid SSL certificate, so this site can&rsquo;t verify the connection. Please contact the Hub administrator to renew the certificate.</p>
                <p style="margin:0 0 12px;font-size:12.5px"><em>Technical:</em> <code><?php echo esc_html( $last_error ); ?></code></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;padding:10px 12px;background:#fff;border:1px solid #fecaca;border-radius:4px">
                    <?php wp_nonce_field( 'pbn_hub_child_save' ); ?>
                    <input type="hidden" name="action" value="pbn_hub_child_save">
                    <input type="hidden" name="hub_url" value="<?php echo esc_attr( $hub_url ); ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
                    <input type="hidden" name="skip_ssl" value="1">
                    <p style="margin:0 0 8px;font-size:13px;color:#7f1d1d">
                        <strong>Workaround:</strong> while the Hub administrator renews the cert, you can temporarily skip SSL verification so this site can still sync. <strong>This is INSECURE</strong> &mdash; disable as soon as the cert is fixed.
                    </p>
                    <button type="submit" class="button button-primary" style="background:#dc2626;border-color:#dc2626">Skip SSL verification &amp; Retry handshake</button>
                </form>
            </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'pbn_hub_child_save' ); ?>
                <input type="hidden" name="action" value="pbn_hub_child_save">

                <table class="form-table">
                    <tr>
                        <th><label for="hub_url">Hub URL</label></th>
                        <td>
                            <input type="url" id="hub_url" name="hub_url" value="<?php echo esc_attr( $hub_url ); ?>" class="regular-text" placeholder="http://hub.d3v.co.il">
                            <p class="description">The Hub WordPress site (no trailing slash).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="token">Site Token</label></th>
                        <td>
                            <input type="text" id="token" name="token" value="<?php echo esc_attr( $token ); ?>" class="regular-text code" placeholder="Paste from Hub → Sites → expand row">
                            <p class="description">Per-site bearer token. The Hub uses this for every request &mdash; keep it secret.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin-top:24px">Advanced</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="skip_ssl">Skip SSL verification for Hub requests</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="skip_ssl" name="skip_ssl" value="1" <?php checked( $skip_ssl ); ?>>
                                <span>Disable SSL certificate verification when calling the Hub</span>
                            </label>
                            <div style="margin-top:8px;padding:10px 12px;border-left:4px solid #dc2626;background:#fef2f2;color:#7f1d1d;font-size:12.5px;line-height:1.55;max-width:640px">
                                <strong>WARNING — INSECURE.</strong> Only enable this temporarily, while the Hub administrator is renewing an expired/invalid SSL certificate. With this on, your site will trust ANY certificate the Hub presents, including a forged one. Disable as soon as the Hub cert is fixed.
                            </div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save &amp; Handshake</button>
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

    public function save() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        check_admin_referer( 'pbn_hub_child_save' );

        $hub_url = esc_url_raw( wp_unslash( $_POST['hub_url'] ?? '' ) );
        $token   = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        $skip_ssl = ! empty( $_POST['skip_ssl'] );

        $hub_url = rtrim( $hub_url, '/' );

        update_option( 'pbn_hub_child_hub_url', $hub_url );
        update_option( 'pbn_hub_child_token',   $token );
        update_option( 'pbn_hub_child_skip_ssl', $skip_ssl ? 1 : 0 );

        $msg = $this->try_handshake( $hub_url, $token, $skip_ssl );

        if ( strpos( $msg, 'OK' ) === 0 ) {
            update_option( 'pbn_hub_child_last_handshake', current_time( 'mysql' ) );
            update_option( 'pbn_hub_child_last_error', '' );
        } else {
            update_option( 'pbn_hub_child_last_error', $msg );
        }
        wp_safe_redirect( add_query_arg( 'pbn_msg', urlencode( $msg ), admin_url( 'options-general.php?page=pbn-hub-child' ) ) );
        exit;
    }

    private function try_handshake( string $hub_url, string $token, bool $skip_ssl ): string {
        if ( ! $hub_url || ! $token ) return 'Hub URL and token both required.';

        // v1.0.8: HTTPS <-> HTTP fallback. SSL on hub.d3v.co.il is unreliable
        // (see HUB-203). If the user-saved Hub URL fails on its primary scheme
        // with a network/TLS/transport-class error, retry once on the other
        // scheme so a stale cert or HTTPS misconfig doesn't strand the child.
        $primary = $this->do_handshake_request( $hub_url, $token, $skip_ssl );
        if ( $primary['ok'] ) return $primary['msg'];

        $err = $primary['err'];
        $is_transport = is_string( $err ) && $err !== ''
            && ( self::is_cert_expired_error( $err )
                || stripos( $err, 'curl error' ) !== false
                || stripos( $err, 'could not resolve' ) !== false
                || stripos( $err, 'connection refused' ) !== false
                || stripos( $err, 'connection timed out' ) !== false
                || stripos( $err, 'operation timed out' ) !== false
                || stripos( $err, 'ssl' ) !== false
                || stripos( $err, 'tls' ) !== false );

        $alt_url = $this->swap_scheme( $hub_url );
        if ( $is_transport && $alt_url !== null && $alt_url !== $hub_url ) {
            $retry = $this->do_handshake_request( $alt_url, $token, $skip_ssl );
            if ( $retry['ok'] ) {
                // Persist the working scheme so future handshakes / Hub calls go straight to it.
                update_option( 'pbn_hub_child_hub_url', $alt_url );
                return $retry['msg'] . ' (auto-switched scheme: ' . esc_html( parse_url( $alt_url, PHP_URL_SCHEME ) ) . ')';
            }
            // Surface the better-diagnosed error of the two attempts.
            return $retry['msg'];
        }

        return $primary['msg'];
    }

    /** Single handshake attempt. Returns [ok, msg, err]. */
    private function do_handshake_request( string $hub_url, string $token, bool $skip_ssl ): array {
        global $wp_version;
        $url = trailingslashit( $hub_url ) . 'wp-json/pbn-hub/v1/handshake';
        $sslverify = $skip_ssl ? false : (bool) apply_filters( 'pbn_hub_child_sslverify', true );
        $r = wp_remote_post( $url, [
            'timeout'   => 15,
            'sslverify' => $sslverify,
            'headers'   => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body'      => wp_json_encode( [
                'token'                 => $token,
                'domain'                => preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( home_url() ) ),
                'wp_version'            => $wp_version,
                'php_version'           => PHP_VERSION,
                'pbn_hub_child_version' => PBN_HUB_CHILD_VERSION,
            ] ),
        ] );
        if ( is_wp_error( $r ) ) {
            $err = $r->get_error_message();
            if ( self::is_cert_expired_error( $err ) ) {
                $hint = $skip_ssl ? '' : ' You can temporarily enable "Skip SSL verification" below as a workaround until the Hub cert is renewed.';
                return [ 'ok' => false, 'err' => $err, 'msg' => 'Handshake error: Hub SSL certificate is expired or invalid.' . $hint . ' (Technical: ' . $err . ')' ];
            }
            return [ 'ok' => false, 'err' => $err, 'msg' => 'Handshake error: ' . $err ];
        }
        $code = wp_remote_retrieve_response_code( $r );
        $body = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( $code !== 200 || empty( $body['ok'] ) ) {
            return [ 'ok' => false, 'err' => "HTTP {$code}", 'msg' => 'Handshake failed: ' . ( $body['message'] ?? "HTTP {$code}" ) ];
        }
        return [ 'ok' => true, 'err' => '', 'msg' => 'OK · Handshake confirmed by Hub v' . ( $body['hub_version'] ?? '?' ) ];
    }

    /** Swap https<->http on a URL. Returns null if no scheme. */
    private function swap_scheme( string $url ): ?string {
        if ( stripos( $url, 'https://' ) === 0 ) return 'http://' . substr( $url, 8 );
        if ( stripos( $url, 'http://' )  === 0 ) return 'https://' . substr( $url, 7 );
        return null;
    }

    /** True if the WP_Error message looks like a TLS cert problem. */
    public static function is_cert_expired_error( string $msg ): bool {
        $msg = strtolower( $msg );
        return ( strpos( $msg, 'curl error 60' ) !== false )
            || ( strpos( $msg, 'certificate has expired' ) !== false )
            || ( strpos( $msg, 'ssl certificate problem' ) !== false )
            || ( strpos( $msg, 'unable to get local issuer' ) !== false )
            || ( strpos( $msg, 'self signed certificate' ) !== false )
            || ( strpos( $msg, 'self-signed certificate' ) !== false );
    }
}
