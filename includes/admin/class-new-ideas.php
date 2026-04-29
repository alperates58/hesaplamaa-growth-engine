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

        foreach ( $enriched as $item ) {
            $this->repo->save_suggestion( [
                'topic'            => $item['keyword'],
                'monthly_volume'   => 0, // Ads API olmadan tahmin yok
                'competition'      => 'unknown',
                'opportunity_score' => $item['opportunity_score'],
                'exists_on_site'   => $item['exists_on_site'],
                'should_create'    => $item['should_create'],
                'source'           => 'google_suggest',
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
