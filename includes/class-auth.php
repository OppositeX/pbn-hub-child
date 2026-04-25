<?php
/**
 * Bearer-token auth. Validates Authorization header against the per-site token
 * stored in the option `pbn_hub_child_token`.
 */
defined( 'ABSPATH' ) || exit;

class PBN_Hub_Child_Auth {

    /** WP REST permission_callback — strict bearer match. */
    public static function require_token() {
        $stored = (string) get_option( 'pbn_hub_child_token', '' );
        if ( $stored === '' ) {
            return new WP_Error( 'no_token_configured', 'PBN Hub Child has no token configured. Set one in Settings → PBN Hub Child.', [ 'status' => 401 ] );
        }
        $supplied = self::extract_bearer();
        if ( $supplied === '' ) {
            return new WP_Error( 'missing_token', 'Authorization Bearer token required.', [ 'status' => 401 ] );
        }
        if ( ! hash_equals( $stored, $supplied ) ) {
            return new WP_Error( 'bad_token', 'Invalid token.', [ 'status' => 403 ] );
        }
        return true;
    }

    /** Permission callback for /health — public, used by Hub's old PBN Core compat path. */
    public static function public_ok() { return true; }

    private static function extract_bearer(): string {
        $headers = self::all_headers();
        foreach ( [ 'Authorization', 'authorization', 'X-Authorization' ] as $name ) {
            if ( ! empty( $headers[ $name ] ) ) {
                if ( preg_match( '/^Bearer\s+(.+)$/i', trim( $headers[ $name ] ), $m ) ) return trim( $m[1] );
            }
        }
        if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            if ( preg_match( '/^Bearer\s+(.+)$/i', trim( $_SERVER['HTTP_AUTHORIZATION'] ), $m ) ) return trim( $m[1] );
        }
        if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
            if ( preg_match( '/^Bearer\s+(.+)$/i', trim( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ), $m ) ) return trim( $m[1] );
        }
        return '';
    }

    private static function all_headers(): array {
        if ( function_exists( 'getallheaders' ) ) {
            $h = getallheaders();
            return is_array( $h ) ? $h : [];
        }
        $headers = [];
        foreach ( $_SERVER as $k => $v ) {
            if ( strpos( $k, 'HTTP_' ) === 0 ) {
                $name = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $k, 5 ) ) ) ) );
                $headers[ $name ] = $v;
            }
        }
        return $headers;
    }
}
