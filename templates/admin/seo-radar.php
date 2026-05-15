<?php defined( 'ABSPATH' ) || exit; ?>
<?php
$items      = $list['items'] ?? [];
$pagination = $list['pagination'] ?? [ 'total' => 0, 'pages' => 1, 'paged' => 1, 'limit' => 50 ];
$views      = [
    'quick-wins' => __( 'Hızlı Kazanımlar', 'hge' ),
    'keywords'   => __( 'Anahtar Kelime Fırsatları', 'hge' ),
    'pages'      => __( 'Sayfa Fırsatları', 'hge' ),
    'quality'    => __( 'Kalite Sorunları', 'hge' ),
];
$current_view = $filters['view'] ?? 'quick-wins';
$base_url     = admin_url( 'admin.php?page=hge-seo-radar' );
?>
<div class="hge-wrap hge-seo-radar-page">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'SEO Radar', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php esc_html_e( 'Mevcut HGE verilerinden fırsat, kalite ve öneri görünümü', 'hge' ); ?></p>
            </div>
        </div>
        <div class="hge-header-right">
            <button class="hge-btn hge-btn-primary" id="hge-seo-radar-refresh" type="button"><?php esc_html_e( 'Fırsatları Hesapla', 'hge' ); ?></button>
            <a class="hge-btn hge-btn-secondary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'CSV Dışa Aktar', 'hge' ); ?></a>
        </div>
    </div>

    <div class="hge-kpi-grid">
        <div class="hge-kpi-card hge-kpi-blue">
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php esc_html_e( 'Toplam Fırsat', 'hge' ); ?></span>
                <span class="hge-kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $summary['total'] ?? 0 ) ) ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-green">
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php esc_html_e( 'Hızlı Kazanım', 'hge' ); ?></span>
                <span class="hge-kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $summary['quick_wins'] ?? 0 ) ) ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-gold">
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php esc_html_e( 'İlk 10’a Yakın', 'hge' ); ?></span>
                <span class="hge-kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $summary['near_top10'] ?? 0 ) ) ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-red">
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php esc_html_e( 'Kalite Sorunu', 'hge' ); ?></span>
                <span class="hge-kpi-value"><?php echo esc_html( number_format_i18n( (int) ( $summary['quality_issues'] ?? 0 ) ) ); ?></span>
            </div>
        </div>
    </div>

    <div class="hge-radar-tabs">
        <?php foreach ( $views as $view_key => $label ): ?>
            <a class="hge-radar-tab<?php echo $current_view === $view_key ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'hge_radar_view', $view_key, $base_url ) ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
    </div>

    <form class="hge-card hge-radar-filters" method="get">
        <input type="hidden" name="page" value="hge-seo-radar">
        <input type="hidden" name="hge_radar_view" value="<?php echo esc_attr( $current_view ); ?>">
        <div class="hge-card-body">
            <div class="hge-radar-filter-grid">
                <input class="hge-input" type="search" name="hge_search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Anahtar kelime veya URL ara', 'hge' ); ?>">
                <select class="hge-select" name="hge_days">
                    <?php foreach ( [ 7, 28, 90 ] as $days ): ?>
                        <option value="<?php echo esc_attr( $days ); ?>" <?php selected( (int) $filters['days'], $days ); ?>><?php echo esc_html( sprintf( __( 'Son %d gün', 'hge' ), $days ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="hge-select" name="hge_position_band">
                    <option value=""><?php esc_html_e( 'Tüm pozisyonlar', 'hge' ); ?></option>
                    <option value="1-3" <?php selected( $filters['position_band'], '1-3' ); ?>>1-3</option>
                    <option value="4-10" <?php selected( $filters['position_band'], '4-10' ); ?>>4-10</option>
                    <option value="11-20" <?php selected( $filters['position_band'], '11-20' ); ?>>11-20</option>
                    <option value="21-50" <?php selected( $filters['position_band'], '21-50' ); ?>>21-50</option>
                    <option value="50+" <?php selected( $filters['position_band'], '50+' ); ?>>50+</option>
                </select>
                <select class="hge-select" name="hge_limit">
                    <?php foreach ( [ 25, 50, 100 ] as $limit ): ?>
                        <option value="<?php echo esc_attr( $limit ); ?>" <?php selected( (int) $filters['limit'], $limit ); ?>><?php echo esc_html( sprintf( __( '%d satır', 'hge' ), $limit ) ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="hge-radar-checks">
                <label><input type="checkbox" name="hge_low_ctr" value="1" <?php checked( ! empty( $filters['low_ctr_only'] ) ); ?>> <?php esc_html_e( 'Düşük CTR', 'hge' ); ?></label>
                <label><input type="checkbox" name="hge_high_impressions" value="1" <?php checked( ! empty( $filters['high_impressions_only'] ) ); ?>> <?php esc_html_e( 'Yüksek impression', 'hge' ); ?></label>
                <label><input type="checkbox" name="hge_high_volume" value="1" <?php checked( ! empty( $filters['high_volume_only'] ) ); ?>> <?php esc_html_e( 'Yüksek hacim', 'hge' ); ?></label>
                <label><input type="checkbox" name="hge_low_competition" value="1" <?php checked( ! empty( $filters['low_competition_only'] ) ); ?>> <?php esc_html_e( 'Düşük/orta competition', 'hge' ); ?></label>
                <label><input type="checkbox" name="hge_intent" value="1" <?php checked( ! empty( $filters['intent_only'] ) ); ?>> <?php esc_html_e( 'Hesaplama niyeti', 'hge' ); ?></label>
                <label><input type="checkbox" name="hge_quality" value="1" <?php checked( ! empty( $filters['quality_only'] ) ); ?>> <?php esc_html_e( 'Kalite sorunu olanlar', 'hge' ); ?></label>
            </div>
            <div class="hge-form-actions">
                <button class="hge-btn hge-btn-secondary" type="submit"><?php esc_html_e( 'Filtrele', 'hge' ); ?></button>
            </div>
        </div>
    </form>

    <div class="hge-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'Radar Sonuçları', 'hge' ); ?></h3>
            <span class="hge-page-subtitle" id="hge-seo-radar-status"><?php echo esc_html( sprintf( __( '%1$d sonuç', 'hge' ), (int) ( $pagination['total'] ?? 0 ) ) ); ?></span>
        </div>
        <div class="hge-card-body hge-table-wrap">
            <?php if ( empty( $items ) ): ?>
                <div class="hge-empty-state">
                    <p><?php esc_html_e( 'Henüz radar verisi yok. “Fırsatları Hesapla” ile analiz tablosunu üretin.', 'hge' ); ?></p>
                </div>
            <?php else: ?>
                <table class="hge-table hge-table-sortable hge-radar-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Anahtar Kelime', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Hedef URL', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Post ID', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Clicks', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Impressions', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'CTR', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Ortalama Pozisyon', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Aylık Arama Hacmi', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Competition', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Fırsat Puanı', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Durum', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Kalite Sinyali', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Önerilen Aksiyonlar', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'İşlemler', 'hge' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $items as $row ): ?>
                            <tr class="hge-radar-row" data-radar-id="<?php echo esc_attr( $row['id'] ); ?>" data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>" data-page-url="<?php echo esc_attr( $row['page_url'] ); ?>">
                                <td><span class="hge-keyword-cell"><?php echo esc_html( $row['keyword'] ); ?></span></td>
                                <td><a class="hge-url-link" href="<?php echo esc_url( $row['page_url'] ); ?>" target="_blank"><?php echo esc_html( wp_parse_url( $row['page_url'], PHP_URL_PATH ) ?: $row['page_url'] ); ?></a></td>
                                <td><?php echo esc_html( (int) $row['post_id'] ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( (int) $row['clicks'] ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( (int) $row['impressions'] ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( (float) $row['ctr'] * 100, 2 ) . '%' ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( (float) $row['position'], 1 ) ); ?></td>
                                <td><?php echo esc_html( number_format_i18n( (int) $row['search_volume'] ) ); ?></td>
                                <td><?php echo esc_html( $row['competition'] ?: 'UNKNOWN' ); ?></td>
                                <td><strong><?php echo esc_html( (int) $row['opportunity_score'] ); ?></strong></td>
                                <td><span class="hge-suggestion-pill"><?php echo esc_html( $row['status'] ); ?></span></td>
                                <td class="hge-radar-quality-text"><?php echo esc_html( $row['quality_status'] ?: __( 'Kontrol bekliyor', 'hge' ) ); ?></td>
                                <td><?php echo esc_html( implode( ', ', $row['recommended_actions'] ?? [] ) ); ?></td>
                                <td>
                                    <div class="hge-radar-actions">
                                        <button class="hge-btn hge-btn-secondary hge-btn-sm hge-radar-quality-check" type="button"><?php esc_html_e( 'Kalite Kontrol Et', 'hge' ); ?></button>
                                        <button class="hge-btn hge-btn-secondary hge-btn-sm hge-radar-ai-suggest" type="button"><?php esc_html_e( 'AI Öneri Üret', 'hge' ); ?></button>
                                        <button class="hge-btn hge-btn-secondary hge-btn-sm hge-radar-csv-toggle" type="button"><?php esc_html_e( 'CSV’ye Dahil', 'hge' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="hge-radar-pagination">
                    <span><?php echo esc_html( sprintf( __( 'Sayfa %1$d / %2$d', 'hge' ), (int) $pagination['paged'], max( 1, (int) $pagination['pages'] ) ) ); ?></span>
                    <div class="hge-radar-pagination-links">
                        <?php if ( (int) $pagination['paged'] > 1 ): ?>
                            <a class="hge-btn hge-btn-secondary" href="<?php echo esc_url( add_query_arg( 'paged', (int) $pagination['paged'] - 1, $_SERVER['REQUEST_URI'] ?? $base_url ) ); ?>"><?php esc_html_e( 'Önceki', 'hge' ); ?></a>
                        <?php endif; ?>
                        <?php if ( (int) $pagination['paged'] < (int) $pagination['pages'] ): ?>
                            <a class="hge-btn hge-btn-secondary" href="<?php echo esc_url( add_query_arg( 'paged', (int) $pagination['paged'] + 1, $_SERVER['REQUEST_URI'] ?? $base_url ) ); ?>"><?php esc_html_e( 'Sonraki', 'hge' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="hge-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'Satır Detayı', 'hge' ); ?></h3>
        </div>
        <div class="hge-card-body">
            <div id="hge-seo-radar-detail-empty"><?php esc_html_e( 'AI önerisi veya kalite kontrol çıktısı burada gösterilir.', 'hge' ); ?></div>
            <div id="hge-seo-radar-ai-result" hidden>
                <div class="hge-ai-meta-box">
                    <span><?php esc_html_e( 'Title Önerisi', 'hge' ); ?></span>
                    <strong id="hge-radar-ai-title">-</strong>
                    <span><?php esc_html_e( 'Meta Description', 'hge' ); ?></span>
                    <p id="hge-radar-ai-meta">-</p>
                    <span><?php esc_html_e( 'İlk Paragraf Önerisi', 'hge' ); ?></span>
                    <p id="hge-radar-ai-intro">-</p>
                </div>
                <div class="hge-radar-detail-grid">
                    <div>
                        <h4><?php esc_html_e( 'SSS Önerileri', 'hge' ); ?></h4>
                        <ul id="hge-radar-ai-faqs"></ul>
                    </div>
                    <div>
                        <h4><?php esc_html_e( 'İç Link Anchor Önerileri', 'hge' ); ?></h4>
                        <ul id="hge-radar-ai-anchors"></ul>
                    </div>
                </div>
            </div>
            <div id="hge-seo-radar-quality-result" hidden>
                <h4><?php esc_html_e( 'Kalite Kontrol Sonucu', 'hge' ); ?></h4>
                <p id="hge-radar-quality-summary">-</p>
                <ul id="hge-radar-quality-signals"></ul>
            </div>
        </div>
    </div>
</div>
