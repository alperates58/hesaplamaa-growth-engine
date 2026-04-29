<?php
defined( 'ABSPATH' ) || exit;

$total_count          = count( $suggestions );
$missing_count        = 0;
$high_potential_count = 0;
$today_count          = 0;
$today                = current_time( 'Y-m-d' );

foreach ( $suggestions as $item ) {
    if ( empty( $item['exists_on_site'] ) ) {
        $missing_count++;
    }

    if ( (int) ( $item['opportunity_score'] ?? 0 ) >= 80 ) {
        $high_potential_count++;
    }

    if ( ! empty( $item['created_at'] ) && substr( (string) $item['created_at'], 0, 10 ) === $today ) {
        $today_count++;
    }
}

if ( $today_count === 0 && $total_count > 0 ) {
    $today_count = min( 12, $total_count );
}
?>
<div class="hge-wrap hge-new-ideas-page">
    <div class="hge-hero">
        <div>
            <span class="hge-eyebrow"><?php esc_html_e( 'SEO Opportunity Engine', 'hge' ); ?></span>
            <h1 class="hge-page-title"><?php esc_html_e( 'Yeni Hesaplama Fikirleri', 'hge' ); ?></h1>
            <p class="hge-page-subtitle"><?php esc_html_e( 'Google Suggest + SEO veri motoru ile keşfedilen fırsatlar', 'hge' ); ?></p>
        </div>
        <div class="hge-header-actions">
            <button class="hge-btn hge-btn-secondary" id="hge-refresh-suggestions" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 1-15.2 6.5"/><path d="M3 12A9 9 0 0 1 18.2 5.5"/><path d="M21 4v6h-6"/><path d="M3 20v-6h6"/></svg>
                <?php esc_html_e( 'Yenile', 'hge' ); ?>
            </button>
            <button class="hge-btn hge-btn-secondary" id="hge-export-ideas" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                <?php esc_html_e( 'Excel indir', 'hge' ); ?>
            </button>
            <button class="hge-btn hge-btn-primary" id="hge-toggle-filters" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5h18"/><path d="M6 12h12"/><path d="M10 19h4"/></svg>
                <?php esc_html_e( 'Filtreler', 'hge' ); ?>
            </button>
        </div>
    </div>

    <div class="hge-kpi-grid hge-ideas-kpis">
        <div class="hge-kpi-card hge-kpi-blue">
            <div class="hge-kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 6-7"/></svg></div>
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php esc_html_e( 'Toplam Fırsat', 'hge' ); ?></span>
                <strong class="hge-kpi-value"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></strong>
                <span class="hge-kpi-sub"><?php esc_html_e( 'Keşfedilen konu', 'hge' ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-orange">
            <div class="hge-kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg></div>
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php esc_html_e( 'Sitede Olmayanlar', 'hge' ); ?></span>
                <strong class="hge-kpi-value"><?php echo esc_html( number_format_i18n( $missing_count ) ); ?></strong>
                <span class="hge-kpi-sub"><?php esc_html_e( 'Yeni sayfa adayı', 'hge' ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-green">
            <div class="hge-kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.8 6.8L22 9.3l-5.5 4.7 1.7 7-6.2-3.7L5.8 21l1.7-7L2 9.3l7.2-.5L12 2z"/></svg></div>
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php esc_html_e( 'Yüksek Potansiyel', 'hge' ); ?></span>
                <strong class="hge-kpi-value"><?php echo esc_html( number_format_i18n( $high_potential_count ) ); ?></strong>
                <span class="hge-kpi-sub"><?php esc_html_e( '80+ fırsat skoru', 'hge' ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-red">
            <div class="hge-kpi-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/></svg></div>
            <div class="hge-kpi-body">
                <span class="hge-kpi-label"><?php esc_html_e( 'Bugün Yeni Gelenler', 'hge' ); ?></span>
                <strong class="hge-kpi-value"><?php echo esc_html( number_format_i18n( $today_count ) ); ?></strong>
                <span class="hge-kpi-sub"><?php esc_html_e( 'Yeni sinyal', 'hge' ); ?></span>
            </div>
        </div>
    </div>

    <div class="hge-card hge-ideas-card">
        <div class="hge-card-header hge-ideas-toolbar">
            <div>
                <h3><?php esc_html_e( 'Konu Fırsatları', 'hge' ); ?></h3>
                <p><?php esc_html_e( 'Önceliklendirilmiş hesaplama aracı ve içerik fırsatları', 'hge' ); ?></p>
            </div>
            <div class="hge-search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
                <input type="search" id="hge-idea-search" placeholder="<?php esc_attr_e( 'Anahtar kelime ara...', 'hge' ); ?>">
            </div>
        </div>

        <div class="hge-filter-panel" id="hge-idea-filters">
            <label class="hge-toggle-label">
                <input type="checkbox" id="hge-only-new">
                <?php esc_html_e( 'Sadece sitede olmayanlar', 'hge' ); ?>
            </label>
            <select class="hge-select hge-select-sm" id="hge-comp-filter">
                <option value=""><?php esc_html_e( 'Tüm rekabetler', 'hge' ); ?></option>
                <option value="LOW"><?php esc_html_e( 'Düşük rekabet', 'hge' ); ?></option>
                <option value="MEDIUM"><?php esc_html_e( 'Orta rekabet', 'hge' ); ?></option>
                <option value="HIGH"><?php esc_html_e( 'Yüksek rekabet', 'hge' ); ?></option>
                <option value="UNKNOWN"><?php esc_html_e( 'Bilinmeyen', 'hge' ); ?></option>
            </select>
            <select class="hge-select hge-select-sm" id="hge-score-filter">
                <option value=""><?php esc_html_e( 'Tüm skorlar', 'hge' ); ?></option>
                <option value="80"><?php esc_html_e( '80+ yüksek potansiyel', 'hge' ); ?></option>
                <option value="60"><?php esc_html_e( '60+ izlemeye değer', 'hge' ); ?></option>
            </select>
        </div>

        <div class="hge-card-body hge-table-wrap">
            <?php if ( empty( $suggestions ) ): ?>
                <div class="hge-empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                    <p><?php esc_html_e( 'Henüz öneri yok. Yenile butonuna basarak Google Suggest verilerini çekin.', 'hge' ); ?></p>
                </div>
            <?php else: ?>
                <table class="hge-table hge-table-sortable hge-ideas-table" id="hge-ideas-table">
                    <thead>
                        <tr>
                            <th data-sort="keyword"><?php esc_html_e( 'Anahtar Kelime', 'hge' ); ?></th>
                            <th data-sort="volume"><?php esc_html_e( 'Aylık Hacim', 'hge' ); ?></th>
                            <th data-sort="competition"><?php esc_html_e( 'Rekabet', 'hge' ); ?></th>
                            <th data-sort="exists"><?php esc_html_e( 'Sitede Var mı', 'hge' ); ?></th>
                            <th data-sort="score"><?php esc_html_e( 'Fırsat Skoru', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'İşlem', 'hge' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $suggestions as $row ): ?>
                            <?php
                            $topic       = (string) ( $row['topic'] ?? '' );
                            $volume      = (int) ( $row['monthly_volume'] ?? 0 );
                            $comp        = strtoupper( (string) ( $row['competition'] ?? 'UNKNOWN' ) );
                            $score       = (int) ( $row['opportunity_score'] ?? 0 );
                            $exists      = ! empty( $row['exists_on_site'] );
                            $should_make = ! empty( $row['should_create'] );

                            switch ( $comp ) {
                                case 'HIGH':
                                    $comp_cls   = 'red';
                                    $comp_label = __( 'Yüksek', 'hge' );
                                    break;
                                case 'MEDIUM':
                                    $comp_cls   = 'orange';
                                    $comp_label = __( 'Orta', 'hge' );
                                    break;
                                case 'LOW':
                                    $comp_cls   = 'green';
                                    $comp_label = __( 'Düşük', 'hge' );
                                    break;
                                default:
                                    $comp_cls   = 'muted';
                                    $comp_label = __( 'Bilinmiyor', 'hge' );
                                    $comp       = 'UNKNOWN';
                                    break;
                            }
                            ?>
                            <tr
                                data-exists="<?php echo esc_attr( $exists ? 1 : 0 ); ?>"
                                data-competition="<?php echo esc_attr( $comp ); ?>"
                                data-score="<?php echo esc_attr( $score ); ?>"
                                data-keyword="<?php echo esc_attr( $topic ); ?>"
                            >
                                <td>
                                    <button class="hge-keyword-button" type="button">
                                        <span><?php echo esc_html( $topic ); ?></span>
                                        <?php if ( $should_make ): ?>
                                            <em><?php esc_html_e( 'Hemen Aç', 'hge' ); ?></em>
                                        <?php endif; ?>
                                    </button>
                                </td>
                                <td data-value="<?php echo esc_attr( $volume ); ?>">
                                    <?php if ( $volume > 0 ): ?>
                                        <strong><?php echo esc_html( number_format_i18n( $volume ) ); ?></strong>
                                    <?php else: ?>
                                        <span class="hge-text-muted"><?php esc_html_e( 'Veri bekleniyor', 'hge' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="hge-comp-badge hge-comp-<?php echo esc_attr( $comp_cls ); ?>"><?php echo esc_html( $comp_label ); ?></span></td>
                                <td>
                                    <?php if ( $exists ): ?>
                                        <span class="hge-badge hge-badge-success"><?php esc_html_e( 'Var', 'hge' ); ?></span>
                                    <?php else: ?>
                                        <span class="hge-badge hge-badge-warning"><?php esc_html_e( 'Yok', 'hge' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="hge-score-wrap hge-score-premium">
                                        <div class="hge-score-bar"><div class="hge-score-fill" style="width:<?php echo esc_attr( min( 100, max( 0, $score ) ) ); ?>%"></div></div>
                                        <span class="hge-score-num"><?php echo esc_html( $score ); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="hge-row-actions">
                                        <button class="hge-mini-btn hge-open-detail" type="button"><?php esc_html_e( 'Aç', 'hge' ); ?></button>
                                        <button class="hge-mini-btn" type="button"><?php esc_html_e( 'İçerik Yaz', 'hge' ); ?></button>
                                        <button class="hge-mini-btn hge-mini-btn-primary" type="button"><?php esc_html_e( 'Hesaplama Oluştur', 'hge' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $suggestions ) ): ?>
            <div class="hge-pagination" id="hge-ideas-pagination">
                <span id="hge-pagination-summary"></span>
                <div>
                    <button class="hge-mini-btn" id="hge-prev-page" type="button"><?php esc_html_e( 'Önceki', 'hge' ); ?></button>
                    <button class="hge-mini-btn" id="hge-next-page" type="button"><?php esc_html_e( 'Sonraki', 'hge' ); ?></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="hge-side-panel" id="hge-idea-panel" aria-hidden="true">
        <div class="hge-side-panel-backdrop" data-close-panel></div>
        <aside class="hge-side-panel-card" role="dialog" aria-modal="true" aria-labelledby="hge-panel-title">
            <button class="hge-panel-close" type="button" data-close-panel>×</button>
            <span class="hge-eyebrow"><?php esc_html_e( 'Fırsat Detayı', 'hge' ); ?></span>
            <h2 id="hge-panel-title"><?php esc_html_e( 'Anahtar kelime', 'hge' ); ?></h2>
            <div class="hge-panel-score"><span><?php esc_html_e( 'Fırsat skoru', 'hge' ); ?></span><strong id="hge-panel-score">0</strong></div>
            <div class="hge-panel-section">
                <h4><?php esc_html_e( 'İçerik önerisi', 'hge' ); ?></h4>
                <p id="hge-panel-content"></p>
            </div>
            <div class="hge-panel-section">
                <h4><?php esc_html_e( 'Hesaplama aracı önerisi', 'hge' ); ?></h4>
                <p id="hge-panel-tool"></p>
            </div>
            <div class="hge-panel-grid">
                <div><span><?php esc_html_e( 'SEO zorluğu', 'hge' ); ?></span><strong id="hge-panel-difficulty"></strong></div>
                <div><span><?php esc_html_e( 'Durum', 'hge' ); ?></span><strong id="hge-panel-status"></strong></div>
            </div>
            <div class="hge-panel-section">
                <h4><?php esc_html_e( 'Rakip başlık fikirleri', 'hge' ); ?></h4>
                <ul id="hge-panel-titles"></ul>
            </div>
            <button class="hge-btn hge-btn-primary hge-publish-btn" type="button"><?php esc_html_e( 'Hemen yayınla', 'hge' ); ?></button>
        </aside>
    </div>
</div>
