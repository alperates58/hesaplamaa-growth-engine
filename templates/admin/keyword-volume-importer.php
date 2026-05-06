<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap hge-keyword-volume-page">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5h18"/><path d="M3 12h18"/><path d="M3 19h18"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Keyword Hacim Yükle', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php esc_html_e( 'Metin dosyası yükleyin, yeni keywordleri Google Ads API ile hacim listesine dönüştürelim.', 'hge' ); ?></p>
            </div>
        </div>
    </div>

    <div class="hge-stats-grid hge-keyword-volume-stats">
        <div class="hge-stat-card">
            <span><?php esc_html_e( 'Toplam Keyword', 'hge' ); ?></span>
            <strong id="hge-volume-total-keywords"><?php echo esc_html( number_format_i18n( (int) ( $summary['total'] ?? 0 ) ) ); ?></strong>
        </div>
        <div class="hge-stat-card">
            <span><?php esc_html_e( 'Toplam Aranma Hacmi', 'hge' ); ?></span>
            <strong id="hge-volume-total-searches"><?php echo esc_html( number_format_i18n( (int) ( $summary['total_volume'] ?? 0 ) ) ); ?></strong>
        </div>
        <div class="hge-stat-card">
            <span><?php esc_html_e( 'Eksik Metrik', 'hge' ); ?></span>
            <strong id="hge-volume-missing-metrics"><?php echo esc_html( number_format_i18n( (int) ( $summary['missing_metrics'] ?? 0 ) ) ); ?></strong>
        </div>
        <div class="hge-stat-card">
            <span><?php esc_html_e( 'Son Güncelleme', 'hge' ); ?></span>
            <strong id="hge-volume-latest-update"><?php echo esc_html( ! empty( $summary['latest_updated'] ) ? $summary['latest_updated'] : '-' ); ?></strong>
        </div>
    </div>

    <div class="hge-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'Toplu Dosya Yükleme', 'hge' ); ?></h3>
            <?php if ( $ads_configured ): ?>
                <span class="hge-badge hge-badge-success"><?php esc_html_e( 'API Hazır', 'hge' ); ?></span>
            <?php else: ?>
                <span class="hge-badge hge-badge-warning"><?php esc_html_e( 'Önce API Ayarı Gerekli', 'hge' ); ?></span>
            <?php endif; ?>
        </div>
        <div class="hge-card-body">
            <form id="hge-keyword-volume-form" class="hge-keyword-volume-form" enctype="multipart/form-data">
                <div class="hge-keyword-volume-upload">
                    <label for="hge_keyword_file" class="hge-field">
                        <span><?php esc_html_e( 'Keyword Metin Dosyası', 'hge' ); ?></span>
                        <input type="file" id="hge_keyword_file" name="keyword_file" accept=".txt,.csv,text/plain" class="hge-input">
                        <small class="hge-field-hint"><?php esc_html_e( 'Her satıra bir keyword yazın. Aynı keyword daha önce yüklendiyse tekrar eklenmez.', 'hge' ); ?></small>
                    </label>
                    <button type="submit" class="hge-btn hge-btn-primary" <?php disabled( ! $ads_configured ); ?>>
                        <?php esc_html_e( 'Listeyi İşle', 'hge' ); ?>
                    </button>
                </div>
            </form>

            <div id="hge-keyword-volume-result" class="hge-keyword-volume-result" hidden></div>

            <?php if ( ! $ads_configured ): ?>
                <p class="hge-text-muted">
                    <?php esc_html_e( 'Bu ekranı kullanmak için önce Google Ads API bağlantısını ayarlayın.', 'hge' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=hge-settings' ) ); ?>"><?php esc_html_e( 'Ayarlar sayfasına git', 'hge' ); ?></a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="hge-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'Detaylı Aranma Hacmi Listesi', 'hge' ); ?></h3>
            <div class="hge-filter-row">
                <input type="text" id="hge-volume-search" class="hge-input hge-input-sm" placeholder="<?php esc_attr_e( 'Keyword ara...', 'hge' ); ?>">
            </div>
        </div>
        <div class="hge-card-body hge-table-wrap">
            <?php if ( empty( $rows ) ): ?>
                <div class="hge-empty-state">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    <p><?php esc_html_e( 'Henüz keyword listesi yüklenmedi.', 'hge' ); ?></p>
                </div>
            <?php else: ?>
                <table class="hge-table hge-table-sortable" id="hge-volume-table">
                    <thead>
                        <tr>
                            <th data-sort="keyword"><?php esc_html_e( 'Keyword', 'hge' ); ?></th>
                            <th data-sort="monthly_volume"><?php esc_html_e( 'Aylık Hacim', 'hge' ); ?></th>
                            <th data-sort="competition"><?php esc_html_e( 'Rekabet', 'hge' ); ?></th>
                            <th data-sort="status"><?php esc_html_e( 'Durum', 'hge' ); ?></th>
                            <th data-sort="source_file"><?php esc_html_e( 'Kaynak Dosya', 'hge' ); ?></th>
                            <th data-sort="updated_at"><?php esc_html_e( 'Güncelleme', 'hge' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="hge-volume-table-body">
                        <?php foreach ( $rows as $row ): ?>
                            <?php $competition = strtoupper( (string) ( $row['competition'] ?? 'UNKNOWN' ) ); ?>
                            <tr>
                                <td><span class="hge-keyword-cell"><?php echo esc_html( $row['keyword'] ); ?></span></td>
                                <td data-sort-value="<?php echo esc_attr( (int) $row['monthly_volume'] ); ?>"><?php echo esc_html( number_format_i18n( (int) $row['monthly_volume'] ) ); ?></td>
                                <td>
                                    <span class="hge-competition hge-competition-<?php echo esc_attr( strtolower( $competition ) ); ?>">
                                        <?php echo esc_html( $competition ); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="hge-badge <?php echo esc_attr( ( $row['status'] ?? '' ) === 'ready' ? 'hge-badge-success' : 'hge-badge-warning' ); ?>">
                                        <?php echo esc_html( ( $row['status'] ?? '' ) === 'ready' ? __( 'Hazır', 'hge' ) : __( 'Metrik Yok', 'hge' ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $row['source_file'] ?: '-' ); ?></td>
                                <td data-sort-value="<?php echo esc_attr( strtotime( (string) $row['updated_at'] ) ?: 0 ); ?>"><?php echo esc_html( $row['updated_at'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
