<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class PageAnalysis {

    private \HGE\DB\Repository $repo;

    public function __construct() {
        $this->repo = new \HGE\DB\Repository();
    }

    /**
     * WordPress sayfalarını tara ve GSC verisiyle zenginleştir
     */
    public function get_enriched_pages(){
        $db_pages   = $this->repo->get_all_page_stats( 300 );
        $wp_pages   = $this->get_wp_pages();
        $db_indexed = [];
        foreach ( $db_pages as $p ) {
            $db_indexed[ $p['page_url'] ] = $p;
        }

        $result = [];
        foreach ( $wp_pages as $wp_page ) {
            $url  = $wp_page['url'];
            $base = $db_indexed[ $url ] ?? [];

            $merged = array_merge(
                [
                    'page_url'      => $url,
                    'page_title'    => $wp_page['title'],
                    'post_id'       => $wp_page['id'],
                    'word_count'    => $wp_page['word_count'],
                    'has_meta_desc' => $wp_page['has_meta_desc'],
                    'internal_links' => $wp_page['internal_links'],
                    'index_status'  => 'unknown',
                    'impressions'   => 0,
                    'clicks'        => 0,
                    'ctr'           => 0,
                    'avg_position'  => 0,
                    'main_keyword'  => '',
                ],
                $base
            );

            if ( empty( $merged['page_title'] ) ) {
                $merged['page_title'] = $wp_page['title'] ?: $this->title_from_url( $url );
            }

            $result[] = $merged;
        }

        // Tıklama'ya göre sırala
        usort( $result, fn( $a, $b ) => $b['clicks'] <=> $a['clicks'] );
        return $result;
    }

    private function get_wp_pages(){
        $posts = get_posts( [
            'post_type'      => [ 'page', 'post' ],
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );

        return array_map( function ( \WP_Post $post ){
            $content      = wp_strip_all_tags( $post->post_content );
            $word_count   = str_word_count( $content );
            $meta_desc    = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true )
                            ?: get_post_meta( $post->ID, '_aioseo_description', true )
                            ?: get_post_meta( $post->ID, 'rank_math_description', true );

            return [
                'id'            => $post->ID,
                'url'           => get_permalink( $post->ID ),
                'title'         => get_the_title( $post->ID ),
                'word_count'    => $word_count,
                'has_meta_desc' => ! empty( $meta_desc ) ? 1 : 0,
                'internal_links' => $this->count_internal_links( $post->post_content ),
            ];
        }, $posts );
    }

    private function count_internal_links( string $content ){
        $site_url = get_site_url();
        preg_match_all( '/<a[^>]+href=["\'](' . preg_quote( $site_url, '/' ) . '[^"\']*)["\'][^>]*>/i', $content, $matches );
        return count( $matches[1] ?? [] );
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
