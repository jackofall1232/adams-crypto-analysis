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
        add_action( 'wp_ajax_adamca_test_coingecko', array( __CLASS__, 'ajax_test_coingecko' ) );
        add_action( 'wp_ajax_adamca_test_ai_provider', array( __CLASS__, 'ajax_test_ai_provider' ) );
        add_action( 'wp_ajax_adamca_clear_cache', array( __CLASS__, 'ajax_clear_cache' ) );
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
            'sanitize_callback' => 'sanitize_text_field',
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
            array( __CLASS__, 'render_text_field' ),
            'adams-crypto-analysis',
            'adamca_ai_section',
            array( 'option_name' => 'adamca_ai_model', 'description' => __( 'e.g. gpt-4o, grok-3, claude-opus-4-5', 'adams-crypto-analysis' ) )
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

        $admin_nonce = wp_create_nonce( 'adamca_admin_nonce' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'adamca_settings_group' );
                do_settings_sections( 'adams-crypto-analysis' );
                submit_button();
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Test Connections', 'adams-crypto-analysis' ); ?></h2>
            <p>
                <button type="button" class="button" id="adamca-test-coingecko" data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">
                    <?php esc_html_e( 'Test CoinGecko', 'adams-crypto-analysis' ); ?>
                </button>
                <button type="button" class="button" id="adamca-test-ai" data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">
                    <?php esc_html_e( 'Test AI Provider', 'adams-crypto-analysis' ); ?>
                </button>
                <span id="adamca-test-result"></span>
            </p>

            <hr>
            <h2><?php esc_html_e( 'Cache Management', 'adams-crypto-analysis' ); ?></h2>
            <p>
                <button type="button" class="button" id="adamca-clear-all-cache" data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">
                    <?php esc_html_e( 'Clear All Cache', 'adams-crypto-analysis' ); ?>
                </button>
                <span id="adamca-cache-result"></span>
            </p>

            <?php
            $cached_status = ADAMCA_Cache::get_all_cached_status();
            if ( ! empty( $cached_status ) ) :
                ?>
                <table class="widefat striped" style="max-width:800px;">
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
                                <td><?php echo $status_entry['still_valid'] ? '&#10003;' : '&#10007;'; ?></td>
                                <td>
                                    <button type="button" class="button button-small adamca-clear-single"
                                            data-coin="<?php echo esc_attr( $status_entry['coin_id'] ); ?>"
                                            data-nonce="<?php echo esc_attr( $admin_nonce ); ?>">
                                        <?php esc_html_e( 'Clear', 'adams-crypto-analysis' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><em><?php esc_html_e( 'No cached analyses found.', 'adams-crypto-analysis' ); ?></em></p>
            <?php endif; ?>
        </div>

        <script>
        (function() {
            function adminAjax(action, extraData, resultElement) {
                var formData = new FormData();
                formData.append('action', action);
                formData.append('nonce', '<?php echo esc_js( $admin_nonce ); ?>');
                if (extraData) {
                    Object.keys(extraData).forEach(function(dataKey) {
                        formData.append(dataKey, extraData[dataKey]);
                    });
                }
                resultElement.textContent = 'Working...';
                fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(fetchResponse) { return fetchResponse.json(); })
                .then(function(responseData) {
                    resultElement.textContent = responseData.success
                        ? (responseData.data.message || 'Success!')
                        : (responseData.data || 'Error occurred.');
                })
                .catch(function(fetchError) {
                    resultElement.textContent = 'Request failed: ' + fetchError.message;
                });
            }

            var testResult = document.getElementById('adamca-test-result');
            var cacheResult = document.getElementById('adamca-cache-result');

            document.getElementById('adamca-test-coingecko').addEventListener('click', function() {
                adminAjax('adamca_test_coingecko', null, testResult);
            });

            document.getElementById('adamca-test-ai').addEventListener('click', function() {
                adminAjax('adamca_test_ai_provider', null, testResult);
            });

            document.getElementById('adamca-clear-all-cache').addEventListener('click', function() {
                adminAjax('adamca_clear_cache', { coin_id: 'all' }, cacheResult);
            });

            document.querySelectorAll('.adamca-clear-single').forEach(function(buttonElement) {
                buttonElement.addEventListener('click', function() {
                    adminAjax('adamca_clear_cache', { coin_id: this.dataset.coin }, cacheResult);
                });
            });
        })();
        </script>
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
            wp_send_json_error( 'CoinGecko returned HTTP ' . $status_code );
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
                $request_url  = 'https://api.x.ai/v1/chat/completions';
                $request_body = wp_json_encode( array(
                    'model'       => $model_name ?: 'grok-3',
                    'messages'    => array(
                        array( 'role' => 'system', 'content' => $system_text ),
                        array( 'role' => 'user',   'content' => $test_prompt ),
                    ),
                    'max_tokens'  => 10,
                    'temperature' => 0,
                    'stream'      => false,
                ) );
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
                $request_url  = 'https://api.openai.com/v1/chat/completions';
                $request_body = wp_json_encode( array(
                    'model'       => $model_name ?: 'gpt-4o',
                    'messages'    => array(
                        array( 'role' => 'system', 'content' => $system_text ),
                        array( 'role' => 'user',   'content' => $test_prompt ),
                    ),
                    'max_tokens'  => 10,
                    'temperature' => 0,
                ) );
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
            $error_body = wp_remote_retrieve_body( $response );
            wp_send_json_error( ucfirst( $provider ) . ' returned HTTP ' . $status_code . ': ' . $error_body );
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

        $coin_id = isset( $_POST['coin_id'] ) ? sanitize_key( $_POST['coin_id'] ) : '';

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
