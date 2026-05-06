<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress admin menüsünü kaydet
 */
class Menu {

    public function register(){
        add_action( 'admin_menu', [ $this, 'add_menus' ] );
    }

    public function add_menus(){
        // Ana menü
        add_menu_page(
            __( 'Hesaplamaa Growth Engine', 'hge' ),
            __( 'HGE', 'hge' ),
            'manage_options',
            'hge-dashboard',
            [ $this, 'render_dashboard' ],
            $this->get_menu_icon(),
            30
        );

        // Alt menüler
        $submenus = [
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Dashboard', 'hge' ),
                'menu'   => __( 'Dashboard', 'hge' ),
                'slug'   => 'hge-dashboard',
                'cb'     => [ $this, 'render_dashboard' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Keyword Fırsatları', 'hge' ),
                'menu'   => __( 'Keyword Fırsatları', 'hge' ),
                'slug'   => 'hge-opportunities',
                'cb'     => [ $this, 'render_opportunities' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Keyword Hacim Yükle', 'hge' ),
                'menu'   => __( 'Keyword Hacim Yükle', 'hge' ),
                'slug'   => 'hge-keyword-volume-importer',
                'cb'     => [ $this, 'render_keyword_volume_importer' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Mevcut Sayfa Analizi', 'hge' ),
                'menu'   => __( 'Sayfa Analizi', 'hge' ),
                'slug'   => 'hge-page-analysis',
                'cb'     => [ $this, 'render_page_analysis' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Dizin Durumları', 'hge' ),
                'menu'   => __( 'Dizin Durumları', 'hge' ),
                'slug'   => 'hge-index-status',
                'cb'     => [ $this, 'render_index_status' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Yeni Hesaplama Fikirleri', 'hge' ),
                'menu'   => __( 'Yeni Fikirler', 'hge' ),
                'slug'   => 'hge-new-ideas',
                'cb'     => [ $this, 'render_new_ideas' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Fikir Arşivi', 'hge' ),
                'menu'   => __( 'Fikir Arşivi', 'hge' ),
                'slug'   => 'hge-suggestion-archive',
                'cb'     => [ $this, 'render_suggestion_archive' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Search Console Ayarları', 'hge' ),
                'menu'   => __( 'GSC Ayarları', 'hge' ),
                'slug'   => 'hge-settings',
                'cb'     => [ $this, 'render_settings' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'AI Entegrasyonu', 'hge' ),
                'menu'   => __( 'AI Entegrasyonu', 'hge' ),
                'slug'   => 'hge-ai-settings',
                'cb'     => [ $this, 'render_ai_settings' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'GitHub Ayarlari', 'hge' ),
                'menu'   => __( 'GitHub Ayarlari', 'hge' ),
                'slug'   => 'hge-github-settings',
                'cb'     => [ $this, 'render_github_settings' ],
            ],
            [
                'parent' => 'hge-dashboard',
                'title'  => __( 'Sistem Durumu', 'hge' ),
                'menu'   => __( 'Sistem Durumu', 'hge' ),
                'slug'   => 'hge-system-status',
                'cb'     => [ $this, 'render_system_status' ],
            ],
        ];

        foreach ( $submenus as $item ) {
            add_submenu_page(
                $item['parent'],
                $item['title'],
                $item['menu'],
                'manage_options',
                $item['slug'],
                $item['cb']
            );
        }
    }

    public function render_dashboard(){
        ( new Dashboard() )->render();
    }

    public function render_opportunities(){
        ( new KeywordOpportunities() )->render();
    }

    public function render_keyword_volume_importer(){
        ( new KeywordVolumeImporter() )->render();
    }

    public function render_page_analysis(){
        ( new PageAnalysis() )->render();
    }

    public function render_index_status(){
        ( new IndexStatus() )->render();
    }

    public function render_new_ideas(){
        ( new NewIdeas() )->render();
    }

    public function render_suggestion_archive(){
        ( new SuggestionArchive() )->render();
    }

    public function render_settings(){
        ( new Settings() )->render();
    }

    public function render_ai_settings(){
        ( new AISettings() )->render();
    }

    public function render_github_settings(){
        ( new GitHubSettings() )->render();
    }

    public function render_system_status(){
        ( new SystemStatus() )->render();
    }

    private function get_menu_icon(){
        // Inline SVG — hafif ve özel görünüm
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>'
        );
    }
}
