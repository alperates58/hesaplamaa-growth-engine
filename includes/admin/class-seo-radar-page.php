<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class SEORadarPage {

    private \HGE\SEORadar $radar;

    public function __construct() {
        $this->radar = new \HGE\SEORadar();
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }

        $filters     = $this->get_filters();
        $list        = $this->radar->get_list( $filters );
        $summary     = $this->radar->get_summary( $filters );
        $export_url  = add_query_arg(
            array_merge(
                [
                    'action' => 'hge_seo_radar_export_csv',
                    'nonce'  => wp_create_nonce( 'hge_nonce' ),
                ],
                $this->export_filter_args( $filters )
            ),
            admin_url( 'admin-ajax.php' )
        );

        require HGE_DIR . 'templates/admin/seo-radar.php';
    }

    private function get_filters(){
        return [
            'view'                  => sanitize_text_field( wp_unslash( $_GET['hge_radar_view'] ?? 'quick-wins' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'search'                => sanitize_text_field( wp_unslash( $_GET['hge_search'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'days'                  => absint( $_GET['hge_days'] ?? 28 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'paged'                 => absint( $_GET['paged'] ?? 1 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'limit'                 => absint( $_GET['hge_limit'] ?? 50 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'position_band'         => sanitize_text_field( wp_unslash( $_GET['hge_position_band'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'low_ctr_only'          => ! empty( $_GET['hge_low_ctr'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'high_impressions_only' => ! empty( $_GET['hge_high_impressions'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'high_volume_only'      => ! empty( $_GET['hge_high_volume'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'low_competition_only'  => ! empty( $_GET['hge_low_competition'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'intent_only'           => ! empty( $_GET['hge_intent'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'quality_only'          => ! empty( $_GET['hge_quality'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ];
    }

    private function export_filter_args( array $filters ){
        return [
            'hge_radar_view'       => $filters['view'],
            'hge_search'           => $filters['search'],
            'hge_days'             => $filters['days'],
            'hge_limit'            => $filters['limit'],
            'hge_position_band'    => $filters['position_band'],
            'hge_low_ctr'          => $filters['low_ctr_only'] ? 1 : 0,
            'hge_high_impressions' => $filters['high_impressions_only'] ? 1 : 0,
            'hge_high_volume'      => $filters['high_volume_only'] ? 1 : 0,
            'hge_low_competition'  => $filters['low_competition_only'] ? 1 : 0,
            'hge_intent'           => $filters['intent_only'] ? 1 : 0,
            'hge_quality'          => $filters['quality_only'] ? 1 : 0,
        ];
    }
}
