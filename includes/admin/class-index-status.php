<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class IndexStatus {

    private const INDEXED_RECHECK_DAYS = 14;
    private const RECENT_RECHECK_HOURS = 12;

    private \HGE\DB\Repository $repo;

    public function __construct() {
        $this->repo = new \HGE\DB\Repository();
    }

    public function get_data(){
        $settings   = get_option( 'hge_settings', [] );
        $status_map = $this->repo->get_index_status_map();
        $rows       = [];

        foreach ( $this->get_public_posts() as $post ) {
            $url    = get_permalink( $post->ID );
            $status = $this->normalize_status_for_post( $status_map[ 'post:' . $post->ID ] ?? $status_map[ $url ] ?? [], $url );
            $status['inspection_link'] = $this->get_preferred_inspection_link( $url, $status, $settings );

            $rows[] = array_merge(
                [
                    'post_id'          => $post->ID,
                    'page_url'         => $url,
                    'page_title'       => get_the_title( $post->ID ) ?: '(Başlıksız)',
                    'verdict'          => '',
                    'coverage_state'   => '',
                    'robots_txt_state' => '',
                    'indexing_state'   => '',
                    'page_fetch_state' => '',
                    'last_crawl_time'  => '',
                    'last_checked'     => '',
                    'inspection_link'  => '',
                    'error_message'    => '',
                ],
                $status,
                [
                    'post_id'    => $post->ID,
                    'page_url'   => $url,
                    'page_title' => get_the_title( $post->ID ) ?: '(BaÅŸlÄ±ksÄ±z)',
                ]
            );
        }

        usort(
            $rows,
            static fn( $a, $b ) => strcmp( (string) ( $a['last_checked'] ?? '' ), (string) ( $b['last_checked'] ?? '' ) )
        );

        return $rows;
    }

    public function get_summary( array $rows ){
        $summary = [
            'total'      => count( $rows ),
            'indexed'    => 0,
            'not_indexed'=> 0,
            'pending'    => 0,
            'errors'     => 0,
        ];

        foreach ( $rows as $row ) {
            if ( ! empty( $row['error_message'] ) ) {
                $summary['errors']++;
            } elseif ( empty( $row['last_checked'] ) ) {
                $summary['pending']++;
            } elseif ( ( $row['verdict'] ?? '' ) === 'PASS' ) {
                $summary['indexed']++;
            } else {
                $summary['not_indexed']++;
            }
        }

        return $summary;
    }

    public function inspect_post( int $post_id ){
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return new \WP_Error( 'hge_invalid_post', __( 'Yayınlanmış sayfa bulunamadı.', 'hge' ) );
        }

        return $this->inspect_url( get_permalink( $post_id ), get_the_title( $post_id ), $post_id );
    }

    public function inspect_url( string $url, string $title = '', int $post_id = 0 ){
        $settings = get_option( 'hge_settings', [] );
        $client   = new \HGE\API\GSCClient();

        if ( ! $client->is_connected() ) {
            return new \WP_Error( 'hge_gsc_not_connected', __( 'GSC bağlı değil.', 'hge' ) );
        }

        $result = null;
        $last_error = null;
        foreach ( $this->get_inspection_site_candidates( $url, $settings, $client ) as $site_url ) {
            $result = $client->inspect_url( $site_url, $url, 'tr-TR' );
            if ( ! is_wp_error( $result ) ) {
                $this->remember_working_site_url( $site_url, $settings );
                break;
            }
            $last_error = $result;
        }

        if ( $result === null ) {
            $last_error = new \WP_Error( 'hge_no_gsc_property', __( 'Bu URL ile eşleşen GSC property bulunamadı.', 'hge' ) );
        }

        if ( is_wp_error( $result ) ) {
            $this->repo->upsert_index_status( [
                'page_url'      => $url,
                'page_title'    => $title,
                'post_id'       => $post_id,
                'error_message' => $last_error ? $last_error->get_error_message() : $result->get_error_message(),
            ] );
            return $last_error ?: $result;
        }

        $index = $result['indexStatusResult'] ?? [];
        $this->repo->upsert_index_status( [
            'page_url'         => $url,
            'page_title'       => $title,
            'post_id'          => $post_id,
            'verdict'          => $index['verdict'] ?? '',
            'coverage_state'   => $index['coverageState'] ?? '',
            'robots_txt_state' => $index['robotsTxtState'] ?? '',
            'indexing_state'   => $index['indexingState'] ?? '',
            'page_fetch_state' => $index['pageFetchState'] ?? '',
            'google_canonical' => $index['googleCanonical'] ?? '',
            'user_canonical'   => $index['userCanonical'] ?? '',
            'crawled_as'       => $index['crawledAs'] ?? '',
            'last_crawl_time'  => $index['lastCrawlTime'] ?? '',
            'inspection_link'  => $this->build_inspection_link( $site_url, $url, $result['inspectionResultLink'] ?? '' ),
            'error_message'    => '',
        ] );

        $status_map = $this->repo->get_index_status_map();
        return $this->format_row_for_response( $status_map[ 'post:' . $post_id ] ?? $status_map[ $url ] ?? [] );
    }

    public function inspect_pending( int $limit = 5 ){
        $rows    = $this->get_data();
        $checked = [];
        $skipped = 0;
        $limit   = max( 1, min( 50, $limit ) );

        foreach ( $rows as $row ) {
            if ( count( $checked ) >= $limit ) {
                break;
            }

            if ( $this->should_skip_batch_check( $row ) ) {
                $skipped++;
                continue;
            }

            $result = $this->inspect_url( $row['page_url'], $row['page_title'], (int) $row['post_id'] );
            $checked[] = [
                'url' => $row['page_url'],
                'ok'  => ! is_wp_error( $result ),
                'msg' => is_wp_error( $result ) ? $result->get_error_message() : 'OK',
            ];
        }

        return [
            'checked' => $checked,
            'skipped' => $skipped,
        ];
    }

    public function queue_published_post( int $post_id ){
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
            return;
        }

        $this->repo->queue_index_status_url( get_permalink( $post_id ), get_the_title( $post_id ), $post_id );
    }

    private function get_public_posts(){
        return get_posts( [
            'post_type'      => [ 'page', 'post' ],
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );
    }

    private function should_skip_batch_check( array $row ){
        if ( empty( $row['last_checked'] ) ) {
            return false;
        }

        $last_checked = strtotime( (string) $row['last_checked'] );
        if ( ! $last_checked ) {
            return false;
        }

        if ( ( $row['verdict'] ?? '' ) === 'PASS' ) {
            return $last_checked > strtotime( '-' . self::INDEXED_RECHECK_DAYS . ' days' );
        }

        return $last_checked > strtotime( '-' . self::RECENT_RECHECK_HOURS . ' hours' );
    }

    private function get_inspection_site_candidates( string $inspection_url, array $settings, \HGE\API\GSCClient $client ){
        $candidates = [];
        $configured = trim( (string) ( $settings['gsc_site_url'] ?? '' ) );

        foreach ( $this->expand_site_url_candidate( $configured ) as $candidate ) {
            $candidates[] = $candidate;
        }

        $sites = $client->get_sites();
        if ( ! is_wp_error( $sites ) ) {
            foreach ( $sites as $site ) {
                $site_url = $site['siteUrl'] ?? '';
                if ( $this->site_matches_url( $site_url, $inspection_url ) ) {
                    foreach ( $this->expand_site_url_candidate( $site_url ) as $candidate ) {
                        $candidates[] = $candidate;
                    }
                }
            }
        }

        foreach ( $this->expand_site_url_candidate( get_site_url() ) as $candidate ) {
            $candidates[] = $candidate;
        }

        return array_values( array_unique( array_filter( $candidates ) ) );
    }

    private function expand_site_url_candidate( string $site_url ){
        $site_url = trim( $site_url );
        if ( $site_url === '' ) {
            return [];
        }

        if ( stripos( $site_url, 'sc-domain:' ) === 0 ) {
            return [ $site_url ];
        }

        return [ trailingslashit( $site_url ), untrailingslashit( $site_url ) ];
    }

    private function site_matches_url( string $site_url, string $inspection_url ){
        if ( $site_url === '' ) {
            return false;
        }

        if ( stripos( $site_url, 'sc-domain:' ) === 0 ) {
            $domain = strtolower( substr( $site_url, 10 ) );
            $host   = strtolower( (string) wp_parse_url( $inspection_url, PHP_URL_HOST ) );
            $host   = preg_replace( '/^www\./i', '', $host );
            return $host === $domain || substr( $host, -1 * ( strlen( $domain ) + 1 ) ) === '.' . $domain;
        }

        return strpos( trailingslashit( $inspection_url ), trailingslashit( $site_url ) ) === 0;
    }

    private function remember_working_site_url( string $site_url, array $settings ){
        if ( empty( $site_url ) || ( $settings['gsc_site_url'] ?? '' ) === $site_url ) {
            return;
        }

        $settings['gsc_site_url'] = $site_url;
        update_option( 'hge_settings', $settings );
    }

    private function build_inspection_link( string $site_url, string $inspection_url, string $fallback_link = '' ){
        $site_url       = trim( $site_url );
        $inspection_url = trim( $inspection_url );

        if ( $site_url === '' || $inspection_url === '' ) {
            return $fallback_link;
        }

        return add_query_arg(
            [
                'resource_id' => $site_url,
                'id'          => $inspection_url,
            ],
            'https://search.google.com/search-console/inspect'
        );
    }

    private function get_preferred_inspection_link( string $inspection_url, array $status, array $settings ){
        $configured_site_url = trim( (string) ( $settings['gsc_site_url'] ?? '' ) );
        if ( $configured_site_url !== '' ) {
            return $this->build_inspection_link( $configured_site_url, $inspection_url, $status['inspection_link'] ?? '' );
        }

        return $status['inspection_link'] ?? '';
    }

    private function normalize_status_for_post( array $status, string $current_url ){
        if ( empty( $status ) || empty( $status['page_url'] ) || $status['page_url'] === $current_url ) {
            return $status;
        }

        foreach ( [
            'verdict',
            'coverage_state',
            'robots_txt_state',
            'indexing_state',
            'page_fetch_state',
            'google_canonical',
            'user_canonical',
            'crawled_as',
            'last_crawl_time',
            'last_checked',
            'inspection_link',
            'error_message',
        ] as $field ) {
            $status[ $field ] = '';
        }

        return $status;
    }

    private function format_row_for_response( array $row ){
        return [
            'verdict'        => $row['verdict'] ?? '',
            'coverage_state' => $row['coverage_state'] ?? '',
            'last_checked'   => $row['last_checked'] ?? '',
            'error_message'  => $row['error_message'] ?? '',
        ];
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }

        $rows          = $this->get_data();
        $summary       = $this->get_summary( $rows );
        $gsc_connected = ( new \HGE\API\GSCClient() )->is_connected();
        require HGE_DIR . 'templates/admin/index-status.php';
    }
}
