<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class KeywordOpportunities {

    private \HGE\DB\Repository $repo;

    public function __construct() {
        $this->repo = new \HGE\DB\Repository();
    }

    public function get_data(){
        $rows = $this->repo->get_opportunities( 200 );
        return array_map( [ $this, 'enrich_row' ], $rows );
    }

    private function enrich_row( array $row ){
        $row['suggestion'] = $this->make_suggestion( $row );
        return $row;
    }

    private function make_suggestion( array $row ){
        $pos = (float) $row['avg_position'];
        $ctr = (float) $row['ctr'];

        if ( $pos <= 5 && $ctr < 0.05 ) {
            return __( 'Başlığı ve meta açıklamayı güçlendir', 'hge' );
        }
        if ( $pos > 5 && $pos <= 10 ) {
            return __( 'İçeriği genişlet, FAQ schema ekle', 'hge' );
        }
        if ( $pos > 10 && $pos <= 20 ) {
            return __( 'İçerik güncellemesi + iç link artır', 'hge' );
        }
        return __( 'Yeni landing page oluştur', 'hge' );
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }
        $opportunities = $this->get_data();
        $gsc_connected = ( new \HGE\API\GSCClient() )->is_connected();
        require HGE_DIR . 'templates/admin/opportunities.php';
    }
}
