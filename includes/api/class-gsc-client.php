<?php
namespace HGE\API;

defined( 'ABSPATH' ) || exit;

/**
 * Google Search Console API İstemcisi
 * OAuth2 Authorization Code Flow
 */
class GSCClient {

    const TOKEN_OPTION     = 'hge_gsc_tokens';
    const TOKEN_ENDPOINT   = 'https://oauth2.googleapis.com/token';
    const SEARCH_ANALYTICS = 'https://searchconsole.googleapis.com/webmasters/v3/sites/{siteUrl}/searchAnalytics/query';
    const URL_INSPECTION   = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
    const SITES_LIST       = 'https://www.googleapis.com/webmasters/v3/sites';

    private string $client_id;
    private string $client_secret;
    private string $redirect_uri;

    public function __construct() {
        $settings            = get_option( 'hge_settings', [] );
        $this->client_id     = $settings['gsc_client_id']     ?? '';
        $this->client_secret = $settings['gsc_client_secret'] ?? '';
        $this->redirect_uri  = admin_url( '?hge_gsc_callback=1' );
    }

    // -------------------------------------------------------------------------
    // OAuth2 Token Yönetimi
    // -------------------------------------------------------------------------

    /**
     * Authorization code → access + refresh token
     */
    public function exchange_code_for_tokens( string $code ){
        $response = wp_remote_post(
            self::TOKEN_ENDPOINT,
            [
                'timeout' => 30,
                'body'    => [
                    'code'          => $code,
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'redirect_uri'  => $this->redirect_uri,
                    'grant_type'    => 'authorization_code',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['error'] ) ) {
            return new \WP_Error( 'gsc_token_error', $body['error_description'] ?? $body['error'] );
        }

        $tokens = [
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at'    => time() + (int) ( $body['expires_in'] ?? 3600 ),
            'token_type'    => $body['token_type'] ?? 'Bearer',
        ];

        update_option( self::TOKEN_OPTION, $tokens );
        return $tokens;
    }

    /**
     * Access token al (gerekirse refresh et)
     */
    public function get_access_token(){
        $tokens = get_option( self::TOKEN_OPTION, [] );

        if ( empty( $tokens['access_token'] ) ) {
            return new \WP_Error( 'gsc_not_connected', __( 'GSC bağlı değil. Lütfen ayarlardan bağlayın.', 'hge' ) );
        }

        // Token süresi dolmadıysa direkt döndür (60s buffer)
        if ( ! empty( $tokens['expires_at'] ) && $tokens['expires_at'] > ( time() + 60 ) ) {
            return $tokens['access_token'];
        }

        // Refresh token ile yenile
        return $this->refresh_access_token( $tokens['refresh_token'] ?? '' );
    }

    private function refresh_access_token( string $refresh_token ){
        if ( empty( $refresh_token ) ) {
            return new \WP_Error( 'gsc_no_refresh', __( 'Refresh token bulunamadı. Lütfen yeniden bağlayın.', 'hge' ) );
        }

        $response = wp_remote_post(
            self::TOKEN_ENDPOINT,
            [
                'timeout' => 30,
                'body'    => [
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type'    => 'refresh_token',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['error'] ) ) {
            // Refresh token geçersiz — bağlantıyı kes
            delete_option( self::TOKEN_OPTION );
            $settings                  = get_option( 'hge_settings', [] );
            $settings['gsc_connected'] = false;
            update_option( 'hge_settings', $settings );
            return new \WP_Error( 'gsc_refresh_failed', $body['error_description'] ?? $body['error'] );
        }

        $tokens                  = get_option( self::TOKEN_OPTION, [] );
        $tokens['access_token']  = $body['access_token'];
        $tokens['expires_at']    = time() + (int) ( $body['expires_in'] ?? 3600 );
        update_option( self::TOKEN_OPTION, $tokens );

        return $tokens['access_token'];
    }

    // -------------------------------------------------------------------------
    // Search Analytics API
    // -------------------------------------------------------------------------

    /**
     * Keyword performans verisi çek
     *
     * @param string $site_url   Doğrulanmış site URL'i (sc-domain:hesaplamaa.com veya https://hesaplamaa.com/)
     * @param int    $days       Kaç günlük veri
     * @param array  $dimensions Boyutlar: ['query', 'page', 'date']
     * @param int    $row_limit  Satır limiti
     */
    public function get_search_analytics(
        string $site_url,
        int    $days       = 30,
        array  $dimensions = [ 'query', 'page' ],
        int    $row_limit  = 1000
    ){

        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $end_date   = gmdate( 'Y-m-d', strtotime( '-3 days' ) ); // GSC 3 gün gecikme
        $start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
        $row_limit  = max( 1, min( 25000, $row_limit ) );

        $endpoint = str_replace( '{siteUrl}', rawurlencode( $site_url ), self::SEARCH_ANALYTICS );

        $body = [
            'startDate'  => $start_date,
            'endDate'    => $end_date,
            'dimensions' => $dimensions,
            'rowLimit'   => $row_limit,
            'startRow'   => 0,
        ];

        $all_rows = [];

        do {
            $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => 45,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( $body ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $data      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $http_code !== 200 ) {
            $msg = $data['error']['message'] ?? __( 'GSC API hatası.', 'hge' );
            return new \WP_Error( 'gsc_api_error', $msg, [ 'status' => $http_code ] );
        }

            $rows     = $data['rows'] ?? [];
            $all_rows = array_merge( $all_rows, $rows );

            $body['startRow'] += $row_limit;
        } while ( count( $rows ) === $row_limit );

        return $all_rows;
    }

    /**
     * Günlük aggregate veri çek (grafik için)
     */
    public function get_daily_stats( string $site_url, int $days = 30 ){
        return $this->get_search_analytics( $site_url, $days, [ 'date' ], 90 );
    }

    /**
     * Sayfa bazlı veri çek
     */
    public function get_page_stats( string $site_url, int $days = 30 ){
        return $this->get_search_analytics( $site_url, $days, [ 'page' ], 25000 );
    }

    public function inspect_url( string $site_url, string $inspection_url, string $language_code = 'tr-TR' ){
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $response = wp_remote_post(
            self::URL_INSPECTION,
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'inspectionUrl' => $inspection_url,
                    'siteUrl'       => $site_url,
                    'languageCode'  => $language_code,
                ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $data      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $http_code !== 200 ) {
            $msg = $data['error']['message'] ?? __( 'URL Inspection API hatası.', 'hge' );
            return new \WP_Error( 'gsc_url_inspection_error', $msg, [ 'status' => $http_code ] );
        }

        return $data['inspectionResult'] ?? [];
    }

    /**
     * Hesap'a bağlı siteleri listele
     */
    public function get_sites(){
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $response = wp_remote_get(
            self::SITES_LIST,
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['siteEntry'] ?? [];
    }

    /**
     * Bağlantı durumunu kontrol et
     */
    public function is_connected() {
        $tokens = get_option( self::TOKEN_OPTION, [] );
        return ! empty( $tokens['access_token'] );
    }
}
