<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Dizin Durumları', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php printf( esc_html__( '%d yayınlanmış URL takip ediliyor', 'hge' ), (int) $summary['total'] ); ?></p>
            </div>
        </div>
        <div class="hge-header-right">
            <button class="hge-btn hge-btn-primary" id="hge-index-batch" data-loading-text="<?php esc_attr_e( 'Kontrol ediliyor...', 'hge' ); ?>" <?php disabled( ! $gsc_connected ); ?>>
                <?php esc_html_e( 'Eksikleri Kontrol Et', 'hge' ); ?>
            </button>
        </div>
    </div>

    <div class="hge-stats-grid">
        <div class="hge-stat-card"><span><?php esc_html_e( 'İndekste', 'hge' ); ?></span><strong><?php echo esc_html( $summary['indexed'] ); ?></strong></div>
        <div class="hge-stat-card"><span><?php esc_html_e( 'İndekste Değil', 'hge' ); ?></span><strong><?php echo esc_html( $summary['not_indexed'] ); ?></strong></div>
        <div class="hge-stat-card"><span><?php esc_html_e( 'Bekliyor', 'hge' ); ?></span><strong><?php echo esc_html( $summary['pending'] ); ?></strong></div>
        <div class="hge-stat-card"><span><?php esc_html_e( 'Hata', 'hge' ); ?></span><strong><?php echo esc_html( $summary['errors'] ); ?></strong></div>
    </div>

    <div class="hge-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'URL Inspection Sonuçları', 'hge' ); ?></h3>
            <div class="hge-filter-row">
                <input type="text" id="hge-index-search" class="hge-input hge-input-sm" placeholder="<?php esc_attr_e( 'URL veya başlık ara...', 'hge' ); ?>">
                <select id="hge-index-filter" class="hge-select hge-select-sm">
                    <option value=""><?php esc_html_e( 'Tüm Durumlar', 'hge' ); ?></option>
                    <option value="indexed"><?php esc_html_e( 'İndekste', 'hge' ); ?></option>
                    <option value="not-indexed"><?php esc_html_e( 'İndekste Değil', 'hge' ); ?></option>
                    <option value="pending"><?php esc_html_e( 'Bekliyor', 'hge' ); ?></option>
                    <option value="error"><?php esc_html_e( 'Hata', 'hge' ); ?></option>
                </select>
            </div>
        </div>
        <div class="hge-card-body hge-table-wrap">
            <?php if ( ! $gsc_connected ): ?>
                <div class="hge-empty-state">
                    <p><?php esc_html_e( 'Dizin durumlarını kontrol etmek için önce GSC bağlantısını tamamlayın.', 'hge' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=hge-settings' ) ); ?>" class="hge-btn hge-btn-primary"><?php esc_html_e( 'GSC Ayarlarına Git', 'hge' ); ?></a>
                </div>
            <?php endif; ?>

            <table class="hge-table hge-table-sm" id="hge-index-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Sayfa', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Durum', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Kapsam', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Son Crawl', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Son Kontrol', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'İşlem', 'hge' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $row ): ?>
                        <?php
                        $state = empty( $row['last_checked'] ) ? 'pending' : ( ! empty( $row['error_message'] ) ? 'error' : ( ( $row['verdict'] ?? '' ) === 'PASS' ? 'indexed' : 'not-indexed' ) );
                        $label = [
                            'indexed'     => __( 'İndekste', 'hge' ),
                            'not-indexed' => __( 'İndekste Değil', 'hge' ),
                            'pending'     => __( 'Bekliyor', 'hge' ),
                            'error'       => __( 'Hata', 'hge' ),
                        ][ $state ];
                        ?>
                        <tr data-index-state="<?php echo esc_attr( $state ); ?>" data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>">
                            <td>
                                <div class="hge-page-cell">
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $row['post_id'] . '&action=edit' ) ); ?>" class="hge-page-title-link"><?php echo esc_html( $row['page_title'] ); ?></a>
                                    <a href="<?php echo esc_url( $row['page_url'] ); ?>" target="_blank" class="hge-url-link" title="<?php echo esc_attr( $row['page_url'] ); ?>">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    </a>
                                </div>
                                <small class="hge-text-muted"><?php echo esc_html( $row['page_url'] ); ?></small>
                            </td>
                            <td><span class="hge-badge hge-index-badge hge-index-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $label ); ?></span></td>
                            <td class="hge-index-coverage"><?php echo esc_html( $row['error_message'] ?: ( $row['coverage_state'] ?: '-' ) ); ?></td>
                            <td><?php echo esc_html( $row['last_crawl_time'] ?: '-' ); ?></td>
                            <td class="hge-index-checked"><?php echo esc_html( $row['last_checked'] ?: '-' ); ?></td>
                            <td>
                                <button class="hge-btn hge-btn-secondary hge-btn-sm hge-index-check" type="button" data-loading-text="<?php esc_attr_e( 'Kontrol...', 'hge' ); ?>" <?php disabled( ! $gsc_connected ); ?>><?php esc_html_e( 'Kontrol Et', 'hge' ); ?></button>
                                <?php if ( ! empty( $row['inspection_link'] ) ): ?>
                                    <a class="hge-btn hge-btn-sm" target="_blank" href="<?php echo esc_url( $row['inspection_link'] ); ?>"><?php esc_html_e( 'GSC’de Aç', 'hge' ); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="hge-text-muted">
        <?php esc_html_e( 'Not: Google genel sayfalar için resmi bir otomatik “dizine ekleme isteği” API’si sunmuyor. Bu ekran URL Inspection API ile durumu kontrol eder; istek göndermek için GSC bağlantısını kullanabilirsiniz.', 'hge' ); ?>
    </p>
</div>
