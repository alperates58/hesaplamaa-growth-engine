<?php
/**
 * Plugin kaldırıldığında çalışır — tabloları ve seçenekleri temizler
 * Sadece "Delete" işleminde çalışır, deaktivasyonda değil
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Tabloları sil
require_once plugin_dir_path( __FILE__ ) . 'includes/db/class-migrator.php';
HGE\DB\Migrator::drop_tables();

// Options sil
delete_option( 'hge_settings' );
delete_option( 'hge_gsc_tokens' );
delete_option( 'hge_last_sync' );
delete_option( 'hge_db_version' );

// Transient'leri sil
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hge_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hge_%'" );

// Cron'ları sil
wp_clear_scheduled_hook( 'hge_daily_sync' );
wp_clear_scheduled_hook( 'hge_weekly_suggestions' );
