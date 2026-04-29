<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Yeni Hesaplama Fikirleri', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php printf( esc_html__( 'Google Suggest\'ten %d konu önerisi', 'hge' ), count( $suggestions ) ); ?></p>
            </div>
        </div>
        <div class="hge-header-right">
            <button class="hge-btn hge-btn-secondary" id="hge-refresh-suggestions">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <?php esc_html_e( 'Yenile', 'hge' ); ?>
            </button>
        </div>
    </div>

    <div class="hge-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'Konu Fırsatları', 'hge' ); ?></h3>
            <div class="hge-filter-row">
                <label class="hge-toggle-label">
                    <input type="checkbox" id="hge-only-new">
                    <?php esc_html_e( 'Sadece Sitede Olmayanlar', 'hge' ); ?>
                </label>
            </div>
        </div>
        <div class="hge-card-body hge-table-wrap">
            <?php if ( empty( $suggestions ) ): ?>
            <div class="hge-empty-state">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                <p><?php esc_html_e( 'Henüz öneri yok. "Yenile" butonuna basın.', 'hge' ); ?></p>
            </div>
            <?php else: ?>
            <table class="hge-table" id="hge-ideas-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Konu', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Aylık Hacim', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Rekabet', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Fırsat', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Sitede Var?', 'hge' ); ?></th>
                        <th><?php esc_html_e( 'Öneri', 'hge' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $suggestions as $row ): ?>
                    <tr data-exists="<?php echo esc_attr( $row['exists_on_site'] ); ?>">
                        <td>
                            <span class="hge-keyword-cell"><?php echo esc_html( $row['topic'] ); ?></span>
                        </td>
                        <td>
                            <?php if ( $row['monthly_volume'] > 0 ): ?>
                                <strong><?php echo esc_html( number_format( $row['monthly_volume'] ) ); ?></strong>
                            <?php else: ?>
                                <span class="hge-text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $comp     = $row['competition'];
                            switch ( $comp ) { case 'HIGH':   $comp_cls = 'red'; break; case 'MEDIUM': $comp_cls = 'orange'; break; case 'LOW':    $comp_cls = 'green'; break; default:        $comp_cls = 'muted'; } // match_end
                                'HIGH'   => 'red',
                                'MEDIUM' => 'orange',
                                'LOW'    => 'green',
                                default  => 'muted',
                            };
                            ?>
                            <span class="hge-comp-badge hge-comp-<?php echo esc_attr( $comp_cls ); ?>">
                                <?php echo esc_html( ucfirst( strtolower( $comp ) ) ); ?>
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
                            <?php if ( $row['exists_on_site'] ): ?>
                                <span class="hge-badge hge-badge-success"><?php esc_html_e( 'Var', 'hge' ); ?></span>
                            <?php else: ?>
                                <span class="hge-badge hge-badge-error"><?php esc_html_e( 'Yok', 'hge' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $row['should_create'] ): ?>
                                <span class="hge-suggestion-pill hge-suggestion-hot"><?php esc_html_e( '🔥 Hemen Aç', 'hge' ); ?></span>
                            <?php else: ?>
                                <span class="hge-suggestion-pill"><?php esc_html_e( 'İzle', 'hge' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
