<?php
namespace HGE\API;

defined( 'ABSPATH' ) || exit;

class GoogleAdsClient {

    const API_VERSION = 'v22';

    private array $settings;
    private string $last_error = '';

    public function __construct() {
        $this->settings = get_option( 'hge_settings', [] );
    }

    public function is_configured(){
        return ! empty( $this->settings['google_ads_enabled'] )
            && ! empty( $this->settings['google_ads_developer_token'] )
            && ! empty( $this->settings['google_ads_customer_id'] )
            && ! empty( $this->settings['google_ads_refresh_token'] )
            && ! empty( $this->settings['gsc_client_id'] )
            && ! empty( $this->settings['gsc_client_secret'] );
    }

    public function get_keyword_metrics( array $keywords ){
        $keywords = array_values( array_filter( array_map( 'sanitize_text_field', $keywords ) ) );
        $keywords = array_slice( array_unique( $keywords ), 0, 100 );

        if ( empty( $keywords ) ) {
            return [];
        }

        if ( ! $this->is_configured() ) {
            $this->last_error = __( 'Google Ads API ayarları eksik.', 'hge' );
            return [];
        }

        $cache_key = 'hge_ads_metrics_' . md5( implode( '|', $keywords ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            $this->last_error = $token->get_error_message();
            return [];
        }

        $customer_id = preg_replace( '/\D+/', '', (string) $this->settings['google_ads_customer_id'] );
        $url = sprintf(
            'https://googleads.googleapis.com/%s/customers/%s:generateKeywordHistoricalMetrics',
            self::API_VERSION,
            $customer_id
        );

        $headers = [
            'Authorization'   => 'Bearer ' . $token,
            'developer-token' => $this->settings['google_ads_developer_token'],
            'Content-Type'    => 'application/json',
        ];

        if ( ! empty( $this->settings['google_ads_login_customer_id'] ) ) {
            $headers['login-customer-id'] = preg_replace( '/\D+/', '', (string) $this->settings['google_ads_login_customer_id'] );
        }

        $body = [
            'keywords'           => $keywords,
            'keywordPlanNetwork' => 'GOOGLE_SEARCH',
        ];

        if ( ! empty( $this->settings['google_ads_language'] ) ) {
            $body['language'] = sanitize_text_field( $this->settings['google_ads_language'] );
        }

        if ( ! empty( $this->settings['google_ads_geo_target'] ) ) {
            $body['geoTargetConstants'] = [ sanitize_text_field( $this->settings['google_ads_geo_target'] ) ];
        }

        $response = wp_remote_post( $url, [
            'timeout' => 35,
            'headers' => $headers,
            'body'    => wp_json_encode( $body, JSON_UNESCAPED_UNICODE ),
        ] );

        if ( is_wp_error( $response ) ) {
            $this->last_error = $response->get_error_message();
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            $this->last_error = $data['error']['message'] ?? sprintf( __( 'Google Ads API HTTP %d hatası döndürdü.', 'hge' ), $code );
            return [];
        }

        $metrics = [];
        foreach ( (array) ( $data['results'] ?? [] ) as $row ) {
            $keyword = sanitize_text_field( $row['text'] ?? '' );
            $metric  = $row['keywordMetrics'] ?? [];

            if ( $keyword === '' || empty( $metric ) ) {
                continue;
            }

            $competition = strtoupper( sanitize_text_field( $metric['competition'] ?? 'MEDIUM' ) );
            if ( ! in_array( $competition, [ 'LOW', 'MEDIUM', 'HIGH' ], true ) ) {
                $competition = 'MEDIUM';
            }

            $metrics[ $keyword ] = [
                'monthly_volume' => max( 0, (int) ( $metric['avgMonthlySearches'] ?? 0 ) ),
                'competition'    => $competition,
                'source'         => 'google_ads',
            ];

            foreach ( (array) ( $row['closeVariants'] ?? [] ) as $variant ) {
                $variant = sanitize_text_field( $variant );
                if ( $variant !== '' ) {
                    $metrics[ $variant ] = $metrics[ $keyword ];
                }
            }
        }

        set_transient( $cache_key, $metrics, 7 * DAY_IN_SECONDS );

        return $metrics;
    }

    public function test_connection(){
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'hge_ads_not_configured', __( 'Google Ads API ayarları eksik.', 'hge' ) );
        }

        $metrics = $this->get_keyword_metrics( [ 'kredi hesaplama', 'faiz hesaplama', 'maaş hesaplama', 'hesaplama' ] );
        if ( empty( $metrics ) ) {
            $message = $this->last_error ?: __( 'Google Ads API yanıt verdi ancak metrik döndürmedi. Keyword Planner erişimi, hesap durumu veya developer token erişimini kontrol edin.', 'hge' );
            return new \WP_Error( 'hge_ads_empty_response', $message );
        }

        update_option( 'hge_google_ads_last_test', [
            'ok'      => true,
            'message' => __( 'Google Ads API bağlantısı başarılı.', 'hge' ),
            'time'    => current_time( 'mysql' ),
        ], false );

        return [
            'message' => __( 'Google Ads API bağlantısı başarılı.', 'hge' ),
            'metrics' => $metrics,
        ];
    }

    private function get_access_token(){
        $cache_key = 'hge_google_ads_access_token';
        $cached    = get_transient( $cache_key );
        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'client_id'     => $this->settings['gsc_client_id'] ?? '',
                'client_secret' => $this->settings['gsc_client_secret'] ?? '',
                'refresh_token' => $this->settings['google_ads_refresh_token'] ?? '',
                'grant_type'    => 'refresh_token',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['access_token'] ) ) {
            return new \WP_Error( 'hge_ads_token_error', __( 'Google Ads access token alınamadı.', 'hge' ) );
        }

        $ttl = max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 120 );
        set_transient( $cache_key, (string) $data['access_token'], $ttl );

        return (string) $data['access_token'];
    }
}
