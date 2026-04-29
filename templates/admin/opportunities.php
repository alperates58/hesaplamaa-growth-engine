<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 8 12 12 14 14"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Keyword Fırsatları', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php printf( esc_html__( '%d fırsat bulundu', 'hge' ), count( $opportunities ) ); ?></p>
            </div>
        </div>
        <?php if ( ! $gsc_connected ): ?>
        <div class="hge-header-right">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=hge-settings' ) ); ?>" class="hge-btn hge-btn-warning">
                <?php esc_html_e( 'GSC Bağla', 'hge' ); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="hge-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'Pozisyon 4–30 Arası Yükseltilebilir Kelimeler', 'hge' ); ?></h3>
            <div class="hge-filter-row">
                <input type="text" id="hge-kw-search" class="hge-input hge-input-sm" placeholder="<?php esc_attr_e( 'Keyword ara...', 'hge' ); ?>">
                <select id="hge-pos-filter" class="hge-select hge-select-sm">
                    <option value=""><?php esc_html_e( 'Tüm Pozisyonlar', 'hge' ); ?></option>
                    <option value="4-10"><?php esc_html_e( '4–10 (Yüksek)', 'hge' ); ?></option>
                    <option value="11-20"><?php esc_html_e( '11–20 (Orta)', 'hge' ); ?></option>
                    <option value="21-30"><?php esc_html_e( '21–30 (Uzak)', 'hge' ); ?></option>
                </select>
            </div>
        </div>
        <div class="hge-card-body hge-table-wrap">
            <?php if ( empty( $opportunities ) ): ?>
                <div class="hge-empty-state">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <p><?php esc_html_e( 'GSC senkronizasyonu tamamlandıktan sonra fırsatlar burada görünecek.', 'hge' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=hge-settings' ) ); ?>" class="hge-btn hge-btn-primary">
                        <?php esc_html_e( 'Ayarlara Git', 'hge' ); ?>
                    </a>
                </div>
            <?php else: ?>
            <table class="hge-table hge-table-sortable" id="hge-opp-table">
                <thead>
                    <tr>
                        <th data-sort="keyword"><?php esc_html_e( 'Keyword', 'hge' ); ?></th>
                        <th data-sort="impressions"><?php esc_html_e( 'Gösterim', 'hge' ); ?></th>
                        <th data-sort="clicks"><?php esc_html_e( 'Tıklama', 'hge' ); ?></th>
                        <th data-sort="ctr"><?php esc_html_e( 'CTR', 'hge' ); ?></th>
                        <th data-sort="avg_position"><?php esc_html_e( 'Pozisyon', 'hge' ); ?></th>
                        <th data-sort="opportunity_score"><?php esc_html_e( 'Fırsat', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Öneri', 'hge' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $opportunities as $row ): ?>
                    <tr data-position="<?php echo esc_attr( $row['avg_position'] ); ?>">
                        <td>
                            <div class="hge-kw-cell">
                                <span class="hge-keyword-cell"><?php echo esc_html( $row['keyword'] ); ?></span>
                                <?php if ( ! empty( $row['page_url'] ) ): ?>
                                <a href="<?php echo esc_url( $row['page_url'] ); ?>" target="_blank" class="hge-url-link" title="<?php echo esc_attr( $row['page_url'] ); ?>">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo esc_html( number_format( $row['impressions'] ) ); ?></td>
                        <td><?php echo esc_html( number_format( $row['clicks'] ) ); ?></td>
                        <td><?php echo esc_html( number_format( $row['ctr'] * 100, 2 ) . '%' ); ?></td>
                        <td>
                            <?php
                            $pos = (float) $row['avg_position'];
                            $cls = $pos <= 10 ? 'top10' : ( $pos <= 20 ? 'top20' : 'out' );
                            ?>
                            <span class="hge-pos-badge hge-pos-<?php echo esc_attr( $cls ); ?>">
                                <?php echo esc_html( number_format( $pos, 1 ) ); ?>
                            </span>
                        </td>
                        <td>
                            <div class="hge-score-wrap">
                                <div class="hge-score-bar">
                                    <div class="hge-score-fill" style="width:<?php echo esc_attr( $row['opportunity_score'] ); ?>%"></div>
                                </div>
                                <span class="hge-score-num"><?php echo esc_html( $row['opportunity_score'] ); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="hge-suggestion-pill">
                                <?php echo esc_html( $row['suggestion'] ); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
