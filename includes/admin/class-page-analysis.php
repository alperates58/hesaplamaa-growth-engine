<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class PageAnalysis {

    private \HGE\DB\Repository $repo;

    public function __construct() {
        $this->repo = new \HGE\DB\Repository();
    }

    /**
     * WordPress sayfalarini tara ve GSC verisiyle zenginlestir
     */
    public function get_enriched_pages(){
        $db_limit   = (int) apply_filters( 'hge_page_analysis_page_stats_limit', 50000 );
        $db_pages   = $this->repo->get_all_page_stats( max( 1000, $db_limit ) );
        $wp_pages   = $this->get_wp_pages();
        $db_grouped = $this->group_page_stats_by_normalized_url( $db_pages );
        $wp_indexed = $this->index_wp_pages_by_path( $wp_pages );
        $path_keys  = array_unique( array_merge( array_keys( $db_grouped ), array_keys( $wp_indexed ) ) );

        $result = [];
        foreach ( $path_keys as $path_key ) {
            $base    = $db_grouped[ $path_key ] ?? [];
            $wp_page = $wp_indexed[ $path_key ] ?? null;
            $url     = $wp_page['url'] ?? ( $base['page_url'] ?? '' );

            if ( $url === '' ) {
                continue;
            }

            $page_title = '';
            if ( is_array( $wp_page ) && ! empty( $wp_page['title'] ) ) {
                $page_title = $wp_page['title'];
            } elseif ( ! empty( $base['page_title'] ) ) {
                $page_title = $base['page_title'];
            } else {
                $page_title = $this->title_from_url( $url );
            }

            $result[] = [
                'page_url'                => $url,
                'page_title'              => $page_title,
                'post_id'                 => (int) ( $wp_page['id'] ?? 0 ),
                'word_count'              => (int) ( $wp_page['word_count'] ?? 0 ),
                'has_meta_desc'           => (int) ( $wp_page['has_meta_desc'] ?? 0 ),
                'internal_links'          => (int) ( $wp_page['internal_links'] ?? 0 ),
                'index_status'            => $base['index_status'] ?? 'unknown',
                'impressions'             => (int) ( $base['impressions'] ?? 0 ),
                'clicks'                  => (int) ( $base['clicks'] ?? 0 ),
                'ctr'                     => (float) ( $base['ctr'] ?? 0 ),
                'avg_position'            => (float) ( $base['avg_position'] ?? 0 ),
                'main_keyword'            => (string) ( $base['main_keyword'] ?? '' ),
                'normalized_legacy_count' => (int) ( $base['normalized_legacy_count'] ?? 0 ),
            ];
        }

        usort( $result, fn( $a, $b ) => $b['clicks'] <=> $a['clicks'] );
        return $result;
    }

    private function index_wp_pages_by_path( array $wp_pages ){
        $indexed = [];

        foreach ( $wp_pages as $wp_page ) {
            $path_key = $this->normalize_url_path( (string) ( $wp_page['url'] ?? '' ) );
            if ( $path_key === '' ) {
                continue;
            }

            $indexed[ $path_key ] = $wp_page;
        }

        return $indexed;
    }

    private function get_wp_pages(){
        $post_ids = get_posts( [
            'post_type'      => [ 'page', 'post' ],
            'post_status'    => 'publish',
            'posts_per_page' => (int) apply_filters( 'hge_page_analysis_posts_per_page', 2000 ),
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        return array_map( function ( int $post_id ){
            $post_content = (string) get_post_field( 'post_content', $post_id );
            $content      = wp_strip_all_tags( $post_content );
            $word_count   = str_word_count( $content );
            $meta_desc    = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
                            ?: get_post_meta( $post_id, '_aioseo_description', true )
                            ?: get_post_meta( $post_id, 'rank_math_description', true );

            return [
                'id'             => $post_id,
                'url'            => get_permalink( $post_id ),
                'title'          => get_the_title( $post_id ),
                'word_count'     => $word_count,
                'has_meta_desc'  => ! empty( $meta_desc ) ? 1 : 0,
                'internal_links' => $this->count_internal_links( $post_content ),
            ];
        }, $post_ids );
    }

    private function count_internal_links( string $content ){
        $site_url = get_site_url();
        preg_match_all( '/<a[^>]+href=["\'](' . preg_quote( $site_url, '/' ) . '[^"\']*)["\'][^>]*>/i', $content, $matches );
        return count( $matches[1] ?? [] );
    }

    private function group_page_stats_by_normalized_url( array $db_pages ){
        $grouped = [];

        foreach ( $db_pages as $page ) {
            $original_url   = (string) ( $page['page_url'] ?? '' );
            $normalized_url = $this->normalize_page_url( $original_url );
            $path_key       = $this->normalize_url_path( $original_url );

            if ( $path_key === '' ) {
                continue;
            }

            $impressions = max( 0, (int) ( $page['impressions'] ?? 0 ) );
            $clicks      = max( 0, (int) ( $page['clicks'] ?? 0 ) );
            $position    = (float) ( $page['avg_position'] ?? 0 );
            $keyword     = sanitize_text_field( (string) ( $page['main_keyword'] ?? '' ) );
            $title       = sanitize_text_field( (string) ( $page['page_title'] ?? '' ) );
            $score       = ( $clicks * 1000000 ) + $impressions;

            if ( ! isset( $grouped[ $path_key ] ) ) {
                $grouped[ $path_key ] = [
                    'page_url'                => $normalized_url,
                    'page_title'              => $title,
                    'impressions'             => 0,
                    'clicks'                  => 0,
                    'ctr'                     => 0,
                    'avg_position'            => 0,
                    'main_keyword'            => $keyword,
                    'index_status'            => $page['index_status'] ?? 'unknown',
                    'normalized_legacy_count' => 0,
                    '_keyword_score'          => $score,
                    '_position_weighted_sum'  => 0.0,
                ];
            }

            $grouped[ $path_key ]['impressions'] += $impressions;
            $grouped[ $path_key ]['clicks'] += $clicks;
            $grouped[ $path_key ]['_position_weighted_sum'] += $position * $impressions;

            if ( $score > $grouped[ $path_key ]['_keyword_score'] ) {
                $grouped[ $path_key ]['_keyword_score'] = $score;
                $grouped[ $path_key ]['main_keyword']   = $keyword;
            }

            if ( $grouped[ $path_key ]['page_title'] === '' && $title !== '' ) {
                $grouped[ $path_key ]['page_title'] = $title;
            }

            if ( $this->is_legacy_host_url( $original_url ) ) {
                $grouped[ $path_key ]['normalized_legacy_count']++;
            }

            if ( ! empty( $normalized_url ) ) {
                $grouped[ $path_key ]['page_url'] = $normalized_url;
            }
        }

        foreach ( $grouped as $path_key => $page ) {
            $impressions = (int) $page['impressions'];
            $clicks      = (int) $page['clicks'];

            $grouped[ $path_key ]['ctr'] = $impressions > 0 ? $clicks / $impressions : 0;
            $grouped[ $path_key ]['avg_position'] = $impressions > 0
                ? $page['_position_weighted_sum'] / $impressions
                : 0;

            unset(
                $grouped[ $path_key ]['_keyword_score'],
                $grouped[ $path_key ]['_position_weighted_sum']
            );
        }

        return $grouped;
    }

    private function normalize_page_url( string $url ){
        $url = trim( $url );
        if ( $url === '' ) {
            return '';
        }

        $parts = wp_parse_url( $url );
        if ( empty( $parts['host'] ) ) {
            return '';
        }

        $host = preg_replace( '/^www\./i', '', strtolower( (string) $parts['host'] ) );
        $path = $this->normalize_url_path( $url );

        if ( $path === '' ) {
            return '';
        }

        if ( $this->is_legacy_host( $host ) ) {
            return 'https://hesaplamaa.com' . $path;
        }

        $scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
        return $scheme . '://' . $host . $path;
    }

    private function normalize_url_path( string $url ){
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );

        if ( $path === '' ) {
            return '/';
        }

        $path = '/' . ltrim( $path, '/' );
        return $path === '/' ? $path : trailingslashit( $path );
    }

    private function is_legacy_host_url( string $url ){
        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        if ( $host === '' ) {
            return false;
        }

        return $this->is_legacy_host( preg_replace( '/^www\./i', '', strtolower( $host ) ) );
    }

    private function is_legacy_host( string $host ){
        return strpos( $host, 'sslip.io' ) !== false
            || strpos( $host, 'wordpress-qmqt6o2ml0b0hbwonxns7cfg' ) !== false;
    }

    private function title_from_url( string $url ){
        $path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
        if ( $path === '' ) {
            return __( 'Ana Sayfa', 'hge' );
        }

        $parts = explode( '/', $path );
        $slug  = end( $parts );
        return ucwords( str_replace( '-', ' ', sanitize_title( $slug ) ) );
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }
        $pages         = $this->get_enriched_pages();
        $gsc_connected = ( new \HGE\API\GSCClient() )->is_connected();
        require HGE_DIR . 'templates/admin/page-analysis.php';
    }
}
