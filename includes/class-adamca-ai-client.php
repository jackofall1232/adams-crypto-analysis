<?php
/**
 * AI provider HTTP calls and prompt builder.
 *
 * @package AdamsCryptoAnalysis
 */

defined( 'ABSPATH' ) || exit;

class ADAMCA_AI_Client {

    /**
     * Generate analysis HTML for a coin using the configured AI provider.
     *
     * @param string $coin_id     CoinGecko coin identifier.
     * @param array  $shaped_data Shaped market data from CoinGecko.
     * @return string|WP_Error Analysis HTML or WP_Error on failure.
     */
    public static function generate_analysis( $coin_id, $shaped_data ) {
        $prompt_text  = self::build_prompt( $coin_id, $shaped_data );
        $provider     = get_option( 'adamca_ai_provider', 'openai' );

        switch ( $provider ) {
            case 'xai':
                $raw_response = self::send_to_grok_xai( $prompt_text );
                break;
            case 'anthropic':
                $raw_response = self::send_to_anthropic( $prompt_text );
                break;
            case 'openai':
            default:
                $raw_response = self::send_to_openai( $prompt_text );
                break;
        }

        if ( is_wp_error( $raw_response ) ) {
            return $raw_response;
        }

        $html_output = self::clean_response( $raw_response );
        return $html_output;
    }

    /**
     * Build the full analysis prompt with coin data.
     *
     * @param string $coin_id     CoinGecko coin identifier.
     * @param array  $shaped_data Shaped market data.
     * @return string The complete prompt text.
     */
    public static function build_prompt( $coin_id, $shaped_data ) {
        $safe_coin_id    = sanitize_key( $coin_id );
        $json_data       = wp_json_encode( $shaped_data );

        $prompt_text = 'Analyze this CoinGecko cryptocurrency data (JSON below).' . "\n\n"
            . 'You are a professional cryptocurrency technical analyst specializing in swing trading signals.' . "\n\n"
            . 'You will receive market data from CoinGecko structured into two sections:' . "\n\n"
            . '1) short_term → contains metadata (timeframe, candle_minutes, lookback_days, bars_per_day) and:' . "\n"
            . '   - ohlc → 4-HOUR OHLC candles (timeframe = 4h), typically ~30 days of history (~180 candles)' . "\n"
            . '2) long_term → contains metadata (timeframe, lookback_days) and a 90-day price summary:' . "\n"
            . '   - weekly_closes_90d (approximately 12-13 evenly spaced price values as plain numbers)' . "\n"
            . '   - price_90d_start' . "\n"
            . '   - price_90d_end' . "\n"
            . '   - price_90d_change_pct' . "\n\n"
            . 'Your task: Generate a complete technical analysis with a BUY, HOLD, or SELL recommendation and output ONLY valid HTML.' . "\n\n"
            . '====================================================' . "\n"
            . 'DATA PROCESSING RULES' . "\n"
            . '====================================================' . "\n"
            . '1. Candle timestamps are full ISO strings (e.g., 2025-12-12T08:00:00.000Z). Treat them as 4-hour candle close times.' . "\n"
            . '2. Data granularity is 4H candles (240 minutes per candle). Do NOT treat this as daily data.' . "\n"
            . '3. OHLC data is complete and needs NO interpolation.' . "\n"
            . '4. Use ALL 4H candles provided for indicator calculations (typically ~180 candles).' . "\n"
            . '5. Volume data is unavailable - do NOT use volume-based indicators or volume assumptions.' . "\n"
            . '6. Long-term price data is provided ONLY as weekly samples over 90 days. Use it for directional bias and trend context, NOT for indicator calculations.' . "\n"
            . '7. All numeric outputs must be precise decimals (e.g., 134.52). No "~" or ranges.' . "\n\n"
            . '====================================================' . "\n"
            . 'TECHNICAL ANALYSIS REQUIREMENTS' . "\n"
            . '====================================================' . "\n"
            . 'Calculate and interpret using 4H candles ONLY:' . "\n\n"
            . '- RSI (14-period): numeric value + interpretation (overbought, oversold, neutral)' . "\n"
            . '- MACD (12, 26, 9): MACD line, signal line, histogram, crossover state (bullish/bearish)' . "\n"
            . '- Bollinger Bands (20 SMA +/- 2 SD): position of price inside or outside bands' . "\n"
            . '- 20-period SMA: trend direction and relationship to price (on 4H candles)' . "\n"
            . '- 50-period SMA: trend direction and relationship to price (on 4H candles)' . "\n"
            . '- Market structure: bullish, bearish, or sideways with explanation (based on 4H swing highs/lows)' . "\n"
            . '- Support & resistance: identify at least two major levels for each side (from 4H swing points)' . "\n"
            . '- Price action: trend, momentum, candle behavior, volatility (4H context)' . "\n\n"
            . 'Use the 90-day price summary ONLY to:' . "\n"
            . '- Confirm or contradict short-term trend' . "\n"
            . '- Describe broader market context (e.g., expansion, drawdown, recovery)' . "\n"
            . '- Adjust confidence level and tone of the recommendation' . "\n\n"
            . 'IMPORTANT: Because this is 4H data, interpret signals as swing-trading opportunities over the next several days to a few weeks.' . "\n\n"
            . '====================================================' . "\n"
            . 'DUAL-AUDIENCE REQUIREMENT (NOOBS + ADVANCED)' . "\n"
            . '====================================================' . "\n"
            . 'This report must serve BOTH beginners and advanced traders:' . "\n\n"
            . '1) Beginner-friendly interpretation:' . "\n"
            . '   - Explain what the current structure means in plain language.' . "\n"
            . '   - Mention trader psychology (capitulation, exhaustion, failed bounce, squeeze risk) when applicable.' . "\n"
            . '   - Explain what indicator confluence implies (continuation risk vs reversal risk).' . "\n"
            . '   - Must be 4-6 sentences.' . "\n\n"
            . '2) Advanced precision:' . "\n"
            . '   - Provide exact numeric indicator outputs and exact S/R, entry/target/stop, and R:R.' . "\n"
            . '   - Keep technical bullet lists concise and exact.' . "\n\n"
            . 'Do NOT inflate the report with filler. Narrative must add meaning beyond repeating indicator values.' . "\n\n"
            . '====================================================' . "\n"
            . 'TRADING SIGNAL REQUIREMENTS' . "\n"
            . '====================================================' . "\n"
            . 'You MUST provide:' . "\n\n"
            . '- Recommendation: BUY, HOLD, or SELL (ONLY these three - no variations)' . "\n"
            . '- Confidence: HIGH, MEDIUM, or LOW' . "\n"
            . '- Entry Price: a specific numerical price level' . "\n"
            . '- Target Price (1st): a realistic target based on 4H structure' . "\n"
            . '- Stop-Loss: a specific value below support (not a range)' . "\n"
            . '- Risk/Reward Ratio: numeric ratio (e.g., 1:2.5)' . "\n"
            . '- Reasoning: 4-5 sentences explaining WHY the signal was chosen based on indicator confluence + 4H structure, with brief 90-day context. Reasoning must include:' . "\n"
            . '  (a) what would invalidate the setup,' . "\n"
            . '  (b) the main risk (trend continuation vs reversal risk).' . "\n\n"
            . '====================================================' . "\n"
            . 'HTML OUTPUT STRUCTURE' . "\n"
            . '====================================================' . "\n"
            . 'Output ONLY valid HTML. No markdown. No code fences. No CSS. No backticks.' . "\n\n"
            . 'Structure:' . "\n\n"
            . '<div class="analysis-container">' . "\n\n"
            . '  <h2>Market Story (Plain-English)</h2>' . "\n"
            . '  <p>4-6 sentences explaining what is happening, why it matters, and how traders may be positioned. Include brief 90-day context.</p>' . "\n\n"
            . '  <h2>Technical Summary</h2>' . "\n"
            . '  <p>One compact paragraph (2-4 sentences) summarizing 4H trend, momentum, volatility, and structure.</p>' . "\n\n"
            . '  <h3>Key Indicators</h3>' . "\n"
            . '  <ul>' . "\n"
            . '    <li><strong>RSI (14):</strong> [value] - [interpretation]</li>' . "\n"
            . '    <li><strong>MACD:</strong> [bullish/bearish + include line, signal, histogram]</li>' . "\n"
            . '    <li><strong>Bollinger Bands:</strong> [price position]</li>' . "\n"
            . '    <li><strong>20-period SMA:</strong> [value] - [direction] - price above/below</li>' . "\n"
            . '    <li><strong>50-period SMA:</strong> [value] - [direction] - price above/below</li>' . "\n"
            . '    <li><strong>Market Structure:</strong> bullish / bearish / sideways with brief explanation</li>' . "\n"
            . '  </ul>' . "\n\n"
            . '  <h3>Support & Resistance</h3>' . "\n"
            . '  <ul>' . "\n"
            . '    <li><strong>Primary Support:</strong> [price] - [reason]</li>' . "\n"
            . '    <li><strong>Secondary Support:</strong> [price] - [reason]</li>' . "\n"
            . '    <li><strong>Primary Resistance:</strong> [price] - [reason]</li>' . "\n"
            . '    <li><strong>Secondary Resistance:</strong> [price] - [reason]</li>' . "\n"
            . '  </ul>' . "\n\n"
            . '  <h3>Final Trading Signal</h3>' . "\n"
            . '  <p><strong>Recommendation:</strong> Buy / Hold / Sell</p>' . "\n"
            . '  <p><strong>Confidence:</strong> HIGH / MEDIUM / LOW</p>' . "\n"
            . '  <p><strong>Entry Price:</strong> $[value]</p>' . "\n"
            . '  <p><strong>Target Price (1st):</strong> $[value]</p>' . "\n"
            . '  <p><strong>Stop-Loss:</strong> $[value]</p>' . "\n"
            . '  <p><strong>Risk/Reward Ratio:</strong> 1:[value]</p>' . "\n"
            . '  <p><strong>Reasoning:</strong> 4-5 sentences: confluence + invalidation + main risk + 90-day alignment.</p>' . "\n\n"
            . '  <h3>JSON Data Export</h3>' . "\n"
            . '  <pre style="white-space:pre-wrap;font-size:0.85em;background:#0a0f1f;padding:12px;border-radius:8px;color:#0f0;">' . "\n"
            . '  {insert ONLY valid JSON - no trailing commas - containing:' . "\n"
            . '   timeframe metadata,' . "\n"
            . '   indicator outputs (including MACD line/signal/histogram),' . "\n"
            . '   support/resistance levels,' . "\n"
            . '   long-term summary values,' . "\n"
            . '   and final signal.' . "\n"
            . '   Do NOT include the full raw OHLC array.}' . "\n"
            . '  </pre>' . "\n"
            . '</div>' . "\n\n"
            . '====================================================' . "\n"
            . 'STYLE GUIDELINES' . "\n"
            . '====================================================' . "\n"
            . '- No Markdown.' . "\n"
            . '- No code blocks.' . "\n"
            . '- No CSS beyond the image tag.' . "\n"
            . '- Do not invent extra fields.' . "\n"
            . '- All numeric values must be exact decimals.' . "\n\n"
            . '====================================================' . "\n"
            . 'GOAL' . "\n"
            . '====================================================' . "\n"
            . 'Produce a professional-grade, investor-ready technical analysis in clean HTML with a' . "\n"
            . 'justified Buy/Hold/Sell signal based on 4H candles, informed - but not overridden - by' . "\n"
            . '90-day trend context, with both plain-English interpretation and advanced precision.' . "\n\n"
            . 'Cryptocurrency: ' . $safe_coin_id . "\n\n"
            . 'DATA:' . "\n"
            . $json_data;

        return $prompt_text;
    }

    /**
     * Get the system prompt for all AI providers.
     *
     * @return string System prompt text.
     */
    public static function get_system_prompt() {
        return 'You are a professional cryptocurrency technical analyst specializing in swing trading signals.';
    }

    /**
     * Send prompt to OpenAI.
     *
     * @param string $prompt_text The full user prompt.
     * @return string|WP_Error Response text or WP_Error.
     */
    private static function send_to_openai( $prompt_text ) {
        $api_key     = get_option( 'adamca_ai_api_key', '' );
        $model_name  = get_option( 'adamca_ai_model', 'gpt-4o' );

        // GPT-5 models use the Responses API.
        if ( 0 === strpos( $model_name, 'gpt-5' ) ) {
            $combined_prompt = self::get_system_prompt() . "\n\n" . $prompt_text;
            return self::send_to_openai_responses( $combined_prompt, $api_key, $model_name );
        }

        $request_url = 'https://api.openai.com/v1/chat/completions';

        $request_body = wp_json_encode( array(
            'model'       => $model_name,
            'messages'    => array(
                array( 'role' => 'system', 'content' => self::get_system_prompt() ),
                array( 'role' => 'user',   'content' => $prompt_text ),
            ),
            'max_tokens'  => 8096,
            
                ) );

        $response = wp_remote_post( $request_url, array(
            'timeout' => 140,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => $request_body,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $error_detail = wp_remote_retrieve_body( $response );
            return new WP_Error( 'adamca_openai_error', 'OpenAI returned HTTP ' . $status_code );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $response_body['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'adamca_openai_empty', __( 'OpenAI returned an empty response.', 'adams-crypto-analysis' ) );
        }

        return $response_body['choices'][0]['message']['content'];
    }

    /**
     * Send prompt to OpenAI Responses API (GPT-5 models).
     *
     * @param string $prompt_text The full user prompt.
     * @param string $api_key     OpenAI API key.
     * @param string $model_name  Model name (e.g. gpt-5, gpt-5-mini).
     * @return string|WP_Error Response text or WP_Error.
     */
    private static function send_to_openai_responses( $prompt_text, $api_key, $model_name ) {
        $request_url = 'https://api.openai.com/v1/responses';

        $request_body = wp_json_encode( array(
            'model'             => $model_name,
            'input'             => array(
                array(
                    'role'    => 'user',
                    'content' => array(
                        array(
                            'type' => 'input_text',
                            'text' => $prompt_text,
                        ),
                    ),
                ),
            ),
            'reasoning'         => array(
                'effort' => 'low',
            ),
            'text'              => array(
                'verbosity' => 'high',
            ),
            
            'max_output_tokens' => 9096,
        ) );

        self::log_openai_debug( 'Responses request', array(
            'endpoint'      => $request_url,
            'model'         => $model_name,
            'payload_chars' => mb_strlen( $request_body ),
            'payload_bytes' => strlen( $request_body ),
            'payload'       => $request_body,
        ) );

        $response = wp_remote_post( $request_url, array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => $request_body,
        ) );

        if ( is_wp_error( $response ) ) {
            self::log_openai_debug( 'Responses transport error', array(
                'error' => $response->get_error_message(),
            ) );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );

        self::log_openai_debug( 'Responses raw response', array(
            'status_code' => $status_code,
            'body'        => $raw_body,
        ) );

        if ( 200 !== $status_code ) {
            $error_detail = $raw_body;
            return new WP_Error( 'adamca_openai_error', 'OpenAI returned HTTP ' . $status_code );
        }

        $response_body = json_decode( $raw_body, true );

        if ( ! is_array( $response_body ) ) {
            $json_error = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'Unknown JSON decode error';
            self::log_openai_debug( 'Responses JSON decode error', array(
                'json_error' => $json_error,
                'raw_body'   => $raw_body,
            ) );
            return new WP_Error( 'adamca_openai_parse_error', __( 'OpenAI returned an unreadable response.', 'adams-crypto-analysis' ) );
        }

        self::log_openai_debug( 'Responses parsed JSON', array(
            'json_decode_ok'   => true,
            'has_output_text'  => isset( $response_body['output_text'] ),
            'output_text_type' => isset( $response_body['output_text'] ) ? gettype( $response_body['output_text'] ) : 'absent',
            'has_output'       => isset( $response_body['output'] ),
            'output_count'     => isset( $response_body['output'] ) && is_array( $response_body['output'] ) ? count( $response_body['output'] ) : 0,
            'top_level_keys'   => is_array( $response_body ) ? array_keys( $response_body ) : array(),
        ) );

        $response_text = self::extract_openai_responses_text( $response_body );

        self::log_openai_debug( 'Responses extraction result', array(
            'parsed_text_length' => mb_strlen( $response_text ),
            'parsed_text_empty'  => '' === $response_text,
        ) );

        if ( '' === $response_text ) {
            self::log_openai_debug( 'Responses full response body (empty result)', array(
                'response_body' => $response_body,
            ) );
            return new WP_Error( 'adamca_openai_empty', __( 'OpenAI returned an empty response.', 'adams-crypto-analysis' ) );
        }

        return $response_text;
    }

    /**
     * Extract assistant text from OpenAI Responses API payload.
     *
     * Handles both the top-level `output_text` convenience field and the nested
     * `output[].content[].text` structure that OpenAI may return.
     *
     * @param array $response_body Decoded JSON response body.
     * @return string
     */
    private static function extract_openai_responses_text( $response_body ) {
        if ( ! is_array( $response_body ) ) {
            return '';
        }

        // 1. Prefer the top-level output_text convenience field if present and non-empty.
        if ( ! empty( $response_body['output_text'] ) ) {
            if ( is_array( $response_body['output_text'] ) ) {
                $output_text_parts = array();

                foreach ( $response_body['output_text'] as $output_text_item ) {
                    $normalized = self::extract_openai_text_value( $output_text_item );
                    if ( '' !== $normalized ) {
                        $output_text_parts[] = $normalized;
                    }
                }

                $joined = trim( implode( "\n", $output_text_parts ) );
                if ( '' !== $joined ) {
                    return $joined;
                }
            }

            if ( is_string( $response_body['output_text'] ) ) {
                $trimmed = trim( $response_body['output_text'] );
                if ( '' !== $trimmed ) {
                    return $trimmed;
                }
            }
        }

        // 2. Fall back to iterating output[] -> content[] -> text.
        if ( empty( $response_body['output'] ) || ! is_array( $response_body['output'] ) ) {
            return '';
        }

        $text_parts = array();

        foreach ( $response_body['output'] as $output_item ) {
            if ( ! is_array( $output_item ) ) {
                continue;
            }

            // Some output items may carry a text field directly (e.g. type=output_text at item level).
            if ( isset( $output_item['type'] ) && 'output_text' === $output_item['type']
                && isset( $output_item['text'] ) ) {
                $normalized_text = self::extract_openai_text_value( $output_item['text'] );
                if ( '' !== $normalized_text ) {
                    $text_parts[] = $normalized_text;
                }
                continue;
            }

            if ( empty( $output_item['content'] ) || ! is_array( $output_item['content'] ) ) {
                continue;
            }

            foreach ( $output_item['content'] as $content_item ) {
                if ( ! is_array( $content_item ) ) {
                    continue;
                }

                // Accept content blocks with type "output_text" or any block that has a text field.
                $is_text_type = isset( $content_item['type'] ) && in_array( $content_item['type'], array( 'output_text', 'text' ), true );
                $has_text     = isset( $content_item['text'] );

                if ( $is_text_type || $has_text ) {
                    if ( $has_text ) {
                        $normalized_text = self::extract_openai_text_value( $content_item['text'] );
                        if ( '' !== $normalized_text ) {
                            $text_parts[] = $normalized_text;
                        }
                    }
                }
            }
        }

        return trim( implode( "\n", $text_parts ) );
    }

    /**
     * Normalize OpenAI Responses text payload variants to a plain string.
     *
     * @param mixed $value Raw text value from Responses JSON.
     * @return string
     */
    private static function extract_openai_text_value( $value ) {
        if ( is_string( $value ) ) {
            return trim( $value );
        }

        if ( is_array( $value ) ) {
            if ( isset( $value['value'] ) && is_string( $value['value'] ) ) {
                return trim( $value['value'] );
            }

            if ( isset( $value['text'] ) && is_string( $value['text'] ) ) {
                return trim( $value['text'] );
            }

            $parts = array();
            foreach ( $value as $sub_value ) {
                $normalized = self::extract_openai_text_value( $sub_value );
                if ( '' !== $normalized ) {
                    $parts[] = $normalized;
                }
            }

            return trim( implode( "\n", $parts ) );
        }

        return '';
    }

    /**
     * Log OpenAI-only debug details when explicitly enabled.
     *
     * @param string $label   Log label.
     * @param array  $context Log context payload.
     * @return void
     */
    private static function log_openai_debug( $label, $context ) {
        // Safe debug mode is disabled by default because prompts can include large data payloads.
        // Enable as needed: add_filter( 'adamca_openai_safe_debug_mode', '__return_true' );
        $enabled = (bool) apply_filters( 'adamca_openai_safe_debug_mode', false );
        if ( ! $enabled ) {
            return;
        }

    }

    /**
     * Send prompt to xAI / Grok.
     *
     * @param string $prompt_text The full user prompt.
     * @return string|WP_Error Response text or WP_Error.
     */
    private static function send_to_grok_xai( $prompt_text ) {
        $api_key     = get_option( 'adamca_ai_api_key', '' );
        $model_name  = get_option( 'adamca_ai_model', 'grok-3' );
        $request_url = 'https://api.x.ai/v1/chat/completions';

        $body_array = array(
            'model'       => $model_name,
            'messages'    => array(
                array( 'role' => 'system', 'content' => self::get_system_prompt() ),
                array( 'role' => 'user',   'content' => $prompt_text ),
            ),
            'max_tokens'  => 4096,
            'temperature' => 0.3,
            'stream'      => false,
        );

        $request_body = wp_json_encode( $body_array );

        $response = wp_remote_post( $request_url, array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => $request_body,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $error_detail = wp_remote_retrieve_body( $response );
            return new WP_Error( 'adamca_xai_error', 'xAI returned HTTP ' . $status_code );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $response_body['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'adamca_xai_empty', __( 'xAI returned an empty response.', 'adams-crypto-analysis' ) );
        }

        return $response_body['choices'][0]['message']['content'];
    }

    /**
     * Send prompt to Anthropic / Claude.
     *
     * @param string $prompt_text The full user prompt.
     * @return string|WP_Error Response text or WP_Error.
     */
    private static function send_to_anthropic( $prompt_text ) {
        $api_key     = get_option( 'adamca_ai_api_key', '' );
        $model_name  = get_option( 'adamca_ai_model', 'claude-opus-4-5' );
        $request_url = 'https://api.anthropic.com/v1/messages';

        $request_body = wp_json_encode( array(
            'model'      => $model_name,
            'max_tokens' => 4096,
            'system'     => self::get_system_prompt(),
            'messages'   => array(
                array( 'role' => 'user', 'content' => $prompt_text ),
            ),
        ) );

        $response = wp_remote_post( $request_url, array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => $request_body,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $error_detail = wp_remote_retrieve_body( $response );
            return new WP_Error( 'adamca_anthropic_error', 'Anthropic returned HTTP ' . $status_code );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $response_body['content'][0]['text'] ) ) {
            return new WP_Error( 'adamca_anthropic_empty', __( 'Anthropic returned an empty response.', 'adams-crypto-analysis' ) );
        }

        return $response_body['content'][0]['text'];
    }

    /**
     * Strip accidental markdown fencing from AI response and inject signal image.
     *
     * @param string $raw_response Raw response text from AI.
     * @return string Cleaned HTML.
     */
    public static function clean_response( $raw_response ) {
        $html = preg_replace( '/^```html\s*|^```\s*|```\s*$/m', '', trim( $raw_response ) );

        // Remove any AI-generated <img> tags (we inject our own).
        $html = preg_replace( '/<img\b[^>]*>/i', '', $html );

        // Detect recommendation and inject the correct signal image.
        $signal = self::detect_recommendation( $html );
        if ( $signal ) {
            $images_url = ADAMS_CRYPTO_ANALYSIS_URL . 'assets/images/';
            $img_tag    = '<img src="' . esc_url( $images_url . $signal . '.jpg' )
                        . '" alt="' . esc_attr( $signal )
                        . '" style="max-width:180px;border-radius:12px;margin-bottom:20px;">';

            // Insert after <div class="analysis-container"> opening tag.
            $html = preg_replace(
                '/(<div\s+class\s*=\s*["\']analysis-container["\'][^>]*>)/i',
                '$1' . "\n  " . $img_tag,
                $html,
                1
            );
        }

        return $html;
    }

    /**
     * Detect BUY/HOLD/SELL recommendation from the AI-generated HTML.
     *
     * @param string $html The analysis HTML.
     * @return string|false 'BUY', 'HOLD', or 'SELL', or false if not found.
     */
    private static function detect_recommendation( $html ) {
        // Primary: look for <strong>Recommendation:</strong> pattern.
        if ( preg_match( '/<strong>\s*Recommendation\s*:?\s*<\/strong>\s*(BUY|HOLD|SELL)/i', $html, $matches ) ) {
            return strtoupper( $matches[1] );
        }

        // Fallback: look for "Recommendation:" as plain text.
        if ( preg_match( '/Recommendation\s*:\s*(BUY|HOLD|SELL)/i', $html, $matches ) ) {
            return strtoupper( $matches[1] );
        }

        return false;
    }
}
