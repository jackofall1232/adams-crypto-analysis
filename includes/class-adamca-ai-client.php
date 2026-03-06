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
        $safe_coin_id = sanitize_key( $coin_id );
        $json_data    = wp_json_encode( $shaped_data );

        $prompt_text = <<<PROMPT
Analyze this CoinGecko cryptocurrency data (JSON below).

You are a professional cryptocurrency technical analyst specializing in swing trading signals.

You will receive market data from CoinGecko structured into two sections:

1) short_term.ohlc → 4-HOUR OHLC candles (timeframe = 4h), typically ~30 days of history (~180 candles)
2) long_term → a 90-day price summary containing:
   - weekly_closes_90d (approximately 12-13 evenly spaced price points)
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
SIGNAL IMAGE REQUIREMENTS
====================================================
Select image based on recommendation:

BUY  -> /assets/images/BUY.jpg
HOLD -> /assets/images/HOLD.jpg
SELL -> /assets/images/SELL.jpg

Place the image at the VERY TOP of the output:

<img src="URL" alt="Buy/Hold/Sell" style="max-width:180px;border-radius:12px;margin-bottom:20px;">

====================================================
HTML OUTPUT STRUCTURE
====================================================
Output ONLY valid HTML. No markdown. No code fences. No CSS. No backticks.

Structure:

<div class="analysis-container">
  <img src="..." alt="..." style="max-width:180px;border-radius:12px;margin-bottom:20px;">

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
     * Send prompt to xAI / Grok.
     *
     * @param string $prompt_text The full user prompt.
     * @return string|WP_Error Response text or WP_Error.
     */
    private static function send_to_grok_xai( $prompt_text ) {
        $api_key     = get_option( 'adamca_ai_api_key', '' );
        $model_name  = get_option( 'adamca_ai_model', 'grok-3' );
        $request_url = 'https://api.x.ai/v1/chat/completions';

        $request_body = wp_json_encode( array(
            'model'       => $model_name,
            'messages'    => array(
                array( 'role' => 'system', 'content' => self::get_system_prompt() ),
                array( 'role' => 'user',   'content' => $prompt_text ),
            ),
            'max_tokens'  => 4096,
            'temperature' => 0.3,
            'stream'      => false,
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
     * Strip accidental markdown fencing from AI response.
     *
     * @param string $raw_response Raw response text from AI.
     * @return string Cleaned HTML.
     */
    public static function clean_response( $raw_response ) {
        $html_output = preg_replace( '/^```html\s*|^```\s*|```\s*$/m', '', trim( $raw_response ) );
        return $html_output;
    }
}
