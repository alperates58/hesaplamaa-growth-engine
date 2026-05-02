<?php
namespace HGE\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Ana plugin sınıfı — Singleton
 */
final class Plugin {

    private static ?self $instance = null;

    private function __construct() {
        $this->register_hooks();
    }

    public static function get_instance(){
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function register_hooks(){
        // Admin menüyü yükle
        ( new \HGE\Admin\Menu() )->register();

        // Ayarlar sayfası
        ( new \HGE\Admin\Settings() )->register();
        ( new \HGE\Admin\AISettings() )->register();

        // GitHub updater
        ( new \HGE\Core\GitHubUpdater() )->register();

        // Cron zamanlayıcı
        ( new \HGE\Cron\Scheduler() )->register();

        // Admin asset'leri yükle
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_init', [ '\HGE\DB\Migrator', 'maybe_run' ] );

        // AJAX handler'ları
        add_action( 'wp_ajax_hge_get_dashboard_data',   [ $this, 'ajax_dashboard_data' ] );
        add_action( 'wp_ajax_hge_get_opportunities',    [ $this, 'ajax_opportunities' ] );
        add_action( 'wp_ajax_hge_get_suggestions',      [ $this, 'ajax_suggestions' ] );
        add_action( 'wp_ajax_hge_gsc_oauth_callback',   [ $this, 'ajax_gsc_oauth' ] );
        add_action( 'wp_ajax_hge_disconnect_gsc',       [ $this, 'ajax_disconnect_gsc' ] );
        add_action( 'wp_ajax_hge_sync_now',             [ $this, 'ajax_sync_now' ] );
        add_action( 'wp_ajax_hge_clear_cache',          [ $this, 'ajax_clear_cache' ] );
        add_action( 'wp_ajax_hge_ai_keyword_insight',   [ $this, 'ajax_ai_keyword_insight' ] );
        add_action( 'wp_ajax_hge_ai_topic_ideas',       [ $this, 'ajax_ai_topic_ideas' ] );
        add_action( 'wp_ajax_hge_ai_global_ideas',      [ $this, 'ajax_ai_global_ideas' ] );
        add_action( 'wp_ajax_hge_test_google_ads',      [ $this, 'ajax_test_google_ads' ] );
        add_action( 'wp_ajax_hge_inspect_index_status', [ $this, 'ajax_inspect_index_status' ] );
        add_action( 'wp_ajax_hge_inspect_index_batch',  [ $this, 'ajax_inspect_index_batch' ] );

        add_action( 'transition_post_status', [ $this, 'queue_post_for_index_check' ], 10, 3 );
        add_action( 'save_post', [ $this, 'queue_saved_post_for_index_check' ], 10, 3 );

        // OAuth redirect (admin_init üzerinden)
        add_action( 'admin_init', [ $this, 'handle_gsc_oauth_return' ] );
        add_action( 'admin_init', [ $this, 'handle_ads_oauth_return' ] );
    }

    /**
     * Admin sayfalarına özel asset'leri yükle
     */
    public function enqueue_admin_assets( string $hook ){
        // Sadece kendi sayfalarımızda yükle
        if ( strpos( $hook, 'hge' ) === false && strpos( $hook, 'hesaplamaa' ) === false ) {
            return;
        }

        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );

        // Ana CSS
        wp_enqueue_style(
            'hge-admin',
            HGE_ASSETS_URL . 'css/admin.css',
            [ 'wp-components' ],
            HGE_VERSION
        );

        // Ana JS
        wp_enqueue_script(
            'hge-admin',
            HGE_ASSETS_URL . 'js/admin.js',
            [ 'jquery', 'chartjs' ],
            HGE_VERSION,
            true
        );

        // JS'e PHP verisi aktar
        wp_localize_script( 'hge-admin', 'HGE', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'hge_nonce' ),
            'site_url'   => get_site_url(),
            'oauth_url'  => $this->get_gsc_auth_url(),
            'version'    => HGE_VERSION,
            'i18n'       => [
                'loading'     => __( 'Yükleniyor...', 'hge' ),
                'error'       => __( 'Bir hata oluştu.', 'hge' ),
                'success'     => __( 'Başarılı!', 'hge' ),
                'confirm_sync' => __( 'Veri senkronizasyonu başlatılsın mı?', 'hge' ),
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX Handlers
    // -------------------------------------------------------------------------

    public function ajax_dashboard_data(){
        $this->verify_ajax_request();
        $dashboard = new \HGE\Admin\Dashboard();
        wp_send_json_success( $dashboard->get_data() );
    }

    public function ajax_opportunities(){
        $this->verify_ajax_request();
        $opp = new \HGE\Admin\KeywordOpportunities();
        wp_send_json_success( $opp->get_data() );
    }

    public function ajax_suggestions(){
        $this->verify_ajax_request();
        $ideas = new \HGE\Admin\NewIdeas();
        wp_send_json_success( $ideas->get_data() );
    }

    public function ajax_gsc_oauth(){
        $this->verify_ajax_request();
        $url = $this->get_gsc_auth_url();
        wp_send_json_success( [ 'url' => $url ] );
    }

    public function ajax_disconnect_gsc(){
        $this->verify_ajax_request();
        delete_option( 'hge_gsc_tokens' );
        $settings = get_option( 'hge_settings', [] );
        $settings['gsc_connected'] = false;
        update_option( 'hge_settings', $settings );
        wp_send_json_success( [ 'message' => __( 'GSC bağlantısı kesildi.', 'hge' ) ] );
    }

    public function ajax_sync_now(){
        $this->verify_ajax_request();
        $scheduler = new \HGE\Cron\Scheduler();
        $result    = $scheduler->run_daily_sync();
        wp_send_json_success( [ 'message' => __( 'Senkronizasyon tamamlandı.', 'hge' ), 'result' => $result ] );
    }

    public function ajax_clear_cache(){
        $this->verify_ajax_request();
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hge_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hge_%'" );
        wp_send_json_success( [ 'message' => __( 'Cache temizlendi.', 'hge' ) ] );
    }

    public function ajax_ai_keyword_insight(){
        $this->verify_ajax_request();

        $keyword = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
        if ( empty( $keyword ) ) {
            wp_send_json_error( [ 'message' => __( 'Anahtar kelime eksik.', 'hge' ) ], 400 );
        }

        $repo   = new \HGE\DB\Repository();
        $cached = $repo->get_ai_insight( $keyword );
        if ( $cached ) {
            wp_send_json_success( [
                'cached'  => true,
                'model'   => $cached['model'],
                'insight' => $cached['insight'],
            ] );
        }

        $client = new \HGE\API\OpenAIClient();
        if ( ! $client->is_configured() ) {
            wp_send_json_error( [ 'message' => __( 'AI entegrasyonu aktif değil veya API key eksik.', 'hge' ) ], 400 );
        }

        $payload = [
            'keyword'           => $keyword,
            'monthly_volume'    => (int) ( $_POST['monthly_volume'] ?? 0 ),
            'competition'       => sanitize_text_field( wp_unslash( $_POST['competition'] ?? 'UNKNOWN' ) ),
            'exists_on_site'    => ! empty( $_POST['exists_on_site'] ),
            'opportunity_score' => (int) ( $_POST['opportunity_score'] ?? 0 ),
        ];

        $result = $client->generate_keyword_insight( $payload );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
        }

        $prompt_hash = hash( 'sha256', wp_json_encode( $payload ) );
        $repo->save_ai_insight( $keyword, $result['model'], $result['insight'], $prompt_hash );

        wp_send_json_success( [
            'cached'  => false,
            'model'   => $result['model'],
            'insight' => $result['insight'],
            'usage'   => $result['usage'],
        ] );
    }

    public function ajax_ai_topic_ideas(){
        $this->verify_ajax_request();

        $topic = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
        if ( empty( $topic ) ) {
            wp_send_json_error( [ 'message' => __( 'Konu girin. Örnek: sağlık, finans, zaman.', 'hge' ) ], 400 );
        }

        $ideas = new \HGE\Admin\NewIdeas();
        $result = $ideas->generate_topic_ideas( $topic );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
        }

        wp_send_json_success( [
            'message' => __( 'AI konu fikirleri eklendi.', 'hge' ),
            'count'   => count( $result ),
        ] );
    }

    public function ajax_ai_global_ideas(){
        $this->verify_ajax_request();

        $ideas  = new \HGE\Admin\NewIdeas();
        $result = $ideas->generate_global_ideas();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
        }

        wp_send_json_success( [
            'message' => __( 'AI genel hesaplama fikirleri eklendi.', 'hge' ),
            'count'   => count( $result ),
            'items'   => array_slice( $result, 0, 200 ),
        ] );
    }

    public function ajax_test_google_ads(){
        $this->verify_ajax_request();

        $client = new \HGE\API\GoogleAdsClient();
        $result = $client->test_connection();

        if ( is_wp_error( $result ) ) {
            update_option( 'hge_google_ads_last_test', [
                'ok'      => false,
                'message' => $result->get_error_message(),
                'time'    => current_time( 'mysql' ),
            ], false );

            wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
        }

        wp_send_json_success( $result );
    }

    public function ajax_inspect_index_status(){
        $this->verify_ajax_request();

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        $service = new \HGE\Admin\IndexStatus();
        $result  = $service->inspect_post( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
        }

        wp_send_json_success( $result );
    }

    public function ajax_inspect_index_batch(){
        $this->verify_ajax_request();

        $limit   = (int) ( $_POST['limit'] ?? 25 );
        $service = new \HGE\Admin\IndexStatus();
        $result  = $service->inspect_pending( $limit );
        $checked = $result['checked'] ?? [];
        $skipped = (int) ( $result['skipped'] ?? 0 );

        wp_send_json_success( [
            'message' => sprintf( __( '%1$d URL kontrol edildi, %2$d URL atlandı.', 'hge' ), count( $checked ), $skipped ),
            'items'   => $checked,
            'skipped' => $skipped,
        ] );
    }

    public function queue_post_for_index_check( string $new_status, string $old_status, \WP_Post $post ){
        if ( $new_status !== 'publish' || $old_status === 'publish' ) {
            return;
        }

        ( new \HGE\Admin\IndexStatus() )->queue_published_post( $post->ID );
    }

    public function queue_saved_post_for_index_check( int $post_id, \WP_Post $post, bool $update ){
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! $update || $post->post_status !== 'publish' || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
            return;
        }

        ( new \HGE\Admin\IndexStatus() )->queue_published_post( $post_id );
    }

    /**
     * GSC OAuth callback'i işle
     */
    public function handle_gsc_oauth_return(){
        if ( ! isset( $_GET['hge_gsc_callback'], $_GET['code'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $client = new \HGE\API\GSCClient();
        $result = $client->exchange_code_for_tokens( $code );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'hge', 'gsc_oauth_error', $result->get_error_message(), 'error' );
        } else {
            $settings                  = get_option( 'hge_settings', [] );
            $settings['gsc_connected'] = true;
            update_option( 'hge_settings', $settings );
            add_settings_error( 'hge', 'gsc_oauth_success', __( 'Google Search Console başarıyla bağlandı!', 'hge' ), 'success' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hge-settings' ) );
        exit;
    }

    public function handle_ads_oauth_return(){
        if ( ! isset( $_GET['hge_ads_callback'], $_GET['code'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $code     = sanitize_text_field( wp_unslash( $_GET['code'] ) );
        $settings = get_option( 'hge_settings', [] );

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'timeout' => 30,
            'body'    => [
                'code'          => $code,
                'client_id'     => $settings['gsc_client_id'] ?? '',
                'client_secret' => $settings['gsc_client_secret'] ?? '',
                'redirect_uri'  => admin_url( '?hge_ads_callback=1' ),
                'grant_type'    => 'authorization_code',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            add_settings_error( 'hge', 'ads_oauth_error', $response->get_error_message(), 'error' );
        } else {
            $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

            if ( ! empty( $body['error'] ) ) {
                add_settings_error( 'hge', 'ads_oauth_error', $body['error_description'] ?? $body['error'], 'error' );
            } elseif ( empty( $body['refresh_token'] ) ) {
                add_settings_error( 'hge', 'ads_oauth_error', __( 'Refresh token alınamadı. Google izin ekranında erişimi kaldırıp tekrar deneyin.', 'hge' ), 'error' );
            } else {
                $settings['google_ads_refresh_token'] = sanitize_text_field( $body['refresh_token'] );
                $settings['google_ads_enabled']       = true;
                update_option( 'hge_settings', $settings );
                add_settings_error( 'hge', 'ads_oauth_success', __( 'Google Ads başarıyla bağlandı.', 'hge' ), 'success' );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=hge-settings' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function verify_ajax_request(){
        if ( ! check_ajax_referer( 'hge_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 'hge' ) ], 400 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetkiniz yok.', 'hge' ) ], 403 );
        }
    }

    public function get_gsc_auth_url(){
        $settings     = get_option( 'hge_settings', [] );
        $client_id    = $settings['gsc_client_id']     ?? '';
        $redirect_uri = admin_url( '?hge_gsc_callback=1' );

        if ( empty( $client_id ) ) {
            return '#';
        }

        $params = [
            'client_id'             => $client_id,
            'redirect_uri'          => $redirect_uri,
            'response_type'         => 'code',
            'scope'                 => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => wp_create_nonce( 'hge_gsc_state' ),
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
    }

    public function get_ads_auth_url(){
        $settings     = get_option( 'hge_settings', [] );
        $client_id    = $settings['gsc_client_id'] ?? '';
        $redirect_uri = admin_url( '?hge_ads_callback=1' );

        if ( empty( $client_id ) ) {
            return '#';
        }

        $params = [
            'client_id'             => $client_id,
            'redirect_uri'          => $redirect_uri,
            'response_type'         => 'code',
            'scope'                 => 'https://www.googleapis.com/auth/adwords',
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => wp_create_nonce( 'hge_ads_state' ),
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
    }

    // Klonlamayı engelle
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception( 'Deserialize edilemez.' );
    }
}
