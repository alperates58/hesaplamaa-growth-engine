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

        // GitHub updater
        ( new \HGE\Core\GitHubUpdater() )->register();

        // Cron zamanlayıcı
        ( new \HGE\Cron\Scheduler() )->register();

        // Admin asset'leri yükle
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // AJAX handler'ları
        add_action( 'wp_ajax_hge_get_dashboard_data',   [ $this, 'ajax_dashboard_data' ] );
        add_action( 'wp_ajax_hge_get_opportunities',    [ $this, 'ajax_opportunities' ] );
        add_action( 'wp_ajax_hge_get_suggestions',      [ $this, 'ajax_suggestions' ] );
        add_action( 'wp_ajax_hge_gsc_oauth_callback',   [ $this, 'ajax_gsc_oauth' ] );
        add_action( 'wp_ajax_hge_disconnect_gsc',       [ $this, 'ajax_disconnect_gsc' ] );
        add_action( 'wp_ajax_hge_sync_now',             [ $this, 'ajax_sync_now' ] );
        add_action( 'wp_ajax_hge_clear_cache',          [ $this, 'ajax_clear_cache' ] );

        // GSC OAuth redirect (admin_init üzerinden)
        add_action( 'admin_init', [ $this, 'handle_gsc_oauth_return' ] );
    }

    /**
     * Admin sayfalarına özel asset'leri yükle
     */
    public function enqueue_admin_assets( string $hook ){
        // Sadece kendi sayfalarımızda yükle
        if ( ! strpos( $hook, 'hge' ) !== false && ! strpos( $hook, 'hesaplamaa' ) !== false ) {
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function verify_ajax_request(){
        if ( ! check_ajax_referer( 'hge_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Güvenlik doğrulaması başarısız.', 'hge' ) ], 403 );
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

    // Klonlamayı engelle
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception( 'Deserialize edilemez.' );
    }
}
