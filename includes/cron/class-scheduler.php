<?php
namespace HGE\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * Günlük ve haftalık otomatik senkronizasyon
 */
class Scheduler {

    public function register(){
        add_action( 'hge_daily_sync',         [ $this, 'run_daily_sync' ] );
        add_action( 'hge_index_status_sync',  [ $this, 'run_index_status_sync' ] );
        add_action( 'hge_weekly_suggestions', [ $this, 'run_weekly_suggestions' ] );
        add_action( 'init', [ $this, 'ensure_scheduled_events' ] );
    }

    public function ensure_scheduled_events(){
        if ( ! wp_next_scheduled( 'hge_daily_sync' ) ) {
            wp_schedule_event( time(), 'daily', 'hge_daily_sync' );
        }

        if ( ! wp_next_scheduled( 'hge_index_status_sync' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hge_index_status_sync' );
        }

        if ( ! wp_next_scheduled( 'hge_weekly_suggestions' ) ) {
            wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'weekly', 'hge_weekly_suggestions' );
        }
    }

    public function run_daily_sync(){
        $settings  = get_option( 'hge_settings', [] );
        $site_url  = $settings['gsc_site_url'] ?? get_site_url();
        $days      = (int) ( $settings['data_range_days'] ?? 30 );

        $gsc  = new \HGE\API\GSCClient();
        $repo = new \HGE\DB\Repository();
        $log  = [];

        if ( ! $gsc->is_connected() ) {
            $log[] = 'GSC bağlı değil, atlandı.';
            return $log;
        }

        $daily = $gsc->get_daily_stats( $site_url, $days );
        if ( ! is_wp_error( $daily ) ) {
            $count = 0;
            foreach ( $daily as $row ) {
                $keys = $row['keys'] ?? [];
                $repo->upsert_daily_stat( [
                    'stat_date'    => $keys[0] ?? gmdate( 'Y-m-d' ),
                    'clicks'       => $row['clicks']       ?? 0,
                    'impressions'  => $row['impressions']  ?? 0,
                    'ctr'          => $row['ctr']          ?? 0,
                    'avg_position' => $row['position']     ?? 0,
                ] );
                $count++;
            }
            $log[] = "Günlük istatistik: {$count} satır kaydedildi.";
        } else {
            $log[] = 'Günlük istatistik hatası: ' . $daily->get_error_message();
        }

        $kw_data = $gsc->get_search_analytics( $site_url, $days, [ 'query', 'page' ], 25000 );
        if ( ! is_wp_error( $kw_data ) ) {
            $count = 0;
            foreach ( $kw_data as $row ) {
                $keys    = $row['keys'] ?? [];
                $keyword = $keys[0] ?? '';
                $page    = $keys[1] ?? '';

                if ( empty( $keyword ) || empty( $page ) ) {
                    continue;
                }

                $repo->upsert_keyword( [
                    'keyword'      => $keyword,
                    'page_url'     => $page,
                    'impressions'  => $row['impressions'] ?? 0,
                    'clicks'       => $row['clicks']      ?? 0,
                    'ctr'          => $row['ctr']         ?? 0,
                    'avg_position' => $row['position']    ?? 0,
                ] );
                $count++;
            }

            $page_count = $this->sync_page_stats( $gsc, $repo, $site_url, $days, $kw_data, $log );
            $log[] = "Keyword: {$count} satır, {$page_count} sayfa kaydedildi.";
        } else {
            $log[] = 'Keyword hatası: ' . $kw_data->get_error_message();
        }

        $this->clear_caches();

        update_option( 'hge_last_sync', current_time( 'mysql' ) );
        $log[] = 'Senkronizasyon tamamlandı: ' . current_time( 'mysql' );

        return $log;
    }

    private function sync_page_stats( \HGE\API\GSCClient $gsc, \HGE\DB\Repository $repo, string $site_url, int $days, array $kw_data, array &$log ){
        $page_data     = $gsc->get_page_stats( $site_url, $days );
        $main_keywords = $this->extract_main_keywords( $kw_data );

        if ( ! is_wp_error( $page_data ) ) {
            $count = 0;
            foreach ( $page_data as $row ) {
                $keys = $row['keys'] ?? [];
                $page = $keys[0] ?? '';

                if ( empty( $page ) ) {
                    continue;
                }

                $impressions = (int) ( $row['impressions'] ?? 0 );
                $clicks      = (int) ( $row['clicks'] ?? 0 );

                $repo->upsert_page_stat( [
                    'page_url'     => $page,
                    'impressions'  => $impressions,
                    'clicks'       => $clicks,
                    'ctr'          => $impressions > 0 ? $clicks / $impressions : 0,
                    'avg_position' => $row['position'] ?? 0,
                    'main_keyword' => $main_keywords[ $page ] ?? '',
                ] );
                $count++;
            }

            return $count;
        }

        $log[] = 'Sayfa istatistiği hatası: ' . $page_data->get_error_message();
        return $this->sync_page_stats_from_keywords( $repo, $kw_data );
    }

    private function sync_page_stats_from_keywords( \HGE\DB\Repository $repo, array $kw_data ){
        $page_agg = [];

        foreach ( $kw_data as $row ) {
            $keys = $row['keys'] ?? [];
            $page = $keys[1] ?? '';

            if ( empty( $page ) ) {
                continue;
            }

            if ( ! isset( $page_agg[ $page ] ) ) {
                $page_agg[ $page ] = [
                    'impressions'     => 0,
                    'clicks'          => 0,
                    'position_sum'    => 0,
                    'position_weight' => 0,
                    'main_keyword'    => $keys[0] ?? '',
                ];
            }

            $impressions = (int) ( $row['impressions'] ?? 0 );
            $weight      = max( 1, $impressions );

            $page_agg[ $page ]['impressions']     += $impressions;
            $page_agg[ $page ]['clicks']          += (int) ( $row['clicks'] ?? 0 );
            $page_agg[ $page ]['position_sum']    += (float) ( $row['position'] ?? 0 ) * $weight;
            $page_agg[ $page ]['position_weight'] += $weight;
        }

        foreach ( $page_agg as $page => $agg ) {
            $impressions = (int) $agg['impressions'];
            $clicks      = (int) $agg['clicks'];
            $weight      = max( 1, (int) $agg['position_weight'] );

            $repo->upsert_page_stat( [
                'page_url'     => $page,
                'impressions'  => $impressions,
                'clicks'       => $clicks,
                'ctr'          => $impressions > 0 ? $clicks / $impressions : 0,
                'avg_position' => $agg['position_sum'] / $weight,
                'main_keyword' => $agg['main_keyword'],
            ] );
        }

        return count( $page_agg );
    }

    private function extract_main_keywords( array $kw_data ){
        $best = [];

        foreach ( $kw_data as $row ) {
            $keys    = $row['keys'] ?? [];
            $keyword = $keys[0] ?? '';
            $page    = $keys[1] ?? '';

            if ( empty( $keyword ) || empty( $page ) ) {
                continue;
            }

            $clicks      = (int) ( $row['clicks'] ?? 0 );
            $impressions = (int) ( $row['impressions'] ?? 0 );
            $score       = ( $clicks * 1000000 ) + $impressions;

            if ( ! isset( $best[ $page ] ) || $score > $best[ $page ]['score'] ) {
                $best[ $page ] = [
                    'keyword' => $keyword,
                    'score'   => $score,
                ];
            }
        }

        return array_map(
            static fn( $row ) => $row['keyword'],
            $best
        );
    }

    private function clear_caches(){
        delete_transient( 'hge_dashboard_summary' );
        foreach ( [ 7, 14, 30, 60, 90 ] as $days ) {
            delete_transient( "hge_daily_stats_{$days}" );
        }
        delete_transient( 'hge_opportunities_100' );
        delete_transient( 'hge_opportunities_200' );
        delete_transient( 'hge_opportunities_1000' );
    }

    public function run_weekly_suggestions(){
        $suggest = new \HGE\API\SuggestClient();
        $ideas   = new \HGE\Admin\NewIdeas();
        $result  = $ideas->fetch_and_store();
        return [ count( $result ) . ' öneri güncellendi.' ];
    }

    public function run_index_status_sync(){
        if ( ! ( new \HGE\API\GSCClient() )->is_connected() ) {
            return [ 'GSC bağlı değil, dizin durumu kontrolü atlandı.' ];
        }

        $index_status = new \HGE\Admin\IndexStatus();
        $result       = $index_status->inspect_pending( 25 );
        $checked      = $result['checked'] ?? [];
        $skipped      = (int) ( $result['skipped'] ?? 0 );
        update_option( 'hge_last_index_status_sync', current_time( 'mysql' ) );
        return [ count( $checked ) . ' URL dizin durumu kontrol edildi, ' . $skipped . ' URL atlandı.' ];
    }
}
