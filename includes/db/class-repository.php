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

    public function __construct() {
        global $wpdb;
        $this->wpdb        = $wpdb;
        $this->keywords    = $wpdb->prefix . 'hge_keywords';
        $this->daily_stats = $wpdb->prefix . 'hge_daily_stats';
        $this->page_stats  = $wpdb->prefix . 'hge_page_stats';
        $this->suggestions = $wpdb->prefix . 'hge_suggestions';
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
                 WHERE avg_position BETWEEN 4 AND 30
                 ORDER BY opportunity_score DESC, impressions DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $data = $results ?: [];
        set_transient( $cache_key, $data, 3600 );
        return $data;
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

    public function get_all_page_stats( int $limit = 200 ){
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
            return true; // zaten var
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

    public function get_suggestions( int $limit = 100 ){
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->suggestions}
                 ORDER BY opportunity_score DESC, monthly_volume DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
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
}
