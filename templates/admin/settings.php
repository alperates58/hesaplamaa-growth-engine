<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap">

    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 1 4.93 19.07 10 10 0 0 1 19.07 4.93z"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'Ayarlar', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php esc_html_e( 'Search Console bağlantısı ve genel yapılandırma', 'hge' ); ?></p>
            </div>
        </div>
    </div>

    <!-- GSC BAĞLANTI KARTI -->
    <div class="hge-card hge-settings-hero">
        <div class="hge-card-header">
            <h3>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <?php esc_html_e( 'Google Search Console', 'hge' ); ?>
            </h3>
            <?php if ( $gsc_connected ): ?>
                <span class="hge-badge hge-badge-success"><?php esc_html_e( 'Bağlı ✓', 'hge' ); ?></span>
            <?php else: ?>
                <span class="hge-badge hge-badge-error"><?php esc_html_e( 'Bağlı Değil', 'hge' ); ?></span>
            <?php endif; ?>
        </div>
        <div class="hge-card-body">

            <?php if ( $gsc_connected ): ?>
            <div class="hge-connected-state">
                <div class="hge-connected-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div>
                    <p><strong><?php esc_html_e( 'Search Console başarıyla bağlandı.', 'hge' ); ?></strong></p>
                    <p class="hge-text-muted"><?php esc_html_e( 'Veri senkronizasyonu otomatik çalışmaktadır.', 'hge' ); ?></p>
                </div>
                <button class="hge-btn hge-btn-danger" id="hge-disconnect-gsc">
                    <?php esc_html_e( 'Bağlantıyı Kes', 'hge' ); ?>
                </button>
            </div>
            <?php else: ?>
            <div class="hge-oauth-steps">
                <div class="hge-step">
                    <div class="hge-step-num">1</div>
                    <div class="hge-step-content">
                        <strong><?php esc_html_e( 'Google Cloud Console\'da OAuth 2.0 kimlik bilgisi oluşturun', 'hge' ); ?></strong>
                        <p class="hge-text-muted">
                            <?php esc_html_e( 'console.cloud.google.com → APIs & Services → Credentials → Create OAuth 2.0 Client ID', 'hge' ); ?>
                        </p>
                        <p class="hge-text-muted">
                            <?php esc_html_e( 'Authorized redirect URI:', 'hge' ); ?>
                            <code class="hge-code"><?php echo esc_url( admin_url( '?hge_gsc_callback=1' ) ); ?></code>
                        </p>
                    </div>
                </div>
                <div class="hge-step">
                    <div class="hge-step-num">2</div>
                    <div class="hge-step-content">
                        <strong><?php esc_html_e( 'Client ID ve Secret\'ı buraya girin', 'hge' ); ?></strong>
                    </div>
                </div>
                <div class="hge-step">
                    <div class="hge-step-num">3</div>
                    <div class="hge-step-content">
                        <strong><?php esc_html_e( 'Google\'a bağlanın', 'hge' ); ?></strong>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- AYARLAR FORMU -->
    <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" class="hge-settings-form">
        <?php settings_fields( 'hge_settings_group' ); ?>

        <div class="hge-settings-grid">

            <!-- GSC Credentials -->
            <div class="hge-card">
                <div class="hge-card-header"><h3><?php esc_html_e( 'OAuth Kimlik Bilgileri', 'hge' ); ?></h3></div>
                <div class="hge-card-body hge-form-fields">

                    <div class="hge-field">
                        <label for="hge_client_id"><?php esc_html_e( 'Google Client ID', 'hge' ); ?></label>
                        <input
                            type="text"
                            id="hge_client_id"
                            name="hge_settings[gsc_client_id]"
                            value="<?php echo esc_attr( $settings['gsc_client_id'] ?? '' ); ?>"
                            placeholder="xxx.apps.googleusercontent.com"
                            class="hge-input"
                        />
                    </div>

                    <div class="hge-field">
                        <label for="hge_client_secret"><?php esc_html_e( 'Google Client Secret', 'hge' ); ?></label>
                        <input
                            type="password"
                            id="hge_client_secret"
                            name="hge_settings[gsc_client_secret]"
                            value="<?php echo esc_attr( $settings['gsc_client_secret'] ?? '' ); ?>"
                            placeholder="GOCSPX-..."
                            class="hge-input"
                        />
                    </div>

                    <div class="hge-field">
                        <label for="hge_site_url"><?php esc_html_e( 'GSC Site URL', 'hge' ); ?></label>
                        <input
                            type="text"
                            id="hge_site_url"
                            name="hge_settings[gsc_site_url]"
                            value="<?php echo esc_attr( $settings['gsc_site_url'] ?? get_site_url() ); ?>"
                            placeholder="https://hesaplamaa.com/"
                            class="hge-input"
                        />
                        <small class="hge-field-hint"><?php esc_html_e( 'GSC\'de doğrulanmış site URL\'i (sc-domain: veya https:// formatında)', 'hge' ); ?></small>
                    </div>

                    <?php if ( ! $gsc_connected && ! empty( $settings['gsc_client_id'] ) ): ?>
                    <div class="hge-field">
                        <a href="<?php echo esc_url( $oauth_url ); ?>" class="hge-btn hge-btn-google">
                            <svg width="24" height="24" viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                            <?php esc_html_e( 'Google ile Bağlan', 'hge' ); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Genel Ayarlar -->
            <div class="hge-card">
                <div class="hge-card-header"><h3><?php esc_html_e( 'Genel Ayarlar', 'hge' ); ?></h3></div>
                <div class="hge-card-body hge-form-fields">

                    <div class="hge-field">
                        <label for="hge_data_range"><?php esc_html_e( 'Veri Aralığı (Gün)', 'hge' ); ?></label>
                        <select id="hge_data_range" name="hge_settings[data_range_days]" class="hge-select">
                            <?php foreach ( [ 7 => '7 gün', 14 => '14 gün', 30 => '30 gün', 60 => '60 gün', 90 => '90 gün' ] as $val => $label ): ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['data_range_days'] ?? 30, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hge-field">
                        <label for="hge_cache_ttl"><?php esc_html_e( 'Cache Süresi', 'hge' ); ?></label>
                        <select id="hge_cache_ttl" name="hge_settings[cache_ttl]" class="hge-select">
                            <?php foreach ( [ 300 => '5 dakika', 900 => '15 dakika', 1800 => '30 dakika', 3600 => '1 saat', 21600 => '6 saat' ] as $val => $label ): ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['cache_ttl'] ?? 3600, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>

        </div><!-- .hge-settings-grid -->

        <div class="hge-card">
            <div class="hge-card-header">
                <h3><?php esc_html_e( 'Google Ads API - Keyword Planner', 'hge' ); ?></h3>
                <?php if ( ! empty( $settings['google_ads_enabled'] ) ): ?>
                    <span class="hge-badge hge-badge-success"><?php esc_html_e( 'Aktif', 'hge' ); ?></span>
                <?php else: ?>
                    <span class="hge-badge hge-badge-warning"><?php esc_html_e( 'Pasif', 'hge' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="hge-card-body hge-form-fields">
                <label class="hge-toggle-label">
                    <input type="checkbox" name="hge_settings[google_ads_enabled]" value="1" <?php checked( ! empty( $settings['google_ads_enabled'] ) ); ?>>
                    <?php esc_html_e( 'Google Ads API ile gerçek hacim ve rekabet verisi çek', 'hge' ); ?>
                </label>

                <div class="hge-settings-grid">
                    <div class="hge-field">
                        <label for="hge_google_ads_developer_token"><?php esc_html_e( 'Developer Token', 'hge' ); ?></label>
                        <input id="hge_google_ads_developer_token" name="hge_settings[google_ads_developer_token]" class="hge-input" type="password" value="<?php echo esc_attr( $settings['google_ads_developer_token'] ?? '' ); ?>" autocomplete="off">
                    </div>
                    <div class="hge-field">
                        <label for="hge_google_ads_customer_id"><?php esc_html_e( 'Customer ID', 'hge' ); ?></label>
                        <input id="hge_google_ads_customer_id" name="hge_settings[google_ads_customer_id]" class="hge-input" type="text" value="<?php echo esc_attr( $settings['google_ads_customer_id'] ?? '' ); ?>" placeholder="1234567890">
                    </div>
                    <div class="hge-field">
                        <label for="hge_google_ads_login_customer_id"><?php esc_html_e( 'Login Customer ID', 'hge' ); ?></label>
                        <input id="hge_google_ads_login_customer_id" name="hge_settings[google_ads_login_customer_id]" class="hge-input" type="text" value="<?php echo esc_attr( $settings['google_ads_login_customer_id'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'MCC varsa doldurun', 'hge' ); ?>">
                    </div>
                    <div class="hge-field">
                        <label for="hge_google_ads_refresh_token"><?php esc_html_e( 'Google Ads Refresh Token', 'hge' ); ?></label>
                        <input id="hge_google_ads_refresh_token" name="hge_settings[google_ads_refresh_token]" class="hge-input" type="password" value="<?php echo esc_attr( $settings['google_ads_refresh_token'] ?? '' ); ?>" autocomplete="off">
                        <?php if ( ! empty( $settings['gsc_client_id'] ) && ! empty( $settings['gsc_client_secret'] ) ): ?>
                            <a class="hge-btn hge-btn-google hge-ads-connect-btn" href="<?php echo esc_url( $ads_oauth_url ); ?>">
                                <?php esc_html_e( 'Google Ads ile Bağlan', 'hge' ); ?>
                            </a>
                        <?php else: ?>
                            <small class="hge-field-hint"><?php esc_html_e( 'Önce Google Client ID ve Client Secret alanlarını kaydedin.', 'hge' ); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="hge-field">
                        <label for="hge_google_ads_language"><?php esc_html_e( 'Dil', 'hge' ); ?></label>
                        <input id="hge_google_ads_language" name="hge_settings[google_ads_language]" class="hge-input" type="text" value="<?php echo esc_attr( $settings['google_ads_language'] ?? 'languageConstants/1037' ); ?>">
                        <small class="hge-field-hint"><?php esc_html_e( 'Varsayılan Türkçe: languageConstants/1037', 'hge' ); ?></small>
                    </div>
                    <div class="hge-field">
                        <label for="hge_google_ads_geo_target"><?php esc_html_e( 'Lokasyon', 'hge' ); ?></label>
                        <input id="hge_google_ads_geo_target" name="hge_settings[google_ads_geo_target]" class="hge-input" type="text" value="<?php echo esc_attr( $settings['google_ads_geo_target'] ?? 'geoTargetConstants/2792' ); ?>">
                        <small class="hge-field-hint"><?php esc_html_e( 'Varsayılan Türkiye: geoTargetConstants/2792', 'hge' ); ?></small>
                    </div>
                </div>

                <p class="hge-text-muted">
                    <?php esc_html_e( 'Ads API verisi varsa AI/lokal tahminin yerine geçer. Refresh token adwords scope ile oluşturulmalıdır.', 'hge' ); ?>
                </p>

                <div class="hge-ads-test-row">
                    <button class="hge-btn hge-btn-secondary" id="hge-test-google-ads" type="button">
                        <?php esc_html_e( 'Google Ads Bağlantısını Test Et', 'hge' ); ?>
                    </button>
                    <span id="hge-google-ads-test-result" class="hge-inline-result <?php echo ! empty( $ads_last_test['ok'] ) ? 'success' : ( ! empty( $ads_last_test ) ? 'error' : '' ); ?>">
                        <?php
                        if ( ! empty( $ads_last_test['message'] ) ) {
                            echo esc_html( $ads_last_test['message'] . ' ' . ( $ads_last_test['time'] ?? '' ) );
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="hge-form-actions">
            <?php submit_button( __( 'Ayarları Kaydet', 'hge' ), 'hge-btn hge-btn-primary', 'submit', false ); ?>
        </div>

    </form>

</div>
