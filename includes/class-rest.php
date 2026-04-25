<?php
/**
 * REST routes for the PBN Hub Child plugin.
 * Namespace: /wp-json/pbn-hub-child/v1/
 */
defined( 'ABSPATH' ) || exit;

class PBN_Hub_Child_Rest {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    public function register(): void {
        $auth = [ 'PBN_Hub_Child_Auth', 'require_token' ];

        register_rest_route( PBN_HUB_CHILD_REST_NS, '/health', [
            'methods'  => 'GET',
            'callback' => [ $this, 'health' ],
            'permission_callback' => [ 'PBN_Hub_Child_Auth', 'public_ok' ],
        ] );

        register_rest_route( PBN_HUB_CHILD_REST_NS, '/whoami', [
            'methods'  => 'GET',
            'callback' => [ $this, 'whoami' ],
            'permission_callback' => $auth,
        ] );

        register_rest_route( PBN_HUB_CHILD_REST_NS, '/categories', [
            'methods'  => 'GET',
            'callback' => [ $this, 'categories' ],
            'permission_callback' => $auth,
        ] );

        register_rest_route( PBN_HUB_CHILD_REST_NS, '/factory-publish', [
            'methods'  => 'POST',
            'callback' => [ $this, 'factory_publish' ],
            'permission_callback' => $auth,
        ] );

        register_rest_route( PBN_HUB_CHILD_REST_NS, '/posts/(?P<id>\d+)/status', [
            'methods'  => 'POST',
            'callback' => [ $this, 'update_post_status' ],
            'permission_callback' => $auth,
            'args' => [ 'id' => [ 'required' => true, 'type' => 'integer' ] ],
        ] );

        register_rest_route( PBN_HUB_CHILD_REST_NS, '/analytics', [
            'methods'  => 'GET',
            'callback' => [ $this, 'analytics' ],
            'permission_callback' => $auth,
        ] );
    }

    public function health() {
        return [
            'status'                => 'ok',
            'plugin'                => 'pbn-hub-child',
            'pbn_hub_child_version' => PBN_HUB_CHILD_VERSION,
        ];
    }

    public function whoami() {
        global $wp_version;
        return [
            'status'                => 'ok',
            'plugin'                => 'pbn-hub-child',
            'pbn_hub_child_version' => PBN_HUB_CHILD_VERSION,
            'wp_version'            => $wp_version,
            'php_version'           => PHP_VERSION,
            'site_url'              => home_url(),
            'plugin_count'          => count( get_option( 'active_plugins', [] ) ),
        ];
    }

    public function categories() {
        $cats = get_categories( [ 'hide_empty' => false, 'number' => 100 ] );
        $out = [];
        foreach ( $cats as $c ) {
            $out[] = [ 'id' => (int) $c->term_id, 'name' => $c->name, 'slug' => $c->slug, 'count' => (int) $c->count ];
        }
        return [ 'categories' => $out ];
    }

    /** Atomic factory publish: media + featured + post + categories in one round-trip. */
    public function factory_publish( WP_REST_Request $req ) {
        $title    = (string) $req->get_param( 'title' );
        $content  = (string) $req->get_param( 'content' );
        $status   = (string) $req->get_param( 'status' ); // draft|publish|future
        if ( ! in_array( $status, [ 'draft', 'publish', 'future' ], true ) ) $status = 'draft';
        $post_date    = (string) $req->get_param( 'post_date' );
        $category_ids = (array)  $req->get_param( 'category_ids' );
        $images       = (array)  $req->get_param( 'images' );
        $featured     = $req->get_param( 'featured_image' );
        $factory      = (bool)   $req->get_param( 'factory' );

        if ( ! $title || ! $content ) {
            return new WP_Error( 'missing_fields', 'title and content are required.', [ 'status' => 400 ] );
        }

        // 1) Upload mid-article images, replace placeholders in content.
        if ( $images ) {
            foreach ( $images as $img ) {
                if ( empty( $img['placeholder'] ) || empty( $img['base64'] ) ) continue;
                $att_id = $this->upload_base64_image( $img );
                if ( is_wp_error( $att_id ) ) {
                    $content = str_replace( $img['placeholder'], '<!-- image upload failed: ' . esc_html( $att_id->get_error_message() ) . ' -->', $content );
                    continue;
                }
                $url = wp_get_attachment_url( $att_id );
                $alt = ! empty( $img['alt'] ) ? $img['alt'] : '';
                $caption = ! empty( $img['caption'] ) ? $img['caption'] : '';
                $tag = '<figure class="pbn-image" style="margin:1.5em 0">';
                $tag .= '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" />';
                if ( $caption ) $tag .= '<figcaption>' . esc_html( $caption ) . '</figcaption>';
                $tag .= '</figure>';
                $content = str_replace( $img['placeholder'], $tag, $content );
            }
        }

        // 2) Upload featured image if provided.
        $featured_id = 0;
        if ( is_array( $featured ) && ! empty( $featured['base64'] ) ) {
            $r = $this->upload_base64_image( $featured );
            if ( ! is_wp_error( $r ) ) $featured_id = (int) $r;
        }

        // 3) Create the post.
        $post_arr = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'post',
        ];
        if ( $status === 'future' && $post_date ) {
            $post_arr['post_date']     = $post_date;
            $post_arr['post_date_gmt'] = get_gmt_from_date( $post_date );
        }
        if ( $factory ) $post_arr['meta_input'] = [ 'pbn_factory' => '1' ];

        $post_id = wp_insert_post( $post_arr, true );
        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'insert_failed', $post_id->get_error_message(), [ 'status' => 500 ] );
        }

        if ( $category_ids ) {
            $category_ids = array_map( 'intval', $category_ids );
            wp_set_post_categories( $post_id, $category_ids );
        }
        if ( $featured_id ) set_post_thumbnail( $post_id, $featured_id );

        return [
            'id'         => (int) $post_id,
            'edit_url'   => get_edit_post_link( $post_id, 'raw' ),
            'view_url'   => get_permalink( $post_id ),
            'status'     => get_post_status( $post_id ),
            'post_date'  => get_post_field( 'post_date', $post_id ),
        ];
    }

    public function update_post_status( WP_REST_Request $req ) {
        $id = (int) $req['id'];
        $status = (string) $req->get_param( 'status' );
        $post_date = (string) $req->get_param( 'post_date' );

        if ( ! in_array( $status, [ 'draft', 'publish', 'future' ], true ) ) {
            return new WP_Error( 'bad_status', 'Status must be draft|publish|future.', [ 'status' => 400 ] );
        }
        if ( ! get_post( $id ) ) return new WP_Error( 'not_found', 'Post not found.', [ 'status' => 404 ] );

        $arr = [ 'ID' => $id, 'post_status' => $status ];
        if ( $status === 'future' && $post_date ) {
            $arr['post_date']     = $post_date;
            $arr['post_date_gmt'] = get_gmt_from_date( $post_date );
        }
        $r = wp_update_post( $arr, true );
        if ( is_wp_error( $r ) ) return $r;

        return [
            'id'        => $id,
            'status'    => get_post_status( $id ),
            'post_date' => get_post_field( 'post_date', $id ),
            'edit_url'  => get_edit_post_link( $id, 'raw' ),
            'view_url'  => get_permalink( $id ),
        ];
    }

    public function analytics( WP_REST_Request $req ) {
        $range = (string) $req->get_param( 'range' );
        $days = 30;
        if ( preg_match( '/(\d+)d/', $range, $m ) ) $days = max( 1, min( 180, (int) $m[1] ) );
        global $wpdb;

        $start_date = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );

        $counts = wp_count_posts( 'post' );
        $cat_count = wp_count_terms( 'category', [ 'hide_empty' => false ] );

        $recent_published = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' AND DATE(post_date) >= %s",
            $start_date
        ) );
        $recent_drafts = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='draft' AND DATE(post_modified) >= %s",
            $start_date
        ) );
        $factory_posted = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id
             WHERE pm.meta_key='pbn_factory' AND pm.meta_value='1' AND p.post_type='post'"
        );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(post_date) AS d, COUNT(*) AS c
             FROM {$wpdb->posts}
             WHERE post_type='post' AND post_status='publish' AND DATE(post_date) >= %s
             GROUP BY DATE(post_date)",
            $start_date
        ), ARRAY_A );
        $map = [];
        foreach ( ( $rows ?: [] ) as $r ) $map[ $r['d'] ] = (int) $r['c'];
        $timeline = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $d = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
            $timeline[] = [ 'date' => $d, 'count' => $map[ $d ] ?? 0 ];
        }

        return [
            'totals'   => [
                'posts_published'        => isset( $counts->publish ) ? (int) $counts->publish : 0,
                'drafts'                 => isset( $counts->draft )   ? (int) $counts->draft   : 0,
                'scheduled'              => isset( $counts->future )  ? (int) $counts->future  : 0,
                'categories'             => (int) $cat_count,
                'recent_posts_published' => $recent_published,
                'recent_drafts'          => $recent_drafts,
                'factory_posted_child'   => $factory_posted,
            ],
            'timeline' => $timeline,
            'days'     => $days,
        ];
    }

    private function upload_base64_image( array $img ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $bytes = base64_decode( $img['base64'] ?? '' );
        if ( $bytes === false || $bytes === '' ) return new WP_Error( 'bad_b64', 'Invalid base64.' );
        $filename = sanitize_file_name( $img['filename'] ?? ( 'pbn-' . wp_generate_password( 8, false ) . '.png' ) );

        $upload = wp_upload_bits( $filename, null, $bytes );
        if ( ! empty( $upload['error'] ) ) return new WP_Error( 'upload_failed', $upload['error'] );

        $mime = $img['mime'] ?? wp_check_filetype( $filename )['type'] ?? 'image/png';

        $attachment = [
            'post_mime_type' => $mime,
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $att_id = wp_insert_attachment( $attachment, $upload['file'] );
        if ( is_wp_error( $att_id ) ) return $att_id;

        $meta = wp_generate_attachment_metadata( $att_id, $upload['file'] );
        wp_update_attachment_metadata( $att_id, $meta );
        if ( ! empty( $img['alt'] ) ) update_post_meta( $att_id, '_wp_attachment_image_alt', sanitize_text_field( $img['alt'] ) );

        return (int) $att_id;
    }
}
