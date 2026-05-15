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

        return [
            'days'                  => $days,
            'limit'                 => $limit,
            'offset'                => ( $page - 1 ) * $limit,
            'paged'                 => $page,
            'view'                  => sanitize_key( (string) ( $filters['view'] ?? 'quick-wins' ) ),
            'position_band'         => sanitize_text_field( (string) ( $filters['position_band'] ?? '' ) ),
            'low_ctr_only'          => ! empty( $filters['low_ctr_only'] ) ? 1 : 0,
            'high_impressions_only' => ! empty( $filters['high_impressions_only'] ) ? 1 : 0,
            'high_volume_only'      => ! empty( $filters['high_volume_only'] ) ? 1 : 0,
            'low_competition_only'  => ! empty( $filters['low_competition_only'] ) ? 1 : 0,
            'intent_only'           => ! empty( $filters['intent_only'] ) ? 1 : 0,
            'quality_only'          => ! empty( $filters['quality_only'] ) ? 1 : 0,
            'search'                => sanitize_text_field( (string) ( $filters['search'] ?? '' ) ),
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
        $index_status_map = $this->normalize_index_status_map( $this->repo->get_index_status_map() );
        $post_index       = $this->build_post_index();

        while ( $offset < $total ) {
            $rows = $this->repo->get_keyword_batch( $batch, $offset );
            if ( empty( $rows ) ) {
                break;
            }

            $processed += count( $rows );
            $metrics     = $this->repo->get_keyword_volume_data_map( array_column( $rows, 'keyword' ) );
            $aggregated  = $this->aggregate_keyword_rows( $rows, $metrics );

            foreach ( $aggregated as $row ) {
                $record = $this->build_opportunity_record(
                    $row,
                    $page_stats,
                    $index_status_map,
                    $post_index,
                    $date_from,
                    $date_to
                );

                if ( $this->repo->save_seo_opportunity( $record ) ) {
                    $saved++;
                }
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
        $normalized_url = $this->normalize_page_url( $page_url );
        $resolved       = $this->resolve_url_target( $normalized_url );
        $quality_state  = [
            'quality_status' => __( 'Kontrol bekliyor', 'hge' ),
            'has_issue'      => false,
            'signals'        => [],
        ];

        if ( $resolved['url_type'] === 'category' ) {
            $quality_state = [
                'quality_status' => __( 'Kategori arşivi', 'hge' ),
                'has_issue'      => false,
                'signals'        => [
                    [ 'label' => 'URL tipi', 'ok' => true ],
                ],
            ];
        } elseif ( in_array( $resolved['url_type'], [ 'tag', 'author', 'archive' ], true ) ) {
            $quality_state = [
                'quality_status' => __( 'Arşiv URL', 'hge' ),
                'has_issue'      => false,
                'signals'        => [
                    [ 'label' => 'URL tipi', 'ok' => true ],
                ],
            ];
        } elseif ( $post_id > 0 ) {
            $post = get_post( $post_id );
            if ( $post && $post->post_status === 'publish' ) {
                $content = (string) $post->post_content;
                $render  = do_shortcode( $content );
                $issues  = [];
                $signals = [];

                $has_shortcode = strpos( $content, '[hc_' ) !== false;
                $signals[] = [ 'label' => 'Shortcode', 'ok' => $has_shortcode ];
                if ( ! $has_shortcode ) {
                    $issues[] = __( 'Shortcode bulunamadı', 'hge' );
                }

                $has_form_signal = strpos( $render, '<form' ) !== false || strpos( $render, 'calculator' ) !== false || strpos( $render, 'hesapla' ) !== false;
                $signals[] = [ 'label' => 'Render', 'ok' => $has_form_signal ];
                if ( ! $has_form_signal ) {
                    $issues[] = __( 'Render çıktısı zayıf', 'hge' );
                }

                $has_raw_shortcode = preg_match( '/\[hc_[^\]]+\]/', $render ) === 1;
                $signals[] = [ 'label' => 'Ham shortcode', 'ok' => ! $has_raw_shortcode ];
                if ( $has_raw_shortcode ) {
                    $issues[] = __( 'Ham shortcode görünüyor', 'hge' );
                }

                $has_artifacts = $this->contains_bad_artifacts( $render );
                $signals[] = [ 'label' => 'Bozuk kalıntı', 'ok' => ! $has_artifacts ];
                if ( $has_artifacts ) {
                    $issues[] = __( 'Bozuk köşeli kalıntı bulundu', 'hge' );
                }

                $quality_state = [
                    'quality_status' => empty( $issues ) ? __( 'Sağlıklı', 'hge' ) : implode( ' · ', $issues ),
                    'has_issue'      => ! empty( $issues ),
                    'signals'        => $signals,
                ];
            }
        } elseif ( $normalized_url !== '' && $resolved['url_type'] === 'unknown' ) {
            $quality_state = [
                'quality_status' => __( 'Kontrol gerekli', 'hge' ),
                'has_issue'      => false,
                'signals'        => [
                    [ 'label' => 'Resolver', 'ok' => false ],
                ],
            ];
        }

        if ( $row_id > 0 ) {
            $row = $this->repo->get_seo_radar_row( $row_id );
            if ( $row ) {
                $modified_gmt = (int) ( $row['post_id'] ?? 0 ) > 0
                    ? (string) get_post_field( 'post_modified_gmt', (int) $row['post_id'] )
                    : '';
                $score = $this->scorer->score( [
                    'keyword'           => $row['keyword'],
                    'position'          => $row['position'],
                    'impressions'       => $row['impressions'],
                    'ctr'               => $row['ctr'],
                    'search_volume'     => $row['search_volume'],
                    'competition'       => $row['competition'],
                    'post_id'           => $row['post_id'],
                    'post_modified_gmt' => $modified_gmt,
                    'has_quality_issue' => $this->is_quality_issue_status( $quality_state['quality_status'] ),
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
            'keyword'             => (string) $row['keyword'],
            'page_url'            => (string) $row['page_url'],
            'clicks'              => (int) $row['clicks'],
            'impressions'         => (int) $row['impressions'],
            'ctr'                 => (float) $row['ctr'],
            'position'            => (float) $row['position'],
            'search_volume'       => (int) $row['search_volume'],
            'competition'         => (string) $row['competition'],
            'opportunity_score'   => (int) $row['opportunity_score'],
            'quality_status'      => (string) $row['quality_status'],
            'recommended_actions' => $row['recommended_actions'] ?? [],
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
        $filters['offset'] = 0;
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

    private function aggregate_keyword_rows( array $rows, array $metrics ){
        $aggregated = [];

        foreach ( $rows as $row ) {
            $keyword        = sanitize_text_field( (string) ( $row['keyword'] ?? '' ) );
            $normalized_key = $this->normalize_keyword( $keyword );
            $page_url       = $this->normalize_page_url( (string) ( $row['page_url'] ?? '' ) );

            if ( $normalized_key === '' || $page_url === '' ) {
                continue;
            }

            $metric      = $metrics[ $normalized_key ] ?? [ 'monthly_volume' => 0, 'competition' => 'UNKNOWN' ];
            $clicks      = (int) ( $row['clicks'] ?? 0 );
            $impressions = (int) ( $row['impressions'] ?? 0 );
            $position    = (float) ( $row['avg_position'] ?? 0 );
            $group_key   = $normalized_key . '|' . $page_url;

            if ( ! isset( $aggregated[ $group_key ] ) ) {
                $aggregated[ $group_key ] = [
                    'keyword'         => $keyword,
                    'page_url'        => $page_url,
                    'clicks'          => 0,
                    'impressions'     => 0,
                    'ctr'             => 0.0,
                    'avg_position'    => 0.0,
                    'position_sum'    => 0.0,
                    'position_weight' => 0,
                    'search_volume'   => (int) ( $metric['monthly_volume'] ?? 0 ),
                    'competition'     => (string) ( $metric['competition'] ?? 'UNKNOWN' ),
                ];
            }

            $aggregated[ $group_key ]['clicks'] += $clicks;
            $aggregated[ $group_key ]['impressions'] += $impressions;
            $aggregated[ $group_key ]['position_sum'] += $position * max( 1, $impressions );
            $aggregated[ $group_key ]['position_weight'] += max( 1, $impressions );
            $aggregated[ $group_key ]['search_volume'] = max( (int) $aggregated[ $group_key ]['search_volume'], (int) ( $metric['monthly_volume'] ?? 0 ) );
            $aggregated[ $group_key ]['competition'] = $this->pick_competition(
                (string) $aggregated[ $group_key ]['competition'],
                (string) ( $metric['competition'] ?? 'UNKNOWN' )
            );
        }

        foreach ( $aggregated as &$item ) {
            $item['ctr'] = (int) $item['impressions'] > 0 ? (float) $item['clicks'] / (int) $item['impressions'] : 0.0;
            $item['avg_position'] = (int) $item['position_weight'] > 0 ? (float) $item['position_sum'] / (int) $item['position_weight'] : 0.0;
            unset( $item['position_sum'], $item['position_weight'] );
        }
        unset( $item );

        return array_values( $aggregated );
    }

    private function build_opportunity_record( array $row, array $page_stats, array $index_status_map, array $post_index, string $date_from, string $date_to ){
        $keyword        = sanitize_text_field( (string) ( $row['keyword'] ?? '' ) );
        $page_url       = $this->normalize_page_url( (string) ( $row['page_url'] ?? '' ) );
        $path_key       = $this->normalize_url_path( $page_url );
        $page_stat      = $page_stats[ $path_key ] ?? [];
        $resolved       = $this->resolve_url_target( $page_url, $post_index );
        $post_id        = (int) ( $resolved['post_id'] ?? 0 );
        $post_match     = $post_id > 0 ? ( $post_index[ $path_key ] ?? [] ) : [];
        $post_modified  = (string) ( $post_match['post_modified_gmt'] ?? '' );
        $quality_status = $this->derive_quality_status( $post_id, $page_url, $index_status_map, (string) ( $resolved['url_type'] ?? 'unknown' ) );
        $has_issue      = $this->is_quality_issue_status( $quality_status );
        $score          = $this->scorer->score( [
            'keyword'           => $keyword,
            'position'          => (float) ( $row['avg_position'] ?? 0 ),
            'impressions'       => (int) ( $row['impressions'] ?? 0 ),
            'ctr'               => (float) ( $row['ctr'] ?? 0 ),
            'search_volume'     => (int) ( $row['search_volume'] ?? 0 ),
            'competition'       => (string) ( $row['competition'] ?? 'UNKNOWN' ),
            'post_id'           => $post_id,
            'post_modified_gmt' => $post_modified,
            'has_quality_issue' => $has_issue,
        ] );

        $recommended_actions = $score['recommended_actions'];
        if ( ( $resolved['url_type'] ?? '' ) === 'category' ) {
            $recommended_actions = [ __( 'Kategori landing page güçlendir', 'hge' ) ];
        }

        return [
            'keyword'                  => $keyword,
            'page_url'                 => $page_url,
            'post_id'                  => $post_id > 0 ? $post_id : (int) ( $page_stat['post_id'] ?? 0 ),
            'clicks'                   => (int) ( $row['clicks'] ?? 0 ),
            'impressions'              => (int) ( $row['impressions'] ?? 0 ),
            'ctr'                      => (float) ( $row['ctr'] ?? 0 ),
            'position'                 => (float) ( $row['avg_position'] ?? 0 ),
            'search_volume'            => (int) ( $row['search_volume'] ?? 0 ),
            'competition'              => (string) ( $row['competition'] ?? 'UNKNOWN' ),
            'intent_score'             => (int) $score['intent_score'],
            'opportunity_score'        => (int) $score['opportunity_score'],
            'status'                   => (string) $score['status'],
            'quality_status'           => $quality_status,
            'recommended_actions_json' => wp_json_encode(
                [
                    'actions'  => $recommended_actions,
                    'url_type' => (string) ( $resolved['url_type'] ?? 'unknown' ),
                ],
                JSON_UNESCAPED_UNICODE
            ),
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
            $normalized_url = $this->normalize_page_url( get_permalink( $post_id ) );
            $path           = $this->normalize_url_path( $normalized_url );
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
            $path = $this->normalize_url_path( $this->normalize_page_url( (string) ( $row['page_url'] ?? '' ) ) );
            if ( $path !== '' ) {
                $indexed[ $path ] = $row;
            }
        }
        return $indexed;
    }

    private function normalize_index_status_map( array $rows ){
        $normalized = [];

        foreach ( $rows as $key => $row ) {
            if ( strpos( (string) $key, 'post:' ) === 0 ) {
                $normalized[ (string) $key ] = $row;
                continue;
            }

            $page_url        = $this->normalize_page_url( (string) ( $row['page_url'] ?? (string) $key ) );
            $row['page_url'] = $page_url;

            if ( ! empty( $row['user_canonical'] ) ) {
                $row['user_canonical'] = $this->normalize_page_url( (string) $row['user_canonical'] );
            }

            if ( ! empty( $row['google_canonical'] ) ) {
                $row['google_canonical'] = $this->normalize_page_url( (string) $row['google_canonical'] );
            }

            $normalized[ $page_url ] = $row;
        }

        return $normalized;
    }

    private function normalize_page_url( string $url ){
        $url = trim( wp_strip_all_tags( $url ) );
        if ( $url === '' ) {
            return 'https://hesaplamaa.com/';
        }

        $parts = wp_parse_url( $url );
        $host  = strtolower( preg_replace( '/^www\./i', '', (string) ( $parts['host'] ?? '' ) ) );
        $path  = (string) ( $parts['path'] ?? '/' );

        $path = '/' . ltrim( $path, '/' );
        $path = preg_replace( '#/+#', '/', $path );
        $path = $path === '/' ? '/' : trailingslashit( $path );

        if ( $host === '' || $this->is_noncanonical_host( $host ) ) {
            $host = 'hesaplamaa.com';
        }

        return 'https://' . $host . $path;
    }

    private function resolve_url_target( string $normalized_url, array $post_index = [] ){
        $path     = $this->normalize_url_path( $normalized_url );
        $url_type = $this->detect_url_type_from_path( $path );

        if ( $url_type !== 'content' ) {
            return [
                'post_id'  => 0,
                'url_type' => $url_type,
            ];
        }

        $post_id = url_to_postid( $normalized_url );
        if ( $post_id <= 0 && isset( $post_index[ $path ]['post_id'] ) ) {
            $post_id = (int) $post_index[ $path ]['post_id'];
        }

        if ( $post_id <= 0 ) {
            $slug = trim( (string) basename( untrailingslashit( $path ) ) );
            if ( $slug !== '' ) {
                $post = get_page_by_path( sanitize_title( $slug ), OBJECT, [ 'post', 'page' ] );
                if ( $post instanceof \WP_Post ) {
                    $post_id = (int) $post->ID;
                    $url_type = $post->post_type === 'page' ? 'page' : 'post';
                }
            }
        } else {
            $post_type = get_post_type( $post_id );
            $url_type  = $post_type === 'page' ? 'page' : 'post';
        }

        if ( $post_id <= 0 ) {
            $slug = trim( (string) basename( untrailingslashit( $path ) ) );
            if ( $slug !== '' ) {
                global $wpdb;

                $post_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT ID
                         FROM {$wpdb->posts}
                         WHERE post_name = %s
                           AND post_type IN ('post', 'page')
                           AND post_status IN ('publish', 'draft')
                         ORDER BY post_status = 'publish' DESC, ID DESC
                         LIMIT 1",
                        sanitize_title( $slug )
                    )
                );

                if ( $post_id > 0 ) {
                    $post_type = get_post_type( $post_id );
                    $url_type  = $post_type === 'page' ? 'page' : 'post';
                }
            }
        }

        return [
            'post_id'  => max( 0, (int) $post_id ),
            'url_type' => $post_id > 0 ? $url_type : 'unknown',
        ];
    }

    private function detect_url_type_from_path( string $path ){
        $trimmed = trim( $path, '/' );

        if ( $trimmed === '' ) {
            return 'home';
        }

        if ( preg_match( '#^(category|kategori)(/|$)#i', $trimmed ) ) {
            return 'category';
        }

        if ( preg_match( '#^tag(/|$)#i', $trimmed ) ) {
            return 'tag';
        }

        if ( preg_match( '#^author(/|$)#i', $trimmed ) ) {
            return 'author';
        }

        if ( preg_match( '#^(archive|archives|date)(/|$)#i', $trimmed ) ) {
            return 'archive';
        }

        if ( preg_match( '#^\d{4}(/\d{1,2}(/\d{1,2})?)?/?$#', $trimmed ) ) {
            return 'archive';
        }

        return 'content';
    }

    private function derive_quality_status( int $post_id, string $page_url, array $index_status_map, string $url_type = 'unknown' ){
        if ( $url_type === 'category' ) {
            return __( 'Kategori arşivi', 'hge' );
        }

        if ( in_array( $url_type, [ 'tag', 'author', 'archive' ], true ) ) {
            return __( 'Arşiv URL', 'hge' );
        }

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

    private function is_quality_issue_status( string $quality_status ){
        return ! in_array(
            $quality_status,
            [
                __( 'Kontrol bekliyor', 'hge' ),
                __( 'Sağlıklı', 'hge' ),
                __( 'Kategori arşivi', 'hge' ),
                __( 'Arşiv URL', 'hge' ),
                __( 'Kategori URL', 'hge' ),
            ],
            true
        );
    }

    private function normalize_url_path( string $url ){
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        if ( $path === '' ) {
            return '/';
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

    private function is_noncanonical_host( string $host ){
        $host = preg_replace( '/^www\./i', '', strtolower( $host ) );

        if ( $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' ) {
            return true;
        }

        if ( strpos( $host, 'sslip.io' ) !== false ) {
            return true;
        }

        if ( strpos( $host, 'wordpress-' ) === 0 ) {
            return true;
        }

        if ( strpos( $host, 'wordpress-qmqt6o2ml0b0hbwonxns7cfg' ) !== false ) {
            return true;
        }

        if ( preg_match( '/^\d{1,3}(\.\d{1,3}){3}$/', $host ) ) {
            return true;
        }

        return false;
    }

    private function pick_competition( string $left, string $right ){
        $priority = [
            'UNKNOWN' => 0,
            'LOW'     => 1,
            'MEDIUM'  => 2,
            'HIGH'    => 3,
        ];

        $left  = strtoupper( sanitize_text_field( $left ) );
        $right = strtoupper( sanitize_text_field( $right ) );

        return ( $priority[ $right ] ?? 0 ) >= ( $priority[ $left ] ?? 0 ) ? $right : $left;
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
