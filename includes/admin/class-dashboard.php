<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard sayfası — veri ve render
 */
class Dashboard {

    private \HGE\DB\Repository $repo;

    public function __construct() {
        $this->repo = new \HGE\DB\Repository();
    }

    /**
     * AJAX için dashboard verisi
     */
    public function get_data(){
        $days = $this->get_selected_range();

        return [
            'summary'           => $this->repo->get_dashboard_summary(),
            'daily_stats'       => $this->repo->get_daily_stats( $days ),
            'top_rising'        => $this->repo->get_top_rising( 10 ),
            'low_ctr_pages'     => $this->repo->get_high_impression_low_ctr( 10 ),
            'last_sync'         => get_option( 'hge_last_sync', null ),
            'gsc_connected'     => ( new \HGE\API\GSCClient() )->is_connected(),
            'selected_range'    => $days,
        ];
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }
        $selected_range = $this->get_selected_range();
        $range_options  = [ 7, 14, 30 ];
        $summary       = $this->repo->get_dashboard_summary();
        $daily_stats   = $this->repo->get_daily_stats( $selected_range );
        $top_rising    = $this->repo->get_top_rising( 10 );
        $low_ctr_pages = $this->repo->get_high_impression_low_ctr( 10 );
        $gsc_connected = ( new \HGE\API\GSCClient() )->is_connected();
        $last_sync     = get_option( 'hge_last_sync', null );

        require HGE_DIR . 'templates/admin/dashboard.php';
    }

    private function get_selected_range(): int {
        $allowed = [ 7, 14, 30 ];
        $days    = isset( $_GET['hge_days'] ) ? absint( wp_unslash( $_GET['hge_days'] ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return in_array( $days, $allowed, true ) ? $days : 30;
    }
}
