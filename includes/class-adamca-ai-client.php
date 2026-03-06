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

        error_log( '[ADAMCA AI] Generating analysis for ' . $coin_id . ' via ' . $provider );

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

        $prompt_text = <<<PROMPT
Analyze this CoinGecko cryptocurrency data (JSON below).

You are a professional cryptocurrency technical analyst specializing in swing trading signals.

You will receive market data from CoinGecko structured into two sections:

1) short_term → contains metadata (timeframe, candle_minutes, lookback_days, bars_per_day) and:
   - ohlc → 4-HOUR OHLC candles (timeframe = 4h), typically ~30 days of history (~180 candles)
2) long_term → contains metadata (timeframe, lookback_days) and a 90-day price summary:
   - weekly_closes_90d (approximately 12-13 evenly spaced price values as plain numbers)
   - price_90d_start
   - price_90d_end
   - price_90d_change_pct

Your task: Generate a complete technical analysis with a BUY, HOLD, or SELL recommendation and output ONLY valid HTML.

====================================================
DATA PROCESSING RULES
====================================================
1. Candle timestamps are full ISO strings (e.g., 2025-12-12T08:00:00.000Z). Treat them as 4-hour candle close times.
2. Data granularity is 4H candles (240 minutes per candle). Do NOT treat this as daily data.
3. OHLC data is complete and needs NO interpolation.
4. Use ALL 4H candles provided for indicator calculations (typically ~180 candles).
5. Volume data is unavailable - do NOT use volume-based indicators or volume assumptions.
6. Long-term price data is provided ONLY as weekly samples over 90 days. Use it for directional bias and trend context, NOT for indicator calculations.
7. All numeric outputs must be precise decimals (e.g., 134.52). No "~" or ranges.

====================================================
TECHNICAL ANALYSIS REQUIREMENTS
====================================================
Calculate and interpret using 4H candles ONLY:

- RSI (14-period): numeric value + interpretation (overbought, oversold, neutral)
- MACD (12, 26, 9): MACD line, signal line, histogram, crossover state (bullish/bearish)
- Bollinger Bands (20 SMA +/- 2 SD): position of price inside or outside bands
- 20-period SMA: trend direction and relationship to price (on 4H candles)
- 50-period SMA: trend direction and relationship to price (on 4H candles)
- Market structure: bullish, bearish, or sideways with explanation (based on 4H swing highs/lows)
- Support & resistance: identify at least two major levels for each side (from 4H swing points)
- Price action: trend, momentum, candle behavior, volatility (4H context)

Use the 90-day price summary ONLY to:
- Confirm or contradict short-term trend
- Describe broader market context (e.g., expansion, drawdown, recovery)
- Adjust confidence level and tone of the recommendation

IMPORTANT: Because this is 4H data, interpret signals as swing-trading opportunities over the next several days to a few weeks.

====================================================
DUAL-AUDIENCE REQUIREMENT (NOOBS + ADVANCED)
====================================================
This report must serve BOTH beginners and advanced traders:

1) Beginner-friendly interpretation:
   - Explain what the current structure means in plain language.
   - Mention trader psychology (capitulation, exhaustion, failed bounce, squeeze risk) when applicable.
   - Explain what indicator confluence implies (continuation risk vs reversal risk).
   - Must be 4-6 sentences.

2) Advanced precision:
   - Provide exact numeric indicator outputs and exact S/R, entry/target/stop, and R:R.
   - Keep technical bullet lists concise and exact.

Do NOT inflate the report with filler. Narrative must add meaning beyond repeating indicator values.

====================================================
TRADING SIGNAL REQUIREMENTS
====================================================
You MUST provide:

- Recommendation: BUY, HOLD, or SELL (ONLY these three - no variations)
- Confidence: HIGH, MEDIUM, or LOW
- Entry Price: a specific numerical price level
- Target Price (1st): a realistic target based on 4H structure
- Stop-Loss: a specific value below support (not a range)
- Risk/Reward Ratio: numeric ratio (e.g., 1:2.5)
- Reasoning: 4-5 sentences explaining WHY the signal was chosen based on indicator confluence + 4H structure, with brief 90-day context. Reasoning must include:
  (a) what would invalidate the setup,
  (b) the main risk (trend continuation vs reversal risk).

====================================================
HTML OUTPUT STRUCTURE
====================================================
Output ONLY valid HTML. No markdown. No code fences. No CSS. No backticks.

Structure:

<div class="analysis-container">

  <h2>Market Story (Plain-English)</h2>
  <p>4-6 sentences explaining what is happening, why it matters, and how traders may be positioned. Include brief 90-day context.</p>

  <h2>Technical Summary</h2>
  <p>One compact paragraph (2-4 sentences) summarizing 4H trend, momentum, volatility, and structure.</p>

  <h3>Key Indicators</h3>
  <ul>
    <li><strong>RSI (14):</strong> [value] - [interpretation]</li>
    <li><strong>MACD:</strong> [bullish/bearish + include line, signal, histogram]</li>
    <li><strong>Bollinger Bands:</strong> [price position]</li>
    <li><strong>20-period SMA:</strong> [value] - [direction] - price above/below</li>
    <li><strong>50-period SMA:</strong> [value] - [direction] - price above/below</li>
    <li><strong>Market Structure:</strong> bullish / bearish / sideways with brief explanation</li>
  </ul>

  <h3>Support & Resistance</h3>
  <ul>
    <li><strong>Primary Support:</strong> [price] - [reason]</li>
    <li><strong>Secondary Support:</strong> [price] - [reason]</li>
    <li><strong>Primary Resistance:</strong> [price] - [reason]</li>
    <li><strong>Secondary Resistance:</strong> [price] - [reason]</li>
  </ul>

  <h3>Final Trading Signal</h3>
  <p><strong>Recommendation:</strong> Buy / Hold / Sell</p>
  <p><strong>Confidence:</strong> HIGH / MEDIUM / LOW</p>
  <p><strong>Entry Price:</strong> $[value]</p>
  <p><strong>Target Price (1st):</strong> $[value]</p>
  <p><strong>Stop-Loss:</strong> $[value]</p>
  <p><strong>Risk/Reward Ratio:</strong> 1:[value]</p>
  <p><strong>Reasoning:</strong> 4-5 sentences: confluence + invalidation + main risk + 90-day alignment.</p>

  <h3>JSON Data Export</h3>
  <pre style="white-space:pre-wrap;font-size:0.85em;background:#0a0f1f;padding:12px;border-radius:8px;color:#0f0;">
  {insert ONLY valid JSON - no trailing commas - containing:
   timeframe metadata,
   indicator outputs (including MACD line/signal/histogram),
   support/resistance levels,
   long-term summary values,
   and final signal.
   Do NOT include the full raw OHLC array.}
  </pre>
</div>

====================================================
STYLE GUIDELINES
====================================================
- No Markdown.
- No code blocks.
- No CSS beyond the image tag.
- Do not invent extra fields.
- All numeric values must be exact decimals.

====================================================
GOAL
====================================================
Produce a professional-grade, investor-ready technical analysis in clean HTML with a
justified Buy/Hold/Sell signal based on 4H candles, informed - but not overridden - by
90-day trend context, with both plain-English interpretation and advanced precision.

Cryptocurrency: {$safe_coin_id}

DATA:
{$json_data}
PROMPT;

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
            return self::send_to_openai_responses( $prompt_text, $api_key, $model_name );
        }

        $request_url = 'https://api.openai.com/v1/chat/completions';

        $request_body = wp_json_encode( array(
            'model'       => $model_name,
            'messages'    => array(
                array( 'role' => 'system', 'content' => self::get_system_prompt() ),
                array( 'role' => 'user',   'content' => $prompt_text ),
            ),
            'max_tokens'  => 4096,
            'temperature' => 0.3,
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
            error_log( '[ADAMCA AI] OpenAI request failed: ' . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $error_detail = wp_remote_retrieve_body( $response );
            error_log( '[ADAMCA AI] OpenAI HTTP ' . $status_code . ': ' . $error_detail );
            return new WP_Error( 'adamca_openai_error', 'OpenAI returned HTTP ' . $status_code );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $response_body['choices'][0]['message']['content'] ) ) {
            error_log( '[ADAMCA AI] OpenAI returned empty content' );
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
            'max_output_tokens' => 4096,
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
            error_log( '[ADAMCA AI] OpenAI Responses API request failed: ' . $response->get_error_message() );
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
            error_log( '[ADAMCA AI] OpenAI Responses API HTTP ' . $status_code . ': ' . $error_detail );
            return new WP_Error( 'adamca_openai_error', 'OpenAI returned HTTP ' . $status_code );
        }

        $response_body = json_decode( $raw_body, true );

        self::log_openai_debug( 'Responses parsed JSON', array(
            'json_decode_ok'   => is_array( $response_body ),
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
            error_log( '[ADAMCA AI] OpenAI Responses API returned empty — no text found in output_text or output[].content[].text' );
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
                $joined = trim( implode( "\n", array_filter( $response_body['output_text'], 'is_string' ) ) );
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
                && isset( $output_item['text'] ) && is_string( $output_item['text'] ) ) {
                $text_parts[] = $output_item['text'];
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
                $is_text_type = isset( $content_item['type'] ) && 'output_text' === $content_item['type'];
                $has_text     = isset( $content_item['text'] ) && is_string( $content_item['text'] );

                if ( $is_text_type || $has_text ) {
                    if ( $has_text ) {
                        $text_parts[] = $content_item['text'];
                    }
                }
            }
        }

        return trim( implode( "\n", $text_parts ) );
    }

    /**
     * Log OpenAI-only debug details when explicitly enabled.
     *
     * @param string $label   Log label.
     * @param array  $context Log context payload.
     * @return void
     */
    private static function log_openai_debug( $label, $context ) {
        // Temporary debug logging enabled by default for GPT-5 Responses API troubleshooting.
        // To disable, add: add_filter( 'adamca_openai_safe_debug_mode', '__return_false' );
        $enabled = (bool) apply_filters( 'adamca_openai_safe_debug_mode', true );
        if ( ! $enabled ) {
            return;
        }

        $encoded_context = wp_json_encode( $context );
        error_log( '[ADAMCA AI][OpenAI Debug] ' . $label . ': ' . $encoded_context );
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
            error_log( '[ADAMCA AI] xAI request failed: ' . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $error_detail = wp_remote_retrieve_body( $response );
            error_log( '[ADAMCA AI] xAI HTTP ' . $status_code . ': ' . $error_detail );
            return new WP_Error( 'adamca_xai_error', 'xAI returned HTTP ' . $status_code );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $response_body['choices'][0]['message']['content'] ) ) {
            error_log( '[ADAMCA AI] xAI returned empty content' );
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
            error_log( '[ADAMCA AI] Anthropic request failed: ' . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $error_detail = wp_remote_retrieve_body( $response );
            error_log( '[ADAMCA AI] Anthropic HTTP ' . $status_code . ': ' . $error_detail );
            return new WP_Error( 'adamca_anthropic_error', 'Anthropic returned HTTP ' . $status_code );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $response_body['content'][0]['text'] ) ) {
            error_log( '[ADAMCA AI] Anthropic returned empty content' );
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

        error_log( '[ADAMCA AI] Could not detect recommendation signal from HTML output' );
        return false;
    }
}
