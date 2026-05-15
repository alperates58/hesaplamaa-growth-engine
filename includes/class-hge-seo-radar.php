<?php
namespace HGE;

defined( 'ABSPATH' ) || exit;

class SEORadar {

    private const DEFAULT_LIMIT = 50;
    private const DEFAULT_DAYS  = 28;

    private \HGE\DB\Repository $repo;
    private OpportunityScore $scorer;

    public function __construct() {
        $this->repo   = new \HGE\DB\Repository();
        $this->scorer = new OpportunityScore();
    }

    public function get_list( array $filters ){
        return $this->repo->get_seo_radar_rows( $this->normalize_filters( $filters ) );
    }

    public function get_summary( array $filters ){
        return $this->repo->get_seo_radar_summary( $this->normalize_filters( $filters ) );
    }

    public function get_row( int $id ){
        return $this->repo->get_seo_radar_row( $id );
    }

    public function normalize_filters( array $filters ){
        $allowed_days = [ 7, 28, 90 ];
        $days         = (int) ( $filters['days'] ?? self::DEFAULT_DAYS );
        $days         = in_array( $days, $allowed_days, true ) ? $days : self::DEFAULT_DAYS;
        $page         = max( 1, (int) ( $filters['paged'] ?? 1 ) );
        $limit        = max( 10, min( 100, (int) ( $filters['limit'] ?? self::DEFAULT_LIMIT ) ) );
        $view         = sanitize_key( (string) ( $filters['view'] ?? 'quick-wins' ) );

        return [
            'days'                => $days,
            'limit'               => $limit,
            'offset'              => ( $page - 1 ) * $limit,
            'paged'               => $page,
            'view'                => $view,
            'position_band'       => sanitize_text_field( (string) ( $filters['position_band'] ?? '' ) ),
            'low_ctr_only'        => ! empty( $filters['low_ctr_only'] ) ? 1 : 0,
            'high_impressions_only' => ! empty( $filters['high_impressions_only'] ) ? 1 : 0,
            'high_volume_only'    => ! empty( $filters['high_volume_only'] ) ? 1 : 0,
            'low_competition_only'=> ! empty( $filters['low_competition_only'] ) ? 1 : 0,
            'intent_only'         => ! empty( $filters['intent_only'] ) ? 1 : 0,
            'quality_only'        => ! empty( $filters['quality_only'] ) ? 1 : 0,
            'search'              => sanitize_text_field( (string) ( $filters['search'] ?? '' ) ),
        ];
    }

    public function refresh_opportunities( array $args = [] ){
        $filters   = $this->normalize_filters( $args );
        $days      = (int) $filters['days'];
        $batch     = max( 50, min( 500, (int) ( $args['batch_size'] ?? 250 ) ) );
        $date_to   = gmdate( 'Y-m-d' );
        $date_from = gmdate( 'Y-m-d', strtotime( '-' . max( 1, $days - 1 ) . ' days' ) );
        $total     = $this->repo->get_keyword_count();
        $saved     = 0;
        $processed = 0;
        $offset    = 0;

        $this->repo->clear_seo_opportunities_window( $date_from, $date_to );

        $page_stats       = $this->index_rows_by_path( $this->repo->get_all_page_stats( 50000 ) );
        $index_status_map = $this->repo->get_index_status_map();
        $post_index       = $this->build_post_index();

        while ( $offset < $total ) {
            $rows = $this->repo->get_keyword_batch( $batch, $offset );
            if ( empty( $rows ) ) {
                break;
            }

            $metrics = $this->repo->get_keyword_volume_data_map( array_column( $rows, 'keyword' ) );

            foreach ( $rows as $row ) {
                $record = $this->build_opportunity_record(
                    $row,
                    $metrics,
                    $page_stats,
                    $index_status_map,
                    $post_index,
                    $date_from,
                    $date_to
                );

                if ( $this->repo->save_seo_opportunity( $record ) ) {
                    $saved++;
                }
                $processed++;
            }

            $offset += $batch;
        }

        return [
            'processed' => $processed,
            'saved'     => $saved,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'message'   => sprintf( __( '%1$d satır işlendi, %2$d radar fırsatı kaydedildi.', 'hge' ), $processed, $saved ),
        ];
    }

    public function run_quality_check( int $post_id = 0, string $page_url = '', int $row_id = 0 ){
        $page_url      = esc_url_raw( $page_url );
        $quality_state = [
            'quality_status' => __( 'Kontrol bekliyor', 'hge' ),
            'has_issue'      => false,
            'signals'        => [],
        ];

        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            if ( $post && $post->post_status === 'publish' ) {
                $content = (string) $post->post_content;
                $render  = do_shortcode( $content );
                $issues  = [];
                $signals = [];

                $has_shortcode = strpos( $content, '[hc_' ) !== false;
                $signals[] = [
                    'label' => 'Shortcode',
                    'ok'    => $has_shortcode,
                ];
                if ( ! $has_shortcode ) {
                    $issues[] = __( 'Shortcode bulunamadı', 'hge' );
                }

                $has_form_signal = strpos( $render, '<form' ) !== false || strpos( $render, 'calculator' ) !== false || strpos( $render, 'hesapla' ) !== false;
                $signals[] = [
                    'label' => 'Render',
                    'ok'    => $has_form_signal,
                ];
                if ( ! $has_form_signal ) {
                    $issues[] = __( 'Render çıktısı zayıf', 'hge' );
                }

                $has_raw_shortcode = preg_match( '/\[hc_[^\]]+\]/', $render ) === 1;
                $signals[] = [
                    'label' => 'Ham shortcode',
                    'ok'    => ! $has_raw_shortcode,
                ];
                if ( $has_raw_shortcode ) {
                    $issues[] = __( 'Ham shortcode görünüyor', 'hge' );
                }

                $has_artifacts = $this->contains_bad_artifacts( $render );
                $signals[] = [
                    'label' => 'Bozuk kalıntı',
                    'ok'    => ! $has_artifacts,
                ];
                if ( $has_artifacts ) {
                    $issues[] = __( 'Bozuk köşeli kalıntı bulundu', 'hge' );
                }

                $quality_state = [
                    'quality_status' => empty( $issues ) ? __( 'Sağlıklı', 'hge' ) : implode( ' · ', $issues ),
                    'has_issue'      => ! empty( $issues ),
                    'signals'        => $signals,
                ];
            }
        } elseif ( $page_url !== '' ) {
            $response = wp_remote_get( $page_url, [ 'timeout' => 10 ] );
            if ( is_wp_error( $response ) ) {
                $quality_state = [
                    'quality_status' => __( 'HTTP kontrolü başarısız', 'hge' ),
                    'has_issue'      => true,
                    'signals'        => [
                        [
                            'label' => 'HTTP',
                            'ok'    => false,
                        ],
                    ],
                ];
            } else {
                $body      = (string) wp_remote_retrieve_body( $response );
                $http_code = (int) wp_remote_retrieve_response_code( $response );
                $quality_state = [
                    'quality_status' => $http_code === 200 && ! $this->contains_bad_artifacts( $body )
                        ? __( 'Sağlıklı', 'hge' )
                        : __( 'Teknik kontrol gerekli', 'hge' ),
                    'has_issue'      => $http_code !== 200 || $this->contains_bad_artifacts( $body ),
                    'signals'        => [
                        [
                            'label' => 'HTTP ' . $http_code,
                            'ok'    => $http_code === 200,
                        ],
                    ],
                ];
            }
        }

        if ( $row_id > 0 ) {
            $row = $this->repo->get_seo_radar_row( $row_id );
            if ( $row ) {
                $modified_gmt = (int) ( $row['post_id'] ?? 0 ) > 0
                    ? (string) get_post_field( 'post_modified_gmt', (int) $row['post_id'] )
                    : '';
                $row['has_quality_issue'] = $quality_state['has_issue'];
                $row['quality_status']    = $quality_state['quality_status'];
                $score                    = $this->scorer->score( [
                    'keyword'          => $row['keyword'],
                    'position'         => $row['position'],
                    'impressions'      => $row['impressions'],
                    'ctr'              => $row['ctr'],
                    'search_volume'    => $row['search_volume'],
                    'competition'      => $row['competition'],
                    'post_id'          => $row['post_id'],
                    'post_modified_gmt'=> $modified_gmt,
                    'has_quality_issue'=> $quality_state['has_issue'],
                ] );
                $this->repo->update_seo_opportunity_quality(
                    $row_id,
                    $quality_state['quality_status'],
                    $score['status'],
                    (int) $score['opportunity_score']
                );
            }
        }

        return $quality_state;
    }

    public function generate_ai_suggestion( int $row_id ){
        $row = $this->repo->get_seo_radar_row( $row_id );
        if ( empty( $row ) ) {
            return new \WP_Error( 'hge_radar_row_missing', __( 'Radar satırı bulunamadı.', 'hge' ) );
        }

        $repo   = new \HGE\DB\Repository();
        $cached = $repo->get_ai_insight( (string) $row['keyword'] );
        if ( ! empty( $cached['insight']['radar_title'] ) ) {
            return [
                'cached'  => true,
                'insight' => $cached['insight'],
                'model'   => (string) ( $cached['model'] ?? '' ),
            ];
        }

        $client = new \HGE\API\OpenAIClient();
        if ( ! $client->is_configured() ) {
            return new \WP_Error( 'hge_ai_not_configured', __( 'AI entegrasyonu aktif değil veya API key eksik.', 'hge' ) );
        }

        $result = $client->generate_seo_radar_suggestion( [
            'keyword'           => (string) $row['keyword'],
            'page_url'          => (string) $row['page_url'],
            'clicks'            => (int) $row['clicks'],
            'impressions'       => (int) $row['impressions'],
            'ctr'               => (float) $row['ctr'],
            'position'          => (float) $row['position'],
            'search_volume'     => (int) $row['search_volume'],
            'competition'       => (string) $row['competition'],
            'opportunity_score' => (int) $row['opportunity_score'],
            'quality_status'    => (string) $row['quality_status'],
            'recommended_actions' => json_decode( (string) ( $row['recommended_actions_json'] ?? '[]' ), true ),
        ] );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $repo->save_ai_insight(
            (string) $row['keyword'],
            (string) $result['model'],
            $result['insight'],
            hash( 'sha256', wp_json_encode( [ 'type' => 'seo_radar', 'row_id' => $row_id, 'keyword' => $row['keyword'] ] ) )
        );

        return [
            'cached'  => false,
            'insight' => $result['insight'],
            'model'   => $result['model'],
        ];
    }

    public function stream_csv( array $filters ){
        $filters          = $this->normalize_filters( $filters );
        $filters['limit'] = 5000;
        $filters['offset']= 0;
        $rows             = $this->repo->get_seo_radar_rows( $filters )['items'] ?? [];

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=seo-radar-' . gmdate( 'Ymd-His' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [
            'Anahtar Kelime',
            'Hedef URL',
            'Post ID',
            'Clicks',
            'Impressions',
            'CTR',
            'Ortalama Pozisyon',
            'Aylik Arama Hacmi',
            'Competition',
            'Firsat Puani',
            'Durum',
            'Kalite Sinyali',
            'Onerilen Aksiyonlar',
        ] );

        foreach ( $rows as $row ) {
            fputcsv( $output, [
                $row['keyword'],
                $row['page_url'],
                $row['post_id'],
                $row['clicks'],
                $row['impressions'],
                $row['ctr'],
                $row['position'],
                $row['search_volume'],
                $row['competition'],
                $row['opportunity_score'],
                $row['status'],
                $row['quality_status'],
                implode( ' | ', $row['recommended_actions'] ?? [] ),
            ] );
        }

        fclose( $output );
        exit;
    }

    private function build_opportunity_record( array $row, array $metrics, array $page_stats, array $index_status_map, array $post_index, string $date_from, string $date_to ){
        $keyword         = sanitize_text_field( (string) ( $row['keyword'] ?? '' ) );
        $page_url        = esc_url_raw( (string) ( $row['page_url'] ?? '' ) );
        $path_key        = $this->normalize_url_path( $page_url );
        $page_stat       = $page_stats[ $path_key ] ?? [];
        $post_match      = $post_index[ $path_key ] ?? [];
        $metric          = $metrics[ $this->normalize_keyword( $keyword ) ] ?? [ 'monthly_volume' => 0, 'competition' => 'UNKNOWN' ];
        $post_id         = (int) ( $page_stat['post_id'] ?? $post_match['post_id'] ?? 0 );
        $post_modified   = (string) ( $post_match['post_modified_gmt'] ?? '' );
        $quality_status  = $this->derive_quality_status( $post_id, $page_url, $index_status_map );
        $has_issue       = $quality_status !== __( 'Kontrol bekliyor', 'hge' ) && $quality_status !== __( 'Sağlıklı', 'hge' );
        $score           = $this->scorer->score( [
            'keyword'           => $keyword,
            'position'          => (float) ( $row['avg_position'] ?? 0 ),
            'impressions'       => (int) ( $row['impressions'] ?? 0 ),
            'ctr'               => (float) ( $row['ctr'] ?? 0 ),
            'search_volume'     => (int) ( $metric['monthly_volume'] ?? 0 ),
            'competition'       => (string) ( $metric['competition'] ?? 'UNKNOWN' ),
            'post_id'           => $post_id,
            'post_modified_gmt' => $post_modified,
            'has_quality_issue' => $has_issue,
        ] );

        return [
            'keyword'                  => $keyword,
            'page_url'                 => $page_url,
            'post_id'                  => $post_id,
            'clicks'                   => (int) ( $row['clicks'] ?? 0 ),
            'impressions'              => (int) ( $row['impressions'] ?? 0 ),
            'ctr'                      => (float) ( $row['ctr'] ?? 0 ),
            'position'                 => (float) ( $row['avg_position'] ?? 0 ),
            'search_volume'            => (int) ( $metric['monthly_volume'] ?? 0 ),
            'competition'              => (string) ( $metric['competition'] ?? 'UNKNOWN' ),
            'intent_score'             => (int) $score['intent_score'],
            'opportunity_score'        => (int) $score['opportunity_score'],
            'status'                   => (string) $score['status'],
            'quality_status'           => $quality_status,
            'recommended_actions_json' => wp_json_encode( $score['recommended_actions'], JSON_UNESCAPED_UNICODE ),
            'source'                   => 'gsc_keyword_planner',
            'date_from'                => $date_from,
            'date_to'                  => $date_to,
            'post_title'               => (string) ( $post_match['post_title'] ?? '' ),
            'post_slug'                => (string) ( $post_match['post_slug'] ?? '' ),
            'category_label'           => (string) ( $post_match['category_label'] ?? '' ),
            'post_modified_gmt'        => $post_modified,
        ];
    }

    private function build_post_index(){
        $post_ids = get_posts( [
            'post_type'      => [ 'post', 'page' ],
            'post_status'    => 'publish',
            'posts_per_page' => 2000,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        $index = [];
        foreach ( $post_ids as $post_id ) {
            $path = $this->normalize_url_path( get_permalink( $post_id ) );
            if ( $path === '' ) {
                continue;
            }

            $terms = get_the_terms( $post_id, 'category' );
            $cats  = [];
            if ( is_array( $terms ) ) {
                foreach ( $terms as $term ) {
                    $cats[] = $term->name;
                }
            }

            $index[ $path ] = [
                'post_id'           => (int) $post_id,
                'post_title'        => get_the_title( $post_id ) ?: '',
                'post_slug'         => (string) get_post_field( 'post_name', $post_id ),
                'post_modified_gmt' => (string) get_post_field( 'post_modified_gmt', $post_id ),
                'category_label'    => implode( ' / ', array_slice( $cats, 0, 2 ) ),
            ];
        }

        return $index;
    }

    private function index_rows_by_path( array $rows ){
        $indexed = [];
        foreach ( $rows as $row ) {
            $path = $this->normalize_url_path( (string) ( $row['page_url'] ?? '' ) );
            if ( $path !== '' ) {
                $indexed[ $path ] = $row;
            }
        }
        return $indexed;
    }

    private function derive_quality_status( int $post_id, string $page_url, array $index_status_map ){
        $index_row = [];
        if ( $post_id > 0 && isset( $index_status_map[ 'post:' . $post_id ] ) ) {
            $index_row = $index_status_map[ 'post:' . $post_id ];
        } elseif ( isset( $index_status_map[ $page_url ] ) ) {
            $index_row = $index_status_map[ $page_url ];
        }

        if ( empty( $index_row ) ) {
            return __( 'Kontrol bekliyor', 'hge' );
        }

        if ( ! empty( $index_row['error_message'] ) ) {
            return __( 'Teknik kontrol gerekli', 'hge' );
        }

        if ( ! empty( $index_row['user_canonical'] ) && ! empty( $index_row['page_url'] ) && untrailingslashit( (string) $index_row['user_canonical'] ) !== untrailingslashit( (string) $index_row['page_url'] ) ) {
            return __( 'Canonical kontrolü gerekli', 'hge' );
        }

        if ( ! empty( $index_row['verdict'] ) && (string) $index_row['verdict'] !== 'PASS' ) {
            return __( 'Index kalitesi zayıf', 'hge' );
        }

        return __( 'Sağlıklı', 'hge' );
    }

    private function normalize_url_path( string $url ){
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        if ( $path === '' ) {
            return '';
        }

        $path = '/' . ltrim( $path, '/' );
        return $path === '/' ? '/' : trailingslashit( $path );
    }

    private function normalize_keyword( string $keyword ){
        $keyword = sanitize_text_field( $keyword );
        $keyword = preg_replace( '/\s+/u', ' ', trim( $keyword ) );

        if ( function_exists( 'mb_strtolower' ) ) {
            return mb_strtolower( (string) $keyword, 'UTF-8' );
        }

        return strtolower( (string) $keyword );
    }

    private function contains_bad_artifacts( string $content ){
        foreach ( [
            '[-0.094rem]',
            '[#303030]',
            '[#8F8F8F]',
            '[9px]',
            '[#F4F4F4]',
            '[show_150ms_ease-in]',
        ] as $pattern ) {
            if ( strpos( $content, $pattern ) !== false ) {
                return true;
            }
        }

        return false;
    }
}
