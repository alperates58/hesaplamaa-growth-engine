<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class NewIdeas {

    private \HGE\DB\Repository    $repo;
    private \HGE\API\SuggestClient $suggest;

    public function __construct() {
        $this->repo    = new \HGE\DB\Repository();
        $this->suggest = new \HGE\API\SuggestClient();
    }

    public function get_data(){
        // Önce DB'den bak
        $cached = $this->repo->get_suggestions( 200 );
        if ( ! empty( $cached ) ) {
            return $cached;
        }
        // DB boşsa Google Suggest'ten çek ve kaydet
        return $this->fetch_and_store();
    }

    public function fetch_and_store(){
        $raw         = $this->suggest->get_all_suggestions();
        $enriched    = $this->suggest->enrich_with_site_data( $raw );
        $metrics     = $this->suggest->get_ai_metric_estimates( array_column( $enriched, 'keyword' ) );

        foreach ( $enriched as $item ) {
            if ( isset( $metrics[ $item['keyword'] ] ) ) {
                $item['monthly_volume'] = (int) $metrics[ $item['keyword'] ]['monthly_volume'];
                $item['competition']    = $metrics[ $item['keyword'] ]['competition'];
            }

            $this->repo->save_suggestion( [
                'topic'            => $item['keyword'],
                'monthly_volume'   => (int) ( $item['monthly_volume'] ?? 0 ),
                'competition'      => $item['competition'] ?? 'unknown',
                'opportunity_score' => $item['opportunity_score'],
                'exists_on_site'   => $item['exists_on_site'],
                'should_create'    => $item['should_create'],
                'source'           => $item['seed_source'] ?? 'google_suggest',
            ] );
        }

        return $this->repo->get_suggestions( 200 );
    }

    public function generate_topic_ideas( string $topic ){
        $client = new \HGE\API\OpenAIClient();
        if ( ! $client->is_configured() ) {
            return new \WP_Error( 'hge_ai_not_configured', __( 'AI entegrasyonu aktif değil veya API key eksik.', 'hge' ) );
        }

        $ideas = $client->generate_topic_calculator_ideas( $topic );
        if ( is_wp_error( $ideas ) ) {
            return $ideas;
        }

        $enriched = $this->suggest->enrich_with_site_data( array_map( function ( array $idea ) use ( $topic ){
            return [
                'keyword'        => $idea['keyword'],
                'seed'           => $topic,
                'seed_source'    => 'ai_topic',
                'monthly_volume' => $idea['monthly_volume'],
                'competition'    => $idea['competition'],
            ];
        }, $ideas ) );

        foreach ( $enriched as $index => $item ) {
            $ai_score = (int) ( $ideas[ $index ]['opportunity_score'] ?? 0 );
            if ( $ai_score > 0 ) {
                $item['opportunity_score'] = $ai_score;
                $item['should_create'] = ( ! $item['exists_on_site'] && $ai_score >= 60 ) ? 1 : 0;
            }

            $this->repo->save_suggestion( [
                'topic'             => $item['keyword'],
                'monthly_volume'    => (int) ( $item['monthly_volume'] ?? 0 ),
                'competition'       => $item['competition'] ?? 'MEDIUM',
                'opportunity_score' => (int) $item['opportunity_score'],
                'exists_on_site'    => (int) $item['exists_on_site'],
                'should_create'     => (int) $item['should_create'],
                'source'            => 'ai_topic',
            ] );
        }

        return $this->repo->get_suggestions( 200 );
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }
        $suggestions = $this->get_data();
        require HGE_DIR . 'templates/admin/new-ideas.php';
    }
}
