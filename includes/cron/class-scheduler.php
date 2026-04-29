<?php
namespace HGE\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * Günlük ve haftalık otomatik senkronizasyon
 */
class Scheduler {

    public function register(){
        add_action( 'hge_daily_sync',        [ $this, 'run_daily_sync' ] );
        add_action( 'hge_weekly_suggestions', [ $this, 'run_weekly_suggestions' ] );
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

        // 1. Günlük aggregate istatistikler
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

        // 2. Keyword + sayfa performansı
        $kw_data = $gsc->get_search_analytics( $site_url, $days, [ 'query', 'page' ], 2000 );
        if ( ! is_wp_error( $kw_data ) ) {
            $count = 0;
            // Sayfa bazlı agregasyon
            $page_agg = [];
            foreach ( $kw_data as $row ) {
                $keys    = $row['keys'] ?? [];
                $keyword = $keys[0] ?? '';
                $page    = $keys[1] ?? '';

                $repo->upsert_keyword( [
                    'keyword'      => $keyword,
                    'page_url'     => $page,
                    'impressions'  => $row['impressions'] ?? 0,
                    'clicks'       => $row['clicks']      ?? 0,
                    'ctr'          => $row['ctr']          ?? 0,
                    'avg_position' => $row['position']     ?? 0,
                ] );

                // Sayfa toplamlarını agrege et
                if ( ! isset( $page_agg[ $page ] ) ) {
                    $page_agg[ $page ] = [
                        'page_url'     => $page,
                        'impressions'  => 0,
                        'clicks'       => 0,
                        'ctr_sum'      => 0,
                        'pos_sum'      => 0,
                        'row_count'    => 0,
                        'main_keyword' => $keyword,
                    ];
                }
                $page_agg[ $page ]['impressions'] += $row['impressions'] ?? 0;
                $page_agg[ $page ]['clicks']      += $row['clicks']      ?? 0;
                $page_agg[ $page ]['ctr_sum']     += $row['ctr']         ?? 0;
                $page_agg[ $page ]['pos_sum']     += $row['position']    ?? 0;
                $page_agg[ $page ]['row_count']   += 1;
                $count++;
            }

            // Sayfa istatistiklerini kaydet
            foreach ( $page_agg as $page => $agg ) {
                $cnt = max( 1, $agg['row_count'] );
                $repo->upsert_page_stat( [
                    'page_url'     => $page,
                    'impressions'  => $agg['impressions'],
                    'clicks'       => $agg['clicks'],
                    'ctr'          => $agg['ctr_sum'] / $cnt,
                    'avg_position' => $agg['pos_sum'] / $cnt,
                    'main_keyword' => $agg['main_keyword'],
                ] );
            }

            $log[] = "Keyword: {$count} satır, " . count( $page_agg ) . " sayfa kaydedildi.";
        } else {
            $log[] = 'Keyword hatası: ' . $kw_data->get_error_message();
        }

        // 3. Cache temizle
        delete_transient( 'hge_dashboard_summary' );
        delete_transient( 'hge_daily_stats_30' );
        delete_transient( 'hge_opportunities_100' );
        delete_transient( 'hge_opportunities_200' );

        update_option( 'hge_last_sync', current_time( 'mysql' ) );
        $log[] = 'Senkronizasyon tamamlandı: ' . current_time( 'mysql' );

        return $log;
    }

    public function run_weekly_suggestions(){
        $suggest = new \HGE\API\SuggestClient();
        $ideas   = new \HGE\Admin\NewIdeas();
        $result  = $ideas->fetch_and_store();
        return [ count( $result ) . ' öneri güncellendi.' ];
    }
}
