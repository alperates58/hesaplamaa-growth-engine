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

    public function refresh_volumes( array $filters = [] ){
        $client = new \HGE\API\GoogleAdsClient();
        if ( ! $client->is_configured() ) {
            return new \WP_Error( 'hge_ads_not_configured', __( 'Google Ads API ayarları eksik. Önce ayarlar ekranından bağlantıyı tamamlayın.', 'hge' ) );
        }

        $rows = $this->repo->get_suggestion_archive( $filters );
        if ( empty( $rows ) ) {
            return new \WP_Error( 'hge_archive_empty', __( 'Güncellenecek öneri bulunamadı.', 'hge' ) );
        }

        $updated = 0;
        $matched = 0;

        foreach ( array_chunk( $rows, 100 ) as $chunk ) {
            $keywords = array_values( array_filter( array_map( function ( $row ) {
                return sanitize_text_field( (string) ( $row['topic'] ?? '' ) );
            }, $chunk ) ) );

            if ( empty( $keywords ) ) {
                continue;
            }

            $metrics = $client->get_keyword_metrics( $keywords );

            foreach ( $chunk as $row ) {
                $keyword = sanitize_text_field( (string) ( $row['topic'] ?? '' ) );
                if ( $keyword === '' || ! isset( $metrics[ $keyword ] ) ) {
                    continue;
                }

                $matched++;

                $saved = $this->repo->save_suggestion( [
                    'topic'             => $keyword,
                    'monthly_volume'    => (int) ( $metrics[ $keyword ]['monthly_volume'] ?? 0 ),
                    'competition'       => sanitize_text_field( (string) ( $metrics[ $keyword ]['competition'] ?? 'UNKNOWN' ) ),
                    'opportunity_score' => (int) ( $row['opportunity_score'] ?? 0 ),
                    'exists_on_site'    => (int) ( $row['exists_on_site'] ?? 0 ),
                    'should_create'     => (int) ( $row['should_create'] ?? 0 ),
                    'source'            => sanitize_text_field( (string) ( $row['source'] ?? '' ) ),
                ] );

                if ( $saved ) {
                    $updated++;
                }
            }
        }

        return [
            'message'       => sprintf(
                __( '%1$d öneri için aranma hacmi güncellendi. %2$d kayıt Google Ads eşleşmesi aldı.', 'hge' ),
                $updated,
                $matched
            ),
            'updated_count' => $updated,
            'matched_count' => $matched,
            'total_count'   => count( $rows ),
        ];
    }
}
