<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Fikir Arşivi', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php esc_html_e( 'Daha önce keşfedilen hesaplama fırsatları', 'hge' ); ?></p>
            </div>
        </div>
        <div class="hge-header-right">
            <a class="hge-btn hge-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=hge-new-ideas' ) ); ?>">
                <?php esc_html_e( 'Yeni Fikirler', 'hge' ); ?>
            </a>
        </div>
    </div>

    <div class="hge-kpi-grid hge-archive-kpis">
        <div class="hge-kpi-card hge-kpi-blue">
            <div class="hge-kpi-content">
                <span class="hge-kpi-label"><?php esc_html_e( 'Toplam kayıt', 'hge' ); ?></span>
                <span class="hge-kpi-value"><?php echo esc_html( number_format_i18n( $summary['total'] ?? 0 ) ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-gold">
            <div class="hge-kpi-content">
                <span class="hge-kpi-label"><?php esc_html_e( 'Sitede yok', 'hge' ); ?></span>
                <span class="hge-kpi-value"><?php echo esc_html( number_format_i18n( $summary['missing'] ?? 0 ) ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-green">
            <div class="hge-kpi-content">
                <span class="hge-kpi-label"><?php esc_html_e( 'Üretim adayı', 'hge' ); ?></span>
                <span class="hge-kpi-value"><?php echo esc_html( number_format_i18n( $summary['should_create'] ?? 0 ) ); ?></span>
            </div>
        </div>
        <div class="hge-kpi-card hge-kpi-purple">
            <div class="hge-kpi-content">
                <span class="hge-kpi-label"><?php esc_html_e( 'Son keşif', 'hge' ); ?></span>
                <span class="hge-kpi-value hge-kpi-date"><?php echo esc_html( $summary['latest_created'] ?: '-' ); ?></span>
            </div>
        </div>
    </div>

    <div class="hge-card hge-archive-card">
        <div class="hge-card-header">
            <h3><?php esc_html_e( 'Kayıtlı Öneriler', 'hge' ); ?></h3>
            <span class="hge-badge"><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?> <?php esc_html_e( 'gösteriliyor', 'hge' ); ?></span>
        </div>
        <div class="hge-card-body">
            <form class="hge-archive-filters" method="get">
                <input type="hidden" name="page" value="hge-suggestion-archive">
                <input class="hge-input" type="search" name="hge_search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Keyword ara', 'hge' ); ?>">
                <select class="hge-select" name="hge_source">
                    <option value=""><?php esc_html_e( 'Tüm kaynaklar', 'hge' ); ?></option>
                    <?php foreach ( (array) ( $summary['sources'] ?? [] ) as $source_row ): ?>
                        <?php $source = (string) ( $source_row['source'] ?? '' ); ?>
                        <option value="<?php echo esc_attr( $source ); ?>" <?php selected( $filters['source'], $source ); ?>>
                            <?php echo esc_html( $source ?: 'unknown' ); ?> (<?php echo esc_html( number_format_i18n( (int) ( $source_row['count'] ?? 0 ) ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="hge-select" name="hge_competition">
                    <option value=""><?php esc_html_e( 'Tüm rekabetler', 'hge' ); ?></option>
                    <?php foreach ( [ 'LOW' => 'Düşük', 'MEDIUM' => 'Orta', 'HIGH' => 'Yüksek', 'UNKNOWN' => 'Bilinmiyor' ] as $value => $label ): ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( strtoupper( $filters['competition'] ), $value ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="hge-select" name="hge_status">
                    <option value=""><?php esc_html_e( 'Tüm durumlar', 'hge' ); ?></option>
                    <option value="missing" <?php selected( $filters['status'], 'missing' ); ?>><?php esc_html_e( 'Sitede yok', 'hge' ); ?></option>
                    <option value="existing" <?php selected( $filters['status'], 'existing' ); ?>><?php esc_html_e( 'Sitede var', 'hge' ); ?></option>
                    <option value="should_create" <?php selected( $filters['status'], 'should_create' ); ?>><?php esc_html_e( 'Üretim adayı', 'hge' ); ?></option>
                </select>
                <select class="hge-select" name="hge_limit">
                    <?php foreach ( [ 100, 300, 500 ] as $limit ): ?>
                        <option value="<?php echo esc_attr( $limit ); ?>" <?php selected( (int) $filters['limit'], $limit ); ?>><?php echo esc_html( $limit ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="hge-btn hge-btn-primary" type="submit"><?php esc_html_e( 'Filtrele', 'hge' ); ?></button>
                <a class="hge-btn hge-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=hge-suggestion-archive' ) ); ?>"><?php esc_html_e( 'Sıfırla', 'hge' ); ?></a>
            </form>

            <div class="hge-table-wrap">
                <table class="hge-table hge-table-sortable hge-archive-table">
                    <thead>
                        <tr>
                            <th data-sort="topic"><?php esc_html_e( 'Öneri', 'hge' ); ?></th>
                            <th data-sort="volume"><?php esc_html_e( 'Aranma Hacmi', 'hge' ); ?></th>
                            <th data-sort="competition"><?php esc_html_e( 'Rekabet', 'hge' ); ?></th>
                            <th data-sort="score"><?php esc_html_e( 'Zorluk / Skor', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Sitede', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Kaynak', 'hge' ); ?></th>
                            <th><?php esc_html_e( 'Tarih', 'hge' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $rows ) ): ?>
                            <tr><td colspan="7"><?php esc_html_e( 'Kayıt bulunamadı.', 'hge' ); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ( $rows as $row ): ?>
                            <?php
                            $competition = strtoupper( (string) ( $row['competition'] ?? 'UNKNOWN' ) );
                            $score       = (int) ( $row['opportunity_score'] ?? 0 );
                            $exists      = ! empty( $row['exists_on_site'] );
                            $created_ts  = ! empty( $row['created_at'] ) ? strtotime( (string) $row['created_at'] ) : 0;
                            $comp_labels = [ 'LOW' => 'Düşük', 'MEDIUM' => 'Orta', 'HIGH' => 'Yüksek', 'UNKNOWN' => 'Bilinmiyor' ];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $row['topic'] ?? '' ); ?></strong>
                                    <?php if ( ! empty( $row['should_create'] ) ): ?>
                                        <span class="hge-badge hge-badge-warning"><?php esc_html_e( 'Üret', 'hge' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-sort-value="<?php echo esc_attr( (int) ( $row['monthly_volume'] ?? 0 ) ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $row['monthly_volume'] ?? 0 ) ) ); ?></td>
                                <td><span class="hge-competition hge-competition-<?php echo esc_attr( strtolower( $competition ) ); ?>"><?php echo esc_html( $comp_labels[ $competition ] ?? $competition ); ?></span></td>
                                <td data-sort-value="<?php echo esc_attr( $score ); ?>">
                                    <div class="hge-score-cell">
                                        <strong><?php echo esc_html( $score ); ?></strong>
                                        <span><i style="width:<?php echo esc_attr( min( 100, max( 0, $score ) ) ); ?>%"></i></span>
                                    </div>
                                </td>
                                <td><?php echo $exists ? esc_html__( 'Var', 'hge' ) : esc_html__( 'Yok', 'hge' ); ?></td>
                                <td><code><?php echo esc_html( $row['source'] ?? '' ); ?></code></td>
                                <td data-sort-value="<?php echo esc_attr( $created_ts ?: 0 ); ?>"><?php echo esc_html( $row['created_at'] ?? '-' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
