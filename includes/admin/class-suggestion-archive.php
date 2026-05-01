<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class SuggestionArchive {

    private \HGE\DB\Repository $repo;

    public function __construct() {
        $this->repo = new \HGE\DB\Repository();
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }

        $filters = [
            'search'      => sanitize_text_field( wp_unslash( $_GET['hge_search'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'source'      => sanitize_text_field( wp_unslash( $_GET['hge_source'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'competition' => sanitize_text_field( wp_unslash( $_GET['hge_competition'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'status'      => sanitize_text_field( wp_unslash( $_GET['hge_status'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'created'     => sanitize_text_field( wp_unslash( $_GET['hge_created'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'limit'       => (int) ( $_GET['hge_limit'] ?? 300 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ];

        $summary = $this->repo->get_suggestion_archive_summary();
        $rows    = $this->repo->get_suggestion_archive( $filters );

        require HGE_DIR . 'templates/admin/suggestion-archive.php';
    }
}
