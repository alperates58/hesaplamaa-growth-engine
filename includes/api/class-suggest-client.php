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

        foreach ( $this->seed_keywords as $seed ) {
            $suggestions = $this->get_suggestions( $seed );
            foreach ( $suggestions as $suggestion ) {
                if ( ! isset( $seen[ $suggestion ] ) ) {
                    $seen[ $suggestion ] = true;
                    $results[] = [
                        'keyword' => $suggestion,
                        'seed'    => $seed,
                    ];
                }
            }
            // Rate limit — Google Suggest'i yavaşlat
            usleep( 200000 ); // 200ms
        }

        return $results;
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

    public function get_seed_keywords(){
        return $this->seed_keywords;
    }
}
