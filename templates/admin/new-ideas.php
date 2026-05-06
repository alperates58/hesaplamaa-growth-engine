<?php
defined( 'ABSPATH' ) || exit;

$total_count     = count( $suggestions );
$missing_count   = 0;
$quick_win_count = 0;
$today_count     = 0;
$today           = current_time( 'Y-m-d' );
$ads_configured  = class_exists( '\HGE\API\GoogleAdsClient' ) ? ( new \HGE\API\GoogleAdsClient() )->is_configured() : false;
$ai_configured   = class_exists( '\HGE\API\OpenAIClient' ) ? ( new \HGE\API\OpenAIClient() )->is_configured() : false;

$format_competition = static function ( string $competition ) {
    $competition = strtoupper( $competition );

    switch ( $competition ) {
        case 'HIGH':
            return [
                'code'  => 'HIGH',
                'label' => __( 'Yüksek', 'hge' ),
                'tone'  => 'danger',
                'rank'  => 3,
            ];
        case 'MEDIUM':
            return [
                'code'  => 'MEDIUM',
                'label' => __( 'Orta', 'hge' ),
                'tone'  => 'warning',
                'rank'  => 2,
            ];
        case 'LOW':
            return [
                'code'  => 'LOW',
                'label' => __( 'Düşük', 'hge' ),
                'tone'  => 'success',
                'rank'  => 1,
            ];
        default:
            return [
                'code'  => 'UNKNOWN',
                'label' => __( 'Bilinmiyor', 'hge' ),
                'tone'  => 'neutral',
                'rank'  => 4,
            ];
    }
};

$derive_page_type = static function ( string $topic ) {
    $topic_lc = function_exists( 'mb_strtolower' ) ? mb_strtolower( $topic, 'UTF-8' ) : strtolower( $topic );

    if ( strpos( $topic_lc, 'hesaplama' ) !== false || strpos( $topic_lc, 'hesaplayıcı' ) !== false ) {
        return __( 'Hesaplama aracı', 'hge' );
    }

    if ( preg_match( '/2025|2026/u', $topic_lc ) ) {
        return __( 'Yıllık güncelleme', 'hge' );
    }

    if ( strpos( $topic_lc, 'nedir' ) !== false ) {
        return __( 'Bilgilendirici içerik', 'hge' );
    }

    return __( 'İçerik fırsatı', 'hge' );
};

$derive_category = static function ( string $topic ) {
    $topic_lc = strtolower( remove_accents( $topic ) );

    $map = [
        'sağlık' => [ 'saglik', 'kilo', 'bmi', 'vucut', 'kalori', 'gebelik', 'hamilelik', 'yumurtlama', 'protein', 'su ihtiyaci', 'metabolizma', 'tansiyon' ],
        'finans' => [ 'finans', 'kredi', 'faiz', 'mevduat', 'taksit', 'enflasyon', 'doviz', 'yatirim', 'asgari odeme' ],
        'maaş'   => [ 'maas', 'brut', 'net maas', 'kidem', 'ihbar', 'mesai ucreti' ],
        'vergi'  => [ 'vergi', 'kdv', 'mtv', 'stopaj', 'damga', 'emlak vergisi' ],
        'araç'   => [ 'arac', 'yakit', 'oto', 'tasit', 'km', 'lpg' ],
        'eğitim' => [ 'egitim', 'yks', 'lgs', 'not ortalamasi', 'sinav', 'puan' ],
        'tarih'  => [ 'tarih', 'gun', 'hafta', 'ay', 'yil', 'yas', 'dogum', 'geri sayim', 'is gunu', 'mesai', 'saat', 'dakika', 'zaman' ],
    ];

    foreach ( $map as $label => $terms ) {
        foreach ( $terms as $term ) {
            if ( strpos( $topic_lc, $term ) !== false ) {
                return $label;
            }
        }
    }

    return __( 'Genel', 'hge' );
};

$derive_site_status = static function ( bool $exists, string $topic, string $page_type ) {
    $topic_lc = strtolower( remove_accents( $topic ) );

    if ( $exists ) {
        if ( preg_match( '/2025|2026/', $topic_lc ) ) {
            return __( 'Güncelleme gerekli', 'hge' );
        }

        return __( 'Mevcut sayfa var', 'hge' );
    }

    if ( $page_type === __( 'Hesaplama aracı', 'hge' ) && strpos( $topic_lc, 'nedir' ) !== false ) {
        return __( 'Araç eksik', 'hge' );
    }

    if ( strpos( $topic_lc, 'rehber' ) !== false || strpos( $topic_lc, 'ornek' ) !== false ) {
        return __( 'Benzer içerik var', 'hge' );
    }

    return __( 'Sitede yok', 'hge' );
};

$derive_priority = static function ( int $score, int $volume, string $competition, string $site_status ) {
    if ( $site_status === __( 'Sitede yok', 'hge' ) && $score >= 82 && $volume >= 1000 && $competition !== 'HIGH' ) {
        return [ 'label' => __( 'Şimdi yap', 'hge' ), 'tone' => 'success', 'rank' => 1 ];
    }

    if ( $site_status === __( 'Güncelleme gerekli', 'hge' ) || $score >= 72 ) {
        return [ 'label' => __( 'Öncelikli', 'hge' ), 'tone' => 'warning', 'rank' => 2 ];
    }

    return [ 'label' => __( 'İzlemeye al', 'hge' ), 'tone' => 'neutral', 'rank' => 3 ];
};

foreach ( $suggestions as $item ) {
    $score = (int) ( $item['opportunity_score'] ?? 0 );

    if ( empty( $item['exists_on_site'] ) ) {
        $missing_count++;
    }

    if ( $score >= 75 && empty( $item['exists_on_site'] ) ) {
        $quick_win_count++;
    }

    if ( ! empty( $item['created_at'] ) && substr( (string) $item['created_at'], 0, 10 ) === $today ) {
        $today_count++;
    }
}

if ( $today_count === 0 && $total_count > 0 ) {
    $today_count = min( 12, $total_count );
}

$archive_url         = admin_url( 'admin.php?page=hge-suggestion-archive' );
$archive_missing_url = add_query_arg( 'hge_status', 'missing', $archive_url );
$archive_quick_url   = add_query_arg( 'hge_status', 'should_create', $archive_url );
$archive_today_url   = add_query_arg( 'hge_created', 'today', $archive_url );
?>
<div
    class="hge-wrap hge-ideas-app"
    data-ai-configured="<?php echo esc_attr( $ai_configured ? 1 : 0 ); ?>"
    data-ads-configured="<?php echo esc_attr( $ads_configured ? 1 : 0 ); ?>"
>
    <section class="hge-ideas-hero">
        <div class="hge-ideas-hero-copy">
            <span class="hge-ideas-kicker"><?php esc_html_e( 'Growth Opportunity Engine', 'hge' ); ?></span>
            <h1><?php esc_html_e( 'Yeni Hesaplama Fikirleri', 'hge' ); ?></h1>
            <p><?php esc_html_e( 'Yeni fikirleri bulun, neden önemli olduklarını görün ve hangi fırsatı önce üretmeniz gerektiğini tek ekranda karar verin.', 'hge' ); ?></p>
        </div>
        <button class="hge-ideas-primary-action" id="hge-refresh-suggestions" type="button">
            <span>+</span>
            <?php esc_html_e( 'Yeni Fikir Tara', 'hge' ); ?>
        </button>
    </section>

    <?php if ( ! $ads_configured ): ?>
        <div class="hge-ideas-notice hge-ideas-notice-warning">
            <strong><?php esc_html_e( 'Keyword Planner verisi eksik görünüyor.', 'hge' ); ?></strong>
            <p><?php esc_html_e( 'Google Ads ayarları tamamlanana kadar hacim ve rekabet alanlarında tahmini veya kontrol edilmedi etiketleri gösterilir.', 'hge' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! $ai_configured ): ?>
        <div class="hge-ideas-notice hge-ideas-notice-info">
            <strong><?php esc_html_e( 'AI entegrasyonu kapalı.', 'hge' ); ?></strong>
            <p><?php esc_html_e( 'Konu önerileri ve SEO planı üretimi için AI ayarlarını tamamlayın. Mevcut fırsatlar yine listelenir.', 'hge' ); ?></p>
        </div>
    <?php endif; ?>

    <section class="hge-ideas-metrics" aria-label="<?php esc_attr_e( 'Fırsat özeti', 'hge' ); ?>">
        <a class="hge-ideas-metric" href="<?php echo esc_url( $archive_url ); ?>">
            <span><?php esc_html_e( 'Toplam fırsat', 'hge' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( $total_count ) ); ?></strong>
            <em><?php esc_html_e( 'Analize hazır konu', 'hge' ); ?></em>
        </a>
        <a class="hge-ideas-metric" href="<?php echo esc_url( $archive_missing_url ); ?>">
            <span><?php esc_html_e( 'Sitede olmayanlar', 'hge' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( $missing_count ) ); ?></strong>
            <em><?php esc_html_e( 'Yeni sayfa adayı', 'hge' ); ?></em>
        </a>
        <a class="hge-ideas-metric" href="<?php echo esc_url( $archive_quick_url ); ?>">
            <span><?php esc_html_e( 'Hızlı kazanımlar', 'hge' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( $quick_win_count ) ); ?></strong>
            <em><?php esc_html_e( 'Önce değerlendirilmesi gerekenler', 'hge' ); ?></em>
        </a>
        <a class="hge-ideas-metric" href="<?php echo esc_url( $archive_today_url ); ?>">
            <span><?php esc_html_e( 'Bugün keşfedilenler', 'hge' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( $today_count ) ); ?></strong>
            <em><?php esc_html_e( 'Taze sinyal', 'hge' ); ?></em>
        </a>
    </section>

    <section class="hge-ideas-workspace">
        <div class="hge-ideas-list-shell">
            <section class="hge-topic-discovery-card">
                <div class="hge-topic-discovery-head">
                    <div>
                        <h2><?php esc_html_e( 'Fırsat Keşfi', 'hge' ); ?></h2>
                        <p><?php esc_html_e( 'Kategori, konu veya anahtar kelime girerek AI destekli hesaplama fikirleri üretin ve Keyword Planner verileriyle önceliklendirin.', 'hge' ); ?></p>
                    </div>
                    <div class="hge-topic-discovery-actions">
                        <div class="hge-topic-discovery">
                            <input type="text" id="hge-ai-topic-input" placeholder="<?php esc_attr_e( 'Örn: maaş, kredi, vergi, gebelik, yaş, tarih, araç kredisi...', 'hge' ); ?>">
                            <button
                                type="button"
                                id="hge-ai-topic-btn"
                                title="<?php esc_attr_e( 'Girilen konuya göre yeni hesaplama başlıkları üretir.', 'hge' ); ?>"
                            ><?php esc_html_e( 'AI ile konu öner', 'hge' ); ?></button>
                            <button
                                type="button"
                                id="hge-ai-global-btn"
                                title="<?php esc_attr_e( 'Mevcut veri kaynaklarına göre sitede olmayan fırsatları listeler.', 'hge' ); ?>"
                            ><?php esc_html_e( 'Fırsatları keşfet', 'hge' ); ?></button>
                        </div>
                    </div>
                </div>

                <div class="hge-topic-chip-row" aria-label="<?php esc_attr_e( 'Hızlı kategori seçimleri', 'hge' ); ?>">
                    <?php foreach ( [ 'Maaş', 'Kredi', 'Vergi', 'Sağlık', 'Tarih', 'Araç', 'Eğitim', 'Finans' ] as $chip ): ?>
                        <button type="button" class="hge-topic-chip" data-topic-chip="<?php echo esc_attr( $chip ); ?>"><?php echo esc_html( $chip ); ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="hge-topic-discovery-feedback" id="hge-topic-discovery-feedback" aria-live="polite">
                    <span class="hge-topic-discovery-feedback-label"><?php esc_html_e( 'Durum', 'hge' ); ?></span>
                    <strong id="hge-topic-feedback-message"><?php esc_html_e( 'Konu girerek AI önerisi oluşturabilir veya tüm fırsatları tarayabilirsiniz.', 'hge' ); ?></strong>
                </div>
            </section>

            <div class="hge-ideas-toolbar">
                <div class="hge-ideas-search">
                    <span></span>
                    <input type="search" id="hge-idea-search" placeholder="<?php esc_attr_e( 'Başlık, kategori veya fırsat tipinde ara', 'hge' ); ?>">
                </div>

                <div class="hge-ideas-toolbar-group">
                    <label class="hge-ideas-sort">
                        <span><?php esc_html_e( 'Sıralama', 'hge' ); ?></span>
                        <select id="hge-idea-sort">
                            <option value="score_desc"><?php esc_html_e( 'Fırsat puanı yüksekten düşüğe', 'hge' ); ?></option>
                            <option value="volume_desc"><?php esc_html_e( 'Aylık aranma hacmi yüksekten düşüğe', 'hge' ); ?></option>
                            <option value="competition_asc"><?php esc_html_e( 'Düşük rekabet önce', 'hge' ); ?></option>
                            <option value="newest"><?php esc_html_e( 'Yeni keşfedilenler önce', 'hge' ); ?></option>
                            <option value="missing_first"><?php esc_html_e( 'Sitede olmayanlar önce', 'hge' ); ?></option>
                        </select>
                    </label>

                    <div class="hge-ideas-view-switch" role="tablist" aria-label="<?php esc_attr_e( 'Görünüm seçici', 'hge' ); ?>">
                        <button type="button" class="is-active" data-ideas-view="cards"><?php esc_html_e( 'Kart görünümü', 'hge' ); ?></button>
                        <button type="button" data-ideas-view="table"><?php esc_html_e( 'Tablo görünümü', 'hge' ); ?></button>
                    </div>
                </div>
            </div>

            <div class="hge-ideas-segments" role="tablist" aria-label="<?php esc_attr_e( 'Fırsat filtreleri', 'hge' ); ?>">
                <button class="is-active" type="button" data-idea-segment="all"><?php esc_html_e( 'Tümü', 'hge' ); ?></button>
                <button type="button" data-idea-segment="missing"><?php esc_html_e( 'Sitede olmayanlar', 'hge' ); ?></button>
                <button type="button" data-idea-segment="quick"><?php esc_html_e( 'Hızlı kazanım', 'hge' ); ?></button>
                <button type="button" data-idea-segment="high-volume"><?php esc_html_e( 'Yüksek hacim', 'hge' ); ?></button>
                <button type="button" data-idea-segment="low-competition"><?php esc_html_e( 'Düşük rekabet', 'hge' ); ?></button>
                <button type="button" data-idea-segment="tool"><?php esc_html_e( 'Hesaplama aracı', 'hge' ); ?></button>
                <button type="button" data-idea-segment="update"><?php esc_html_e( 'Güncelleme gerekli', 'hge' ); ?></button>
                <button type="button" data-idea-segment="finans"><?php esc_html_e( 'Finans', 'hge' ); ?></button>
                <button type="button" data-idea-segment="maaş"><?php esc_html_e( 'Maaş', 'hge' ); ?></button>
                <button type="button" data-idea-segment="araç"><?php esc_html_e( 'Araç', 'hge' ); ?></button>
                <button type="button" data-idea-segment="sağlık"><?php esc_html_e( 'Sağlık', 'hge' ); ?></button>
                <button type="button" data-idea-segment="tarih"><?php esc_html_e( 'Tarih/Zaman', 'hge' ); ?></button>
            </div>

            <?php if ( empty( $suggestions ) ): ?>
                <div class="hge-ideas-empty">
                    <strong><?php esc_html_e( 'Henüz fırsat bulunamadı.', 'hge' ); ?></strong>
                    <p><?php esc_html_e( 'Bir kategori girerek AI ile konu önerisi oluşturabilir veya mevcut veri kaynaklarına göre tüm fırsatları tarayabilirsiniz.', 'hge' ); ?></p>
                </div>
            <?php else: ?>
                <div class="hge-ideas-loading" id="hge-ideas-loading" hidden>
                    <div class="hge-ideas-loading-cards">
                        <span class="hge-ideas-skeleton-card"></span>
                        <span class="hge-ideas-skeleton-card"></span>
                        <span class="hge-ideas-skeleton-card"></span>
                        <span class="hge-ideas-skeleton-card"></span>
                    </div>
                    <div class="hge-ideas-loading-copy">
                        <strong id="hge-loading-message"><?php esc_html_e( 'AI fikirleri hazırlanıyor', 'hge' ); ?></strong>
                        <p><?php esc_html_e( 'Keyword Planner ve fırsat puanı adımları sırayla tamamlanıyor.', 'hge' ); ?></p>
                    </div>
                </div>

                <div class="hge-ideas-view-shell is-active" data-view-panel="cards">
                    <div class="hge-ideas-list" id="hge-ideas-list">
                        <?php foreach ( $suggestions as $index => $row ): ?>
                            <?php
                            $topic             = (string) ( $row['topic'] ?? '' );
                            $volume            = (int) ( $row['monthly_volume'] ?? 0 );
                            $score             = (int) ( $row['opportunity_score'] ?? 0 );
                            $exists            = ! empty( $row['exists_on_site'] );
                            $should_make       = ! empty( $row['should_create'] );
                            $created_at        = (string) ( $row['created_at'] ?? '' );
                            $page_type         = $derive_page_type( $topic );
                            $site_status       = $derive_site_status( $exists, $topic, $page_type );
                            $category          = $derive_category( $topic );
                            $competition_data  = $format_competition( (string) ( $row['competition'] ?? 'UNKNOWN' ) );
                            $priority_data     = $derive_priority( $score, $volume, $competition_data['code'], $site_status );
                            $metric_state      = $ads_configured ? 'verified' : ( $volume > 0 ? 'estimate' : 'unknown' );
                            $metric_state_text = 'verified' === $metric_state ? __( 'Keyword Planner', 'hge' ) : ( 'estimate' === $metric_state ? __( 'Tahmini', 'hge' ) : __( 'Kontrol edilmedi', 'hge' ) );
                            $is_quick          = $score >= 75 && ! $exists && in_array( $competition_data['code'], [ 'LOW', 'MEDIUM' ], true );
                            ?>
                            <article
                                class="hge-idea-card<?php echo 0 === $index ? ' is-selected' : ''; ?>"
                                data-keyword="<?php echo esc_attr( $topic ); ?>"
                                data-volume="<?php echo esc_attr( $volume ); ?>"
                                data-competition="<?php echo esc_attr( $competition_data['code'] ); ?>"
                                data-competition-label="<?php echo esc_attr( $competition_data['label'] ); ?>"
                                data-competition-rank="<?php echo esc_attr( $competition_data['rank'] ); ?>"
                                data-score="<?php echo esc_attr( $score ); ?>"
                                data-exists="<?php echo esc_attr( $exists ? 1 : 0 ); ?>"
                                data-quick="<?php echo esc_attr( $is_quick ? 1 : 0 ); ?>"
                                data-page-type="<?php echo esc_attr( $page_type ); ?>"
                                data-site-status="<?php echo esc_attr( $site_status ); ?>"
                                data-priority="<?php echo esc_attr( $priority_data['label'] ); ?>"
                                data-priority-rank="<?php echo esc_attr( $priority_data['rank'] ); ?>"
                                data-category="<?php echo esc_attr( $category ); ?>"
                                data-created-at="<?php echo esc_attr( $created_at ); ?>"
                                data-source="<?php echo esc_attr( (string) ( $row['source'] ?? '' ) ); ?>"
                                data-metric-state="<?php echo esc_attr( $metric_state ); ?>"
                                data-metric-state-label="<?php echo esc_attr( $metric_state_text ); ?>"
                                tabindex="0"
                            >
                                <div class="hge-idea-card-main">
                                    <div>
                                        <div class="hge-idea-card-badges">
                                            <span class="hge-idea-label"><?php echo $should_make ? esc_html__( 'Öncelikli fırsat', 'hge' ) : esc_html__( 'Fırsat adayı', 'hge' ); ?></span>
                                            <span class="hge-idea-chip hge-idea-chip-<?php echo esc_attr( $priority_data['tone'] ); ?>"><?php echo esc_html( $priority_data['label'] ); ?></span>
                                        </div>
                                        <h3><?php echo esc_html( $topic ); ?></h3>
                                    </div>
                                    <div class="hge-idea-score">
                                        <strong><?php echo esc_html( $score ); ?></strong>
                                        <span><?php esc_html_e( 'puan', 'hge' ); ?></span>
                                    </div>
                                </div>

                                <div class="hge-idea-meta-line">
                                    <span class="hge-idea-chip"><?php echo esc_html( $category ); ?></span>
                                    <span class="hge-idea-chip"><?php echo esc_html( $page_type ); ?></span>
                                    <span class="hge-idea-chip hge-idea-chip-muted"><?php echo esc_html( $metric_state_text ); ?></span>
                                </div>

                                <div class="hge-idea-stats">
                                    <span>
                                        <em><?php esc_html_e( 'Aylık hacim', 'hge' ); ?></em>
                                        <strong><?php echo $volume > 0 ? esc_html( number_format_i18n( $volume ) ) : esc_html__( 'Bilinmiyor', 'hge' ); ?></strong>
                                    </span>
                                    <span>
                                        <em><?php esc_html_e( 'Rekabet', 'hge' ); ?></em>
                                        <strong class="hge-tone-<?php echo esc_attr( $competition_data['tone'] ); ?>"><?php echo esc_html( $competition_data['label'] ); ?></strong>
                                    </span>
                                    <span>
                                        <em><?php esc_html_e( 'Site durumu', 'hge' ); ?></em>
                                        <strong class="<?php echo $exists ? 'hge-tone-success' : 'hge-tone-warning'; ?>"><?php echo esc_html( $site_status ); ?></strong>
                                    </span>
                                    <span>
                                        <em><?php esc_html_e( 'Sayfa tipi', 'hge' ); ?></em>
                                        <strong><?php echo esc_html( $page_type ); ?></strong>
                                    </span>
                                    <span>
                                        <em><?php esc_html_e( 'Öncelik', 'hge' ); ?></em>
                                        <strong><?php echo esc_html( $priority_data['label'] ); ?></strong>
                                    </span>
                                    <span>
                                        <em><?php esc_html_e( 'Kaynak', 'hge' ); ?></em>
                                        <strong><?php echo esc_html( $metric_state_text ); ?></strong>
                                    </span>
                                </div>

                                <div class="hge-idea-progress" aria-hidden="true">
                                    <span style="width:<?php echo esc_attr( min( 100, max( 0, $score ) ) ); ?>%"></span>
                                </div>

                                <div class="hge-idea-actions">
                                    <button class="hge-idea-card-action" type="button"><?php esc_html_e( 'Detay aç', 'hge' ); ?></button>
                                    <button class="hge-idea-card-action" type="button" data-idea-action="seo"><?php esc_html_e( 'SEO planı üret', 'hge' ); ?></button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="hge-ideas-view-shell" data-view-panel="table">
                    <div class="hge-ideas-table-wrap">
                        <table class="hge-ideas-table-v2" id="hge-ideas-table-v2">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Skor', 'hge' ); ?></th>
                                    <th><?php esc_html_e( 'Başlık', 'hge' ); ?></th>
                                    <th><?php esc_html_e( 'Hacim', 'hge' ); ?></th>
                                    <th><?php esc_html_e( 'Rekabet', 'hge' ); ?></th>
                                    <th><?php esc_html_e( 'Site durumu', 'hge' ); ?></th>
                                    <th><?php esc_html_e( 'Sayfa tipi', 'hge' ); ?></th>
                                    <th><?php esc_html_e( 'Öncelik', 'hge' ); ?></th>
                                    <th><?php esc_html_e( 'Aksiyon', 'hge' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $suggestions as $index => $row ): ?>
                                    <?php
                                    $topic             = (string) ( $row['topic'] ?? '' );
                                    $volume            = (int) ( $row['monthly_volume'] ?? 0 );
                                    $score             = (int) ( $row['opportunity_score'] ?? 0 );
                                    $exists            = ! empty( $row['exists_on_site'] );
                                    $created_at        = (string) ( $row['created_at'] ?? '' );
                                    $page_type         = $derive_page_type( $topic );
                                    $site_status       = $derive_site_status( $exists, $topic, $page_type );
                                    $category          = $derive_category( $topic );
                                    $competition_data  = $format_competition( (string) ( $row['competition'] ?? 'UNKNOWN' ) );
                                    $priority_data     = $derive_priority( $score, $volume, $competition_data['code'], $site_status );
                                    $metric_state      = $ads_configured ? 'verified' : ( $volume > 0 ? 'estimate' : 'unknown' );
                                    $metric_state_text = 'verified' === $metric_state ? __( 'Keyword Planner', 'hge' ) : ( 'estimate' === $metric_state ? __( 'Tahmini', 'hge' ) : __( 'Kontrol edilmedi', 'hge' ) );
                                    $is_quick          = $score >= 75 && ! $exists && in_array( $competition_data['code'], [ 'LOW', 'MEDIUM' ], true );
                                    ?>
                                    <tr
                                        class="<?php echo 0 === $index ? 'is-selected' : ''; ?>"
                                        data-keyword="<?php echo esc_attr( $topic ); ?>"
                                        data-volume="<?php echo esc_attr( $volume ); ?>"
                                        data-competition="<?php echo esc_attr( $competition_data['code'] ); ?>"
                                        data-competition-label="<?php echo esc_attr( $competition_data['label'] ); ?>"
                                        data-competition-rank="<?php echo esc_attr( $competition_data['rank'] ); ?>"
                                        data-score="<?php echo esc_attr( $score ); ?>"
                                        data-exists="<?php echo esc_attr( $exists ? 1 : 0 ); ?>"
                                        data-quick="<?php echo esc_attr( $is_quick ? 1 : 0 ); ?>"
                                        data-page-type="<?php echo esc_attr( $page_type ); ?>"
                                        data-site-status="<?php echo esc_attr( $site_status ); ?>"
                                        data-priority="<?php echo esc_attr( $priority_data['label'] ); ?>"
                                        data-priority-rank="<?php echo esc_attr( $priority_data['rank'] ); ?>"
                                        data-category="<?php echo esc_attr( $category ); ?>"
                                        data-created-at="<?php echo esc_attr( $created_at ); ?>"
                                        data-source="<?php echo esc_attr( (string) ( $row['source'] ?? '' ) ); ?>"
                                        data-metric-state="<?php echo esc_attr( $metric_state ); ?>"
                                        data-metric-state-label="<?php echo esc_attr( $metric_state_text ); ?>"
                                        tabindex="0"
                                    >
                                        <td><strong><?php echo esc_html( $score ); ?></strong></td>
                                        <td>
                                            <button type="button" class="hge-idea-table-link">
                                                <span><?php echo esc_html( $topic ); ?></span>
                                                <small><?php echo esc_html( $category ); ?> · <?php echo esc_html( $metric_state_text ); ?></small>
                                            </button>
                                        </td>
                                        <td><?php echo $volume > 0 ? esc_html( number_format_i18n( $volume ) ) : esc_html__( 'Bilinmiyor', 'hge' ); ?></td>
                                        <td><span class="hge-idea-chip hge-idea-chip-<?php echo esc_attr( $competition_data['tone'] ); ?>"><?php echo esc_html( $competition_data['label'] ); ?></span></td>
                                        <td><?php echo esc_html( $site_status ); ?></td>
                                        <td><?php echo esc_html( $page_type ); ?></td>
                                        <td><span class="hge-idea-chip hge-idea-chip-<?php echo esc_attr( $priority_data['tone'] ); ?>"><?php echo esc_html( $priority_data['label'] ); ?></span></td>
                                        <td>
                                            <div class="hge-idea-table-actions">
                                                <button type="button" class="hge-idea-card-action"><?php esc_html_e( 'Detay aç', 'hge' ); ?></button>
                                                <button type="button" class="hge-idea-card-action" data-idea-action="seo"><?php esc_html_e( 'SEO planı üret', 'hge' ); ?></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="hge-ideas-list-footer">
                    <span id="hge-ideas-count"></span>
                    <button class="hge-ideas-secondary-action" id="hge-load-more-ideas" type="button"><?php esc_html_e( 'Daha fazla göster', 'hge' ); ?></button>
                </div>
            <?php endif; ?>
        </div>

        <aside class="hge-idea-detail" id="hge-idea-detail">
            <span class="hge-ideas-kicker"><?php esc_html_e( 'Seçili fırsat', 'hge' ); ?></span>
            <h2 id="hge-detail-title"><?php esc_html_e( 'Fırsat seçin', 'hge' ); ?></h2>

            <div class="hge-detail-score">
                <span><?php esc_html_e( 'Fırsat skoru', 'hge' ); ?></span>
                <strong id="hge-detail-score">0</strong>
            </div>

            <div class="hge-detail-grid">
                <div>
                    <span><?php esc_html_e( 'Aylık aranma hacmi', 'hge' ); ?></span>
                    <strong id="hge-detail-volume">-</strong>
                </div>
                <div>
                    <span><?php esc_html_e( 'Rekabet', 'hge' ); ?></span>
                    <strong id="hge-detail-difficulty">-</strong>
                </div>
                <div>
                    <span><?php esc_html_e( 'Site durumu', 'hge' ); ?></span>
                    <strong id="hge-detail-site-status">-</strong>
                </div>
                <div>
                    <span><?php esc_html_e( 'Sayfa tipi', 'hge' ); ?></span>
                    <strong id="hge-detail-page-type">-</strong>
                </div>
                <div>
                    <span><?php esc_html_e( 'Öncelik', 'hge' ); ?></span>
                    <strong id="hge-detail-priority">-</strong>
                </div>
                <div>
                    <span><?php esc_html_e( 'Önerilen aksiyon', 'hge' ); ?></span>
                    <strong id="hge-detail-action-type">-</strong>
                </div>
            </div>

            <div class="hge-detail-section">
                <h3><?php esc_html_e( 'Bu konu neden önemli?', 'hge' ); ?></h3>
                <p id="hge-detail-why"><?php esc_html_e( 'Listeden bir fırsat seçildiğinde karar özeti burada görünür.', 'hge' ); ?></p>
            </div>

            <div class="hge-detail-section">
                <h3><?php esc_html_e( 'Ne yapılmalı?', 'hge' ); ?></h3>
                <ul class="hge-detail-checklist" id="hge-detail-checklist">
                    <li><?php esc_html_e( 'Seçili fırsata göre aksiyon önerileri burada listelenir.', 'hge' ); ?></li>
                </ul>
            </div>

            <div class="hge-detail-section">
                <h3><?php esc_html_e( 'Mevcut site eşleşmesi', 'hge' ); ?></h3>
                <p id="hge-detail-match"><?php esc_html_e( 'Mevcut eşleşme kontrol edilmedi.', 'hge' ); ?></p>
            </div>

            <div class="hge-detail-actions">
                <button class="hge-detail-primary" id="hge-ai-insight-btn" type="button"><?php esc_html_e( 'AI ile SEO planı üret', 'hge' ); ?></button>
                <button class="hge-detail-secondary" type="button" disabled title="<?php esc_attr_e( 'Bu özellik sonraki sürümde eklenecek.', 'hge' ); ?>"><?php esc_html_e( 'Araç alanlarını öner', 'hge' ); ?></button>
                <button class="hge-detail-secondary" type="button" disabled title="<?php esc_attr_e( 'Bu özellik sonraki sürümde eklenecek.', 'hge' ); ?>"><?php esc_html_e( 'Taslak oluştur', 'hge' ); ?></button>
                <button class="hge-detail-secondary" type="button" disabled title="<?php esc_attr_e( 'Bu özellik sonraki sürümde eklenecek.', 'hge' ); ?>"><?php esc_html_e( 'Planlandı olarak işaretle', 'hge' ); ?></button>
                <button class="hge-detail-secondary" type="button" disabled title="<?php esc_attr_e( 'Bu özellik sonraki sürümde eklenecek.', 'hge' ); ?>"><?php esc_html_e( 'Arşivle', 'hge' ); ?></button>
            </div>
            <p class="hge-ai-insight-status" id="hge-ai-insight-status"><?php esc_html_e( 'AI ile SEO planı üretildiğinde slug, başlık ve meta alanları burada görünür.', 'hge' ); ?></p>

            <div class="hge-ai-insight-result" id="hge-ai-insight-result" hidden>
                <div class="hge-detail-section">
                    <h3><?php esc_html_e( 'SEO planı önizlemesi', 'hge' ); ?></h3>
                    <div class="hge-ai-meta-box">
                        <span><?php esc_html_e( 'Önerilen URL slug', 'hge' ); ?></span>
                        <code id="hge-ai-slug">-</code>
                        <span><?php esc_html_e( 'Önerilen H1', 'hge' ); ?></span>
                        <strong id="hge-ai-h1">-</strong>
                        <span><?php esc_html_e( 'Önerilen SEO title', 'hge' ); ?></span>
                        <strong id="hge-ai-meta-title">-</strong>
                        <span><?php esc_html_e( 'Önerilen meta description', 'hge' ); ?></span>
                        <p id="hge-ai-meta-description">-</p>
                    </div>
                </div>

                <div class="hge-detail-section">
                    <h3><?php esc_html_e( 'İçerik yönü', 'hge' ); ?></h3>
                    <p id="hge-ai-content-angle">-</p>
                </div>

                <div class="hge-detail-section">
                    <h3><?php esc_html_e( 'Önerilen araç yaklaşımı', 'hge' ); ?></h3>
                    <p id="hge-ai-calculator-idea">-</p>
                </div>

                <div class="hge-detail-section">
                    <h3><?php esc_html_e( 'Önerilen H2 fikirleri', 'hge' ); ?></h3>
                    <ul class="hge-ai-title-list" id="hge-ai-title-list"></ul>
                </div>
            </div>
        </aside>
    </section>
</div>
