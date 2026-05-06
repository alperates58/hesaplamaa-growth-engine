<?php
namespace HGE\API;

defined( 'ABSPATH' ) || exit;

/**
 * Google Suggest / Autocomplete İstemcisi
 * Ücretsiz, API key gerektirmez
 */
class SuggestClient {

    const SUGGEST_URL = 'https://suggestqueries.google.com/complete/search';

    /**
     * hesaplamaa.com için seed keyword listesi
     */
    private array $seed_keywords = [
        'hesaplama', 'hesaplayıcı', 'kaç gün', 'kaç hafta',
        'ne kadar', 'oran hesaplama', 'maaş hesaplama',
        'faiz hesaplama', 'kredi hesaplama', 'burç hesaplama',
        'yaş hesaplama', 'gebelik hesaplama', 'emeklilik hesaplama',
        'vergi hesaplama', 'vade hesaplama', 'puan hesaplama',
        'beden hesaplama', 'kilo hesaplama', 'bmi hesaplama',
    ];

    /**
     * Tek bir seed için öneri al
     */
    public function get_suggestions( string $seed, string $lang = 'tr' ){
        $cache_key = 'hge_suggest_' . md5( $seed . $lang );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $url = add_query_arg(
            [
                'client' => 'firefox',
                'hl'     => $lang,
                'q'      => rawurlencode( $seed ),
            ],
            self::SUGGEST_URL
        );

        $response = wp_remote_get( $url, [
            'timeout'    => 10,
            'user-agent' => 'Mozilla/5.0 (compatible; HGE/1.0)',
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $suggestions = $body[1] ?? [];

        // Sadece string değerleri al
        $suggestions = array_filter( $suggestions, 'is_string' );
        $suggestions = array_values( $suggestions );

        // 12 saat cache
        set_transient( $cache_key, $suggestions, 12 * HOUR_IN_SECONDS );

        return $suggestions;
    }

    /**
     * Tüm seed'ler için toplu öneri al
     *
     * @return array [ 'keyword' => string, 'seed' => string ]
     */
    public function get_all_suggestions(){
        $results = [];
        $seen    = [];

        foreach ( $this->get_active_seed_keywords() as $seed ) {
            $seed_source = in_array( $seed, $this->seed_keywords, true ) ? 'google_suggest' : 'ai_seed_google_suggest';
            $suggestions = $this->get_suggestions( $seed );
            foreach ( $suggestions as $suggestion ) {
                if ( ! $this->is_calculator_keyword( $suggestion ) ) {
                    continue;
                }

                if ( ! isset( $seen[ $suggestion ] ) ) {
                    $seen[ $suggestion ] = true;
                    $results[] = [
                        'keyword'     => $suggestion,
                        'seed'        => $seed,
                        'seed_source' => $seed_source,
                    ];
                }
            }
            // Rate limit — Google Suggest'i yavaşlat
            usleep( 200000 ); // 200ms
        }

        return $results;
    }

    /**
     * Kullanici konusuna ozel Google Suggest taramasi yap.
     *
     * @return array [ 'keyword' => string, 'seed' => string, 'seed_source' => string ]
     */
    public function get_topic_suggestions( string $topic, int $limit = 40 ){
        $topic = sanitize_text_field( $topic );
        if ( $topic === '' ) {
            return [];
        }

        $seeds   = $this->build_topic_seeds( $topic );
        $results = [];
        $seen    = [];

        foreach ( $seeds as $seed ) {
            foreach ( $this->get_suggestions( $seed ) as $suggestion ) {
                if ( ! $this->is_calculator_keyword( $suggestion ) || ! $this->is_topic_relevant( $suggestion, $topic ) ) {
                    continue;
                }

                $key = $this->normalize_keyword( $suggestion );
                if ( isset( $seen[ $key ] ) ) {
                    continue;
                }

                $seen[ $key ] = true;
                $results[] = [
                    'keyword'     => $suggestion,
                    'seed'        => $seed,
                    'seed_source' => 'topic_google_suggest',
                ];

                if ( count( $results ) >= $limit ) {
                    return $results;
                }
            }

            usleep( 150000 );
        }

        return $results;
    }

    public function is_topic_keyword_relevant( string $keyword, string $topic ){
        return $this->is_topic_relevant( $keyword, $topic );
    }

    /**
     * Sitenin mevcut URL'lerini al ve eşleştir
     */
    public function enrich_with_site_data( array $suggestions ){
        // Mevcut hesaplama sayfalarının slug'larını al
        $args  = [
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];
        $pages = get_posts( $args );

        $existing_slugs = [];
        foreach ( $pages as $page_id ) {
            $existing_slugs[] = strtolower( get_post_field( 'post_name', $page_id ) );
        }

        return array_map( function ( array $item ) use ( $existing_slugs ){
            $slug                 = sanitize_title( $item['keyword'] );
            $item['exists_on_site'] = in_array( $slug, $existing_slugs, true ) ? 1 : 0;
            $metrics              = $this->estimate_local_metrics( $item['keyword'] );
            $item['monthly_volume'] = (int) ( $item['monthly_volume'] ?? $metrics['monthly_volume'] );
            $item['competition']    = sanitize_text_field( $item['competition'] ?? $metrics['competition'] );
            $item['opportunity_score'] = $this->score_suggestion( $item );
            $item['should_create']    = ( ! $item['exists_on_site'] && $item['opportunity_score'] >= 60 ) ? 1 : 0;
            return $item;
        }, $suggestions );
    }

    private function score_suggestion( array $item ){
        $score = 50; // Temel puan

        // Yüksek hacimli seed'lerden gelenlere ekstra puan
        $high_value_seeds = [ 'maaş hesaplama', 'kredi hesaplama', 'faiz hesaplama', 'vergi hesaplama' ];
        if ( in_array( $item['seed'], $high_value_seeds, true ) ) {
            $score += 25;
        }

        // Sitede yoksa fırsat puanı artır
        if ( empty( $item['exists_on_site'] ) ) {
            $score += 15;
        }

        return min( 100, $score );
    }

    private function estimate_local_metrics( string $keyword ){
        $keyword = strtolower( $keyword );
        $volume = 450;
        $competition = 'LOW';

        $high_volume_terms = [ 'maaş', 'kredi', 'faiz', 'vergi', 'emeklilik', 'gebelik', 'yaş', 'bmi', 'kilo' ];
        foreach ( $high_volume_terms as $term ) {
            if ( strpos( $keyword, $term ) !== false ) {
                $volume = 5400;
                $competition = 'HIGH';
                break;
            }
        }

        $medium_terms = [ 'puan', 'vade', 'oran', 'yüzde', 'tarih', 'gün', 'hafta', 'beden' ];
        foreach ( $medium_terms as $term ) {
            if ( strpos( $keyword, $term ) !== false && $volume < 1000 ) {
                $volume = 1900;
                $competition = 'MEDIUM';
                break;
            }
        }

        if ( strpos( $keyword, '2025' ) !== false || strpos( $keyword, '2026' ) !== false ) {
            $volume = (int) round( $volume * 1.35 );
        }

        if ( strpos( $keyword, 'nasıl' ) !== false || strpos( $keyword, 'formülü' ) !== false ) {
            $competition = $competition === 'HIGH' ? 'MEDIUM' : $competition;
        }

        return [
            'monthly_volume' => $volume,
            'competition'    => $competition,
        ];
    }

    private function build_topic_seeds( string $topic ){
        $topic_lc = $this->normalize_keyword( $topic );

        $topic_map = [
            'zaman' => [
                'zaman hesaplama',
                'tarih hesaplama',
                'gün hesaplama',
                'kaç gün',
                'kaç hafta',
                'iki tarih arası',
                'mesai saati hesaplama',
                'iş günü hesaplama',
            ],
            'tarih' => [
                'tarih hesaplama',
                'iki tarih arası',
                'gün hesaplama',
                'kaç gün',
                'hafta hesaplama',
            ],
            'saglik' => [
                'sağlık hesaplama',
                'ideal kilo hesaplama',
                'vücut kitle indeksi hesaplama',
                'kalori hesaplama',
                'gebelik hesaplama',
                'yumurtlama hesaplama',
                'protein ihtiyacı hesaplama',
                'su ihtiyacı hesaplama',
                'bazal metabolizma hesaplama',
                'bel kalça oranı hesaplama',
                'vücut yağ oranı hesaplama',
                'doğum tarihi gebelik hesaplama',
            ],
            'gebelik' => [
                'gebelik hesaplama',
                'gebelik haftası hesaplama',
                'hamilelik hesaplama',
                'yumurtlama hesaplama',
                'doğum tarihi hesaplama',
            ],
            'vki' => [
                'vki hesaplama',
                'vücut kitle indeksi hesaplama',
                'bmi hesaplama',
                'ideal kilo hesaplama',
            ],
            'finans' => [
                'finans hesaplama',
                'kredi hesaplama',
                'faiz hesaplama',
                'mevduat hesaplama',
                'taksit hesaplama',
                'enflasyon hesaplama',
            ],
            'kredi' => [
                'kredi hesaplama',
                'ihtiyaç kredisi hesaplama',
                'konut kredisi hesaplama',
                'araç kredisi hesaplama',
                'taksit hesaplama',
                'faiz hesaplama',
            ],
        ];

        $seeds = $topic_map[ $topic_lc ] ?? [];
        $seeds = array_merge( [
            $topic,
            $topic . ' hesaplama',
            $topic . ' hesaplayıcı',
            $topic . ' aracı',
            $topic . ' kaç',
            $topic . ' ne kadar',
        ], $seeds );

        $seeds = array_map( 'sanitize_text_field', $seeds );
        $seeds = array_filter( $seeds );

        return array_values( array_unique( $seeds ) );
    }

    private function is_calculator_keyword( string $keyword ){
        $keyword_lc = $this->normalize_keyword( $keyword );

        $blocked_exact = [
            'donusturucu hesaplama',
            'dönüştürücü hesaplama',
            'cevirici hesaplama',
            'çevirici hesaplama',
            'hesaplama donusturucu',
            'hesaplama dönüştürücü',
        ];

        if ( in_array( $keyword_lc, $blocked_exact, true ) ) {
            return false;
        }

        $signals = [
            'hesap',
            'hesaplama',
            'hesaplayici',
            'hesaplayıcı',
            'kac gun',
            'kaç gün',
            'kac hafta',
            'kaç hafta',
            'ne kadar',
            'oran',
            'yuzde',
            'yüzde',
        ];

        foreach ( $signals as $signal ) {
            if ( strpos( $keyword_lc, $signal ) !== false ) {
                return true;
            }
        }

        return false;
    }

    private function is_topic_relevant( string $keyword, string $topic ){
        $keyword_lc = $this->normalize_keyword( $keyword );
        $topic_lc   = $this->normalize_keyword( $topic );

        $topic_terms = [
            'zaman' => [ 'zaman', 'tarih', 'gun', 'gün', 'hafta', 'ay', 'yil', 'yıl', 'saat', 'dakika', 'mesai', 'is gunu', 'iş günü', 'yas', 'yaş', 'dogum', 'doğum', 'kac gun', 'kaç gün' ],
            'tarih' => [ 'tarih', 'gun', 'gün', 'hafta', 'ay', 'yil', 'yıl', 'saat', 'mesai', 'is gunu', 'iş günü', 'kac gun', 'kaç gün' ],
            'saglik' => [ 'saglik', 'sağlık', 'kilo', 'vucut', 'vücut', 'bmi', 'vki', 'kalori', 'gebelik', 'hamilelik', 'yumurtlama', 'protein', 'su ihtiyaci', 'su ihtiyacı', 'tansiyon', 'bel kalca', 'bel kalça', 'metabolizma', 'yag orani', 'yağ oranı', 'bazal' ],
            'gebelik' => [ 'gebelik', 'hamilelik', 'yumurtlama', 'doğum tarihi', 'gebelik haftası', 'gebelik hesaplama' ],
            'vki' => [ 'vki', 'bmi', 'vücut kitle', 'ideal kilo', 'kalori', 'metabolizma' ],
            'finans' => [ 'finans', 'kredi', 'faiz', 'maas', 'maaş', 'vergi', 'mevduat', 'taksit', 'enflasyon', 'kur', 'doviz', 'döviz', 'kredi karti', 'kredi kartı' ],
            'kredi' => [ 'kredi', 'faiz', 'taksit', 'konut', 'ihtiyaç kredisi', 'araç kredisi', 'kredi kartı' ],
        ];

        $terms = $topic_terms[ $topic_lc ] ?? [ $topic_lc ];
        foreach ( $terms as $term ) {
            if ( $term !== '' && strpos( $keyword_lc, $term ) !== false ) {
                return true;
            }
        }

        return strpos( $keyword_lc, $topic_lc ) !== false;
    }

    private function normalize_keyword( string $keyword ){
        $keyword = strtolower( remove_accents( $keyword ) );
        $keyword = preg_replace( '/\s+/u', ' ', $keyword );
        return trim( (string) $keyword );
    }

    public function get_ai_metric_estimates( array $keywords ){
        $ads_metrics = $this->get_google_ads_metrics( $keywords );
        if ( ! empty( $ads_metrics ) ) {
            return $ads_metrics;
        }

        if ( ! class_exists( '\HGE\API\OpenAIClient' ) ) {
            return [];
        }

        $client   = new OpenAIClient();
        $settings = $client->get_settings();

        if ( empty( $settings['enabled'] ) || empty( $settings['metrics_enabled'] ) || empty( $settings['api_key'] ) ) {
            return [];
        }

        $all_metrics = [];
        foreach ( array_chunk( $keywords, 30 ) as $chunk ) {
            $metrics = $client->estimate_keyword_metrics( $chunk );
            if ( is_wp_error( $metrics ) || ! is_array( $metrics ) ) {
                continue;
            }
            $all_metrics = array_merge( $all_metrics, $metrics );
        }

        return $all_metrics;
    }

    public function get_google_ads_metrics( array $keywords ){
        if ( ! class_exists( '\HGE\API\GoogleAdsClient' ) ) {
            return [];
        }

        $client = new GoogleAdsClient();
        $all_metrics = [];

        foreach ( array_chunk( $keywords, 100 ) as $chunk ) {
            $metrics = $client->get_keyword_metrics( $chunk );
            if ( ! empty( $metrics ) && is_array( $metrics ) ) {
                $all_metrics = array_merge( $all_metrics, $metrics );
            }
        }

        return $all_metrics;
    }

    public function get_seed_keywords(){
        return $this->seed_keywords;
    }

    public function get_active_seed_keywords(){
        $seeds = $this->seed_keywords;
        $ai_seeds = $this->get_ai_seed_keywords();

        if ( ! empty( $ai_seeds ) ) {
            $seeds = array_merge( $ai_seeds, $seeds );
        }

        $seeds = array_map( 'sanitize_text_field', $seeds );
        $seeds = array_filter( $seeds );
        $seeds = array_values( array_unique( $seeds ) );

        return array_slice( $seeds, 0, 80 );
    }

    private function get_ai_seed_keywords(){
        if ( ! class_exists( '\HGE\API\OpenAIClient' ) ) {
            return [];
        }

        $client   = new OpenAIClient();
        $settings = $client->get_settings();

        if ( empty( $settings['enabled'] ) || empty( $settings['seed_enabled'] ) || empty( $settings['api_key'] ) ) {
            return [];
        }

        $seeds = $client->generate_seed_keywords();
        if ( is_wp_error( $seeds ) || empty( $seeds ) || ! is_array( $seeds ) ) {
            return [];
        }

        return $seeds;
    }
}
