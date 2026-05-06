<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class KeywordVolumeImporter {

    private \HGE\DB\Repository $repo;

    public function __construct() {
        $this->repo = new \HGE\DB\Repository();
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }

        $rows            = $this->repo->get_keyword_volumes( [ 'limit' => 5000 ] );
        $summary         = $this->repo->get_keyword_volume_summary();
        $ads_client      = new \HGE\API\GoogleAdsClient();
        $ads_configured  = $ads_client->is_configured();

        require HGE_DIR . 'templates/admin/keyword-volume-importer.php';
    }

    public function handle_import_request(){
        if ( empty( $_FILES['keyword_file'] ) || ! is_array( $_FILES['keyword_file'] ) ) {
            return new \WP_Error( 'hge_keyword_file_missing', __( 'Yüklenecek metin dosyası bulunamadı.', 'hge' ) );
        }

        $file = $_FILES['keyword_file'];
        if ( ! empty( $file['error'] ) ) {
            return new \WP_Error( 'hge_keyword_file_error', __( 'Dosya yükleme sırasında bir hata oluştu.', 'hge' ) );
        }

        $tmp_name = (string) ( $file['tmp_name'] ?? '' );
        if ( $tmp_name === '' || ! is_uploaded_file( $tmp_name ) ) {
            return new \WP_Error( 'hge_keyword_file_invalid', __( 'Geçersiz dosya yüklemesi.', 'hge' ) );
        }

        $client = new \HGE\API\GoogleAdsClient();
        if ( ! $client->is_configured() ) {
            return new \WP_Error( 'hge_ads_not_configured', __( 'Google Ads API ayarları eksik. Önce ayarlar ekranından bağlantıyı tamamlayın.', 'hge' ) );
        }

        $contents = file_get_contents( $tmp_name );
        if ( ! is_string( $contents ) || trim( $contents ) === '' ) {
            return new \WP_Error( 'hge_keyword_file_empty', __( 'Dosya boş görünüyor. Her satıra bir keyword yazın.', 'hge' ) );
        }

        $parsed_keywords = $this->parse_keywords( $contents );
        if ( empty( $parsed_keywords ) ) {
            return new \WP_Error( 'hge_keyword_file_no_keywords', __( 'Dosyada işlenecek keyword bulunamadı. Her satıra bir keyword yazın.', 'hge' ) );
        }

        $existing_map   = $this->repo->get_existing_keyword_volume_map( $parsed_keywords );
        $keywords_to_add = [];
        $skipped_existing = [];

        foreach ( $parsed_keywords as $keyword ) {
            $hash = md5( $this->normalize_keyword( $keyword ) );
            if ( isset( $existing_map[ $hash ] ) ) {
                $skipped_existing[] = $keyword;
                continue;
            }
            $keywords_to_add[] = $keyword;
        }

        if ( empty( $keywords_to_add ) ) {
            return [
                'message'          => __( 'Bu dosyadaki tüm keywordler daha önce yüklenmiş.', 'hge' ),
                'inserted_count'   => 0,
                'skipped_count'    => count( $skipped_existing ),
                'total_in_file'    => count( $parsed_keywords ),
                'items'            => [],
                'summary'          => $this->repo->get_keyword_volume_summary(),
            ];
        }

        $upload_batch = wp_generate_uuid4();
        $source_file  = sanitize_file_name( (string) ( $file['name'] ?? 'keywords.txt' ) );
        $inserted_count = 0;

        foreach ( $keywords_to_add as $keyword ) {
            $saved = $this->repo->save_keyword_volume( [
                'keyword'        => $keyword,
                'monthly_volume' => 0,
                'competition'    => 'UNKNOWN',
                'status'         => 'no_metrics',
                'source_file'    => $source_file,
                'upload_batch'   => $upload_batch,
                'api_source'     => 'google_ads',
            ] );
            if ( $saved ) {
                $inserted_count++;
            }
        }

        return [
            'message'        => sprintf(
                __( '%1$d yeni keyword eklendi, %2$d tekrar atlandı. Arka planda hacim sorgusu başlıyor...', 'hge' ),
                $inserted_count,
                count( $skipped_existing )
            ),
            'inserted_count' => $inserted_count,
            'skipped_count'  => count( $skipped_existing ),
            'total_in_file'  => count( $parsed_keywords ),
            'has_more'       => true,
            'summary'        => $this->repo->get_keyword_volume_summary(),
        ];
    }

    public function process_pending_keywords() {
        $client = new \HGE\API\GoogleAdsClient();
        if ( ! $client->is_configured() ) {
            return new \WP_Error( 'hge_ads_not_configured', __( 'Google Ads API ayarları eksik.', 'hge' ) );
        }

        $pending = $this->repo->get_keyword_volumes( [ 'limit' => 200 ] ); // Need to fetch only no_metrics
        // Wait, get_keyword_volumes doesn't support 'status' filter out of the box in Repository.
        // Let's use WPDB directly here for simplicity, or modify Repository.
        // Actually, let's just add 'status' to get_keyword_volumes in Repository next.
        
        // I will use direct repo query since it's cleaner:
        global $wpdb;
        $table = $this->repo->keyword_volumes;
        $rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'no_metrics' ORDER BY id ASC LIMIT 200", ARRAY_A ) ?: [];

        if ( empty( $rows ) ) {
            return [
                'message'  => __( 'Bekleyen keyword bulunamadı.', 'hge' ),
                'has_more' => false,
                'summary'  => $this->repo->get_keyword_volume_summary(),
            ];
        }

        $keywords = array_column( $rows, 'keyword' );
        $source_files = array_column( $rows, 'source_file', 'keyword' );
        $upload_batches = array_column( $rows, 'upload_batch', 'keyword' );

        foreach ( array_chunk( $keywords, 100 ) as $chunk ) {
            $metrics = $client->get_keyword_metrics( $chunk );

            foreach ( $chunk as $keyword ) {
                $metric = $metrics[ $keyword ] ?? null;
                $this->repo->save_keyword_volume( [
                    'keyword'        => $keyword,
                    'monthly_volume' => is_array( $metric ) ? (int) ( $metric['monthly_volume'] ?? 0 ) : 0,
                    'competition'    => is_array( $metric ) ? (string) ( $metric['competition'] ?? 'UNKNOWN' ) : 'UNKNOWN',
                    'status'         => is_array( $metric ) ? 'ready' : 'no_metrics',
                    'source_file'    => $source_files[$keyword] ?? '',
                    'upload_batch'   => $upload_batches[$keyword] ?? '',
                    'api_source'     => is_array( $metric ) ? (string) ( $metric['source'] ?? 'google_ads' ) : 'google_ads',
                ] );
            }
            sleep(1);
        }

        $remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'no_metrics'" );

        return [
            'message'   => sprintf( __( '%d keyword işlendi. Kalan: %d', 'hge' ), count($keywords), $remaining ),
            'has_more'  => $remaining > 0,
            'summary'   => $this->repo->get_keyword_volume_summary(),
        ];
    }

    private function parse_keywords( string $contents ){
        $lines  = preg_split( '/\r\n|\r|\n/', $contents ) ?: [];
        $seen   = [];
        $result = [];

        foreach ( $lines as $line ) {
            $parts = preg_split( '/[\t,;]+/', (string) $line ) ?: [];
            foreach ( $parts as $part ) {
                $keyword = trim( wp_strip_all_tags( (string) $part ) );
                if ( $keyword === '' ) {
                    continue;
                }

                $normalized = $this->normalize_keyword( $keyword );
                if ( isset( $seen[ $normalized ] ) ) {
                    continue;
                }

                $seen[ $normalized ] = true;
                $result[] = $keyword;
            }
        }

        return array_slice( $result, 0, 5000 );
    }

    private function normalize_keyword( string $keyword ){
        $keyword = preg_replace( '/\s+/u', ' ', trim( sanitize_text_field( $keyword ) ) );

        if ( function_exists( 'mb_strtolower' ) ) {
            return mb_strtolower( $keyword, 'UTF-8' );
        }

        return strtolower( $keyword );
    }
}
