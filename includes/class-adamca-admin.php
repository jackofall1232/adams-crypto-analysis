<?php
/**
 * Admin settings page.
 *
 * @package AdamsCryptoAnalysis
 */

defined( 'ABSPATH' ) || exit;

class ADAMCA_Admin {

    /**
     * Initialize admin hooks.
     */
    public static function register_hooks() {
        add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_adamca_test_coingecko', array( __CLASS__, 'ajax_test_coingecko' ) );
        add_action( 'wp_ajax_adamca_test_ai_provider', array( __CLASS__, 'ajax_test_ai_provider' ) );
        add_action( 'wp_ajax_adamca_clear_cache', array( __CLASS__, 'ajax_clear_cache' ) );
    }

    /**
     * Enqueue admin CSS and JS only on the plugin settings page.
     *
     * @param string $hook_suffix The admin page hook suffix.
     */
    public static function enqueue_admin_assets( $hook_suffix ) {
        if ( 'settings_page_adams-crypto-analysis' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'adamca-admin',
            ADAMS_CRYPTO_ANALYSIS_URL . 'assets/css/crypto-analysis-admin.css',
            array(),
            ADAMS_CRYPTO_ANALYSIS_VERSION
        );

        wp_enqueue_script(
            'adamca-admin',
            ADAMS_CRYPTO_ANALYSIS_URL . 'assets/js/crypto-analysis-admin.js',
            array(),
            ADAMS_CRYPTO_ANALYSIS_VERSION,
            true
        );

        wp_localize_script( 'adamca-admin', 'adamcaAdmin', array(
            'ajaxUrl'    => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
            'nonce'      => wp_create_nonce( 'adamca_admin_nonce' ),
            'savedModel' => get_option( 'adamca_ai_model', '' ),
            'i18n'       => array(
                'working'       => __( 'Working...', 'adams-crypto-analysis' ),
                'success'       => __( 'Success!', 'adams-crypto-analysis' ),
                'errorOccurred' => __( 'Error occurred.', 'adams-crypto-analysis' ),
                'requestFailed' => __( 'Request failed: ', 'adams-crypto-analysis' ),
            ),
        ) );
    }

    /**
     * Add the settings page under the Settings menu.
     */
    public static function add_settings_page() {
        add_options_page(
            __( 'Adams Crypto Analysis', 'adams-crypto-analysis' ),
            __( 'Adams Crypto Analysis', 'adams-crypto-analysis' ),
            'manage_options',
            'adams-crypto-analysis',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Register all plugin settings.
     */
    public static function register_settings() {
        $settings_group = 'adamca_settings_group';

        register_setting( $settings_group, 'adamca_coingecko_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        register_setting( $settings_group, 'adamca_ai_provider', array(
            'type'              => 'string',
            'sanitize_callback' => array( __CLASS__, 'sanitize_provider' ),
            'default'           => 'openai',
        ) );

        register_setting( $settings_group, 'adamca_ai_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        register_setting( $settings_group, 'adamca_ai_model', array(
            'type'              => 'string',
            'sanitize_callback' => array( __CLASS__, 'sanitize_model' ),
            'default'           => '',
        ) );

        register_setting( $settings_group, 'adamca_top10_coins', array(
            'type'              => 'string',
            'sanitize_callback' => array( __CLASS__, 'sanitize_coins_list' ),
            'default'           => '',
        ) );

        register_setting( $settings_group, 'adamca_cache_expiry', array(
            'type'              => 'integer',
            'sanitize_callback' => array( __CLASS__, 'sanitize_cache_expiry' ),
            'default'           => 43200,
        ) );

        // CoinGecko section.
        add_settings_section(
            'adamca_coingecko_section',
            __( 'CoinGecko Settings', 'adams-crypto-analysis' ),
            '__return_null',
            'adams-crypto-analysis'
        );

        add_settings_field(
            'adamca_coingecko_api_key',
            __( 'CoinGecko API Key (Pro)', 'adams-crypto-analysis' ),
            array( __CLASS__, 'render_password_field' ),
            'adams-crypto-analysis',
            'adamca_coingecko_section',
            array( 'option_name' => 'adamca_coingecko_api_key', 'description' => __( 'Leave blank for free tier.', 'adams-crypto-analysis' ) )
        );

        // AI section.
        add_settings_section(
            'adamca_ai_section',
            __( 'AI Provider Settings', 'adams-crypto-analysis' ),
            '__return_null',
            'adams-crypto-analysis'
        );

        add_settings_field(
            'adamca_ai_provider',
            __( 'AI Provider', 'adams-crypto-analysis' ),
            array( __CLASS__, 'render_provider_field' ),
            'adams-crypto-analysis',
            'adamca_ai_section'
        );

        add_settings_field(
            'adamca_ai_api_key',
            __( 'AI API Key', 'adams-crypto-analysis' ),
            array( __CLASS__, 'render_password_field' ),
            'adams-crypto-analysis',
            'adamca_ai_section',
            array( 'option_name' => 'adamca_ai_api_key', 'description' => __( 'API key for the selected provider.', 'adams-crypto-analysis' ) )
        );

        add_settings_field(
            'adamca_ai_model',
            __( 'AI Model', 'adams-crypto-analysis' ),
            array( __CLASS__, 'render_model_field' ),
            'adams-crypto-analysis',
            'adamca_ai_section'
        );

        // Cache section.
        add_settings_section(
            'adamca_cache_section',
            __( 'Cache Settings', 'adams-crypto-analysis' ),
            '__return_null',
            'adams-crypto-analysis'
        );

        add_settings_field(
            'adamca_cache_expiry',
            __( 'Cache Expiry (seconds)', 'adams-crypto-analysis' ),
            array( __CLASS__, 'render_number_field' ),
            'adams-crypto-analysis',
            'adamca_cache_section',
            array( 'option_name' => 'adamca_cache_expiry', 'description' => __( 'How long to cache analysis results. Default: 43200 (12 hours). Minimum: 3600 (1 hour).', 'adams-crypto-analysis' ) )
        );

        add_settings_field(
            'adamca_top10_coins',
            __( 'Top 10 Coins', 'adams-crypto-analysis' ),
            array( __CLASS__, 'render_textarea_field' ),
            'adams-crypto-analysis',
            'adamca_cache_section',
            array( 'option_name' => 'adamca_top10_coins', 'description' => __( 'CoinGecko IDs, one per line. These get a lightning bolt in the frontend dropdown.', 'adams-crypto-analysis' ) )
        );
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <div class="adamca-admin-wrap">
            <img src="<?php echo esc_url( ADAMS_CRYPTO_ANALYSIS_URL . 'assets/images/adminbanner.png' ); ?>"
                 alt="<?php esc_attr_e( 'Adams Crypto Analysis', 'adams-crypto-analysis' ); ?>"
                 class="adamca-banner">

            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="adamca-how-to-use">
                <h3><?php esc_html_e( 'How to Use', 'adams-crypto-analysis' ); ?></h3>
                <p><?php esc_html_e( 'Use the following shortcode to display the crypto analysis tool on any post, page, or shortcode block:', 'adams-crypto-analysis' ); ?></p>
                <p><code>[adamca_crypto_analysis]</code></p>
                <h3><?php esc_html_e( 'Recommended AI Models', 'adams-crypto-analysis' ); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e( 'Grok 4 Fast Non-Reasoning', 'adams-crypto-analysis' ); ?></strong> — <?php esc_html_e( 'Cheapest & fastest (recommended)', 'adams-crypto-analysis' ); ?></li>
                    <li><strong><?php esc_html_e( 'Claude Sonnet 4.5', 'adams-crypto-analysis' ); ?></strong> — <?php esc_html_e( 'Balanced speed & accuracy', 'adams-crypto-analysis' ); ?></li>
                    <li><strong><?php esc_html_e( 'Claude Opus 4.5', 'adams-crypto-analysis' ); ?></strong> — <?php esc_html_e( 'Most accurate, expensive (not recommended on free tier)', 'adams-crypto-analysis' ); ?></li>
                    <li><strong><?php esc_html_e( 'GPT-5', 'adams-crypto-analysis' ); ?></strong> — <?php esc_html_e( 'Accurate but slow; increased caching times suggested', 'adams-crypto-analysis' ); ?></li>
                    <li><strong><?php esc_html_e( 'GPT-4o', 'adams-crypto-analysis' ); ?></strong> — <?php esc_html_e( 'Legacy', 'adams-crypto-analysis' ); ?></li>
                </ul>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'adamca_settings_group' );
                do_settings_sections( 'adams-crypto-analysis' );
                submit_button( __( 'Save Settings', 'adams-crypto-analysis' ) );
                ?>
            </form>

            <hr class="adamca-divider">
            <h2><?php esc_html_e( 'Test Connections', 'adams-crypto-analysis' ); ?></h2>
            <div class="adamca-actions">
                <button type="button" class="adamca-btn adamca-btn-secondary" id="adamca-test-coingecko">
                    <?php esc_html_e( 'Test CoinGecko', 'adams-crypto-analysis' ); ?>
                </button>
                <button type="button" class="adamca-btn adamca-btn-secondary" id="adamca-test-ai">
                    <?php esc_html_e( 'Test AI Provider', 'adams-crypto-analysis' ); ?>
                </button>
                <span id="adamca-test-result" class="adamca-result-msg"></span>
            </div>

            <hr class="adamca-divider">
            <h2><?php esc_html_e( 'Cache Management', 'adams-crypto-analysis' ); ?></h2>
            <div class="adamca-actions">
                <button type="button" class="adamca-btn adamca-btn-danger" id="adamca-clear-all-cache">
                    <?php esc_html_e( 'Clear All Cache', 'adams-crypto-analysis' ); ?>
                </button>
                <span id="adamca-cache-result" class="adamca-result-msg"></span>
            </div>

            <?php
            $cached_status = ADAMCA_Cache::get_all_cached_status();
            if ( ! empty( $cached_status ) ) :
                ?>
                <table class="adamca-cache-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Coin', 'adams-crypto-analysis' ); ?></th>
                            <th><?php esc_html_e( 'Age (min)', 'adams-crypto-analysis' ); ?></th>
                            <th><?php esc_html_e( 'Provider', 'adams-crypto-analysis' ); ?></th>
                            <th><?php esc_html_e( 'Model', 'adams-crypto-analysis' ); ?></th>
                            <th><?php esc_html_e( 'Valid', 'adams-crypto-analysis' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'adams-crypto-analysis' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $cached_status as $status_entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( $status_entry['coin_id'] ); ?></td>
                                <td><?php echo esc_html( $status_entry['cache_age_minutes'] ); ?></td>
                                <td><?php echo esc_html( $status_entry['provider'] ); ?></td>
                                <td><?php echo esc_html( $status_entry['model'] ); ?></td>
                                <td class="<?php echo esc_attr( $status_entry['still_valid'] ? 'adamca-valid' : 'adamca-invalid' ); ?>">
                                    <?php echo esc_html( $status_entry['still_valid'] ? '✓' : '✗' ); ?>
                                </td>
                                <td>
                                    <button type="button" class="adamca-btn adamca-btn-danger adamca-btn-sm adamca-clear-single"
                                            data-coin="<?php echo esc_attr( $status_entry['coin_id'] ); ?>">
                                        <?php esc_html_e( 'Clear', 'adams-crypto-analysis' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="adamca-empty-state"><?php esc_html_e( 'No cached analyses found.', 'adams-crypto-analysis' ); ?></div>
            <?php endif; ?>
        </div>

        <?php
    }

    /**
     * Render a password input field.
     *
     * @param array $field_args Field arguments with option_name and description.
     */
    public static function render_password_field( $field_args ) {
        $option_name  = $field_args['option_name'];
        $option_value = get_option( $option_name, '' );
        $description  = isset( $field_args['description'] ) ? $field_args['description'] : '';
        ?>
        <input type="password" name="<?php echo esc_attr( $option_name ); ?>"
               value="<?php echo esc_attr( $option_value ); ?>" class="regular-text" autocomplete="off">
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a text input field.
     *
     * @param array $field_args Field arguments with option_name and description.
     */
    public static function render_text_field( $field_args ) {
        $option_name  = $field_args['option_name'];
        $option_value = get_option( $option_name, '' );
        $description  = isset( $field_args['description'] ) ? $field_args['description'] : '';
        ?>
        <input type="text" name="<?php echo esc_attr( $option_name ); ?>"
               value="<?php echo esc_attr( $option_value ); ?>" class="regular-text">
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a number input field.
     *
     * @param array $field_args Field arguments with option_name and description.
     */
    public static function render_number_field( $field_args ) {
        $option_name  = $field_args['option_name'];
        $option_value = get_option( $option_name, 43200 );
        $description  = isset( $field_args['description'] ) ? $field_args['description'] : '';
        ?>
        <input type="number" name="<?php echo esc_attr( $option_name ); ?>"
               value="<?php echo esc_attr( $option_value ); ?>" min="3600" step="1" class="small-text">
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the AI provider select field.
     */
    public static function render_provider_field() {
        $current_provider = get_option( 'adamca_ai_provider', 'openai' );
        $provider_options = array(
            'openai'    => 'OpenAI',
            'xai'       => 'xAI / Grok',
            'anthropic' => 'Anthropic / Claude',
        );
        ?>
        <select name="adamca_ai_provider">
            <?php foreach ( $provider_options as $provider_value => $provider_label ) : ?>
                <option value="<?php echo esc_attr( $provider_value ); ?>"
                    <?php selected( $current_provider, $provider_value ); ?>>
                    <?php echo esc_html( $provider_label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render the AI model select field (filtered by provider via JS).
     */
    public static function render_model_field() {
        $current_model    = get_option( 'adamca_ai_model', '' );
        $current_provider = get_option( 'adamca_ai_provider', 'openai' );
        $models_by_provider = array(
            'openai'    => array( 'gpt-5', 'gpt-5-mini', 'gpt-4o', 'gpt-4o-mini' ),
            'xai'       => array( 'grok-4-fast-non-reasoning', 'grok-4', 'grok-3' ),
            'anthropic' => array( 'claude-opus-4-5', 'claude-sonnet-4-5' ),
        );
        ?>
        <select name="adamca_ai_model" id="adamca-ai-model-select">
            <?php foreach ( $models_by_provider as $provider_key => $model_list ) : ?>
                <?php foreach ( $model_list as $model_value ) : ?>
                    <option value="<?php echo esc_attr( $model_value ); ?>"
                            data-provider="<?php echo esc_attr( $provider_key ); ?>"
                            <?php selected( $current_model, $model_value ); ?>>
                        <?php echo esc_html( $model_value ); ?>
                    </option>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render a textarea field.
     *
     * @param array $field_args Field arguments with option_name and description.
     */
    public static function render_textarea_field( $field_args ) {
        $option_name  = $field_args['option_name'];
        $option_value = get_option( $option_name, '' );
        $description  = isset( $field_args['description'] ) ? $field_args['description'] : '';
        ?>
        <textarea name="<?php echo esc_attr( $option_name ); ?>" rows="10" cols="40"
                  class="large-text code"><?php echo esc_textarea( $option_value ); ?></textarea>
        <?php if ( $description ) : ?>
            <p class="description"><?php echo esc_html( $description ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Sanitize provider value against allowed list.
     *
     * @param string $input_value Raw provider value.
     * @return string Sanitized provider or default.
     */
    public static function sanitize_provider( $input_value ) {
        $allowed_values = array( 'openai', 'xai', 'anthropic' );
        return in_array( $input_value, $allowed_values, true ) ? $input_value : 'openai';
    }

    /**
     * Sanitize model value against allowed list.
     *
     * @param string $input_value Raw model value.
     * @return string Sanitized model or empty string.
     */
    public static function sanitize_model( $input_value ) {
        $allowed_values = array(
            'gpt-5', 'gpt-5-mini', 'gpt-4o', 'gpt-4o-mini',
            'grok-4-fast-non-reasoning', 'grok-4', 'grok-3',
            'claude-opus-4-5', 'claude-sonnet-4-5',
        );
        return in_array( $input_value, $allowed_values, true ) ? $input_value : '';
    }

    /**
     * Sanitize coins list textarea.
     *
     * @param string $input_value Raw textarea content.
     * @return string Sanitized coins, one per line.
     */
    public static function sanitize_coins_list( $input_value ) {
        $lines_array  = explode( "\n", $input_value );
        $clean_lines  = array_filter( array_map( function ( $line_text ) {
            return sanitize_key( trim( $line_text ) );
        }, $lines_array ) );
        return implode( "\n", $clean_lines );
    }

    /**
     * Sanitize cache expiry value.
     *
     * @param mixed $input_value Raw expiry value.
     * @return int Sanitized expiry in seconds (minimum 3600).
     */
    public static function sanitize_cache_expiry( $input_value ) {
        $expiry_value = absint( $input_value );
        return max( 3600, $expiry_value );
    }

    /**
     * AJAX handler: Test CoinGecko connection.
     */
    public static function ajax_test_coingecko() {
        check_ajax_referer( 'adamca_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized.', 'adams-crypto-analysis' ) );
        }

        $request_url = 'https://api.coingecko.com/api/v3/coins/bitcoin/ohlc?vs_currency=usd&days=1';
        $headers_arr = array();
        $api_key     = get_option( 'adamca_coingecko_api_key', '' );

        if ( ! empty( $api_key ) ) {
            $headers_arr['x-cg-pro-api-key'] = $api_key;
        }

        $response = wp_remote_get( $request_url, array(
            'timeout' => 15,
            'headers' => $headers_arr,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 === $status_code ) {
            wp_send_json_success( array( 'message' => __( 'CoinGecko connection successful!', 'adams-crypto-analysis' ) ) );
        } else {
            wp_send_json_error(
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'CoinGecko returned HTTP %d.', 'adams-crypto-analysis' ),
                    $status_code
                )
            );
        }
    }

    /**
     * AJAX handler: Test AI provider connection.
     */
    public static function ajax_test_ai_provider() {
        check_ajax_referer( 'adamca_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized.', 'adams-crypto-analysis' ) );
        }

        $provider    = get_option( 'adamca_ai_provider', 'openai' );
        $api_key     = get_option( 'adamca_ai_api_key', '' );
        $model_name  = get_option( 'adamca_ai_model', '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'AI API key is not configured.', 'adams-crypto-analysis' ) );
        }

        $test_prompt = 'Reply with exactly the word OK.';
        $system_text = 'You are a helpful assistant.';

        switch ( $provider ) {
            case 'xai':
                $effective_model = $model_name ?: 'grok-3';
                $xai_body = array(
                    'model'       => $effective_model,
                    'messages'    => array(
                        array( 'role' => 'system', 'content' => $system_text ),
                        array( 'role' => 'user',   'content' => $test_prompt ),
                    ),
                    'max_tokens'  => 10,
                    'temperature' => 0,
                    'stream'      => false,
                );
                $request_url  = 'https://api.x.ai/v1/chat/completions';
                $request_body = wp_json_encode( $xai_body );
                $headers_arr = array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                );
                break;

            case 'anthropic':
                $request_url  = 'https://api.anthropic.com/v1/messages';
                $request_body = wp_json_encode( array(
                    'model'      => $model_name ?: 'claude-opus-4-5',
                    'max_tokens' => 10,
                    'system'     => $system_text,
                    'messages'   => array(
                        array( 'role' => 'user', 'content' => $test_prompt ),
                    ),
                ) );
                $headers_arr = array(
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $api_key,
                    'anthropic-version' => '2023-06-01',
                );
                break;

            case 'openai':
            default:
                $effective_model = $model_name ?: 'gpt-4o';
                if ( 0 === strpos( $effective_model, 'gpt-5' ) ) {
                    $request_url      = 'https://api.openai.com/v1/responses';
                    $combined_prompt  = $system_text . "\n\n" . $test_prompt;
                    $request_body     = wp_json_encode( array(
                        'model'             => $effective_model,
                        'input'             => array(
                            array(
                                'role'    => 'user',
                                'content' => array(
                                    array(
                                        'type' => 'input_text',
                                        'text' => $combined_prompt,
                                    ),
                                ),
                            ),
                        ),
                        'max_output_tokens' => 16,
                    ) );
                } else {
                    $request_url  = 'https://api.openai.com/v1/chat/completions';
                    $request_body = wp_json_encode( array(
                        'model'       => $effective_model,
                        'messages'    => array(
                            array( 'role' => 'system', 'content' => $system_text ),
                            array( 'role' => 'user',   'content' => $test_prompt ),
                        ),
                        'max_tokens'  => 10,
                        'temperature' => 0,
                    ) );
                }
                $headers_arr = array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                );
                break;
        }

        $response = wp_remote_post( $request_url, array(
            'timeout' => 30,
            'headers' => $headers_arr,
            'body'    => $request_body,
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 === $status_code ) {
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %s: AI provider name */
                    __( '%s connection successful!', 'adams-crypto-analysis' ),
                    ucfirst( $provider )
                ),
            ) );
        } else {
            $error_body  = wp_remote_retrieve_body( $response );
            $safe_error  = sanitize_text_field( substr( $error_body, 0, 200 ) );
            wp_send_json_error(
                sprintf(
                    /* translators: 1: AI provider name, 2: HTTP status code, 3: truncated error message */
                    __( '%1$s returned HTTP %2$d: %3$s', 'adams-crypto-analysis' ),
                    ucfirst( $provider ),
                    $status_code,
                    $safe_error
                )
            );
        }
    }

    /**
     * AJAX handler: Clear cache for a coin or all coins.
     */
    public static function ajax_clear_cache() {
        check_ajax_referer( 'adamca_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized.', 'adams-crypto-analysis' ) );
        }

        $coin_id = isset( $_POST['coin_id'] ) ? sanitize_key( wp_unslash( $_POST['coin_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.

        if ( empty( $coin_id ) ) {
            wp_send_json_error( __( 'No coin ID provided.', 'adams-crypto-analysis' ) );
        }

        if ( 'all' === $coin_id ) {
            $clear_count = ADAMCA_Cache::clear_all_cache();
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %d: number of coins cleared */
                    __( 'Cleared cache for %d coin(s).', 'adams-crypto-analysis' ),
                    $clear_count
                ),
            ) );
        } else {
            ADAMCA_Cache::clear_cache( $coin_id );
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %s: coin ID */
                    __( 'Cleared cache for %s.', 'adams-crypto-analysis' ),
                    $coin_id
                ),
            ) );
        }
    }
}
