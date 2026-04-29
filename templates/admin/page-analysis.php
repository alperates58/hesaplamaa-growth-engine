<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Mevcut Sayfa Analizi', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php printf( esc_html__( '%d sayfa analiz edildi', 'hge' ), count( $pages ) ); ?></p>
            </div>
        </div>
    </div>

    <div class="hge-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'Tüm Hesaplama Sayfaları', 'hge' ); ?></h3>
            <div class="hge-filter-row">
                <input type="text" id="hge-page-search" class="hge-input hge-input-sm" placeholder="<?php esc_attr_e( 'Sayfa ara...', 'hge' ); ?>">
                <select id="hge-meta-filter" class="hge-select hge-select-sm">
                    <option value=""><?php esc_html_e( 'Tüm Sayfalar', 'hge' ); ?></option>
                    <option value="0"><?php esc_html_e( 'Meta Açıklaması Eksik', 'hge' ); ?></option>
                    <option value="1"><?php esc_html_e( 'Meta Açıklaması Var', 'hge' ); ?></option>
                </select>
            </div>
        </div>
        <div class="hge-card-body hge-table-wrap">
            <table class="hge-table hge-table-sm" id="hge-pages-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Sayfa', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Kelime', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Tıklama', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Gösterim', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Pozisyon', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Ana KW', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'İç Link', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Meta', 'hge' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $pages as $page ): ?>
                    <tr data-meta="<?php echo esc_attr( $page['has_meta_desc'] ); ?>">
                        <td>
                            <div class="hge-page-cell">
                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $page['post_id'] . '&action=edit' ) ); ?>" class="hge-page-title-link">
                                    <?php echo esc_html( $page['page_title'] ?: '(Başlıksız)' ); ?>
                                </a>
                                <a href="<?php echo esc_url( $page['page_url'] ); ?>" target="_blank" class="hge-url-link">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>
                            </div>
                        </td>
                        <td><?php echo esc_html( number_format( $page['word_count'] ) ); ?></td>
                        <td><strong><?php echo esc_html( number_format( $page['clicks'] ) ); ?></strong></td>
                        <td><?php echo esc_html( number_format( $page['impressions'] ) ); ?></td>
                        <td>
                            <?php if ( $page['avg_position'] > 0 ):
                                $pos = (float) $page['avg_position'];
                                $cls = $pos <= 3 ? 'top3' : ( $pos <= 10 ? 'top10' : ( $pos <= 20 ? 'top20' : 'out' ) );
                            ?>
                            <span class="hge-pos-badge hge-pos-<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( number_format( $pos, 1 ) ); ?></span>
                            <?php else: ?>
                            <span class="hge-text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $page['main_keyword'] ): ?>
                            <span class="hge-keyword-cell hge-keyword-sm"><?php echo esc_html( $page['main_keyword'] ); ?></span>
                            <?php else: ?>
                            <span class="hge-text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $page['internal_links'] ); ?></td>
                        <td>
                            <?php if ( $page['has_meta_desc'] ): ?>
                                <span class="hge-status-dot ok" title="<?php esc_attr_e( 'Meta açıklaması var', 'hge' ); ?>"></span>
                            <?php else: ?>
                                <span class="hge-status-dot error" title="<?php esc_attr_e( 'Meta açıklaması eksik', 'hge' ); ?>"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
