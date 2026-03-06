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

        $admin_nonce = wp_create_nonce( 'adamca_admin_nonce' );
        ?>
        <style>
            .adamca-admin-wrap {
                max-width: 900px;
                margin: 20px auto;
                background: linear-gradient(135deg, #0a0f1f 0%, #111827 100%);
                border-radius: 16px;
                padding: 0 32px 32px;
                color: #e2e8f0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .adamca-admin-wrap .adamca-banner {
                width: calc(100% + 64px);
                margin: 0 -32px 24px;
                border-radius: 16px 16px 0 0;
                display: block;
                max-height: 280px;
                object-fit: cover;
            }
            .adamca-admin-wrap h1 {
                color: #f7931a;
                font-size: 28px;
                margin: 0 0 8px;
                padding: 0;
            }
            .adamca-admin-wrap h2 {
                color: #f7931a;
                font-size: 20px;
                border-bottom: 1px solid #2d3748;
                padding-bottom: 10px;
                margin-top: 32px;
            }
            .adamca-admin-wrap h3 {
                color: #3861fb;
                font-size: 16px;
                margin-top: 24px;
            }
            .adamca-admin-wrap .form-table th {
                color: #cbd5e1;
                font-weight: 600;
                padding: 16px 10px 16px 0;
                vertical-align: top;
            }
            .adamca-admin-wrap .form-table td {
                padding: 12px 10px;
            }
            .adamca-admin-wrap input[type="text"],
            .adamca-admin-wrap input[type="password"],
            .adamca-admin-wrap input[type="number"],
            .adamca-admin-wrap select,
            .adamca-admin-wrap textarea {
                background: #1a1f2e;
                border: 1px solid #2d3748;
                color: #e2e8f0;
                border-radius: 8px;
                padding: 8px 12px;
                font-size: 14px;
                width: 100%;
                max-width: 400px;
                box-sizing: border-box;
            }
            .adamca-admin-wrap textarea {
                max-width: 100%;
            }
            .adamca-admin-wrap input:focus,
            .adamca-admin-wrap select:focus,
            .adamca-admin-wrap textarea:focus {
                border-color: #3861fb;
                outline: none;
                box-shadow: 0 0 0 2px rgba(56, 97, 251, 0.25);
            }
            .adamca-admin-wrap select option {
                background: #1a1f2e;
                color: #e2e8f0;
            }
            .adamca-admin-wrap .description {
                color: #94a3b8;
                font-size: 12px;
                margin-top: 4px;
            }
            .adamca-admin-wrap .submit input[type="submit"],
            .adamca-admin-wrap .adamca-btn {
                background: linear-gradient(135deg, #f7931a 0%, #e2820e 100%);
                color: #fff;
                border: none;
                border-radius: 8px;
                padding: 10px 24px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                transition: all 0.2s;
            }
            .adamca-admin-wrap .submit input[type="submit"]:hover,
            .adamca-admin-wrap .adamca-btn:hover {
                background: linear-gradient(135deg, #e2820e 0%, #d4760a 100%);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(247, 147, 26, 0.3);
            }
            .adamca-admin-wrap .adamca-btn-secondary {
                background: linear-gradient(135deg, #3861fb 0%, #2d4fd8 100%);
            }
            .adamca-admin-wrap .adamca-btn-secondary:hover {
                background: linear-gradient(135deg, #2d4fd8 0%, #2444c0 100%);
                box-shadow: 0 4px 12px rgba(56, 97, 251, 0.3);
            }
            .adamca-admin-wrap .adamca-btn-danger {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            }
            .adamca-admin-wrap .adamca-btn-danger:hover {
                background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            }
            .adamca-admin-wrap .adamca-btn-sm {
                padding: 5px 14px;
                font-size: 12px;
            }
            .adamca-admin-wrap .adamca-actions {
                display: flex;
                gap: 12px;
                align-items: center;
                flex-wrap: wrap;
                margin-top: 12px;
            }
            .adamca-admin-wrap .adamca-result-msg {
                color: #34d399;
                font-weight: 500;
                font-size: 13px;
                min-height: 20px;
            }
            .adamca-admin-wrap .adamca-divider {
                border: none;
                border-top: 1px solid #2d3748;
                margin: 28px 0;
            }
            .adamca-admin-wrap .adamca-cache-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                border-radius: 10px;
                overflow: hidden;
                margin-top: 16px;
                font-size: 13px;
            }
            .adamca-admin-wrap .adamca-cache-table thead th {
                background: #1a1f2e;
                color: #94a3b8;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 11px;
                letter-spacing: 0.8px;
                padding: 12px 14px;
                text-align: left;
                border-bottom: 2px solid #3861fb;
            }
            .adamca-admin-wrap .adamca-cache-table tbody td {
                background: #0f1525;
                padding: 10px 14px;
                border-bottom: 1px solid #1e293b;
                color: #e2e8f0;
            }
            .adamca-admin-wrap .adamca-cache-table tbody tr:hover td {
                background: #1a1f2e;
            }
            .adamca-admin-wrap .adamca-cache-table .adamca-valid {
                color: #34d399;
                font-weight: bold;
            }
            .adamca-admin-wrap .adamca-cache-table .adamca-invalid {
                color: #f87171;
                font-weight: bold;
            }
            .adamca-admin-wrap .adamca-empty-state {
                color: #64748b;
                font-style: italic;
                padding: 20px;
                text-align: center;
                background: #0f1525;
                border-radius: 10px;
                margin-top: 16px;
            }
        </style>

        <div class="adamca-admin-wrap">
            <img src="<?php echo esc_url( ADAMS_CRYPTO_ANALYSIS_URL . 'assets/images/adminbanner.png' ); ?>"
                 alt="<?php esc_attr_e( 'Adams Crypto Analysis', 'adams-crypto-analysis' ); ?>"
                 class="adamca-banner">

            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

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
                                <td class="<?php echo $status_entry['still_valid'] ? 'adamca-valid' : 'adamca-invalid'; ?>">
                                    <?php echo $status_entry['still_valid'] ? '&#10003;' : '&#10007;'; ?>
                                </td>
                                <td>
                                    <button type="button" class="adamca-btn adamca-btn-danger adamca-btn-sm adamca-clear-single"
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
                <div class="adamca-empty-state"><?php esc_html_e( 'No cached analyses found.', 'adams-crypto-analysis' ); ?></div>
            <?php endif; ?>
        </div>

        <script>
        (function() {
            /* --- Model dropdown sync with provider --- */
            var modelsByProvider = {
                openai:    ['gpt-5', 'gpt-5-mini', 'gpt-4o', 'gpt-4o-mini'],
                xai:       ['grok-4', 'grok-3'],
                anthropic: ['claude-opus-4-5', 'claude-sonnet-4-5']
            };
            var providerSelect = document.querySelector('select[name="adamca_ai_provider"]');
            var modelSelect = document.getElementById('adamca-ai-model-select');
            var savedModel = '<?php echo esc_js( get_option( 'adamca_ai_model', '' ) ); ?>';

            function updateModelOptions() {
                if (!providerSelect || !modelSelect) return;
                var provider = providerSelect.value;
                var models = modelsByProvider[provider] || [];
                modelSelect.innerHTML = '';
                var hasSelected = false;
                models.forEach(function(m) {
                    var opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = m;
                    if (m === savedModel) {
                        opt.selected = true;
                        hasSelected = true;
                    }
                    modelSelect.appendChild(opt);
                });
                if (!hasSelected && models.length > 0) {
                    modelSelect.options[0].selected = true;
                }
            }
            if (providerSelect) {
                providerSelect.addEventListener('change', function() { savedModel = ''; updateModelOptions(); });
            }
            updateModelOptions();

            /* --- AJAX helpers --- */
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
     * Render the AI model select field (filtered by provider via JS).
     */
    public static function render_model_field() {
        $current_model    = get_option( 'adamca_ai_model', '' );
        $current_provider = get_option( 'adamca_ai_provider', 'openai' );
        $models_by_provider = array(
            'openai'    => array( 'gpt-5', 'gpt-5-mini', 'gpt-4o', 'gpt-4o-mini' ),
            'xai'       => array( 'grok-4', 'grok-3' ),
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
            'grok-4', 'grok-3',
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
                if ( 0 === strpos( $effective_model, 'grok-4' ) ) {
                    $xai_body['reasoning_effort'] = 'none';
                }
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
                    $request_url  = 'https://api.openai.com/v1/responses';
                    $request_body = wp_json_encode( array(
                        'model'             => $effective_model,
                        'instructions'      => $system_text,
                        'input'             => $test_prompt,
                        'max_output_tokens' => 10,
                        'temperature'       => 0,
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
