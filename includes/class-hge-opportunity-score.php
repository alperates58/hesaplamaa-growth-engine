<?php
namespace HGE;

defined( 'ABSPATH' ) || exit;

class OpportunityScore {

    private const INTENT_TERMS = [
        'hesaplama',
        'hesaplayici',
        'hesaplayıcı',
        'formulu',
        'formülü',
        'kac',
        'kaç',
        'ne kadar',
        'oran',
        'donusturucu',
        'dönüştürücü',
        'cevirici',
        'çevirici',
    ];

    public function score( array $row ){
        $quality_issue = ! empty( $row['has_quality_issue'] );
        $position      = (float) ( $row['position'] ?? 999 );
        $impressions   = (int) ( $row['impressions'] ?? 0 );
        $ctr           = (float) ( $row['ctr'] ?? 0 );
        $volume        = (int) ( $row['search_volume'] ?? 0 );
        $competition   = strtoupper( sanitize_text_field( (string) ( $row['competition'] ?? 'UNKNOWN' ) ) );
        $post_id       = (int) ( $row['post_id'] ?? 0 );
        $score         = 0;

        if ( $position >= 4 && $position <= 10 ) {
            $score += 25;
        } elseif ( $position >= 11 && $position <= 20 ) {
            $score += 35;
        } elseif ( $position >= 21 && $position <= 50 ) {
            $score += 20;
        }

        if ( $this->has_high_impression_low_ctr( $impressions, $ctr ) ) {
            $score += 20;
        }

        if ( $this->is_high_volume( $volume ) ) {
            $score += 15;
        }

        if ( in_array( $competition, [ 'LOW', 'MEDIUM' ], true ) ) {
            $score += 10;
        }

        $intent_score = $this->get_intent_score( (string) ( $row['keyword'] ?? '' ) );
        if ( $intent_score > 0 ) {
            $score += 10;
        }

        if ( $post_id > 0 ) {
            $score += 10;
        }

        if ( $this->is_stale_page( (string) ( $row['post_modified_gmt'] ?? '' ) ) ) {
            $score += 5;
        }

        $score  = max( 0, min( 100, $score ) );
        $status = $quality_issue ? __( 'Önce kalite sorunu çözülmeli', 'hge' ) : $this->map_status( $score );

        return [
            'intent_score'      => $intent_score,
            'opportunity_score' => $score,
            'status'            => $status,
            'recommended_actions' => $this->build_actions(
                $row,
                $quality_issue,
                $intent_score
            ),
        ];
    }

    public function get_intent_score( string $keyword ){
        $normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( remove_accents( $keyword ), 'UTF-8' ) : strtolower( remove_accents( $keyword ) );

        foreach ( self::INTENT_TERMS as $term ) {
            $term = function_exists( 'mb_strtolower' ) ? mb_strtolower( remove_accents( $term ), 'UTF-8' ) : strtolower( remove_accents( $term ) );
            if ( $term !== '' && strpos( $normalized, $term ) !== false ) {
                return 10;
            }
        }

        return 0;
    }

    public function map_status( int $score ){
        if ( $score >= 85 ) {
            return __( 'Acil Büyüt', 'hge' );
        }

        if ( $score >= 70 ) {
            return __( 'Hızlı Kazanım', 'hge' );
        }

        if ( $score >= 50 ) {
            return __( 'Destekle', 'hge' );
        }

        if ( $score >= 30 ) {
            return __( 'İzle', 'hge' );
        }

        return __( 'Düşük Öncelik', 'hge' );
    }

    public function has_high_impression_low_ctr( int $impressions, float $ctr ){
        return $impressions >= 500 && $ctr <= 0.03;
    }

    public function is_high_volume( int $volume ){
        return $volume >= 1000;
    }

    private function is_stale_page( string $modified_gmt ){
        if ( $modified_gmt === '' ) {
            return false;
        }

        $modified = strtotime( $modified_gmt );
        if ( ! $modified ) {
            return false;
        }

        return $modified < strtotime( '-30 days' );
    }

    private function build_actions( array $row, bool $quality_issue, int $intent_score ){
        $actions  = [];
        $position = (float) ( $row['position'] ?? 999 );
        $ctr      = (float) ( $row['ctr'] ?? 0 );
        $volume   = (int) ( $row['search_volume'] ?? 0 );
        $post_id  = (int) ( $row['post_id'] ?? 0 );

        if ( $quality_issue ) {
            return [
                __( 'Shortcode/render kontrolü yap', 'hge' ),
                __( 'Canonical/noindex/HTTP kontrolü yap', 'hge' ),
            ];
        }

        if ( $this->has_high_impression_low_ctr( (int) ( $row['impressions'] ?? 0 ), $ctr ) ) {
            $actions[] = __( 'Title iyileştir', 'hge' );
            $actions[] = __( 'Meta description iyileştir', 'hge' );
        }

        if ( $position >= 4 && $position <= 20 ) {
            $actions[] = __( 'İlk paragrafı güçlendir', 'hge' );
            $actions[] = __( 'SSS ekle', 'hge' );
        }

        if ( $post_id > 0 ) {
            $actions[] = __( 'İç link ver', 'hge' );
            $actions[] = __( 'Benzer hesaplamalar bloğuna ekle', 'hge' );
        }

        if ( $volume >= 1000 || $intent_score > 0 ) {
            $actions[] = __( 'Kategori sayfasında öne çıkar', 'hge' );
        }

        if ( empty( $actions ) ) {
            $actions[] = __( 'IndexNow gönder', 'hge' );
        }

        return array_values( array_unique( $actions ) );
    }
}
