<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class GitHubSettings {

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }

        $updater  = new \HGE\Core\GitHubUpdater();
        $settings = $updater->get_settings();
        $saved    = isset( $_GET['saved'] );
        $update   = sanitize_text_field( wp_unslash( $_GET['update'] ?? '' ) );
        $error    = sanitize_text_field( wp_unslash( $_GET['update_error'] ?? '' ) );
        $last     = get_option( 'hge_last_update', '-' );
        $sha      = substr( (string) get_option( 'hge_last_update_sha', '' ), 0, 7 );

        require HGE_DIR . 'templates/admin/github-settings.php';
    }
}
