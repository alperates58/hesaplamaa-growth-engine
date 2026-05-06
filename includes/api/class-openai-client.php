<?php
namespace HGE\API;

defined( 'ABSPATH' ) || exit;

class OpenAIClient {

    const SETTINGS_KEY = 'hge_ai_settings';

    private const PROVIDER_OPENAI   = 'openai';
    private const PROVIDER_DEEPSEEK = 'deepseek';

    public function get_settings(){
        $settings = get_option( self::SETTINGS_KEY, [] );

        return wp_parse_args( $settings, [
            'enabled'          => false,
            'provider'         => self::PROVIDER_OPENAI,
            'api_key'          => '',
            'api_base_url'     => '',
            'model'            => 'gpt-5-mini',
            'daily_limit'      => 50,
            'seed_enabled'     => false,
            'seed_limit'       => 25,
            'metrics_enabled'  => false,
        ] );
    }

    public function get_supported_models(){
        return [
            self::PROVIDER_OPENAI => [
                'gpt-5-mini',
                'o4-mini',
            ],
            self::PROVIDER_DEEPSEEK => [
                'deepseek-v4-flash',
                'deepseek-v4-pro',
            ],
        ];
    }

    public function is_configured(){
        $settings = $this->get_settings();
        return ! empty( $settings['enabled'] ) && ! empty( $settings['api_key'] );
    }

    public function get_provider_label( ?string $provider = null ){
        $provider = $provider ?: (string) ( $this->get_settings()['provider'] ?? self::PROVIDER_OPENAI );

        if ( $provider === self::PROVIDER_DEEPSEEK ) {
            return 'DeepSeek';
        }

        return 'OpenAI';
    }

    public function generate_keyword_insight( array $payload ){
        $settings = $this->get_settings();

        if ( empty( $settings['api_key'] ) ) {
            return new \WP_Error( 'hge_ai_missing_key', sprintf( __( '%s API key tanımlı değil.', 'hge' ), $this->get_provider_label() ) );
        }

        if ( ! $this->has_daily_quota() ) {
            return new \WP_Error( 'hge_ai_limit', __( 'Günlük AI analiz limiti doldu.', 'hge' ) );
        }

        $model    = $this->resolve_model( $settings );
        $messages = $this->build_prompt( $payload );
        $result   = $this->request_json( $settings, $model, $messages, 700, true );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $data = json_decode( (string) $result['text'], true );
        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'hge_ai_invalid_json', __( 'AI yanıtı JSON formatında alınamadı.', 'hge' ) );
        }

        $this->increment_daily_usage();

        return [
            'model'    => $model,
            'provider' => $this->get_provider_label( (string) ( $settings['provider'] ?? '' ) ),
            'insight'  => $this->sanitize_insight( $data ),
            'usage'    => $result['usage'],
        ];
    }

    public function generate_seed_keywords(){
        $settings = $this->get_settings();

        if ( empty( $settings['api_key'] ) ) {
            return new \WP_Error( 'hge_ai_missing_key', sprintf( __( '%s API key tanımlı değil.', 'hge' ), $this->get_provider_label() ) );
        }

        if ( ! $this->has_daily_quota() ) {
            return new \WP_Error( 'hge_ai_limit', __( 'Günlük AI analiz limiti doldu.', 'hge' ) );
        }

        $model     = $this->resolve_model( $settings );
        $cache_key = 'hge_ai_seed_keywords_' . md5( (string) $settings['provider'] . '_' . $model . '_' . (int) ( $settings['seed_limit'] ?? 25 ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $limit    = max( 10, min( 60, (int) ( $settings['seed_limit'] ?? 25 ) ) );
        $messages = [
            [
                'role'    => 'system',
                'content' => 'Sen Türkçe SEO fırsatları bulan bir asistansın. Sadece geçerli JSON döndür. Kısa, arama odaklı seed keywordler üret.',
            ],
            [
                'role'    => 'user',
                'content' => "hesaplamaa.com için Google Suggest'e gönderilecek {$limit} adet ana seed keyword üret. Sadece hesaplama aracı potansiyeli olan finans, maaş, kredi, vergi, sağlık, eğitim, tarih, ölçüm, günlük hayat ve resmi işlem konularını kapsa. Marka adı yazma. JSON formatı: {\"seeds\":[\"...\"]}",
            ],
        ];

        $result = $this->request_json( $settings, $model, $messages, 500, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $data  = json_decode( (string) $result['text'], true );
        $seeds = [];

        foreach ( (array) ( $data['seeds'] ?? [] ) as $seed ) {
            $seed = sanitize_text_field( $seed );
            if ( $seed !== '' && strlen( $seed ) <= 120 ) {
                $seeds[] = strtolower( $seed );
            }
        }

        $seeds = array_values( array_unique( array_slice( $seeds, 0, $limit ) ) );
        if ( empty( $seeds ) ) {
            return new \WP_Error( 'hge_ai_empty_seeds', __( 'AI seed konu üretemedi.', 'hge' ) );
        }

        $this->increment_daily_usage();
        set_transient( $cache_key, $seeds, 7 * DAY_IN_SECONDS );

        return $seeds;
    }

    public function estimate_keyword_metrics( array $keywords ){
        $settings = $this->get_settings();

        if ( empty( $settings['api_key'] ) ) {
            return new \WP_Error( 'hge_ai_missing_key', sprintf( __( '%s API key tanımlı değil.', 'hge' ), $this->get_provider_label() ) );
        }

        if ( ! $this->has_daily_quota() ) {
            return new \WP_Error( 'hge_ai_limit', __( 'Günlük AI analiz limiti doldu.', 'hge' ) );
        }

        $keywords = array_values( array_filter( array_map( 'sanitize_text_field', $keywords ) ) );
        $keywords = array_slice( array_unique( $keywords ), 0, 30 );

        if ( empty( $keywords ) ) {
            return [];
        }

        $model     = $this->resolve_model( $settings );
        $cache_key = 'hge_ai_metric_estimates_' . md5( (string) $settings['provider'] . '_' . $model . '_' . implode( '|', $keywords ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => 'Sen Türkçe SEO keyword metriklerini kaba tahmin eden bir asistansın. Gerçek Ads verisi yoksa makul aralık tahmini yap. Sadece JSON döndür.',
            ],
            [
                'role'    => 'user',
                'content' => 'Aşağıdaki keywordler için Türkiye pazarı aylık arama hacmi tahmini ve rekabet seviyesi üret. competition yalnızca LOW, MEDIUM, HIGH olabilir. JSON formatı: {"metrics":[{"keyword":"...","monthly_volume":1000,"competition":"MEDIUM"}]}. Keywordler: ' . wp_json_encode( $keywords, JSON_UNESCAPED_UNICODE ),
            ],
        ];

        $result = $this->request_json( $settings, $model, $messages, 900, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $data    = json_decode( (string) $result['text'], true );
        $metrics = [];

        foreach ( (array) ( $data['metrics'] ?? [] ) as $item ) {
            $keyword = sanitize_text_field( $item['keyword'] ?? '' );
            if ( $keyword === '' ) {
                continue;
            }

            $competition = strtoupper( sanitize_text_field( $item['competition'] ?? 'MEDIUM' ) );
            if ( ! in_array( $competition, [ 'LOW', 'MEDIUM', 'HIGH' ], true ) ) {
                $competition = 'MEDIUM';
            }

            $metrics[ $keyword ] = [
                'monthly_volume' => max( 0, (int) ( $item['monthly_volume'] ?? 0 ) ),
                'competition'    => $competition,
            ];
        }

        $this->increment_daily_usage();
        set_transient( $cache_key, $metrics, 30 * DAY_IN_SECONDS );

        return $metrics;
    }

    public function generate_topic_calculator_ideas( string $topic ){
        $settings = $this->get_settings();

        if ( empty( $settings['api_key'] ) ) {
            return new \WP_Error( 'hge_ai_missing_key', sprintf( __( '%s API key tanımlı değil.', 'hge' ), $this->get_provider_label() ) );
        }

        if ( ! $this->has_daily_quota() ) {
            return new \WP_Error( 'hge_ai_limit', __( 'Günlük AI analiz limiti doldu.', 'hge' ) );
        }

        $topic = sanitize_text_field( $topic );
        if ( $topic === '' ) {
            return new \WP_Error( 'hge_ai_empty_topic', __( 'Konu alanı boş.', 'hge' ) );
        }

        $model     = $this->resolve_model( $settings );
        $cache_key = 'hge_ai_topic_ideas_v2_' . md5( (string) $settings['provider'] . '_' . $model . '_' . strtolower( $topic ) );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => 'Sen Türkçe hesaplama aracı fikirleri bulan bir SEO ürün uzmanısın. Sadece JSON döndür. Kullanıcının yazdığı konunun dışına çıkma. Farklı kategoriye kayan, alakasız veya genel finans başlıkları üretme. Her öneri doğrudan aynı konu kümesinde kalmalı.',
            ],
            [
                'role'    => 'user',
                'content' => "\"{$topic}\" konusu için hesaplamaa.com sitesine eklenebilecek 30 gerçek hesaplama aracı keywordü öner. Kurallar: 1) Keyword mutlaka kullanıcının yazdığı konuyla doğrudan ilgili olsun. 2) Konu dışına çıkma. Örnek: kullanıcı sağlık yazdıysa kredi, faiz, maaş, vergi gibi finans başlıkları üretme. Kullanıcı kredi yazdıysa gebelik, yaş, kalori gibi sağlık başlıkları üretme. 3) Genel ve anlamsız başlık üretme: \"dönüştürücü hesaplama\", \"oran hesaplama\", \"puan hesaplama\" gibi konu belirtmeyen ifadeler yasak. 4) Her öneri Google'da aranabilecek doğal Türkçe sorgu olsun; tercihen \"... hesaplama\" veya net hesaplayıcı niyeti taşısın. 5) Zaman konusu için tarih, gün, hafta, saat, mesai, yaş, geri sayım ve iş günü araçlarına odaklan. 6) Sağlık konusu için kilo, VKİ, kalori, gebelik, yumurtlama, su/protein ihtiyacı, metabolizma ve yağ oranı gibi ölçülebilir araçlara odaklan. 7) Sonuç listesinde konu dışı tek bir öneri bile verme. Türkiye pazarı için aylık hacim tahmini, rekabet ve fırsat skoru ver. competition LOW, MEDIUM, HIGH olmalı. JSON: {\"ideas\":[{\"keyword\":\"...\",\"monthly_volume\":1000,\"competition\":\"MEDIUM\",\"opportunity_score\":80,\"reason\":\"...\"}]}",
            ],
        ];

        $result = $this->request_json( $settings, $model, $messages, 1400, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $data     = $this->decode_json_text( (string) $result['text'] );
        $ideas    = [];
        $raw_ideas = $data['ideas'] ?? $data['items'] ?? $data['keywords'] ?? ( $this->is_list_array( $data ) ? $data : [] );

        foreach ( (array) $raw_ideas as $item ) {
            if ( is_string( $item ) ) {
                $item = [ 'keyword' => $item ];
            }

            $keyword = sanitize_text_field( $item['keyword'] ?? '' );
            if ( $keyword === '' ) {
                continue;
            }

            $competition = strtoupper( sanitize_text_field( $item['competition'] ?? 'MEDIUM' ) );
            if ( ! in_array( $competition, [ 'LOW', 'MEDIUM', 'HIGH' ], true ) ) {
                $competition = 'MEDIUM';
            }

            $ideas[] = [
                'keyword'           => $keyword,
                'monthly_volume'    => max( 0, (int) ( $item['monthly_volume'] ?? 0 ) ),
                'competition'       => $competition,
                'opportunity_score' => max( 1, min( 100, (int) ( $item['opportunity_score'] ?? 70 ) ) ),
                'reason'            => sanitize_textarea_field( $item['reason'] ?? '' ),
            ];
        }

        $ideas = array_slice( $ideas, 0, 30 );
        if ( empty( $ideas ) ) {
            $ideas = $this->fallback_topic_ideas( $topic );
        }

        $this->increment_daily_usage();
        set_transient( $cache_key, $ideas, 14 * DAY_IN_SECONDS );

        return $ideas;
    }

    public function generate_calculator_universe_ideas(){
        $settings = $this->get_settings();

        if ( empty( $settings['api_key'] ) ) {
            return new \WP_Error( 'hge_ai_missing_key', sprintf( __( '%s API key tanımlı değil.', 'hge' ), $this->get_provider_label() ) );
        }

        if ( ! $this->has_daily_quota() ) {
            return new \WP_Error( 'hge_ai_limit', __( 'Günlük AI analiz limiti doldu.', 'hge' ) );
        }

        $model     = $this->resolve_model( $settings );
        $cache_key = 'hge_ai_calculator_universe_v2_' . md5( (string) $settings['provider'] . '_' . $model );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => 'Sen Türkiye pazarı için SEO odaklı hesaplama aracı fırsatları bulan bir ürün stratejistisin. Sadece geçerli JSON döndür.',
            ],
            [
                'role'    => 'user',
                'content' => 'hesaplamaa.com için kategori sormadan, tüm hesaplama aracı evreninden 80 gerçek keyword öner. Finans, maaş, vergi, SGK, emeklilik, kredi, yatırım, sağlık, gebelik, kilo, zaman, tarih, eğitim, not, sınav, ölçü birimi, inşaat, araç, yakıt, enerji, hukuk, resmi işlem, e-ticaret ve günlük hayat alanlarını kapsa. Her keyword doğal Türkçe arama sorgusu olsun; tercihen "... hesaplama", "... hesaplayıcı", "kaç gün", "ne kadar" gibi net hesaplama niyeti taşısın. Marka adı, haber konusu ve hesaplama aracı olmayan genel makale fikri üretme. Türkiye pazarı için aylık hacim tahmini, rekabet ve fırsat skoru ver. competition sadece LOW, MEDIUM, HIGH olabilir. JSON: {"ideas":[{"keyword":"...","category":"finans","monthly_volume":1000,"competition":"MEDIUM","opportunity_score":80,"reason":"..."}]}',
            ],
        ];

        $result = $this->request_json( $settings, $model, $messages, 5000, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $data     = $this->decode_json_text( (string) $result['text'] );
        $ideas    = [];
        $raw_ideas = $data['ideas'] ?? $data['items'] ?? $data['keywords'] ?? ( $this->is_list_array( $data ) ? $data : [] );

        foreach ( (array) $raw_ideas as $item ) {
            if ( is_string( $item ) ) {
                $item = [ 'keyword' => $item ];
            }

            $keyword = sanitize_text_field( $item['keyword'] ?? '' );
            if ( $keyword === '' ) {
                continue;
            }

            $competition = strtoupper( sanitize_text_field( $item['competition'] ?? 'MEDIUM' ) );
            if ( ! in_array( $competition, [ 'LOW', 'MEDIUM', 'HIGH' ], true ) ) {
                $competition = 'MEDIUM';
            }

            $ideas[] = [
                'keyword'           => $keyword,
                'category'          => sanitize_text_field( $item['category'] ?? 'genel' ),
                'monthly_volume'    => max( 0, (int) ( $item['monthly_volume'] ?? 0 ) ),
                'competition'       => $competition,
                'opportunity_score' => max( 1, min( 100, (int) ( $item['opportunity_score'] ?? 70 ) ) ),
                'reason'            => sanitize_textarea_field( $item['reason'] ?? '' ),
            ];
        }

        $ideas = array_slice( $ideas, 0, 80 );
        if ( empty( $ideas ) ) {
            $ideas = $this->fallback_global_ideas();
        }

        $this->increment_daily_usage();
        set_transient( $cache_key, $ideas, 14 * DAY_IN_SECONDS );

        return $ideas;
    }

    private function request_json( array $settings, string $model, array $messages, int $max_tokens, bool $expect_json = true ){
        $provider = $this->normalize_provider( (string) ( $settings['provider'] ?? self::PROVIDER_OPENAI ) );
        $api_key  = (string) ( $settings['api_key'] ?? '' );

        if ( $provider === self::PROVIDER_DEEPSEEK ) {
            $response = wp_remote_post( $this->get_base_url( $provider, (string) ( $settings['api_base_url'] ?? '' ) ) . '/chat/completions', [
                'timeout' => 45,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'           => $model,
                    'messages'        => $messages,
                    'max_tokens'      => $max_tokens,
                    'temperature'     => 0.2,
                    'response_format' => $expect_json ? [ 'type' => 'json_object' ] : [ 'type' => 'text' ],
                    'thinking'        => [ 'type' => 'disabled' ],
                ], JSON_UNESCAPED_UNICODE ),
            ] );
        } else {
            $response = wp_remote_post( $this->get_base_url( $provider, (string) ( $settings['api_base_url'] ?? '' ) ) . '/responses', [
                'timeout' => 45,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'             => $model,
                    'input'             => $messages,
                    'max_output_tokens' => $max_tokens,
                    'text'              => [
                        'format' => [
                            'type' => $expect_json ? 'json_object' : 'text',
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE ),
            ] );
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            $message = is_array( $body ) && ! empty( $body['error']['message'] )
                ? (string) $body['error']['message']
                : sprintf( __( '%s isteği başarısız oldu.', 'hge' ), $this->get_provider_label( $provider ) );

            return new \WP_Error( 'hge_ai_request_error', $message );
        }

        $text = $this->extract_output_text( is_array( $body ) ? $body : [], $provider );
        if ( $text === '' ) {
            return new \WP_Error( 'hge_ai_empty_response', __( 'AI yanıtı boş döndü.', 'hge' ) );
        }

        return [
            'text'  => $text,
            'usage' => $body['usage'] ?? null,
            'raw'   => $body,
        ];
    }

    private function resolve_model( array $settings ){
        $provider = $this->normalize_provider( (string) ( $settings['provider'] ?? self::PROVIDER_OPENAI ) );
        $model    = sanitize_text_field( (string) ( $settings['model'] ?? '' ) );
        $allowed  = $this->get_supported_models();

        if ( ! empty( $allowed[ $provider ] ) && in_array( $model, $allowed[ $provider ], true ) ) {
            return $model;
        }

        return $provider === self::PROVIDER_DEEPSEEK ? 'deepseek-v4-flash' : 'gpt-5-mini';
    }

    private function normalize_provider( string $provider ){
        return $provider === self::PROVIDER_DEEPSEEK ? self::PROVIDER_DEEPSEEK : self::PROVIDER_OPENAI;
    }

    private function get_base_url( string $provider, string $base_url ){
        $base_url = untrailingslashit( trim( $base_url ) );
        if ( $base_url !== '' ) {
            return $base_url;
        }

        return $provider === self::PROVIDER_DEEPSEEK
            ? 'https://api.deepseek.com'
            : 'https://api.openai.com/v1';
    }

    private function decode_json_text( string $text ){
        $data = json_decode( $text, true );
        if ( is_array( $data ) ) {
            return $data;
        }

        if ( preg_match( '/\{.*\}/s', $text, $match ) ) {
            $data = json_decode( $match[0], true );
            if ( is_array( $data ) ) {
                return $data;
            }
        }

        if ( preg_match( '/\[.*\]/s', $text, $match ) ) {
            $data = json_decode( $match[0], true );
            if ( is_array( $data ) ) {
                return $data;
            }
        }

        return [];
    }

    private function is_list_array( array $value ){
        return array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    private function fallback_topic_ideas( string $topic ){
        $topic_lc = strtolower( $topic );
        $map = [
            'sağlık' => [ 'ideal kilo hesaplama', 'vücut kitle indeksi hesaplama', 'kalori ihtiyacı hesaplama', 'gebelik haftası hesaplama', 'yumurtlama günü hesaplama', 'bazal metabolizma hesaplama', 'su ihtiyacı hesaplama', 'tansiyon risk hesaplama', 'bel kalça oranı hesaplama', 'protein ihtiyacı hesaplama', 'günlük kalori hesaplama', 'hamilelik haftası hesaplama' ],
            'saglik' => [ 'ideal kilo hesaplama', 'vücut kitle indeksi hesaplama', 'kalori ihtiyacı hesaplama', 'gebelik haftası hesaplama', 'yumurtlama günü hesaplama', 'bazal metabolizma hesaplama', 'su ihtiyacı hesaplama', 'tansiyon risk hesaplama', 'bel kalça oranı hesaplama', 'protein ihtiyacı hesaplama', 'günlük kalori hesaplama', 'hamilelik haftası hesaplama' ],
            'finans' => [ 'kredi hesaplama', 'faiz hesaplama', 'mevduat faizi hesaplama', 'kredi kartı asgari ödeme hesaplama', 'ihtiyaç kredisi hesaplama', 'konut kredisi hesaplama', 'araç kredisi hesaplama', 'enflasyon hesaplama', 'bileşik faiz hesaplama', 'taksit hesaplama' ],
            'zaman' => [ 'iki tarih arası gün hesaplama', 'kaç gün kaldı hesaplama', 'hafta hesaplama', 'mesai saati hesaplama', 'yaş hesaplama', 'doğum günü hesaplama', 'yılın kaçıncı günü hesaplama', 'iş günü hesaplama', 'tatil günü hesaplama', 'saat farkı hesaplama', 'geri sayım hesaplama', 'dakika saat hesaplama', 'ay farkı hesaplama', 'çalışma saati hesaplama' ],
        ];

        $keywords = $map[ $topic_lc ] ?? array_map( function ( $suffix ) use ( $topic ){
            return trim( $topic . ' ' . $suffix );
        }, [ 'hesaplama aracı', 'süre hesaplama', 'ihtiyaç hesaplama', 'risk hesaplama', 'tutar hesaplama', 'gün hesaplama', 'formül hesaplama' ] );

        $ideas = [];
        foreach ( array_slice( $keywords, 0, 20 ) as $index => $keyword ) {
            $ideas[] = [
                'keyword'           => $keyword,
                'monthly_volume'    => max( 250, 5200 - ( $index * 320 ) ),
                'competition'       => $index < 3 ? 'HIGH' : ( $index < 7 ? 'MEDIUM' : 'LOW' ),
                'opportunity_score' => max( 65, 94 - $index ),
                'reason'            => 'Konu odaklı hesaplama aracı fırsatı.',
            ];
        }

        return $ideas;
    }

    public function fallback_global_ideas(){
        $keywords = [
            [ 'kredi hesaplama', 'finans' ],
            [ 'ihtiyaç kredisi hesaplama', 'finans' ],
            [ 'konut kredisi hesaplama', 'finans' ],
            [ 'araç kredisi hesaplama', 'finans' ],
            [ 'mevduat faizi hesaplama', 'finans' ],
            [ 'bileşik faiz hesaplama', 'finans' ],
            [ 'maaş hesaplama', 'maaş' ],
            [ 'net brüt maaş hesaplama', 'maaş' ],
            [ 'kıdem tazminatı hesaplama', 'maaş' ],
            [ 'ihbar tazminatı hesaplama', 'maaş' ],
            [ 'fazla mesai hesaplama', 'maaş' ],
            [ 'gelir vergisi hesaplama', 'vergi' ],
            [ 'kdv hesaplama', 'vergi' ],
            [ 'mtv hesaplama', 'vergi' ],
            [ 'emlak vergisi hesaplama', 'vergi' ],
            [ 'emeklilik yaşı hesaplama', 'sgk' ],
            [ 'prim günü hesaplama', 'sgk' ],
            [ 'ideal kilo hesaplama', 'sağlık' ],
            [ 'vücut kitle indeksi hesaplama', 'sağlık' ],
            [ 'kalori ihtiyacı hesaplama', 'sağlık' ],
            [ 'gebelik haftası hesaplama', 'sağlık' ],
            [ 'yumurtlama günü hesaplama', 'sağlık' ],
            [ 'iki tarih arası gün hesaplama', 'zaman' ],
            [ 'yaş hesaplama', 'zaman' ],
            [ 'iş günü hesaplama', 'zaman' ],
            [ 'not ortalaması hesaplama', 'eğitim' ],
            [ 'yks puan hesaplama', 'eğitim' ],
            [ 'lgs puan hesaplama', 'eğitim' ],
            [ 'yakıt tüketimi hesaplama', 'araç' ],
            [ 'metrekare hesaplama', 'inşaat' ],
        ];

        $ideas = [];
        foreach ( $keywords as $index => $row ) {
            $ideas[] = [
                'keyword'           => $row[0],
                'category'          => $row[1],
                'monthly_volume'    => max( 500, 8000 - ( $index * 180 ) ),
                'competition'       => $index < 10 ? 'HIGH' : ( $index < 22 ? 'MEDIUM' : 'LOW' ),
                'opportunity_score' => max( 65, 95 - $index ),
                'reason'            => 'Genel hesaplama aracı fırsatı.',
            ];
        }

        return $ideas;
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
                'role'    => 'system',
                'content' => 'Sen SEO ve ürün odaklı kısa brief üreten bir asistansın. Sadece geçerli JSON döndür. Gereksiz açıklama yazma. Yanıt kısa, uygulanabilir ve Türkçe olmalı.',
            ],
            [
                'role'    => 'user',
                'content' => "Aşağıdaki fırsatı analiz et. JSON alanları: why_important, recommended_type, seo_difficulty, competitor_signal, content_angle, calculator_idea, titles, meta_title, meta_description, slug.\n\n" . wp_json_encode( $context, JSON_UNESCAPED_UNICODE ),
            ],
        ];
    }

    private function extract_output_text( array $body, string $provider ){
        if ( $provider === self::PROVIDER_DEEPSEEK ) {
            return (string) ( $body['choices'][0]['message']['content'] ?? '' );
        }

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
            'why_important'     => sanitize_textarea_field( $data['why_important'] ?? '' ),
            'recommended_type'  => sanitize_text_field( $data['recommended_type'] ?? '' ),
            'seo_difficulty'    => sanitize_text_field( $data['seo_difficulty'] ?? '' ),
            'competitor_signal' => sanitize_textarea_field( $data['competitor_signal'] ?? '' ),
            'content_angle'     => sanitize_textarea_field( $data['content_angle'] ?? '' ),
            'calculator_idea'   => sanitize_textarea_field( $data['calculator_idea'] ?? '' ),
            'titles'            => array_slice( $titles, 0, 5 ),
            'meta_title'        => sanitize_text_field( $data['meta_title'] ?? '' ),
            'meta_description'  => sanitize_textarea_field( $data['meta_description'] ?? '' ),
            'slug'              => sanitize_title( $data['slug'] ?? '' ),
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
