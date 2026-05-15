<?php defined( 'ABSPATH' ) || exit; ?>
<?php
$items      = $list['items'] ?? [];
$pagination = $list['pagination'] ?? [ 'total' => 0, 'pages' => 1, 'paged' => 1, 'limit' => 50 ];
$current_view = $filters['view'] ?? 'all';
$base_url     = admin_url( 'admin.php' );
$page_url     = admin_url( 'admin.php?page=hge-seo-radar' );
$current_args = [
    'page'                 => 'hge-seo-radar',
    'hge_radar_view'       => $current_view,
    'hge_search'           => $filters['search'] ?? '',
    'hge_days'             => (int) ( $filters['days'] ?? 28 ),
    'hge_limit'            => (int) ( $filters['limit'] ?? 50 ),
    'hge_sort'             => $filters['sort'] ?? 'opportunity',
    'hge_order'            => $filters['order'] ?? 'desc',
    'hge_position_band'    => $filters['position_band'] ?? '',
    'hge_volume_band'      => $filters['volume_band'] ?? '',
    'hge_ctr_band'         => $filters['ctr_band'] ?? '',
    'hge_competition'      => $filters['competition'] ?? '',
    'hge_url_type'         => $filters['url_type'] ?? '',
    'hge_quality_band'     => $filters['quality_band'] ?? '',
    'hge_low_ctr'          => ! empty( $filters['low_ctr_only'] ) ? 1 : 0,
    'hge_high_impressions' => ! empty( $filters['high_impressions_only'] ) ? 1 : 0,
    'hge_high_volume'      => ! empty( $filters['high_volume_only'] ) ? 1 : 0,
    'hge_low_competition'  => ! empty( $filters['low_competition_only'] ) ? 1 : 0,
    'hge_intent'           => ! empty( $filters['intent_only'] ) ? 1 : 0,
    'hge_quality'          => ! empty( $filters['quality_only'] ) ? 1 : 0,
];

$clean_args = array_filter(
    $current_args,
    static function ( $value, $key ) {
        if ( 'page' === $key || 'hge_days' === $key || 'hge_limit' === $key || 'hge_radar_view' === $key || 'hge_sort' === $key || 'hge_order' === $key ) {
            return true;
        }

        return '' !== $value && null !== $value && false !== $value && 0 !== $value;
    },
    ARRAY_FILTER_USE_BOTH
);

global $wpdb;
$seo_table = $wpdb->prefix . 'hge_seo_opportunities';
$days      = ! empty( $filters['days'] ) ? (int) $filters['days'] : 28;
$date_to   = gmdate( 'Y-m-d' );
$date_from = gmdate( 'Y-m-d', strtotime( '-' . max( 1, $days - 1 ) . ' days' ) );
$summary_sql = $wpdb->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('Acil Büyüt', 'Hızlı Kazanım') THEN 1 ELSE 0 END) AS quick_wins,
        SUM(CASE WHEN position BETWEEN 4 AND 10 THEN 1 ELSE 0 END) AS near_top10,
        SUM(CASE WHEN quality_status NOT IN ('Sağlıklı', 'Kontrol bekliyor', 'Kategori arşivi', 'Kategori URL', 'Arşiv URL') AND quality_status <> '' THEN 1 ELSE 0 END) AS quality_issues,
        SUM(CASE WHEN recommended_actions_json LIKE '%\"url_type\":\"category\"%' THEN 1 ELSE 0 END) AS categories,
        SUM(CASE WHEN recommended_actions_json NOT LIKE '%\"url_type\":\"category\"%' AND recommended_actions_json NOT LIKE '%\"url_type\":\"tag\"%' AND recommended_actions_json NOT LIKE '%\"url_type\":\"author\"%' AND recommended_actions_json NOT LIKE '%\"url_type\":\"archive\"%' THEN 1 ELSE 0 END) AS keywords,
        SUM(CASE WHEN post_id > 0 THEN 1 ELSE 0 END) AS pages,
        SUM(clicks) AS total_clicks,
        SUM(impressions) AS total_impressions,
        AVG(position) AS avg_position
     FROM {$seo_table}
     WHERE date_from = %s AND date_to = %s",
    $date_from,
    $date_to
);
$summary_totals = $wpdb->get_row( $summary_sql, ARRAY_A ) ?: [];
$summary_totals = wp_parse_args(
    $summary_totals,
    [
        'total'             => 0,
        'quick_wins'        => 0,
        'near_top10'        => 0,
        'quality_issues'    => 0,
        'categories'        => 0,
        'keywords'          => 0,
        'pages'             => 0,
        'total_clicks'      => 0,
        'total_impressions' => 0,
        'avg_position'      => 0,
    ]
);
$summary_totals['avg_ctr'] = ! empty( $summary_totals['total_impressions'] )
    ? ( (float) $summary_totals['total_clicks'] / (float) $summary_totals['total_impressions'] )
    : 0.0;

$views = [
    'all'        => __( 'Tüm Fırsatlar', 'hge' ),
    'quick-wins' => __( 'Hızlı Kazanımlar', 'hge' ),
    'near-top10' => __( 'İlk 10’a Yakın', 'hge' ),
    'keywords'   => __( 'Anahtar Kelimeler', 'hge' ),
    'pages'      => __( 'Sayfalar', 'hge' ),
    'quality'    => __( 'Kalite Sorunları', 'hge' ),
    'categories' => __( 'Kategoriler', 'hge' ),
];
$tab_counts = [
    'all'        => (int) $summary_totals['total'],
    'quick-wins' => (int) $summary_totals['quick_wins'],
    'near-top10' => (int) $summary_totals['near_top10'],
    'keywords'   => (int) $summary_totals['keywords'],
    'pages'      => (int) $summary_totals['pages'],
    'quality'    => (int) $summary_totals['quality_issues'],
    'categories' => (int) $summary_totals['categories'],
];
$active_view_label = $views[ $current_view ] ?? $views['all'];
$active_total      = (int) ( $pagination['total'] ?? 0 );
$current_page      = max( 1, (int) ( $pagination['paged'] ?? 1 ) );
$per_page          = max( 1, (int) ( $pagination['limit'] ?? 50 ) );
$range_start       = $active_total > 0 ? ( ( $current_page - 1 ) * $per_page ) + 1 : 0;
$range_end         = min( $active_total, $current_page * $per_page );
$all_results_url   = $reset_url ?? add_query_arg(
    [
        'page'           => 'hge-seo-radar',
        'hge_radar_view' => 'all',
        'hge_days'       => (int) ( $filters['days'] ?? 28 ),
        'hge_limit'      => 50,
        'paged'          => 1,
    ],
    $base_url
);
$clear_url         = $all_results_url;
$current_sort      = sanitize_key( (string) ( $filters['sort'] ?? 'opportunity' ) );
$current_order     = 'asc' === strtolower( (string) ( $filters['order'] ?? 'desc' ) ) ? 'asc' : 'desc';

$build_page_url = static function ( int $page_number ) use ( $base_url, $clean_args ) {
    return add_query_arg(
        array_merge(
            $clean_args,
            [
                'paged' => $page_number,
            ]
        ),
        $base_url
    );
};

$format_compact_path = static function ( string $url ) {
    $path = wp_parse_url( $url, PHP_URL_PATH );
    if ( ! is_string( $path ) || '' === $path ) {
        return $url;
    }

    return '/' === $path ? '/' : trailingslashit( $path );
};

$position_class = static function ( float $position ) {
    if ( $position > 0 && $position <= 3 ) {
        return 'is-top3';
    }
    if ( $position <= 10 ) {
        return 'is-top10';
    }
    if ( $position <= 20 ) {
        return 'is-top20';
    }
    if ( $position <= 50 ) {
        return 'is-top50';
    }

    return 'is-beyond';
};

$score_meta = static function ( int $score ) {
    if ( $score >= 85 ) {
        return [ 'label' => __( 'Acil', 'hge' ), 'class' => 'is-critical' ];
    }
    if ( $score >= 70 ) {
        return [ 'label' => __( 'Yüksek', 'hge' ), 'class' => 'is-high' ];
    }
    if ( $score >= 50 ) {
        return [ 'label' => __( 'Orta', 'hge' ), 'class' => 'is-medium' ];
    }
    if ( $score >= 30 ) {
        return [ 'label' => __( 'İzle', 'hge' ), 'class' => 'is-watch' ];
    }

    return [ 'label' => __( 'Düşük', 'hge' ), 'class' => 'is-low' ];
};

$competition_class = static function ( string $competition ) {
    switch ( strtoupper( $competition ) ) {
        case 'LOW':
            return 'is-low';
        case 'MEDIUM':
            return 'is-medium';
        case 'HIGH':
            return 'is-high';
        default:
            return 'is-muted';
    }
};

$status_class = static function ( string $status ) {
    $map = [
        'Acil Büyüt'                    => 'is-positive',
        'Hızlı Kazanım'                 => 'is-info',
        'Destekle'                      => 'is-warn',
        'İzle'                          => 'is-muted',
        'Önce kalite sorunu çözülmeli'  => 'is-danger',
        'Kategori URL'                  => 'is-category',
    ];

    return $map[ $status ] ?? 'is-muted';
};

$quality_class = static function ( string $quality ) {
    if ( '' === $quality || __( 'Kontrol bekliyor', 'hge' ) === $quality || 'Kontrol bekliyor' === $quality ) {
        return 'is-muted';
    }
    if ( false !== stripos( $quality, 'Sağlıklı' ) ) {
        return 'is-positive';
    }
    if ( false !== stripos( $quality, 'Kategori' ) || false !== stripos( $quality, 'Arşiv' ) ) {
        return 'is-category';
    }
    if ( false !== stripos( $quality, 'Kontrol' ) ) {
        return 'is-warn';
    }

    return 'is-danger';
};

$build_sort_url = static function ( string $sort_key ) use ( $base_url, $clean_args, $current_sort, $current_order ) {
    $next_order = ( $current_sort === $sort_key && 'asc' === $current_order ) ? 'desc' : 'asc';

    return add_query_arg(
        array_merge(
            $clean_args,
            [
                'hge_sort'  => $sort_key,
                'hge_order' => $next_order,
                'paged'     => 1,
            ]
        ),
        $base_url
    );
};

$render_sort_header = static function ( string $label, string $sort_key ) use ( $build_sort_url, $current_sort, $current_order ) {
    $is_active = $current_sort === $sort_key;
    $arrow     = $is_active ? ( 'asc' === $current_order ? '↑' : '↓' ) : '↕';
    $class     = $is_active ? ' is-active' : '';

    return sprintf(
        '<a class="hge-radar-sort-link%1$s" href="%2$s"><span>%3$s</span><span class="hge-radar-sort-link__icon" aria-hidden="true">%4$s</span></a>',
        esc_attr( $class ),
        esc_url( $build_sort_url( $sort_key ) ),
        esc_html( $label ),
        esc_html( $arrow )
    );
};
?>
<div class="hge-wrap hge-seo-radar-page">
    <div class="hge-radar-shell">
        <div class="hge-radar-topbar">
            <div class="hge-radar-topbar__title">
                <div class="hge-radar-topbar__icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>
                </div>
                <div>
                    <h1 class="hge-page-title"><?php esc_html_e( 'SEO Radar', 'hge' ); ?></h1>
                    <p class="hge-page-subtitle"><?php esc_html_e( 'GSC + Keyword Planner verilerinden fırsat, kalite ve büyüme analizi', 'hge' ); ?></p>
                </div>
            </div>
            <div class="hge-radar-topbar__actions">
                <select class="hge-select hge-radar-topbar__days" name="hge_days" form="hge-radar-filter-form">
                    <?php foreach ( [ 7, 28, 90 ] as $days_option ): ?>
                        <option value="<?php echo esc_attr( $days_option ); ?>" <?php selected( (int) ( $filters['days'] ?? 28 ), $days_option ); ?>>
                            <?php echo esc_html( sprintf( __( 'Son %d Gün', 'hge' ), $days_option ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="hge-btn hge-btn-primary" id="hge-seo-radar-refresh" type="button"><?php esc_html_e( 'Fırsatları Hesapla', 'hge' ); ?></button>
                <a class="hge-btn hge-btn-secondary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'CSV Dışa Aktar', 'hge' ); ?></a>
                <a class="hge-btn hge-btn-secondary hge-radar-reset-trigger" href="<?php echo esc_url( $all_results_url ); ?>" data-reset-url="<?php echo esc_url( $all_results_url ); ?>"><?php esc_html_e( 'Tüm sonuçları göster', 'hge' ); ?></a>
            </div>
        </div>

        <div class="hge-radar-metrics">
            <?php
            $metric_cards = [
                [ 'label' => __( 'Toplam Fırsat', 'hge' ), 'value' => number_format_i18n( (int) $summary_totals['total'] ), 'tone' => 'blue' ],
                [ 'label' => __( 'Hızlı Kazanım', 'hge' ), 'value' => number_format_i18n( (int) $summary_totals['quick_wins'] ), 'tone' => 'green' ],
                [ 'label' => __( 'İlk 10’a Yakın', 'hge' ), 'value' => number_format_i18n( (int) $summary_totals['near_top10'] ), 'tone' => 'purple' ],
                [ 'label' => __( 'Kalite Sorunu', 'hge' ), 'value' => number_format_i18n( (int) $summary_totals['quality_issues'] ), 'tone' => 'red' ],
                [ 'label' => __( 'Toplam Impression', 'hge' ), 'value' => number_format_i18n( (int) $summary_totals['total_impressions'] ), 'tone' => 'amber' ],
                [ 'label' => __( 'Toplam Click', 'hge' ), 'value' => number_format_i18n( (int) $summary_totals['total_clicks'] ), 'tone' => 'blue' ],
                [ 'label' => __( 'Ortalama CTR', 'hge' ), 'value' => number_format_i18n( (float) $summary_totals['avg_ctr'] * 100, 2 ) . '%', 'tone' => 'green' ],
                [ 'label' => __( 'Ortalama Pozisyon', 'hge' ), 'value' => number_format_i18n( (float) $summary_totals['avg_position'], 1 ), 'tone' => 'purple' ],
            ];
            ?>
            <?php foreach ( $metric_cards as $card ): ?>
                <div class="hge-radar-metric hge-radar-metric--<?php echo esc_attr( $card['tone'] ); ?>">
                    <span class="hge-radar-metric__label"><?php echo esc_html( $card['label'] ); ?></span>
                    <strong class="hge-radar-metric__value"><?php echo esc_html( $card['value'] ); ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="hge-radar-tabs">
            <?php foreach ( $views as $view_key => $label ): ?>
                <?php
                $tab_url = add_query_arg(
                    array_merge(
                        $clean_args,
                        [
                            'hge_radar_view' => $view_key,
                            'paged'          => 1,
                        ]
                    ),
                    $base_url
                );
                ?>
                <a class="hge-radar-tabs__pill<?php echo $current_view === $view_key ? ' is-active' : ''; ?>" href="<?php echo esc_url( $tab_url ); ?>">
                    <span><?php echo esc_html( $label ); ?></span>
                    <span class="hge-radar-tabs__count"><?php echo esc_html( number_format_i18n( (int) ( $tab_counts[ $view_key ] ?? 0 ) ) ); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form id="hge-radar-filter-form" class="hge-radar-filterbar" method="get" action="<?php echo esc_url( $base_url ); ?>">
            <input type="hidden" name="page" value="hge-seo-radar">
            <input type="hidden" name="hge_radar_view" value="<?php echo esc_attr( $current_view ); ?>">
            <input type="hidden" name="paged" value="1">
            <div class="hge-radar-filterbar__row">
                <input class="hge-input" type="search" name="hge_search" value="<?php echo esc_attr( $filters['search'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Keyword veya URL ara', 'hge' ); ?>">
                <select class="hge-select" name="hge_position_band">
                    <option value=""><?php esc_html_e( 'Pozisyon', 'hge' ); ?></option>
                    <option value="1-3" <?php selected( $filters['position_band'] ?? '', '1-3' ); ?>>1-3</option>
                    <option value="4-10" <?php selected( $filters['position_band'] ?? '', '4-10' ); ?>>4-10</option>
                    <option value="11-20" <?php selected( $filters['position_band'] ?? '', '11-20' ); ?>>11-20</option>
                    <option value="21-50" <?php selected( $filters['position_band'] ?? '', '21-50' ); ?>>21-50</option>
                    <option value="50+" <?php selected( $filters['position_band'] ?? '', '50+' ); ?>>50+</option>
                </select>
                <select class="hge-select" name="hge_volume_band">
                    <option value=""><?php esc_html_e( 'Hacim', 'hge' ); ?></option>
                    <option value="1000+" <?php selected( $filters['volume_band'] ?? '', '1000+' ); ?>>1.000+</option>
                    <option value="2500+" <?php selected( $filters['volume_band'] ?? '', '2500+' ); ?>>2.500+</option>
                    <option value="5000+" <?php selected( $filters['volume_band'] ?? '', '5000+' ); ?>>5.000+</option>
                </select>
                <select class="hge-select" name="hge_ctr_band">
                    <option value=""><?php esc_html_e( 'CTR', 'hge' ); ?></option>
                    <option value="lt1" <?php selected( $filters['ctr_band'] ?? '', 'lt1' ); ?>>&lt; 1%</option>
                    <option value="lt3" <?php selected( $filters['ctr_band'] ?? '', 'lt3' ); ?>>&lt; 3%</option>
                    <option value="gte3" <?php selected( $filters['ctr_band'] ?? '', 'gte3' ); ?>>3%+</option>
                </select>
                <select class="hge-select" name="hge_competition">
                    <option value=""><?php esc_html_e( 'Competition', 'hge' ); ?></option>
                    <?php foreach ( [ 'LOW', 'MEDIUM', 'HIGH', 'UNKNOWN' ] as $comp_option ): ?>
                        <option value="<?php echo esc_attr( $comp_option ); ?>" <?php selected( strtoupper( (string) ( $filters['competition'] ?? '' ) ), $comp_option ); ?>><?php echo esc_html( $comp_option ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="hge-select" name="hge_url_type">
                    <option value=""><?php esc_html_e( 'URL Tipi', 'hge' ); ?></option>
                    <option value="content" <?php selected( $filters['url_type'] ?? '', 'content' ); ?>><?php esc_html_e( 'İçerik', 'hge' ); ?></option>
                    <option value="category" <?php selected( $filters['url_type'] ?? '', 'category' ); ?>><?php esc_html_e( 'Kategori', 'hge' ); ?></option>
                    <option value="archive" <?php selected( $filters['url_type'] ?? '', 'archive' ); ?>><?php esc_html_e( 'Arşiv', 'hge' ); ?></option>
                    <option value="unknown" <?php selected( $filters['url_type'] ?? '', 'unknown' ); ?>><?php esc_html_e( 'Bilinmiyor', 'hge' ); ?></option>
                </select>
                <select class="hge-select" name="hge_quality_band">
                    <option value=""><?php esc_html_e( 'Kalite', 'hge' ); ?></option>
                    <option value="issues" <?php selected( $filters['quality_band'] ?? '', 'issues' ); ?>><?php esc_html_e( 'Sorunlu', 'hge' ); ?></option>
                    <option value="healthy" <?php selected( $filters['quality_band'] ?? '', 'healthy' ); ?>><?php esc_html_e( 'Sağlıklı', 'hge' ); ?></option>
                    <option value="pending" <?php selected( $filters['quality_band'] ?? '', 'pending' ); ?>><?php esc_html_e( 'Kontrol bekliyor', 'hge' ); ?></option>
                    <option value="category" <?php selected( $filters['quality_band'] ?? '', 'category' ); ?>><?php esc_html_e( 'Kategori/Arşiv', 'hge' ); ?></option>
                </select>
                <select class="hge-select" name="hge_limit">
                    <?php foreach ( [ 50, 100, 250 ] as $limit_option ): ?>
                        <option value="<?php echo esc_attr( $limit_option ); ?>" <?php selected( (int) ( $filters['limit'] ?? 50 ), $limit_option ); ?>><?php echo esc_html( sprintf( __( '%d satır', 'hge' ), $limit_option ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="hge-btn hge-btn-secondary" type="submit"><?php esc_html_e( 'Filtrele', 'hge' ); ?></button>
                <a class="hge-radar-filterbar__clear hge-radar-reset-trigger" href="<?php echo esc_url( $clear_url ); ?>" data-reset-url="<?php echo esc_url( $clear_url ); ?>"><?php esc_html_e( 'Temizle', 'hge' ); ?></a>
            </div>
            <div class="hge-radar-filterbar__chips">
                <label class="hge-radar-chip"><input type="checkbox" name="hge_low_ctr" value="1" <?php checked( ! empty( $filters['low_ctr_only'] ) ); ?>> <span><?php esc_html_e( 'Düşük CTR', 'hge' ); ?></span></label>
                <label class="hge-radar-chip"><input type="checkbox" name="hge_high_impressions" value="1" <?php checked( ! empty( $filters['high_impressions_only'] ) ); ?>> <span><?php esc_html_e( 'Yüksek impression', 'hge' ); ?></span></label>
                <label class="hge-radar-chip"><input type="checkbox" name="hge_high_volume" value="1" <?php checked( ! empty( $filters['high_volume_only'] ) ); ?>> <span><?php esc_html_e( 'Yüksek hacim', 'hge' ); ?></span></label>
                <label class="hge-radar-chip"><input type="checkbox" name="hge_low_competition" value="1" <?php checked( ! empty( $filters['low_competition_only'] ) ); ?>> <span><?php esc_html_e( 'Düşük/orta competition', 'hge' ); ?></span></label>
                <label class="hge-radar-chip"><input type="checkbox" name="hge_intent" value="1" <?php checked( ! empty( $filters['intent_only'] ) ); ?>> <span><?php esc_html_e( 'Hesaplama niyeti', 'hge' ); ?></span></label>
                <label class="hge-radar-chip"><input type="checkbox" name="hge_quality" value="1" <?php checked( ! empty( $filters['quality_only'] ) ); ?>> <span><?php esc_html_e( 'Kalite sorunu olanlar', 'hge' ); ?></span></label>
                <a class="hge-radar-chip hge-radar-chip--cta hge-radar-reset-trigger" href="<?php echo esc_url( $all_results_url ); ?>" data-reset-url="<?php echo esc_url( $all_results_url ); ?>"><?php esc_html_e( 'Tüm sonuçları göster', 'hge' ); ?></a>
            </div>
        </form>

        <div class="hge-radar-table-wrap">
            <div class="hge-radar-table__toolbar">
                <div>
                    <h3><?php esc_html_e( 'Radar Sonuçları', 'hge' ); ?></h3>
                    <p class="hge-radar-table__viewinfo" id="hge-seo-radar-status"><?php echo esc_html( sprintf( __( '%1$s gösteriliyor: %2$s sonuç', 'hge' ), $active_view_label, number_format_i18n( $active_total ) ) ); ?></p>
                </div>
                <div class="hge-radar-table__range">
                    <?php echo esc_html( $active_total > 0 ? sprintf( '%1$s-%2$s / %3$s', number_format_i18n( $range_start ), number_format_i18n( $range_end ), number_format_i18n( $active_total ) ) : sprintf( '0 / %s', number_format_i18n( $active_total ) ) ); ?>
                </div>
            </div>

            <?php if ( empty( $items ) ): ?>
                <div class="hge-radar-empty">
                    <div class="hge-radar-empty__icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <h3><?php esc_html_e( 'Bu filtreyle sonuç yok', 'hge' ); ?></h3>
                    <p><?php esc_html_e( 'Aktif görünüm veya filtreler sonucu daraltmış olabilir. Tüm sonuçlara dönüp veri setini yeniden inceleyin.', 'hge' ); ?></p>
                    <div class="hge-radar-empty__actions">
                        <a class="hge-btn hge-btn-secondary hge-radar-reset-trigger" href="<?php echo esc_url( $all_results_url ); ?>" data-reset-url="<?php echo esc_url( $all_results_url ); ?>"><?php esc_html_e( 'Tüm sonuçları göster', 'hge' ); ?></a>
                        <button class="hge-btn hge-btn-primary" id="hge-seo-radar-refresh-empty" type="button"><?php esc_html_e( 'Fırsatları Hesapla', 'hge' ); ?></button>
                    </div>
                </div>
            <?php else: ?>
                <table class="hge-radar-table">
                    <colgroup>
                        <col style="width:22%">
                        <col style="width:19%">
                        <col style="width:13%">
                        <col style="width:8%">
                        <col style="width:12%">
                        <col style="width:8%">
                        <col style="width:12%">
                        <col style="width:6%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th><?php echo wp_kses_post( $render_sort_header( __( 'Anahtar Kelime', 'hge' ), 'keyword' ) ); ?></th>
                            <th><?php echo wp_kses_post( $render_sort_header( __( 'URL', 'hge' ), 'url' ) ); ?></th>
                            <th><?php echo wp_kses_post( $render_sort_header( __( 'Metrikler', 'hge' ), 'impressions' ) ); ?></th>
                            <th><?php echo wp_kses_post( $render_sort_header( __( 'Pozisyon', 'hge' ), 'position' ) ); ?></th>
                            <th><?php echo wp_kses_post( $render_sort_header( __( 'Hacim / Rekabet', 'hge' ), 'volume' ) ); ?></th>
                            <th><?php echo wp_kses_post( $render_sort_header( __( 'Fırsat', 'hge' ), 'opportunity' ) ); ?></th>
                            <th><?php echo wp_kses_post( $render_sort_header( __( 'Durum / Kalite', 'hge' ), 'status' ) ); ?></th>
                            <th><?php esc_html_e( 'Aksiyonlar', 'hge' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $items as $row ): ?>
                            <?php
                            $path      = $format_compact_path( (string) $row['page_url'] );
                            $quality   = (string) ( $row['quality_status'] ?: __( 'Kontrol bekliyor', 'hge' ) );
                            $score     = (int) ( $row['opportunity_score'] ?? 0 );
                            $score_ui  = $score_meta( $score );
                            $detail    = [
                                'id'                  => (int) $row['id'],
                                'keyword'             => (string) $row['keyword'],
                                'page_url'            => (string) $row['page_url'],
                                'path'                => (string) $path,
                                'post_id'             => (int) $row['post_id'],
                                'clicks'              => (int) $row['clicks'],
                                'impressions'         => (int) $row['impressions'],
                                'ctr'                 => (float) $row['ctr'],
                                'position'            => (float) $row['position'],
                                'search_volume'       => (int) $row['search_volume'],
                                'competition'         => (string) ( $row['competition'] ?: 'UNKNOWN' ),
                                'opportunity_score'   => $score,
                                'score_label'         => (string) $score_ui['label'],
                                'status'              => (string) $row['status'],
                                'quality_status'      => $quality,
                                'recommended_actions' => array_values( (array) ( $row['recommended_actions'] ?? [] ) ),
                                'updated_at'          => (string) ( $row['updated_at'] ?? '' ),
                            ];
                            ?>
                            <tr class="hge-radar-row" data-radar-id="<?php echo esc_attr( $row['id'] ); ?>" data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>" data-page-url="<?php echo esc_attr( $row['page_url'] ); ?>" data-detail="<?php echo esc_attr( wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?>">
                                <td>
                                    <button class="hge-radar-table__keyword hge-radar-detail-trigger" type="button">
                                        <span class="hge-radar-table__ellipsis"><?php echo esc_html( $row['keyword'] ); ?></span>
                                        <span class="hge-radar-pill hge-radar-pill--tr">TR</span>
                                    </button>
                                </td>
                                <td title="<?php echo esc_attr( $row['page_url'] ); ?>">
                                    <a class="hge-radar-table__url" href="<?php echo esc_url( $row['page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $path ); ?></a>
                                    <span class="hge-radar-table__sub"><?php echo esc_html( sprintf( __( 'Post #%d', 'hge' ), (int) $row['post_id'] ) ); ?></span>
                                </td>
                                <td>
                                    <div class="hge-radar-stack">
                                        <span><?php echo esc_html( 'C: ' . number_format_i18n( (int) $row['clicks'] ) ); ?></span>
                                        <span><?php echo esc_html( 'I: ' . number_format_i18n( (int) $row['impressions'] ) ); ?></span>
                                        <span class="hge-radar-table__ctr<?php echo ( (float) $row['ctr'] < 0.01 ) ? ' is-alert' : ( (float) $row['ctr'] >= 0.03 ? ' is-good' : '' ); ?>">
                                            <?php echo esc_html( 'CTR: ' . number_format_i18n( (float) $row['ctr'] * 100, 2 ) . '%' ); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="hge-radar-pill hge-radar-pill--position <?php echo esc_attr( $position_class( (float) $row['position'] ) ); ?>">
                                        <?php echo esc_html( number_format_i18n( (float) $row['position'], 1 ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="hge-radar-stack">
                                        <span><?php echo esc_html( sprintf( __( 'Hacim: %s', 'hge' ), number_format_i18n( (int) $row['search_volume'] ) ) ); ?></span>
                                        <span class="hge-radar-pill hge-radar-pill--competition <?php echo esc_attr( $competition_class( (string) ( $row['competition'] ?: 'UNKNOWN' ) ) ); ?>">
                                            <?php echo esc_html( strtoupper( (string) ( $row['competition'] ?: 'UNKNOWN' ) ) ); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="hge-radar-score">
                                        <span class="hge-radar-pill hge-radar-pill--score <?php echo esc_attr( $score_ui['class'] ); ?>"><?php echo esc_html( $score ); ?></span>
                                        <span class="hge-radar-score__label"><?php echo esc_html( $score_ui['label'] ); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="hge-radar-stack">
                                        <span class="hge-radar-pill hge-radar-pill--status <?php echo esc_attr( $status_class( (string) $row['status'] ) ); ?>"><?php echo esc_html( $row['status'] ); ?></span>
                                        <span class="hge-radar-pill hge-radar-pill--quality <?php echo esc_attr( $quality_class( $quality ) ); ?> hge-radar-quality-text"><?php echo esc_html( $quality ); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="hge-radar-actions">
                                        <button class="hge-radar-actions__btn hge-radar-detail-trigger" type="button" title="<?php esc_attr_e( 'Detay', 'hge' ); ?>">D</button>
                                        <a class="hge-radar-actions__btn" href="<?php echo esc_url( 'https://www.google.com/search?q=' . rawurlencode( (string) $row['keyword'] ) ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'SERP', 'hge' ); ?>">S</a>
                                        <button class="hge-radar-actions__btn hge-radar-quality-check" type="button" title="<?php esc_attr_e( 'Kalite', 'hge' ); ?>">K</button>
                                        <button class="hge-radar-actions__btn hge-radar-ai-suggest" type="button" title="<?php esc_attr_e( 'AI', 'hge' ); ?>">AI</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="hge-radar-pagination">
                    <span class="hge-radar-pagination__summary"><?php echo esc_html( $active_total > 0 ? sprintf( '%1$s-%2$s / %3$s', number_format_i18n( $range_start ), number_format_i18n( $range_end ), number_format_i18n( $active_total ) ) : sprintf( '0 / %s', number_format_i18n( $active_total ) ) ); ?></span>
                    <div class="hge-radar-pagination__links">
                        <?php if ( $current_page > 1 ): ?>
                            <a class="hge-btn hge-btn-secondary" href="<?php echo esc_url( $build_page_url( $current_page - 1 ) ); ?>"><?php esc_html_e( 'Önceki', 'hge' ); ?></a>
                        <?php endif; ?>
                        <?php if ( $current_page < (int) ( $pagination['pages'] ?? 1 ) ): ?>
                            <a class="hge-btn hge-btn-secondary" href="<?php echo esc_url( $build_page_url( $current_page + 1 ) ); ?>"><?php esc_html_e( 'Sonraki', 'hge' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="hge-radar-detail">
            <div class="hge-radar-detail__header">
                <h3><?php esc_html_e( 'Satır Detayı', 'hge' ); ?></h3>
                <span id="hge-radar-detail-caption"><?php esc_html_e( 'Bir satır seçin; kalite kontrol ve AI önerileri burada görünecek.', 'hge' ); ?></span>
            </div>
            <div class="hge-radar-detail__body">
                <div id="hge-seo-radar-detail-empty" class="hge-radar-detail__empty"><?php esc_html_e( 'Bir satır seçin; kalite kontrol ve AI önerileri burada görünecek.', 'hge' ); ?></div>
                <div id="hge-radar-detail-summary" hidden>
                    <div class="hge-radar-detail__grid">
                        <div class="hge-radar-detail__card hge-radar-detail__card--wide">
                            <span class="hge-radar-detail__label"><?php esc_html_e( 'Keyword', 'hge' ); ?></span>
                            <strong id="hge-radar-detail-keyword">-</strong>
                            <div class="hge-radar-detail__meta">
                                <a id="hge-radar-detail-url" href="#" target="_blank" rel="noopener noreferrer">-</a>
                                <span id="hge-radar-detail-post">-</span>
                            </div>
                        </div>
                        <div class="hge-radar-detail__card">
                            <span class="hge-radar-detail__label"><?php esc_html_e( 'Metrikler', 'hge' ); ?></span>
                            <strong id="hge-radar-detail-metrics">-</strong>
                            <span id="hge-radar-detail-position">-</span>
                        </div>
                        <div class="hge-radar-detail__card">
                            <span class="hge-radar-detail__label"><?php esc_html_e( 'Hacim / Rekabet', 'hge' ); ?></span>
                            <strong id="hge-radar-detail-volume">-</strong>
                            <span id="hge-radar-detail-competition">-</span>
                        </div>
                        <div class="hge-radar-detail__card">
                            <span class="hge-radar-detail__label"><?php esc_html_e( 'Fırsat Skoru', 'hge' ); ?></span>
                            <strong id="hge-radar-detail-score">-</strong>
                            <span id="hge-radar-detail-score-note">-</span>
                        </div>
                        <div class="hge-radar-detail__card">
                            <span class="hge-radar-detail__label"><?php esc_html_e( 'Durum / Kalite', 'hge' ); ?></span>
                            <strong id="hge-radar-detail-status">-</strong>
                            <span id="hge-radar-detail-quality">-</span>
                        </div>
                        <div class="hge-radar-detail__card hge-radar-detail__card--wide">
                            <span class="hge-radar-detail__label"><?php esc_html_e( 'Önerilen Aksiyonlar', 'hge' ); ?></span>
                            <ul id="hge-radar-detail-actions"></ul>
                        </div>
                    </div>
                </div>
                <div id="hge-seo-radar-quality-result" hidden>
                    <h4><?php esc_html_e( 'Kalite Kontrol Sonucu', 'hge' ); ?></h4>
                    <p id="hge-radar-quality-summary">-</p>
                    <ul id="hge-radar-quality-signals"></ul>
                </div>
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
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.hge-seo-radar-page');
    if (!root || typeof jQuery === 'undefined') {
        return;
    }

    const $ = jQuery;
    const formatNumber = value => new Intl.NumberFormat('tr-TR').format(Number(value || 0));
    const formatPercent = value => `${new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0) * 100)}%`;

    const detailEmpty = document.getElementById('hge-seo-radar-detail-empty');
    const detailSummary = document.getElementById('hge-radar-detail-summary');

    const activateRow = row => {
        document.querySelectorAll('.hge-radar-row').forEach(item => item.classList.remove('is-selected'));
        row.classList.add('is-selected');
    };

    const populateDetail = row => {
        const payload = row.getAttribute('data-detail');
        if (!payload) {
            return;
        }

        let detail = {};
        try {
            detail = JSON.parse(payload);
        } catch (error) {
            detail = {};
        }

        activateRow(row);
        if (detailEmpty) {
            detailEmpty.hidden = true;
        }
        if (detailSummary) {
            detailSummary.hidden = false;
        }

        $('#hge-radar-detail-caption').text('Seçili satır özeti');
        $('#hge-radar-detail-keyword').text(detail.keyword || '-');
        $('#hge-radar-detail-url').attr('href', detail.page_url || '#').text(detail.path || detail.page_url || '-');
        $('#hge-radar-detail-post').text(detail.post_id ? `Post #${detail.post_id}` : 'Post #-');
        $('#hge-radar-detail-metrics').text(`C: ${formatNumber(detail.clicks)} | I: ${formatNumber(detail.impressions)} | CTR: ${formatPercent(detail.ctr)}`);
        $('#hge-radar-detail-position').text(`Pozisyon: ${Number(detail.position || 0).toFixed(1)}`);
        $('#hge-radar-detail-volume').text(`Hacim: ${formatNumber(detail.search_volume)}`);
        $('#hge-radar-detail-competition').text(detail.competition || 'UNKNOWN');
        $('#hge-radar-detail-score').text(detail.opportunity_score || 0);
        $('#hge-radar-detail-score-note').text(detail.score_label || '-');
        $('#hge-radar-detail-status').text(detail.status || '-');
        $('#hge-radar-detail-quality').text(detail.quality_status || '-');

        const $actions = $('#hge-radar-detail-actions').empty();
        (detail.recommended_actions || []).forEach(item => $('<li />').text(item).appendTo($actions));
        if (!$actions.children().length) {
            $('<li />').text('Önerilen aksiyon bulunmuyor.').appendTo($actions);
        }
    };

    root.querySelectorAll('.hge-radar-reset-trigger').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            const target = link.getAttribute('data-reset-url') || link.getAttribute('href');
            if (target) {
                window.location.assign(target);
            }
        });
    });

    root.addEventListener('click', event => {
        const detailTrigger = event.target.closest('.hge-radar-detail-trigger');
        if (detailTrigger) {
            event.preventDefault();
            const row = detailTrigger.closest('.hge-radar-row');
            if (row) {
                populateDetail(row);
            }
            return;
        }

        const row = event.target.closest('.hge-radar-row');
        if (!row) {
            return;
        }

        if (event.target.closest('.hge-radar-actions, a, button')) {
            return;
        }

        populateDetail(row);
    });

    root.querySelectorAll('.hge-radar-quality-check, .hge-radar-ai-suggest').forEach(button => {
        button.addEventListener('click', event => {
            const row = event.currentTarget.closest('.hge-radar-row');
            if (row) {
                populateDetail(row);
            }
        });
    });

    const refreshEmpty = document.getElementById('hge-seo-radar-refresh-empty');
    const refreshMain = document.getElementById('hge-seo-radar-refresh');
    if (refreshEmpty && refreshMain) {
        refreshEmpty.addEventListener('click', () => refreshMain.click());
    }

    const firstRow = root.querySelector('.hge-radar-row');
    if (firstRow) {
        populateDetail(firstRow);
    }
});
</script>
