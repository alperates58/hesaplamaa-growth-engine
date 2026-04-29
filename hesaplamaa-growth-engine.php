<?php
/**
 * Plugin Name:       Hesaplamaa Growth Engine
 * Plugin URI:        https://github.com/alperates58/hesaplamaa-growth-engine
 * Description:       hesaplamaa.com için profesyonel SEO büyüme paneli. GSC entegrasyonu, keyword fırsatları ve içerik analizi.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Hesaplamaa
 * Author URI:        https://hesaplamaa.com
 * License:           GPL v2 or later
 * Text Domain:       hge
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin sabitleri
$hge_last_update_sha     = substr( (string) get_option( 'hge_last_update_sha', '' ), 0, 7 );
$hge_last_update_version = (string) get_option( 'hge_last_update_version', '0' );

define( 'HGE_VERSION',    '1.0.0-' . $hge_last_update_version . ( $hge_last_update_sha ? '-' . $hge_last_update_sha : '' ) );
define( 'HGE_FILE',       __FILE__ );
define( 'HGE_DIR',        plugin_dir_path( __FILE__ ) );
define( 'HGE_URL',        plugin_dir_url( __FILE__ ) );
define( 'HGE_ASSETS_URL', HGE_URL . 'assets/' );
define( 'HGE_BASENAME',   plugin_basename( __FILE__ ) );

/**
 * Tüm sınıf dosyalarını doğrudan yükle
 * Autoloader yerine explicit include — hosting uyumluluğu için
 */
function hge_load_files() {
    $files = array(
        // DB (önce — diğerleri kullanıyor)
        'includes/db/class-migrator.php',
        'includes/db/class-repository.php',
        // API
        'includes/api/class-gsc-client.php',
        'includes/api/class-suggest-client.php',
        // Admin
        'includes/admin/class-menu.php',
        'includes/admin/class-dashboard.php',
        'includes/admin/class-keyword-opportunities.php',
        'includes/admin/class-page-analysis.php',
        'includes/admin/class-new-ideas.php',
        'includes/admin/class-settings.php',
        'includes/admin/class-github-settings.php',
        'includes/admin/class-system-status.php',
        // Cron
        'includes/cron/class-scheduler.php',
        'includes/Core/class-github-updater.php',
        // Core — en son
        'includes/Core/class-plugin.php',
    );

    foreach ( $files as $file ) {
        $path = HGE_DIR . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}
hge_load_files();

/**
 * Plugin'i başlat
 */
function hge_init() {
    load_plugin_textdomain( 'hge', false, dirname( HGE_BASENAME ) . '/languages' );
    HGE\Core\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'hge_init' );

/**
 * Aktivasyon hook
 */
register_activation_hook( HGE_FILE, function () {
    HGE\DB\Migrator::run();
    if ( ! get_option( 'hge_settings' ) ) {
        add_option( 'hge_settings', array(
            'gsc_connected'   => false,
            'gsc_site_url'    => get_site_url(),
            'data_range_days' => 30,
            'cache_ttl'       => 3600,
        ) );
    }
    if ( ! wp_next_scheduled( 'hge_daily_sync' ) ) {
        wp_schedule_event( time(), 'daily', 'hge_daily_sync' );
    }
    flush_rewrite_rules();
} );

/**
 * Deaktivasyon hook
 */
register_deactivation_hook( HGE_FILE, function () {
    wp_clear_scheduled_hook( 'hge_daily_sync' );
    wp_clear_scheduled_hook( 'hge_weekly_suggestions' );
    flush_rewrite_rules();
} );
