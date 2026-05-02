<?php defined( 'ABSPATH' ) || exit; ?>
<div class="hge-wrap">

    <div class="hge-header">
        <div class="hge-header-left">
            <div class="hge-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 18l6-6-6-6"/><path d="M8 6l-6 6 6 6"/><path d="M14.5 4l-5 16"/></svg>
            </div>
            <div>
                <h1 class="hge-page-title"><?php esc_html_e( 'GitHub Ayarlari', 'hge' ); ?></h1>
                <p class="hge-page-subtitle"><?php esc_html_e( 'Eklentiyi GitHub deposundan guncelleyin', 'hge' ); ?></p>
            </div>
        </div>
    </div>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'GitHub ayarlari kaydedildi.', 'hge' ); ?></p></div>
    <?php endif; ?>

    <?php if ( 'success' === $update ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Eklenti GitHub uzerinden basariyla guncellendi.', 'hge' ); ?></p></div>
    <?php elseif ( $error ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( rawurldecode( $error ) ); ?></p></div>
    <?php endif; ?>

    <?php if ( $settings_error ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( rawurldecode( $settings_error ) ); ?></p></div>
    <?php endif; ?>

    <div class="hge-settings-grid">
        <div class="hge-card">
            <div class="hge-card-header"><h3><?php esc_html_e( 'GitHub Baglantisi', 'hge' ); ?></h3></div>
            <div class="hge-card-body">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hge-form-fields">
                    <?php wp_nonce_field( 'hge_save_github_settings' ); ?>
                    <input type="hidden" name="action" value="hge_save_github_settings" />

                    <div class="hge-field">
                        <label for="hge_github_repo"><?php esc_html_e( 'Repository', 'hge' ); ?></label>
                        <input
                            type="text"
                            id="hge_github_repo"
                            name="repo"
                            value="<?php echo esc_attr( $settings['repo'] ); ?>"
                            placeholder="alperates58/hesaplamaa-growth-engine"
                            class="hge-input"
                        />
                        <small class="hge-field-hint"><?php esc_html_e( 'GitHub kullanici adi ve repository adini birlikte girin.', 'hge' ); ?></small>
                    </div>

                    <div class="hge-field">
                        <label for="hge_github_branch"><?php esc_html_e( 'Branch', 'hge' ); ?></label>
                        <input
                            type="text"
                            id="hge_github_branch"
                            name="branch"
                            value="<?php echo esc_attr( $settings['branch'] ); ?>"
                            placeholder="main"
                            class="hge-input hge-input-small"
                        />
                    </div>

                    <div class="hge-field">
                        <label for="hge_github_token"><?php esc_html_e( 'Token', 'hge' ); ?></label>
                        <input
                            type="password"
                            id="hge_github_token"
                            name="token"
                            value=""
                            placeholder="<?php echo ! empty( $settings['token'] ) ? esc_attr__( 'Token kayitli; degistirmek icin yeni token girin', 'hge' ) : 'ghp_xxxx'; ?>"
                            class="hge-input"
                            autocomplete="new-password"
                        />
                        <small class="hge-field-hint"><?php esc_html_e( 'Public repo icin bos birakabilirsiniz.', 'hge' ); ?></small>
                    </div>

                    <div class="hge-form-actions">
                        <button type="submit" class="hge-btn hge-btn-primary"><?php esc_html_e( 'Kaydet', 'hge' ); ?></button>
                        <button type="button" class="hge-btn hge-btn-secondary" id="hge-check-github-version">
                            <?php esc_html_e( 'Son Versiyonu Kontrol Et', 'hge' ); ?>
                        </button>
                        <span id="hge-github-version-result" class="hge-inline-result"></span>
                    </div>
                </form>
            </div>
        </div>

        <div class="hge-card hge-update-card">
            <div class="hge-card-header"><h3><?php esc_html_e( 'Guncelleme', 'hge' ); ?></h3></div>
            <div class="hge-card-body">
                <table class="hge-status-table">
                    <tr>
                        <td><?php esc_html_e( 'Son guncelleme', 'hge' ); ?></td>
                        <td><strong><?php echo esc_html( $last ); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Son commit', 'hge' ); ?></td>
                        <td><?php echo $sha ? esc_html( $sha ) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Aktif surum', 'hge' ); ?></td>
                        <td><?php echo esc_html( HGE_VERSION ); ?></td>
                    </tr>
                </table>

                <p class="hge-text-muted"><?php esc_html_e( 'GitHub uzerindeki en guncel kodu cekip eklentiyi bu panelden yenileyebilirsiniz.', 'hge' ); ?></p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'hge_update_from_github' ); ?>
                    <input type="hidden" name="action" value="hge_update_from_github" />
                    <button type="submit" class="hge-btn hge-btn-primary" onclick="return confirm('<?php echo esc_js( __( 'GitHub uzerinden guncelleme yapmak istediginize emin misiniz?', 'hge' ) ); ?>')">
                        <?php esc_html_e( "GitHub'dan Guncelle", 'hge' ); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
