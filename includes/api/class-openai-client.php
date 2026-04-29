<?php
namespace HGE\API;

defined( 'ABSPATH' ) || exit;

class OpenAIClient {

    const SETTINGS_KEY = 'hge_ai_settings';

    public function get_settings(){
        $settings = get_option( self::SETTINGS_KEY, [] );
        return wp_parse_args( $settings, [
            'api_key'     => '',
            'model'       => 'gpt-5-mini',
            'daily_limit' => 50,
            'enabled'     => false,
        ] );
    }

    public function is_configured(){
        $settings = $this->get_settings();
        return ! empty( $settings['enabled'] ) && ! empty( $settings['api_key'] );
    }

    public function generate_keyword_insight( array $payload ){
        $settings = $this->get_settings();

        if ( empty( $settings['api_key'] ) ) {
            return new \WP_Error( 'hge_openai_missing_key', __( 'OpenAI API key tanımlı değil.', 'hge' ) );
        }

        if ( ! $this->has_daily_quota() ) {
            return new \WP_Error( 'hge_openai_limit', __( 'Günlük AI analiz limiti doldu.', 'hge' ) );
        }

        $model = sanitize_text_field( $settings['model'] ?: 'gpt-5-mini' );
        $input = $this->build_prompt( $payload );

        $response = wp_remote_post( 'https://api.openai.com/v1/responses', [
            'timeout' => 35,
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model' => $model,
                'input' => $input,
                'max_output_tokens' => 700,
                'text' => [
                    'format' => [
                        'type' => 'json_object',
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            $message = $body['error']['message'] ?? __( 'OpenAI isteği başarısız oldu.', 'hge' );
            return new \WP_Error( 'hge_openai_error', $message );
        }

        $text = $this->extract_output_text( is_array( $body ) ? $body : [] );
        $data = json_decode( $text, true );

        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'hge_openai_invalid_json', __( 'AI yanıtı JSON formatında alınamadı.', 'hge' ) );
        }

        $this->increment_daily_usage();

        return [
            'model'   => $model,
            'insight' => $this->sanitize_insight( $data ),
            'usage'   => $body['usage'] ?? null,
        ];
    }

    private function build_prompt( array $payload ){
        $keyword = sanitize_text_field( $payload['keyword'] ?? '' );
        $context = [
            'keyword'           => $keyword,
            'monthly_volume'    => (int) ( $payload['monthly_volume'] ?? 0 ),
            'competition'       => sanitize_text_field( $payload['competition'] ?? 'UNKNOWN' ),
            'exists_on_site'    => ! empty( $payload['exists_on_site'] ),
            'opportunity_score' => (int) ( $payload['opportunity_score'] ?? 0 ),
            'site_type'         => 'Türkçe hesaplama araçları sitesi',
            'language'          => 'tr',
        ];

        return [
            [
                'role' => 'system',
                'content' => 'Sen SEO ve ürün odaklı kısa brief üreten bir asistansın. Sadece geçerli JSON döndür. Gereksiz açıklama yazma. Yanıt kısa, uygulanabilir ve Türkçe olmalı.',
            ],
            [
                'role' => 'user',
                'content' => "Aşağıdaki fırsatı analiz et. JSON alanları: why_important, recommended_type, seo_difficulty, competitor_signal, content_angle, calculator_idea, titles, meta_title, meta_description, slug.\n\n" . wp_json_encode( $context, JSON_UNESCAPED_UNICODE ),
            ],
        ];
    }

    private function extract_output_text( array $body ){
        if ( ! empty( $body['output_text'] ) ) {
            return (string) $body['output_text'];
        }

        foreach ( $body['output'] ?? [] as $item ) {
            foreach ( $item['content'] ?? [] as $content ) {
                if ( isset( $content['text'] ) ) {
                    return (string) $content['text'];
                }
            }
        }

        return '';
    }

    private function sanitize_insight( array $data ){
        $titles = [];
        foreach ( (array) ( $data['titles'] ?? [] ) as $title ) {
            $titles[] = sanitize_text_field( $title );
        }

        return [
            'why_important'    => sanitize_textarea_field( $data['why_important'] ?? '' ),
            'recommended_type' => sanitize_text_field( $data['recommended_type'] ?? '' ),
            'seo_difficulty'   => sanitize_text_field( $data['seo_difficulty'] ?? '' ),
            'competitor_signal'=> sanitize_textarea_field( $data['competitor_signal'] ?? '' ),
            'content_angle'    => sanitize_textarea_field( $data['content_angle'] ?? '' ),
            'calculator_idea'  => sanitize_textarea_field( $data['calculator_idea'] ?? '' ),
            'titles'           => array_slice( $titles, 0, 5 ),
            'meta_title'       => sanitize_text_field( $data['meta_title'] ?? '' ),
            'meta_description' => sanitize_textarea_field( $data['meta_description'] ?? '' ),
            'slug'             => sanitize_title( $data['slug'] ?? '' ),
        ];
    }

    private function usage_key(){
        return 'hge_ai_usage_' . gmdate( 'Ymd' );
    }

    public function get_daily_usage(){
        return (int) get_option( $this->usage_key(), 0 );
    }

    public function has_daily_quota(){
        $settings = $this->get_settings();
        return $this->get_daily_usage() < (int) $settings['daily_limit'];
    }

    private function increment_daily_usage(){
        update_option( $this->usage_key(), $this->get_daily_usage() + 1, false );
    }
}
