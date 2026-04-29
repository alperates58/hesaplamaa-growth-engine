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

        if ( $api_key === '••••••••••••••••' ) {
            $api_key = $existing['api_key'] ?? '';
        }

        $model = sanitize_text_field( $input['model'] ?? 'gpt-5-mini' );
        if ( ! in_array( $model, [ 'gpt-5-mini', 'o4-mini' ], true ) ) {
            $model = 'gpt-5-mini';
        }

        return [
            'enabled'     => ! empty( $input['enabled'] ),
            'api_key'     => $api_key,
            'model'       => $model,
            'daily_limit' => max( 5, min( 500, (int) ( $input['daily_limit'] ?? 50 ) ) ),
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
