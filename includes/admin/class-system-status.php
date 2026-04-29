<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class SystemStatus {

    public function get_status(){
        global $wpdb;

        $gsc_client = new \HGE\API\GSCClient();

        return [
            'php_version'     => PHP_VERSION,
            'php_ok'          => version_compare( PHP_VERSION, '8.0', '>=' ),
            'wp_version'      => get_bloginfo( 'version' ),
            'wp_ok'           => version_compare( get_bloginfo( 'version' ), '6.0', '>=' ),
            'db_version'      => get_option( \HGE\DB\Migrator::DB_VERSION_OPTION, 'Kurulmadı' ),
            'gsc_connected'   => $gsc_client->is_connected(),
            'last_sync'       => get_option( 'hge_last_sync', 'Henüz senkronize edilmedi' ),
            'next_cron'       => wp_next_scheduled( 'hge_daily_sync' )
                                    ? gmdate( 'd.m.Y H:i', wp_next_scheduled( 'hge_daily_sync' ) )
                                    : 'Planlanmadı',
            'cache_count'     => $this->count_transients(),
            'table_counts'    => [
                'keywords'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hge_keywords" ),
                'daily_stats' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hge_daily_stats" ),
                'page_stats'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hge_page_stats" ),
                'suggestions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hge_suggestions" ),
            ],
            'memory_limit'    => ini_get( 'memory_limit' ),
            'memory_usage'    => size_format( memory_get_usage( true ) ),
            'curl_enabled'    => function_exists( 'curl_init' ),
            'ssl_enabled'     => is_ssl(),
            'plugin_version'  => HGE_VERSION,
        ];
    }

    private function count_transients(){
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_hge_%'"
        );
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }
        $status = $this->get_status();
        require HGE_DIR . 'templates/admin/system-status.php';
    }
}
