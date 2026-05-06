<?php
namespace HGE\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Tüm DB okuma/yazma işlemleri bu sınıf üzerinden
 */
class Repository {

    private \wpdb $wpdb;

    // Tablo adları
    public string $keywords;
    public string $daily_stats;
    public string $page_stats;
    public string $suggestions;
    public string $ai_insights;
    public string $index_status;
    public string $keyword_volumes;

    public function __construct() {
        global $wpdb;
        $this->wpdb        = $wpdb;
        $this->keywords    = $wpdb->prefix . 'hge_keywords';
        $this->daily_stats = $wpdb->prefix . 'hge_daily_stats';
        $this->page_stats  = $wpdb->prefix . 'hge_page_stats';
        $this->suggestions = $wpdb->prefix . 'hge_suggestions';
        $this->ai_insights = $wpdb->prefix . 'hge_ai_insights';
        $this->index_status = $wpdb->prefix . 'hge_index_status';
        $this->keyword_volumes = $wpdb->prefix . 'hge_keyword_volumes';
    }

    // -------------------------------------------------------------------------
    // Dashboard summary
    // -------------------------------------------------------------------------

    public function get_dashboard_summary(){
        $cache_key = 'hge_dashboard_summary';
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $data = [
            'total_keywords'    => (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->keywords}" ),
            'top3_count'        => (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->keywords} WHERE avg_position <= 3" ),
            'top10_count'       => (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->keywords} WHERE avg_position <= 10" ),
            'avg_position'      => (float) $this->wpdb->get_var( "SELECT AVG(avg_position) FROM {$this->keywords}" ),
            'total_clicks'      => (int) $this->wpdb->get_var( "SELECT SUM(clicks) FROM {$this->keywords}" ),
            'total_impressions' => (int) $this->wpdb->get_var( "SELECT SUM(impressions) FROM {$this->keywords}" ),
            'rising_30d'        => 0, // cron ile güncellenir
            'falling_30d'       => 0,
        ];

        set_transient( $cache_key, $data, 3600 );
        return $data;
    }

    // -------------------------------------------------------------------------
    // Günlük istatistikler
    // -------------------------------------------------------------------------

    public function get_daily_stats( int $days = 30 ){
        $cache_key = "hge_daily_stats_{$days}";
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT stat_date, clicks, impressions, ctr, avg_position
                 FROM {$this->daily_stats}
                 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                 ORDER BY stat_date ASC",
                $days
            ),
            ARRAY_A
        );

        $data = $results ?: [];
        set_transient( $cache_key, $data, 3600 );
        return $data;
    }

    public function upsert_daily_stat( array $row ){
        $result = $this->wpdb->replace(
            $this->daily_stats,
            [
                'stat_date'    => sanitize_text_field( $row['stat_date'] ),
                'clicks'       => (int) $row['clicks'],
                'impressions'  => (int) $row['impressions'],
                'ctr'          => (float) $row['ctr'],
                'avg_position' => (float) $row['avg_position'],
            ],
            [ '%s', '%d', '%d', '%f', '%f' ]
        );
        return $result !== false;
    }

    // -------------------------------------------------------------------------
    // Keyword işlemleri
    // -------------------------------------------------------------------------

    public function upsert_keyword( array $row ){
        $existing_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->keywords} WHERE keyword = %s AND page_url = %s",
                $row['keyword'],
                $row['page_url']
            )
        );

        $data = [
            'keyword'          => sanitize_text_field( $row['keyword'] ),
            'page_url'         => esc_url_raw( $row['page_url'] ),
            'impressions'      => (int) $row['impressions'],
            'clicks'           => (int) $row['clicks'],
            'ctr'              => (float) $row['ctr'],
            'avg_position'     => (float) $row['avg_position'],
            'opportunity_score' => $this->calc_opportunity_score( $row ),
            'opportunity_type'  => sanitize_text_field( $row['opportunity_type'] ?? '' ),
            'last_updated'     => current_time( 'mysql' ),
        ];

        if ( $existing_id ) {
            return (bool) $this->wpdb->update( $this->keywords, $data, [ 'id' => $existing_id ] );
        }

        return (bool) $this->wpdb->insert( $this->keywords, $data );
    }

    public function get_opportunities( int $limit = 100 ){
        $cache_key = "hge_opportunities_{$limit}";
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->keywords}
                 ORDER BY opportunity_score DESC, impressions DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $data = $results ?: [];
        set_transient( $cache_key, $data, $this->get_cache_ttl() );
        return $data;
    }

    public function get_keyword_count(){
        return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->keywords}" );
    }

    public function get_top_rising( int $limit = 10 ){
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT keyword, page_url, clicks, impressions, avg_position, opportunity_score
                 FROM {$this->keywords}
                 ORDER BY clicks DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public function get_high_impression_low_ctr( int $limit = 10 ){
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT keyword, page_url, impressions, clicks, ctr, avg_position
                 FROM {$this->keywords}
                 WHERE impressions > 100 AND ctr < 0.03
                 ORDER BY impressions DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    // -------------------------------------------------------------------------
    // Sayfa istatistikleri
    // -------------------------------------------------------------------------

    public function upsert_page_stat( array $row ){
        $existing_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->page_stats} WHERE page_url = %s",
                $row['page_url']
            )
        );

        $data = [
            'page_url'      => esc_url_raw( $row['page_url'] ),
            'page_title'    => sanitize_text_field( $row['page_title'] ?? '' ),
            'impressions'   => (int) $row['impressions'],
            'clicks'        => (int) $row['clicks'],
            'ctr'           => (float) $row['ctr'],
            'avg_position'  => (float) $row['avg_position'],
            'main_keyword'  => sanitize_text_field( $row['main_keyword'] ?? '' ),
            'word_count'    => (int) ( $row['word_count'] ?? 0 ),
            'has_meta_desc' => (int) ( $row['has_meta_desc'] ?? 0 ),
            'internal_links' => (int) ( $row['internal_links'] ?? 0 ),
            'post_id'       => (int) ( $row['post_id'] ?? 0 ),
            'last_updated'  => current_time( 'mysql' ),
        ];

        if ( $existing_id ) {
            return (bool) $this->wpdb->update( $this->page_stats, $data, [ 'id' => $existing_id ] );
        }

        return (bool) $this->wpdb->insert( $this->page_stats, $data );
    }

    public function get_all_page_stats( int $limit = 1000 ){
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->page_stats}
                 ORDER BY impressions DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    // -------------------------------------------------------------------------
    // Suggestions
    // -------------------------------------------------------------------------

    public function save_suggestion( array $row ){
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->suggestions} WHERE topic = %s",
                $row['topic']
            )
        );
        if ( $exists ) {
            return (bool) $this->wpdb->update(
                $this->suggestions,
                [
                    'monthly_volume'    => (int) ( $row['monthly_volume'] ?? 0 ),
                    'competition'       => sanitize_text_field( $row['competition'] ?? 'unknown' ),
                    'opportunity_score' => (int) ( $row['opportunity_score'] ?? 0 ),
                    'exists_on_site'    => (int) ( $row['exists_on_site'] ?? 0 ),
                    'should_create'     => (int) ( $row['should_create'] ?? 0 ),
                    'source'            => sanitize_text_field( $row['source'] ?? 'suggest' ),
                ],
                [ 'id' => $exists ]
            );
        }

        return (bool) $this->wpdb->insert(
            $this->suggestions,
            [
                'topic'            => sanitize_text_field( $row['topic'] ),
                'monthly_volume'   => (int) ( $row['monthly_volume'] ?? 0 ),
                'competition'      => sanitize_text_field( $row['competition'] ?? 'unknown' ),
                'opportunity_score' => (int) ( $row['opportunity_score'] ?? 0 ),
                'exists_on_site'   => (int) ( $row['exists_on_site'] ?? 0 ),
                'should_create'    => (int) ( $row['should_create'] ?? 0 ),
                'source'           => sanitize_text_field( $row['source'] ?? 'suggest' ),
            ]
        );
    }

    public function delete_suggestions_by_source( string $source ){
        return (bool) $this->wpdb->delete(
            $this->suggestions,
            [ 'source' => sanitize_text_field( $source ) ],
            [ '%s' ]
        );
    }

    public function get_suggestions( int $limit = 100, string $preferred_source = '' ){
        $preferred_source = sanitize_text_field( $preferred_source );

        if ( $preferred_source !== '' ) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->suggestions}
                     ORDER BY
                        CASE WHEN source = %s THEN 0 ELSE 1 END ASC,
                        opportunity_score DESC,
                        monthly_volume DESC,
                        id DESC
                     LIMIT %d",
                    $preferred_source,
                    $limit
                ),
                ARRAY_A
            ) ?: [];
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->suggestions}
                 ORDER BY
                    CASE
                        WHEN source = 'ai_global' THEN 0
                        WHEN source = 'ai_topic' THEN 1
                        WHEN source = 'ai_seed_google_suggest' THEN 2
                        ELSE 3
                    END ASC,
                    opportunity_score DESC,
                    monthly_volume DESC,
                    id DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public function get_suggestion_archive( array $filters = [] ){
        $limit       = max( 20, min( 500, (int) ( $filters['limit'] ?? 300 ) ) );
        $search      = sanitize_text_field( $filters['search'] ?? '' );
        $source      = sanitize_text_field( $filters['source'] ?? '' );
        $competition = strtoupper( sanitize_text_field( $filters['competition'] ?? '' ) );
        $status      = sanitize_text_field( $filters['status'] ?? '' );
        $created     = sanitize_text_field( $filters['created'] ?? '' );

        $where  = [ '1=1' ];
        $params = [];

        if ( $search !== '' ) {
            $where[]  = 'topic LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like( $search ) . '%';
        }

        if ( $source !== '' ) {
            $where[]  = 'source = %s';
            $params[] = $source;
        }

        if ( in_array( $competition, [ 'LOW', 'MEDIUM', 'HIGH', 'UNKNOWN' ], true ) ) {
            $where[]  = 'UPPER(competition) = %s';
            $params[] = $competition;
        }

        if ( $status === 'missing' ) {
            $where[] = 'exists_on_site = 0';
        } elseif ( $status === 'existing' ) {
            $where[] = 'exists_on_site = 1';
        } elseif ( $status === 'should_create' ) {
            $where[] = 'should_create = 1';
        }

        if ( $created === 'today' ) {
            $where[] = 'DATE(created_at) = CURDATE()';
        }

        $sql = "SELECT * FROM {$this->suggestions}
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY opportunity_score DESC, monthly_volume DESC, id DESC
                LIMIT %d";
        $params[] = $limit;

        return $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, $params ),
            ARRAY_A
        ) ?: [];
    }

    public function get_suggestion_archive_summary(){
        $row = $this->wpdb->get_row(
            "SELECT
                COUNT(*) total,
                SUM(CASE WHEN exists_on_site = 0 THEN 1 ELSE 0 END) missing,
                SUM(CASE WHEN should_create = 1 THEN 1 ELSE 0 END) should_create,
                MAX(created_at) latest_created
             FROM {$this->suggestions}",
            ARRAY_A
        ) ?: [];

        $sources = $this->wpdb->get_results(
            "SELECT source, COUNT(*) count
             FROM {$this->suggestions}
             GROUP BY source
             ORDER BY count DESC, source ASC",
            ARRAY_A
        ) ?: [];

        return [
            'total'          => (int) ( $row['total'] ?? 0 ),
            'missing'        => (int) ( $row['missing'] ?? 0 ),
            'should_create'  => (int) ( $row['should_create'] ?? 0 ),
            'latest_created' => $row['latest_created'] ?? '',
            'sources'        => $sources,
        ];
    }

    public function get_ai_insight( string $keyword ){
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->ai_insights} WHERE keyword = %s LIMIT 1",
                $keyword
            ),
            ARRAY_A
        );

        if ( empty( $row ) || empty( $row['insight_json'] ) ) {
            return null;
        }

        $decoded = json_decode( (string) $row['insight_json'], true );
        if ( ! is_array( $decoded ) ) {
            return null;
        }

        $row['insight'] = $decoded;
        return $row;
    }

    // -------------------------------------------------------------------------
    // Keyword volume import
    // -------------------------------------------------------------------------

    public function get_existing_keyword_volume_map( array $keywords ){
        $keywords = array_values( array_filter( array_unique( array_map( [ $this, 'normalize_keyword' ], $keywords ) ) ) );
        if ( empty( $keywords ) ) {
            return [];
        }

        $hashes       = array_map( 'md5', $keywords );
        $placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
        $rows         = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT keyword, keyword_hash FROM {$this->keyword_volumes} WHERE keyword_hash IN ($placeholders)",
                $hashes
            ),
            ARRAY_A
        ) ?: [];

        $map = [];
        foreach ( $rows as $row ) {
            $map[ (string) $row['keyword_hash'] ] = (string) $row['keyword'];
        }

        return $map;
    }

    public function save_keyword_volume( array $row ){
        $keyword = $this->normalize_keyword( (string) ( $row['keyword'] ?? '' ) );
        if ( $keyword === '' ) {
            return false;
        }

        $hash = md5( $keyword );
        $data = [
            'keyword'        => $keyword,
            'keyword_hash'   => $hash,
            'monthly_volume' => max( 0, (int) ( $row['monthly_volume'] ?? 0 ) ),
            'competition'    => sanitize_text_field( strtoupper( (string) ( $row['competition'] ?? 'UNKNOWN' ) ) ),
            'status'         => sanitize_text_field( (string) ( $row['status'] ?? 'ready' ) ),
            'source_file'    => sanitize_file_name( (string) ( $row['source_file'] ?? '' ) ),
            'upload_batch'   => sanitize_text_field( (string) ( $row['upload_batch'] ?? '' ) ),
            'api_source'     => sanitize_text_field( (string) ( $row['api_source'] ?? 'google_ads' ) ),
            'updated_at'     => current_time( 'mysql' ),
        ];

        $existing_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->keyword_volumes} WHERE keyword_hash = %s",
                $hash
            )
        );

        if ( $existing_id ) {
            return (bool) $this->wpdb->update( $this->keyword_volumes, $data, [ 'id' => $existing_id ] );
        }

        $data['created_at'] = current_time( 'mysql' );
        return (bool) $this->wpdb->insert( $this->keyword_volumes, $data );
    }

    public function get_keyword_volumes( array $filters = [] ){
        $limit  = max( 20, min( 500, (int) ( $filters['limit'] ?? 200 ) ) );
        $search = sanitize_text_field( (string) ( $filters['search'] ?? '' ) );

        $where  = [ '1=1' ];
        $params = [];

        if ( $search !== '' ) {
            $where[]  = 'keyword LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like( $search ) . '%';
        }

        $sql = "SELECT *
                FROM {$this->keyword_volumes}
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY updated_at DESC, id DESC
                LIMIT %d";
        $params[] = $limit;

        return $this->wpdb->get_results(
            $this->wpdb->prepare( $sql, $params ),
            ARRAY_A
        ) ?: [];
    }

    public function get_keyword_volume_summary(){
        $row = $this->wpdb->get_row(
            "SELECT
                COUNT(*) total,
                SUM(monthly_volume) total_volume,
                SUM(CASE WHEN status = 'no_metrics' THEN 1 ELSE 0 END) missing_metrics,
                MAX(updated_at) latest_updated
             FROM {$this->keyword_volumes}",
            ARRAY_A
        ) ?: [];

        return [
            'total'           => (int) ( $row['total'] ?? 0 ),
            'total_volume'    => (int) ( $row['total_volume'] ?? 0 ),
            'missing_metrics' => (int) ( $row['missing_metrics'] ?? 0 ),
            'latest_updated'  => (string) ( $row['latest_updated'] ?? '' ),
        ];
    }

    public function save_ai_insight( string $keyword, string $model, array $insight, string $prompt_hash = '' ){
        $json = wp_json_encode( $insight, JSON_UNESCAPED_UNICODE );
        if ( ! $json ) {
            return false;
        }

        $existing_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->ai_insights} WHERE keyword = %s",
                $keyword
            )
        );

        $data = [
            'keyword'      => sanitize_text_field( $keyword ),
            'model'        => sanitize_text_field( $model ),
            'insight_json' => $json,
            'prompt_hash'  => sanitize_text_field( $prompt_hash ),
            'updated_at'   => current_time( 'mysql' ),
        ];

        if ( $existing_id ) {
            return (bool) $this->wpdb->update( $this->ai_insights, $data, [ 'id' => $existing_id ] );
        }

        $data['created_at'] = current_time( 'mysql' );
        return (bool) $this->wpdb->insert( $this->ai_insights, $data );
    }

    // -------------------------------------------------------------------------
    // Dizin durumu
    // -------------------------------------------------------------------------

    public function upsert_index_status( array $row ){
        $url      = esc_url_raw( $row['page_url'] ?? '' );
        $post_id  = (int) ( $row['post_id'] ?? 0 );
        $url_hash = md5( $url );
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->index_status} WHERE url_hash = %s",
                $url_hash
            )
        );

        if ( ! $existing && $post_id > 0 ) {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->index_status} WHERE post_id = %d",
                    $post_id
                )
            );
        }

        $data = [
            'url_hash'         => $url_hash,
            'page_url'         => $url,
            'page_title'       => sanitize_text_field( $row['page_title'] ?? '' ),
            'post_id'          => $post_id,
            'verdict'          => sanitize_text_field( $row['verdict'] ?? '' ),
            'coverage_state'   => sanitize_text_field( $row['coverage_state'] ?? '' ),
            'robots_txt_state' => sanitize_text_field( $row['robots_txt_state'] ?? '' ),
            'indexing_state'   => sanitize_text_field( $row['indexing_state'] ?? '' ),
            'page_fetch_state' => sanitize_text_field( $row['page_fetch_state'] ?? '' ),
            'google_canonical' => esc_url_raw( $row['google_canonical'] ?? '' ),
            'user_canonical'   => esc_url_raw( $row['user_canonical'] ?? '' ),
            'crawled_as'       => sanitize_text_field( $row['crawled_as'] ?? '' ),
            'last_crawl_time'  => $this->mysql_datetime_or_null( $row['last_crawl_time'] ?? '' ),
            'inspection_link'  => esc_url_raw( $row['inspection_link'] ?? '' ),
            'error_message'    => sanitize_textarea_field( $row['error_message'] ?? '' ),
            'last_checked'     => current_time( 'mysql' ),
        ];

        if ( $existing ) {
            return (bool) $this->wpdb->update( $this->index_status, $data, [ 'id' => $existing ] );
        }

        $data['created_at'] = current_time( 'mysql' );
        return (bool) $this->wpdb->insert( $this->index_status, $data );
    }

    public function queue_index_status_url( string $url, string $title = '', int $post_id = 0 ){
        $url = esc_url_raw( $url );
        if ( empty( $url ) ) {
            return false;
        }

        $url_hash = md5( $url );
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->index_status} WHERE url_hash = %s",
                $url_hash
            )
        );

        if ( ! $existing && $post_id > 0 ) {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->index_status} WHERE post_id = %d",
                    $post_id
                )
            );
        }

        if ( $existing ) {
            return (bool) $this->wpdb->update(
                $this->index_status,
                [
                    'url_hash'         => $url_hash,
                    'page_url'         => $url,
                    'page_title'       => sanitize_text_field( $title ),
                    'post_id'          => (int) $post_id,
                    'verdict'          => '',
                    'coverage_state'   => '',
                    'error_message'    => '',
                    'last_checked'     => null,
                ],
                [ 'id' => $existing ]
            );
        }

        return (bool) $this->wpdb->insert(
            $this->index_status,
            [
                'url_hash'     => $url_hash,
                'page_url'     => $url,
                'page_title'   => sanitize_text_field( $title ),
                'post_id'      => (int) $post_id,
                'last_checked' => null,
                'created_at'   => current_time( 'mysql' ),
            ]
        );
    }

    public function get_index_status_map(){
        $rows = $this->wpdb->get_results(
            "SELECT * FROM {$this->index_status}",
            ARRAY_A
        ) ?: [];

        $map = [];
        foreach ( $rows as $row ) {
            $map[ $row['page_url'] ] = $row;
            if ( ! empty( $row['post_id'] ) ) {
                $map[ 'post:' . (int) $row['post_id'] ] = $row;
            }
        }
        return $map;
    }

    private function mysql_datetime_or_null( string $value ){
        if ( empty( $value ) ) {
            return null;
        }

        $timestamp = strtotime( $value );
        return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
    }

    private function normalize_keyword( string $keyword ){
        $keyword = sanitize_text_field( $keyword );
        $keyword = preg_replace( '/\s+/u', ' ', trim( $keyword ) );

        if ( function_exists( 'mb_strtolower' ) ) {
            return mb_strtolower( $keyword, 'UTF-8' );
        }

        return strtolower( $keyword );
    }

    // -------------------------------------------------------------------------
    // Yardımcı
    // -------------------------------------------------------------------------

    private function calc_opportunity_score( array $row ){
        $score    = 0;
        $position = (float) ( $row['avg_position'] ?? 99 );
        $ctr      = (float) ( $row['ctr'] ?? 0 );
        $imp      = (int) ( $row['impressions'] ?? 0 );

        // Pozisyon 4-10 → en yüksek fırsat
        if ( $position >= 4 && $position <= 10 ) {
            $score += 50;
        } elseif ( $position > 10 && $position <= 20 ) {
            $score += 30;
        } elseif ( $position > 20 && $position <= 30 ) {
            $score += 15;
        }

        // Yüksek gösterim → değerli
        if ( $imp >= 1000 ) {
            $score += 30;
        } elseif ( $imp >= 500 ) {
            $score += 20;
        } elseif ( $imp >= 100 ) {
            $score += 10;
        }

        // Düşük CTR → iyileştirme potansiyeli
        if ( $ctr < 0.02 ) {
            $score += 20;
        } elseif ( $ctr < 0.05 ) {
            $score += 10;
        }

        return min( 100, $score );
    }

    private function get_cache_ttl(){
        $settings = get_option( 'hge_settings', [] );
        return max( 60, (int) ( $settings['cache_ttl'] ?? 3600 ) );
    }
}
