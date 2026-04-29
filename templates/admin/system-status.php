<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Sistem Durumu', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php esc_html_e( 'Plugin sağlığı ve entegrasyon durumu', 'hge' ); ?></p>
            </div>
        </div>
        <div class="hge-header-right">
            <button class="hge-btn hge-btn-secondary" id="hge-clear-cache">
                <?php esc_html_e( 'Cache Temizle', 'hge' ); ?>
            </button>
        </div>
    </div>

    <div class="hge-status-grid">

        <!-- Sistem Bilgileri -->
        <div class="hge-card">
            <div class="hge-card-header"><h3><?php esc_html_e( 'Sistem', 'hge' ); ?></h3></div>
            <div class="hge-card-body">
                <table class="hge-status-table">
                    <tr>
                        <td><?php esc_html_e( 'PHP Versiyonu', 'hge' ); ?></td>
                        <td>
                            <span class="hge-status-dot <?php echo $status['php_ok'] ? 'ok' : 'error'; ?>"></span>
                            <?php echo esc_html( $status['php_version'] ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'WordPress Versiyonu', 'hge' ); ?></td>
                        <td>
                            <span class="hge-status-dot <?php echo $status['wp_ok'] ? 'ok' : 'error'; ?>"></span>
                            <?php echo esc_html( $status['wp_version'] ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Plugin Versiyonu', 'hge' ); ?></td>
                        <td><?php echo esc_html( $status['plugin_version'] ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'DB Versiyonu', 'hge' ); ?></td>
                        <td><?php echo esc_html( $status['db_version'] ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Memory Limiti', 'hge' ); ?></td>
                        <td><?php echo esc_html( $status['memory_limit'] ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Mevcut Memory', 'hge' ); ?></td>
                        <td><?php echo esc_html( $status['memory_usage'] ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'cURL', 'hge' ); ?></td>
                        <td><span class="hge-status-dot <?php echo $status['curl_enabled'] ? 'ok' : 'error'; ?>"></span><?php echo $status['curl_enabled'] ? 'Aktif' : 'Pasif'; ?></td>
                    </tr>
                    <tr>
                        <td>SSL</td>
                        <td><span class="hge-status-dot <?php echo $status['ssl_enabled'] ? 'ok' : 'warn'; ?>"></span><?php echo $status['ssl_enabled'] ? 'Aktif' : 'Pasif'; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Entegrasyon & Senkronizasyon -->
        <div class="hge-card">
            <div class="hge-card-header"><h3><?php esc_html_e( 'Entegrasyon & Senkronizasyon', 'hge' ); ?></h3></div>
            <div class="hge-card-body">
                <table class="hge-status-table">
                    <tr>
                        <td><?php esc_html_e( 'GSC Bağlantısı', 'hge' ); ?></td>
                        <td>
                            <span class="hge-status-dot <?php echo $status['gsc_connected'] ? 'ok' : 'error'; ?>"></span>
                            <?php echo $status['gsc_connected'] ? esc_html__( 'Bağlı', 'hge' ) : esc_html__( 'Bağlı Değil', 'hge' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Son Senkronizasyon', 'hge' ); ?></td>
                        <td><?php echo esc_html( $status['last_sync'] ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Sonraki Otomatik Sync', 'hge' ); ?></td>
                        <td><?php echo esc_html( $status['next_cron'] ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Cache Sayısı', 'hge' ); ?></td>
                        <td><?php echo esc_html( $status['cache_count'] ); ?> transient</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Veritabanı İstatistikleri -->
        <div class="hge-card">
            <div class="hge-card-header"><h3><?php esc_html_e( 'Veritabanı', 'hge' ); ?></h3></div>
            <div class="hge-card-body">
                <table class="hge-status-table">
                    <?php foreach ( $status['table_counts'] as $table => $count ): ?>
                    <tr>
                        <td><code>wp_hge_<?php echo esc_html( $table ); ?></code></td>
                        <td><strong><?php echo esc_html( number_format( $count ) ); ?></strong> <?php esc_html_e( 'kayıt', 'hge' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

    </div>
</div>
