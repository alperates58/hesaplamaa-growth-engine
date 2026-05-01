<?php
defined( 'ABSPATH' ) || exit;

$total_count          = count( $suggestions );
$missing_count        = 0;
$quick_win_count      = 0;
$today_count          = 0;
$today                = current_time( 'Y-m-d' );

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
?>
<div class="hge-wrap hge-ideas-app">
    <section class="hge-ideas-hero">
        <div class="hge-ideas-hero-copy">
            <span class="hge-ideas-kicker"><?php esc_html_e( 'Growth Opportunity Engine', 'hge' ); ?></span>
            <h1><?php esc_html_e( 'Yeni Hesaplama Fikirleri', 'hge' ); ?></h1>
            <p><?php esc_html_e( 'Google verilerinden fırsat tespiti', 'hge' ); ?></p>
        </div>
        <button class="hge-ideas-primary-action" id="hge-refresh-suggestions" type="button">
            <span>+</span>
            <?php esc_html_e( 'Yeni Fikir Tara', 'hge' ); ?>
        </button>
    </section>

    <section class="hge-ideas-metrics" aria-label="<?php esc_attr_e( 'Fırsat özeti', 'hge' ); ?>">
        <article class="hge-ideas-metric">
            <span><?php esc_html_e( 'Toplam fırsat', 'hge' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( $total_count ) ); ?></strong>
            <em><?php esc_html_e( 'Analize hazır konu', 'hge' ); ?></em>
        </article>
        <article class="hge-ideas-metric">
            <span><?php esc_html_e( 'Sitede olmayanlar', 'hge' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( $missing_count ) ); ?></strong>
            <em><?php esc_html_e( 'Yeni sayfa adayı', 'hge' ); ?></em>
        </article>
        <article class="hge-ideas-metric">
            <span><?php esc_html_e( 'Hızlı kazanılacaklar', 'hge' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( $quick_win_count ) ); ?></strong>
            <em><?php esc_html_e( 'Öncelikli üretim', 'hge' ); ?></em>
        </article>
        <article class="hge-ideas-metric">
            <span><?php esc_html_e( 'Bugün keşfedilenler', 'hge' ); ?></span>
            <strong><?php echo esc_html( number_format_i18n( $today_count ) ); ?></strong>
            <em><?php esc_html_e( 'Taze sinyal', 'hge' ); ?></em>
        </article>
    </section>

    <section class="hge-ideas-workspace">
        <div class="hge-ideas-list-shell">
            <div class="hge-ideas-controls">
                <div class="hge-topic-discovery">
                    <input type="text" id="hge-ai-topic-input" placeholder="<?php esc_attr_e( 'Sağlık, finans, zaman...', 'hge' ); ?>">
                    <button type="button" id="hge-ai-topic-btn"><?php esc_html_e( 'AI ile konu öner', 'hge' ); ?></button>
                    <button type="button" id="hge-ai-global-btn"><?php esc_html_e( 'Tüm fırsatları keşfet', 'hge' ); ?></button>
                </div>
                <div class="hge-ideas-search">
                    <span></span>
                    <input type="search" id="hge-idea-search" placeholder="<?php esc_attr_e( 'Fırsatlarda ara', 'hge' ); ?>">
                </div>
                <div class="hge-ideas-segments" role="tablist" aria-label="<?php esc_attr_e( 'Fırsat filtreleri', 'hge' ); ?>">
                    <button class="is-active" type="button" data-idea-segment="all"><?php esc_html_e( 'Tümü', 'hge' ); ?></button>
                    <button type="button" data-idea-segment="missing"><?php esc_html_e( 'Sitede yok', 'hge' ); ?></button>
                    <button type="button" data-idea-segment="quick"><?php esc_html_e( 'Hızlı kazanım', 'hge' ); ?></button>
                </div>
            </div>

            <?php if ( empty( $suggestions ) ): ?>
                <div class="hge-ideas-empty">
                    <strong><?php esc_html_e( 'Henüz fırsat yok', 'hge' ); ?></strong>
                    <p><?php esc_html_e( 'Yeni Fikir Tara ile Google Suggest verilerini çekip ilk fırsat listesini oluşturun.', 'hge' ); ?></p>
                </div>
            <?php else: ?>
                <div class="hge-ideas-list" id="hge-ideas-list">
                    <?php foreach ( $suggestions as $index => $row ): ?>
                        <?php
                        $topic       = (string) ( $row['topic'] ?? '' );
                        $volume      = (int) ( $row['monthly_volume'] ?? 0 );
                        $comp        = strtoupper( (string) ( $row['competition'] ?? 'UNKNOWN' ) );
                        $score       = (int) ( $row['opportunity_score'] ?? 0 );
                        $exists      = ! empty( $row['exists_on_site'] );
                        $should_make = ! empty( $row['should_create'] );

                        switch ( $comp ) {
                            case 'HIGH':
                                $comp_label = __( 'Yüksek', 'hge' );
                                $comp_tone  = 'danger';
                                break;
                            case 'MEDIUM':
                                $comp_label = __( 'Orta', 'hge' );
                                $comp_tone  = 'warning';
                                break;
                            case 'LOW':
                                $comp_label = __( 'Düşük', 'hge' );
                                $comp_tone  = 'success';
                                break;
                            default:
                                $comp       = 'UNKNOWN';
                                $comp_label = __( 'Bilinmiyor', 'hge' );
                                $comp_tone  = 'neutral';
                                break;
                        }
                        ?>
                        <article
                            class="hge-idea-card<?php echo 0 === $index ? ' is-selected' : ''; ?>"
                            data-keyword="<?php echo esc_attr( $topic ); ?>"
                            data-volume="<?php echo esc_attr( $volume ); ?>"
                            data-competition="<?php echo esc_attr( $comp ); ?>"
                            data-competition-label="<?php echo esc_attr( $comp_label ); ?>"
                            data-score="<?php echo esc_attr( $score ); ?>"
                            data-exists="<?php echo esc_attr( $exists ? 1 : 0 ); ?>"
                            data-quick="<?php echo esc_attr( ( $score >= 75 && ! $exists ) ? 1 : 0 ); ?>"
                            tabindex="0"
                        >
                            <div class="hge-idea-card-main">
                                <div>
                                    <span class="hge-idea-label"><?php echo $should_make ? esc_html__( 'Öncelikli fırsat', 'hge' ) : esc_html__( 'Fırsat adayı', 'hge' ); ?></span>
                                    <h3><?php echo esc_html( $topic ); ?></h3>
                                </div>
                                <div class="hge-idea-score">
                                    <strong><?php echo esc_html( $score ); ?></strong>
                                    <span><?php esc_html_e( 'puan', 'hge' ); ?></span>
                                </div>
                            </div>

                            <div class="hge-idea-stats">
                                <span>
                                    <em><?php esc_html_e( 'Hacim', 'hge' ); ?></em>
                                    <strong><?php echo $volume > 0 ? esc_html( number_format_i18n( $volume ) ) : esc_html__( 'Bekleniyor', 'hge' ); ?></strong>
                                </span>
                                <span>
                                    <em><?php esc_html_e( 'Rekabet', 'hge' ); ?></em>
                                    <strong class="hge-tone-<?php echo esc_attr( $comp_tone ); ?>"><?php echo esc_html( $comp_label ); ?></strong>
                                </span>
                                <span>
                                    <em><?php esc_html_e( 'Durum', 'hge' ); ?></em>
                                    <strong class="<?php echo $exists ? 'hge-tone-success' : 'hge-tone-warning'; ?>"><?php echo $exists ? esc_html__( 'Var', 'hge' ) : esc_html__( 'Yok', 'hge' ); ?></strong>
                                </span>
                            </div>

                            <div class="hge-idea-progress" aria-hidden="true">
                                <span style="width:<?php echo esc_attr( min( 100, max( 0, $score ) ) ); ?>%"></span>
                            </div>

                            <button class="hge-idea-card-action" type="button"><?php esc_html_e( 'Detayı aç', 'hge' ); ?></button>
                        </article>
                    <?php endforeach; ?>
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
                <span><?php esc_html_e( 'Fırsat puanı', 'hge' ); ?></span>
                <strong id="hge-detail-score">0</strong>
            </div>

            <div class="hge-detail-grid">
                <div>
                    <span><?php esc_html_e( 'SEO zorluğu', 'hge' ); ?></span>
                    <strong id="hge-detail-difficulty">-</strong>
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
                <h3><?php esc_html_e( 'Rakipler var mı?', 'hge' ); ?></h3>
                <p id="hge-detail-competitors">-</p>
            </div>

            <div class="hge-detail-section">
                <h3><?php esc_html_e( 'Ne açılmalı?', 'hge' ); ?></h3>
                <p id="hge-detail-recommendation">-</p>
            </div>

            <button class="hge-detail-primary" id="hge-ai-insight-btn" type="button"><?php esc_html_e( 'AI ile analiz et', 'hge' ); ?></button>
            <p class="hge-ai-insight-status" id="hge-ai-insight-status"></p>

            <div class="hge-ai-insight-result" id="hge-ai-insight-result" hidden>
                <div class="hge-detail-section">
                    <h3><?php esc_html_e( 'AI içerik açısı', 'hge' ); ?></h3>
                    <p id="hge-ai-content-angle"></p>
                </div>
                <div class="hge-detail-section">
                    <h3><?php esc_html_e( 'AI hesaplama fikri', 'hge' ); ?></h3>
                    <p id="hge-ai-calculator-idea"></p>
                </div>
                <div class="hge-detail-section">
                    <h3><?php esc_html_e( 'Başlık önerileri', 'hge' ); ?></h3>
                    <ul class="hge-ai-title-list" id="hge-ai-title-list"></ul>
                </div>
                <div class="hge-ai-meta-box">
                    <span><?php esc_html_e( 'Meta title', 'hge' ); ?></span>
                    <strong id="hge-ai-meta-title"></strong>
                    <span><?php esc_html_e( 'Meta description', 'hge' ); ?></span>
                    <p id="hge-ai-meta-description"></p>
                    <span><?php esc_html_e( 'Slug', 'hge' ); ?></span>
                    <code id="hge-ai-slug"></code>
                </div>
            </div>
        </aside>
    </section>
</div>
