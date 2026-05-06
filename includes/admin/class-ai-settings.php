<?php
namespace HGE\Admin;

defined( 'ABSPATH' ) || exit;

class AISettings {

    const OPTION_KEY = 'hge_ai_settings';

    public function register(){
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings(){
        register_setting(
            'hge_ai_settings_group',
            self::OPTION_KEY,
            [ $this, 'sanitize_settings' ]
        );
    }

    public function sanitize_settings( array $input ){
        $existing = get_option( self::OPTION_KEY, [] );
        $api_key  = sanitize_text_field( $input['api_key'] ?? '' );
        $provider = sanitize_text_field( $input['provider'] ?? 'openai' );
        $provider = in_array( $provider, [ 'openai', 'deepseek' ], true ) ? $provider : 'openai';

        if ( $api_key === '••••••••••••••••' || $api_key === 'â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢' ) {
            $api_key = $existing['api_key'] ?? '';
        }

        $allowed_models = [
            'openai'   => [ 'gpt-5-mini', 'o4-mini' ],
            'deepseek' => [ 'deepseek-v4-flash', 'deepseek-v4-pro' ],
        ];
        $default_models = [
            'openai'   => 'gpt-5-mini',
            'deepseek' => 'deepseek-v4-flash',
        ];

        $model = sanitize_text_field( $input['model'] ?? $default_models[ $provider ] );
        if ( ! in_array( $model, $allowed_models[ $provider ], true ) ) {
            $model = $default_models[ $provider ];
        }

        return [
            'enabled'          => ! empty( $input['enabled'] ),
            'provider'         => $provider,
            'api_key'          => $api_key,
            'api_base_url'     => esc_url_raw( $input['api_base_url'] ?? '' ),
            'model'            => $model,
            'daily_limit'      => max( 5, min( 500, (int) ( $input['daily_limit'] ?? 50 ) ) ),
            'seed_enabled'     => ! empty( $input['seed_enabled'] ),
            'seed_limit'       => max( 10, min( 60, (int) ( $input['seed_limit'] ?? 25 ) ) ),
            'metrics_enabled'  => ! empty( $input['metrics_enabled'] ),
        ];
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkiniz yok.', 'hge' ) );
        }

        settings_errors( 'hge' );

        $client   = new \HGE\API\OpenAIClient();
        $settings = $client->get_settings();
        $usage    = $client->get_daily_usage();

        require HGE_DIR . 'templates/admin/ai-settings.php';
    }
}
