<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap hge-ai-settings-page">
    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'AI Entegrasyonu', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php esc_html_e( 'Yeni fırsatlar için token kontrollü OpenAI analizleri', 'hge' ); ?></p>
            </div>
        </div>
    </div>

    <div class="hge-settings-grid">
        <div class="hge-card">
            <div class="hge-card-header">
                <h3><?php esc_html_e( 'OpenAI Ayarları', 'hge' ); ?></h3>
                <?php if ( ! empty( $settings['enabled'] ) && ! empty( $settings['api_key'] ) ): ?>
                    <span class="hge-badge hge-badge-success"><?php esc_html_e( 'Aktif', 'hge' ); ?></span>
                <?php else: ?>
                    <span class="hge-badge hge-badge-warning"><?php esc_html_e( 'Pasif', 'hge' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="hge-card-body">
                <form method="post" action="options.php" class="hge-form-fields">
                    <?php settings_fields( 'hge_ai_settings_group' ); ?>

                    <label class="hge-toggle-label">
                        <input type="checkbox" name="hge_ai_settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
                        <?php esc_html_e( 'AI analizlerini aktif et', 'hge' ); ?>
                    </label>

                    <div class="hge-field">
                        <label for="hge_ai_api_key"><?php esc_html_e( 'OpenAI API Key', 'hge' ); ?></label>
                        <input
                            id="hge_ai_api_key"
                            name="hge_ai_settings[api_key]"
                            type="password"
                            class="hge-input"
                            value="<?php echo esc_attr( ! empty( $settings['api_key'] ) ? '••••••••••••••••' : '' ); ?>"
                            placeholder="sk-..."
                            autocomplete="off"
                        />
                        <small class="hge-field-hint"><?php esc_html_e( 'Key sadece sunucuda saklanır; tarayıcıya gönderilmez.', 'hge' ); ?></small>
                    </div>

                    <div class="hge-field">
                        <label for="hge_ai_model"><?php esc_html_e( 'Model', 'hge' ); ?></label>
                        <select id="hge_ai_model" name="hge_ai_settings[model]" class="hge-select">
                            <option value="gpt-5-mini" <?php selected( $settings['model'], 'gpt-5-mini' ); ?>>gpt-5-mini</option>
                            <option value="o4-mini" <?php selected( $settings['model'], 'o4-mini' ); ?>>o4-mini</option>
                        </select>
                        <small class="hge-field-hint"><?php esc_html_e( 'Varsayılan öneri: gpt-5-mini. o4-mini fallback olarak tutulabilir.', 'hge' ); ?></small>
                    </div>

                    <div class="hge-field">
                        <label for="hge_ai_daily_limit"><?php esc_html_e( 'Günlük analiz limiti', 'hge' ); ?></label>
                        <input
                            id="hge_ai_daily_limit"
                            name="hge_ai_settings[daily_limit]"
                            type="number"
                            min="5"
                            max="500"
                            class="hge-input hge-input-small"
                            value="<?php echo esc_attr( (int) $settings['daily_limit'] ); ?>"
                        />
                        <small class="hge-field-hint"><?php esc_html_e( 'Token kontrolü için her gün kaç yeni AI analizi yapılabileceğini sınırlar.', 'hge' ); ?></small>
                    </div>

                    <label class="hge-toggle-label">
                        <input type="checkbox" name="hge_ai_settings[seed_enabled]" value="1" <?php checked( ! empty( $settings['seed_enabled'] ) ); ?>>
                        <?php esc_html_e( 'Yeni fikir taramada AI seed konu üretimini kullan', 'hge' ); ?>
                    </label>

                    <label class="hge-toggle-label">
                        <input type="checkbox" name="hge_ai_settings[metrics_enabled]" value="1" <?php checked( ! empty( $settings['metrics_enabled'] ) ); ?>>
                        <?php esc_html_e( 'Hacim ve rekabet için AI tahmini kullan', 'hge' ); ?>
                    </label>

                    <div class="hge-field">
                        <label for="hge_ai_seed_limit"><?php esc_html_e( 'AI seed konu limiti', 'hge' ); ?></label>
                        <input
                            id="hge_ai_seed_limit"
                            name="hge_ai_settings[seed_limit]"
                            type="number"
                            min="10"
                            max="60"
                            class="hge-input hge-input-small"
                            value="<?php echo esc_attr( (int) ( $settings['seed_limit'] ?? 25 ) ); ?>"
                        />
                        <small class="hge-field-hint"><?php esc_html_e( 'AI sadece ana konu listesi üretir; gerçek öneriler yine Google Suggest ile doğrulanır.', 'hge' ); ?></small>
                    </div>

                    <div class="hge-form-actions">
                        <?php submit_button( __( 'AI Ayarlarını Kaydet', 'hge' ), 'hge-btn hge-btn-primary', 'submit', false ); ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="hge-card">
            <div class="hge-card-header"><h3><?php esc_html_e( 'Kullanım ve Maliyet Kontrolü', 'hge' ); ?></h3></div>
            <div class="hge-card-body">
                <table class="hge-status-table">
                    <tr>
                        <td><?php esc_html_e( 'Bugünkü kullanım', 'hge' ); ?></td>
                        <td><strong><?php echo esc_html( number_format_i18n( $usage ) ); ?> / <?php echo esc_html( number_format_i18n( (int) $settings['daily_limit'] ) ); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Çalışma şekli', 'hge' ); ?></td>
                        <td><?php esc_html_e( 'Sadece butona basınca analiz', 'hge' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Cache', 'hge' ); ?></td>
                        <td><?php esc_html_e( 'Keyword bazlı kalıcı kayıt', 'hge' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Token stratejisi', 'hge' ); ?></td>
                        <td><?php esc_html_e( 'Kısa JSON girdi ve kısa JSON çıktı', 'hge' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Fikir tarama AI', 'hge' ); ?></td>
                        <td><?php echo ! empty( $settings['seed_enabled'] ) ? esc_html__( 'Aktif: AI seed + Google Suggest', 'hge' ) : esc_html__( 'Pasif: kod içi seed listesi', 'hge' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Hacim / rekabet', 'hge' ); ?></td>
                        <td><?php echo ! empty( $settings['metrics_enabled'] ) ? esc_html__( 'AI tahmini aktif', 'hge' ) : esc_html__( 'Lokal tahmin aktif', 'hge' ); ?></td>
                    </tr>
                </table>
                <p class="hge-text-muted">
                    <?php esc_html_e( 'Liste açıldığında AI çalışmaz. Kullanıcı bir fırsatta AI analizi istediğinde çağrı yapılır; aynı keyword tekrar istenirse cache döner.', 'hge' ); ?>
                </p>
            </div>
        </div>
    </div>
</div>
