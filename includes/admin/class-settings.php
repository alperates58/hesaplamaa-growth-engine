<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Ayarlar sayfası — GSC OAuth + genel ayarlar
 */
class Settings {

    const OPTION_KEY = 'hge_settings';

    public function register(){
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_hge_save_settings', [ $this, 'handle_save_settings' ] );
    }

    public function register_settings(){
        register_setting(
            'hge_settings_group',
            self::OPTION_KEY,
            [ $this, 'sanitize_settings' ]
        );
    }

    public function sanitize_settings( array $input ){
        $clean = [];
        $existing = get_option( self::OPTION_KEY, [] );

        $clean['gsc_client_id']     = sanitize_text_field( $input['gsc_client_id'] ?? '' );
        $clean['gsc_client_secret'] = sanitize_text_field( $input['gsc_client_secret'] ?? '' );
        if ( '' === $clean['gsc_client_secret'] && ! empty( $existing['gsc_client_secret'] ) ) {
            $clean['gsc_client_secret'] = $existing['gsc_client_secret'];
        }
        $site_url = trim( (string) ( $input['gsc_site_url'] ?? get_site_url() ) );
        $clean['gsc_site_url'] = stripos( $site_url, 'sc-domain:' ) === 0
            ? sanitize_text_field( $site_url )
            : esc_url_raw( $site_url );
        $clean['data_range_days']   = max( 7, min( 90, (int) ( $input['data_range_days'] ?? 30 ) ) );
        $clean['cache_ttl']         = max( 300, min( 86400, (int) ( $input['cache_ttl'] ?? 3600 ) ) );
        $clean['google_ads_enabled']         = ! empty( $input['google_ads_enabled'] );
        $clean['google_ads_developer_token'] = sanitize_text_field( $input['google_ads_developer_token'] ?? '' );
        if ( '' === $clean['google_ads_developer_token'] && ! empty( $existing['google_ads_developer_token'] ) ) {
            $clean['google_ads_developer_token'] = $existing['google_ads_developer_token'];
        }
        $clean['google_ads_customer_id']     = preg_replace( '/\D+/', '', (string) ( $input['google_ads_customer_id'] ?? '' ) );
        $clean['google_ads_login_customer_id'] = preg_replace( '/\D+/', '', (string) ( $input['google_ads_login_customer_id'] ?? '' ) );
        $clean['google_ads_refresh_token']   = sanitize_text_field( $input['google_ads_refresh_token'] ?? '' );
        if ( '' === $clean['google_ads_refresh_token'] && ! empty( $existing['google_ads_refresh_token'] ) ) {
            $clean['google_ads_refresh_token'] = $existing['google_ads_refresh_token'];
        }
        $clean['google_ads_language']        = sanitize_text_field( $input['google_ads_language'] ?? 'languageConstants/1037' );
        $clean['google_ads_geo_target']      = sanitize_text_field( $input['google_ads_geo_target'] ?? 'geoTargetConstants/2792' );

        // Mevcut bağlantı durumunu koru
        $clean['gsc_connected']    = $existing['gsc_connected'] ?? false;

        return $clean;
    }

    public function handle_save_settings(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }

        check_admin_referer( 'hge_settings_group-options' );

        $input = isset( $_POST[ self::OPTION_KEY ] ) && is_array( $_POST[ self::OPTION_KEY ] )
            ? wp_unslash( $_POST[ self::OPTION_KEY ] )
            : [];

        update_option( self::OPTION_KEY, $this->sanitize_settings( $input ) );

        add_settings_error(
            'hge',
            'settings_saved',
            __( 'Ayarlar kaydedildi.', 'hge' ),
            'success'
        );
        set_transient( 'settings_errors', get_settings_errors(), 30 );

        wp_safe_redirect( add_query_arg( 'settings-updated', 'true', admin_url( 'admin.php?page=hge-settings' ) ) );
        exit;
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }

        settings_errors( 'hge' );

        $settings      = get_option( self::OPTION_KEY, [] );
        $gsc_client    = new \HGE\API\GSCClient();
        $gsc_connected = $gsc_client->is_connected();
        $oauth_url     = \HGE\Core\Plugin::get_instance()->get_gsc_auth_url();
        $ads_oauth_url = \HGE\Core\Plugin::get_instance()->get_ads_auth_url();
        $ads_last_test = get_option( 'hge_google_ads_last_test', [] );

        require HGE_DIR . 'templates/admin/settings.php';
    }
}
