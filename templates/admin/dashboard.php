<?php defined( 'ABSPATH' ) || exit; ?>
<?php
// Yardımcı pozisyon sınıfı
function hge_pos_class( float $pos ): string {
    if ( $pos <= 3 )  return 'top3';
    if ( $pos <= 10 ) return 'top10';
    if ( $pos <= 20 ) return 'top20';
    return 'out';
}
?>
<div class="hge-wrap">

    <!-- HEADER -->
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Growth Dashboard', 'hge' ); ?></h1>
                <p class="hge-page-subtitle">
                    <?php if ( $last_sync ): ?>
                        <?php printf( esc_html__( 'Son güncelleme: %s', 'hge' ), esc_html( wp_date( 'd M Y, H:i', strtotime( $last_sync ) ) ) ); ?>
                    <?php else: ?>
                        <?php esc_html_e( 'Henüz senkronize edilmedi.', 'hge' ); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="hge-header-right">
            <?php if ( ! $gsc_connected ): ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=hge-settings' ) ); ?>" class="hge-btn hge-btn-warning">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php esc_html_e( 'GSC Bağlı Değil', 'hge' ); ?>
                </a>
            <?php else: ?>
                <span class="hge-badge hge-badge-success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php esc_html_e( 'GSC Bağlı', 'hge' ); ?>
                </span>
            <?php endif; ?>
            <button class="hge-btn hge-btn-primary" id="hge-sync-now">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <?php esc_html_e( 'Şimdi Senkronize Et', 'hge' ); ?>
            </button>
        </div>
    </div>

    <!-- KPI KARTLARI -->
    <div class="hge-kpi-grid">
        <?php
        $kpis = [
            [ 'label' => __( 'Takip Edilen Keyword', 'hge' ), 'value' => number_format( $summary['total_keywords'] ?? 0 ), 'icon' => '<path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>', 'color' => 'blue', 'sub' => '' ],
            [ 'label' => __( 'İlk 3 Sıra', 'hge' ), 'value' => number_format( $summary['top3_count'] ?? 0 ), 'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>', 'color' => 'gold', 'sub' => __( 'İlk 10: ', 'hge' ) . number_format( $summary['top10_count'] ?? 0 ) ],
            [ 'label' => __( 'Ort. Pozisyon', 'hge' ), 'value' => number_format( $summary['avg_position'] ?? 0, 1 ), 'icon' => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>', 'color' => 'purple', 'sub' => '' ],
            [ 'label' => __( 'Toplam Tıklama', 'hge' ), 'value' => number_format( $summary['total_clicks'] ?? 0 ), 'icon' => '<path d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/>', 'color' => 'green', 'sub' => '' ],
            [ 'label' => __( 'Toplam Gösterim', 'hge' ), 'value' => number_format( $summary['total_impressions'] ?? 0 ), 'icon' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>', 'color' => 'cyan', 'sub' => '' ],
            [ 'label' => __( 'Yükselen (30g)', 'hge' ), 'value' => number_format( $summary['rising_30d'] ?? 0 ), 'icon' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>', 'color' => 'emerald', 'sub' => '' ],
            [ 'label' => __( 'Düşen (30g)', 'hge' ), 'value' => number_format( $summary['falling_30d'] ?? 0 ), 'icon' => '<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>', 'color' => 'red', 'sub' => '' ],
            [ 'label' => __( 'Ort. CTR', 'hge' ), 'value' => ( ! empty( $summary['total_impressions'] ) ) ? number_format( ( $summary['total_clicks'] / $summary['total_impressions'] ) * 100, 2 ) . '%' : '—', 'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', 'color' => 'orange', 'sub' => '' ],
        ];
        foreach ( $kpis as $kpi ): ?>
        <div class="hge-kpi-card hge-kpi-<?php echo esc_attr( $kpi['color'] ); ?>">
            <div class="hge-kpi-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?php echo $kpi['icon']; // phpcs:ignore ?></svg>
            </div>
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php echo esc_html( $kpi['label'] ); ?></span>
                <span class="hge-kpi-value"><?php echo esc_html( $kpi['value'] ); ?></span>
                <?php if ( $kpi['sub'] ): ?><span class="hge-kpi-sub"><?php echo esc_html( $kpi['sub'] ); ?></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- GRAFİKLER -->
    <div class="hge-charts-grid">
        <div class="hge-card hge-chart-card">
            <div class="hge-card-header">
                <h3><?php esc_html_e( 'Günlük Tıklama & Gösterim', 'hge' ); ?></h3>
                <span class="hge-badge"><?php esc_html_e( 'Son 30 Gün', 'hge' ); ?></span>
            </div>
            <div class="hge-card-body"><canvas id="hge-clicks-chart" height="280"></canvas></div>
        </div>
        <div class="hge-card hge-chart-card">
            <div class="hge-card-header">
                <h3><?php esc_html_e( 'Ortalama Pozisyon Trendi', 'hge' ); ?></h3>
                <span class="hge-badge"><?php esc_html_e( 'Son 30 Gün', 'hge' ); ?></span>
            </div>
            <div class="hge-card-body"><canvas id="hge-position-chart" height="280"></canvas></div>
        </div>
    </div>

    <!-- TABLOLAR -->
    <div class="hge-tables-grid">
        <div class="hge-card">
            <div class="hge-card-header">
                <h3><?php esc_html_e( 'En Çok Tıklanan Kelimeler', 'hge' ); ?></h3>
            </div>
            <div class="hge-card-body hge-table-wrap">
                <table class="hge-table">
                    <thead><tr><th><?php esc_html_e( 'Keyword', 'hge' ); ?></th><th><?php esc_html_e( 'Tıklama', 'hge' ); ?></th><th><?php esc_html_e( 'Gösterim', 'hge' ); ?></th><th><?php esc_html_e( 'Pozisyon', 'hge' ); ?></th></tr></thead>
                    <tbody>
                    <?php if ( empty( $top_rising ) ): ?>
                        <tr><td colspan="4" class="hge-empty"><?php esc_html_e( 'GSC senkronizasyonu çalıştırın.', 'hge' ); ?></td></tr>
                    <?php else: foreach ( $top_rising as $row ): ?>
                        <tr>
                            <td><span class="hge-keyword-cell"><?php echo esc_html( $row['keyword'] ); ?></span></td>
                            <td><strong><?php echo esc_html( number_format( $row['clicks'] ) ); ?></strong></td>
                            <td><?php echo esc_html( number_format( $row['impressions'] ) ); ?></td>
                            <td><span class="hge-pos-badge hge-pos-<?php echo esc_attr( hge_pos_class( (float) $row['avg_position'] ) ); ?>"><?php echo esc_html( number_format( (float) $row['avg_position'], 1 ) ); ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hge-card">
            <div class="hge-card-header">
                <h3><?php esc_html_e( 'Yüksek Gösterim / Düşük CTR', 'hge' ); ?></h3>
                <span class="hge-badge hge-badge-warning"><?php esc_html_e( 'Fırsat!', 'hge' ); ?></span>
            </div>
            <div class="hge-card-body hge-table-wrap">
                <table class="hge-table">
                    <thead><tr><th><?php esc_html_e( 'Keyword', 'hge' ); ?></th><th><?php esc_html_e( 'Gösterim', 'hge' ); ?></th><th><?php esc_html_e( 'CTR', 'hge' ); ?></th><th><?php esc_html_e( 'Pozisyon', 'hge' ); ?></th></tr></thead>
                    <tbody>
                    <?php if ( empty( $low_ctr_pages ) ): ?>
                        <tr><td colspan="4" class="hge-empty"><?php esc_html_e( 'Veri bulunamadı.', 'hge' ); ?></td></tr>
                    <?php else: foreach ( $low_ctr_pages as $row ): ?>
                        <tr>
                            <td><span class="hge-keyword-cell"><?php echo esc_html( $row['keyword'] ); ?></span></td>
                            <td><?php echo esc_html( number_format( $row['impressions'] ) ); ?></td>
                            <td>
                                <span class="hge-ctr-bar">
                                    <span class="hge-ctr-fill" style="width:<?php echo esc_attr( min( 100, round( $row['ctr'] * 500 ) ) ); ?>%"></span>
                                    <span class="hge-ctr-label"><?php echo esc_html( number_format( $row['ctr'] * 100, 2 ) . '%' ); ?></span>
                                </span>
                            </td>
                            <td><span class="hge-pos-badge hge-pos-<?php echo esc_attr( hge_pos_class( (float) $row['avg_position'] ) ); ?>"><?php echo esc_html( number_format( (float) $row['avg_position'], 1 ) ); ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Chart.js için veri -->
    <script id="hge-chart-data" type="application/json">
    <?php
    $labels = $clicks_arr = $imps_arr = $pos_arr = [];
    foreach ( $daily_stats as $d ) {
        $labels[]    = $d['stat_date'];
        $clicks_arr[] = (int) $d['clicks'];
        $imps_arr[]   = (int) $d['impressions'];
        $pos_arr[]    = round( (float) $d['avg_position'], 2 );
    }
    echo wp_json_encode( [ 'labels' => $labels, 'clicks' => $clicks_arr, 'impressions' => $imps_arr, 'positions' => $pos_arr ] );
    ?>
    </script>

</div>
