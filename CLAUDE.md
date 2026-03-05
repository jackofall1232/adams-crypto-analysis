# CLAUDE.md — Crypto Nerd Analysis Plugin

> This file is the authoritative reference for building and maintaining the
> **Crypto Nerd Analysis** WordPress plugin. Read this entire file before
> writing any code. It contains the exact API endpoints, data processing logic,
> AI prompt, and output structure required.

---

## Project Overview

**Plugin Name:** Crypto Nerd Analysis
**Slug:** `crypto-nerd-analysis`
**Text Domain:** `crypto-nerd-analysis`
**PHP Minimum:** 7.4
**WordPress Minimum:** 6.0
**License:** GPLv2 or later
**WordPress.org:** Yes — free version submitted to .org

This plugin provides AI-powered cryptocurrency technical analysis via a
`[crypto_analysis]` shortcode. It:

1. Fetches OHLC + market chart data from **CoinGecko** (PHP server-side)
2. Builds a structured technical analysis prompt
3. Calls an **AI API** (user-configurable: OpenAI, xAI/Grok, Anthropic, etc.)
4. Returns fully formatted **HTML analysis** with a BUY/SELL/HOLD signal
5. **Caches the result** for 12 hours (top 10 coins pre-cached daily at 4:30am EST)

There is **no n8n**, no webhook, no external middleware. All logic lives inside the plugin.

---

## Plugin File Structure

```
crypto-nerd-analysis/
├── crypto-nerd-analysis.php              # Main plugin file (headers + bootstrap)
├── uninstall.php                         # Clean up all options + transients on delete
├── readme.txt                            # WordPress.org readme
├── README.md                             # GitHub readme
├── CLAUDE.md                             # This file (git only, never distributed)
├── .gitattributes                        # CLAUDE.md export-ignore
├── includes/
│   ├── class-cna-core.php               # Main class: shortcode, REST API, cron
│   ├── class-cna-coingecko.php          # CoinGecko API calls + data shaping
│   ├── class-cna-ai-client.php          # AI provider HTTP calls + prompt builder
│   ├── class-cna-cache.php              # Transient cache helpers
│   └── class-cna-admin.php             # Settings page
├── assets/
│   ├── css/
│   │   └── crypto-analysis.css          # Frontend styles (enqueued as file)
│   └── js/
│       └── crypto-analysis.js           # Frontend app (enqueued as file)
└── languages/
    └── crypto-nerd-analysis.pot
```

`.gitattributes` must contain:
```
CLAUDE.md export-ignore
```

---

## Settings Page

**Location:** Settings → Crypto Nerd Analysis
**Capability required:** `manage_options`
**Option prefix:** `cna_`

| Option Key | Type | Description | Default |
|---|---|---|---|
| `cna_coingecko_api_key` | password | CoinGecko API key (Pro) — leave blank for free tier | `''` |
| `cna_ai_provider` | select | `openai`, `xai`, `anthropic` | `openai` |
| `cna_ai_api_key` | password | API key for selected AI provider | `''` |
| `cna_ai_model` | text | Model name (e.g. `gpt-4o`, `grok-3`, `claude-opus-4-5`) | `''` |
| `cna_top10_coins` | textarea | CoinGecko IDs, one per line | (see default list below) |
| `cna_top10_cache_ttl` | integer | Cache TTL for top 10 coins (seconds) | `86400` |
| `cna_default_cache_ttl` | integer | Cache TTL for all other coins (seconds) | `43200` |
| `cna_batch_time_est` | time | Time to run pre-market batch (EST) | `04:30` |
| `cna_enable_admin_bar` | checkbox | Show batch status in admin bar | `1` |

All options sanitized on save. API keys stored as-is (sensitive — never echoed in JS or
output). Settings page must show a "Test Connection" button for both CoinGecko and AI
that fires an AJAX request and returns success/failure.

**Default top 10 coins list:**
```
bitcoin
ethereum
binancecoin
solana
ripple
cardano
dogecoin
avalanche-2
chainlink
polkadot
```

---

## Step 1 — CoinGecko Data Fetching (`class-cna-coingecko.php`)

The plugin makes **two sequential API calls** per coin, then shapes the data before
passing it to the AI prompt.

### Call 1 — 30-Day OHLC (4H candles)

```
GET https://api.coingecko.com/api/v3/coins/{coin_id}/ohlc?vs_currency=usd&days=30
```

- Returns 4-hour OHLC candles for the past 30 days (~180 candles)
- Each item: `[timestamp_ms, open, high, low, close]`
- Timestamps are Unix milliseconds — convert to ISO 8601 strings for the prompt

### Call 2 — 90-Day Market Chart

```
GET https://api.coingecko.com/api/v3/coins/{coin_id}/market_chart?vs_currency=usd&days=90
```

- Returns `{ prices: [[ts, price], ...], market_caps: [...], total_volumes: [...] }`
- Use `prices` array only (volume data is excluded from the prompt per analysis rules)

### Data Shaping

Before sending to the AI, shape the raw responses into this structure:

```php
$data = [
    'short_term' => [
        'ohlc' => $ohlc_with_iso_timestamps, // array of [iso_string, o, h, l, c]
    ],
    'long_term' => [
        'weekly_closes_90d'    => $weekly_samples,   // ~12-13 evenly spaced price points
        'price_90d_start'      => $prices[0][1],
        'price_90d_end'        => $prices[count($prices)-1][1],
        'price_90d_change_pct' => round((($end - $start) / $start) * 100, 2),
    ],
];
```

**Weekly samples:** Sample the 90-day price array at evenly spaced intervals to get
~12-13 data points. Use `array_values(array_filter(...))` with a modulo on the index.

### CoinGecko API Key

If `cna_coingecko_api_key` is set, include the header:
```
x-cg-pro-api-key: {key}
```

If blank, use the free public endpoint (no auth header). Free tier rate limits:
~30 req/min. The 3-minute cron chain for the top 10 batch is designed around this.

### Error Handling

Return `WP_Error` on:
- `wp_remote_get()` failure
- HTTP response code !== 200
- Empty or non-array JSON response

Log all errors: `error_log('[CNA CoinGecko] ' . $message)`

---

## Step 2 — AI Prompt Builder (`class-cna-ai-client.php`)

### System Prompt

```
You are a professional cryptocurrency technical analyst specializing in swing trading signals.
```

### User Prompt

The following prompt is stored as a PHP heredoc in `build_prompt( $coin_id, $data )`.
Substitute `{coin_id}` with `sanitize_key($coin_id)` and `{json_data}` with
`wp_json_encode($shaped_data)`. Do not alter the prompt text.

```
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

BUY  -> /assets/images/BUY.png
HOLD -> /assets/images/HOLD.png
SELL -> /assets/images/SELL.png

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

Cryptocurrency: {coin_id}

DATA:
{json_data}
```

---

## Step 3 — AI Provider HTTP Calls (`class-cna-ai-client.php`)

The `CNA_AI_Client` class reads `cna_ai_provider` from options and routes accordingly.
All calls use `wp_remote_post()` with `timeout => 120`.

### OpenAI

```
POST https://api.openai.com/v1/chat/completions
Authorization: Bearer {key}

{
    "model": "{cna_ai_model}",
    "messages": [
        { "role": "system", "content": "You are a professional cryptocurrency technical analyst specializing in swing trading signals." },
        { "role": "user",   "content": "{full_prompt}" }
    ],
    "max_tokens": 4096,
    "temperature": 0.3
}
```

Extract: `$body['choices'][0]['message']['content']`

### xAI / Grok

```
POST https://api.x.ai/v1/chat/completions
Authorization: Bearer {key}

{
    "model": "{cna_ai_model}",
    "messages": [
        { "role": "system", "content": "..." },
        { "role": "user",   "content": "..." }
    ],
    "max_tokens": 4096,
    "temperature": 0.3,
    "stream": false
}
```

**IMPORTANT:** Do NOT pass `reasoning_effort`. Use non-reasoning mode only. The original
n8n implementation confirmed that reasoning mode added 90-120 seconds of latency with no
meaningful quality improvement for this use case.

Extract: `$body['choices'][0]['message']['content']`

### Anthropic / Claude

```
POST https://api.anthropic.com/v1/messages
x-api-key: {key}
anthropic-version: 2023-06-01

{
    "model": "{cna_ai_model}",
    "max_tokens": 4096,
    "system": "You are a professional cryptocurrency technical analyst specializing in swing trading signals.",
    "messages": [
        { "role": "user", "content": "{full_prompt}" }
    ]
}
```

Extract: `$body['content'][0]['text']`

### AI Response Cleanup

Strip any accidental markdown fencing before caching:
```php
$html = preg_replace( '/^```html\s*|^```\s*|```\s*$/m', '', trim( $response ) );
```

---

## Step 4 — Caching (`class-cna-cache.php`)

### Cache Keys

```php
$transient_key = 'cna_analysis_' . sanitize_key( $coin_id );
$meta_key      = 'cna_analysis_' . sanitize_key( $coin_id ) . '_meta';
```

### TTL Logic

```php
$ttl = $this->is_top10( $coin_id )
    ? (int) get_option( 'cna_top10_cache_ttl',   86400 )
    : (int) get_option( 'cna_default_cache_ttl', 43200 );
```

### Cache Metadata (stored in wp_options, autoload = false)

```php
array(
    'coin_id'   => $coin_id,
    'timestamp' => time(),
    'source'    => 'batch_cron' | 'on_demand',
    'ttl'       => $ttl,
    'provider'  => get_option( 'cna_ai_provider' ),
    'model'     => get_option( 'cna_ai_model' ),
)
```

### Flush Methods

- `CNA_Cache::flush( $coin_id )` — deletes transient + meta option for one coin
- `CNA_Cache::flush_all()` — loops top 10 + any cached on-demand coins and flushes all

Exposed in admin settings as buttons (AJAX).

---

## REST API (`class-cna-core.php`)

### POST `/wp-json/crypto-nerd/v1/analyze`

Public. Validates `coin_id` matches `/^[a-z0-9\-]{1,100}$/`.

**Success response:**
```json
{
    "success": true,
    "html": "<div class=\"analysis-container\">...</div>",
    "cached": true,
    "cache_age_minutes": 45,
    "cache_source": "batch_cron",
    "is_top10": true,
    "coin_id": "bitcoin",
    "provider": "xai",
    "model": "grok-3"
}
```

**Error response:** `{ "success": false, "error": "..." }` with HTTP 500.

### GET `/wp-json/crypto-nerd/v1/status`

Admin only (`manage_options`). Cache health for all top 10 coins + next batch time.

### POST `/wp-json/crypto-nerd/v1/flush`

Admin only. Body: `{ "coin_id": "bitcoin" }` or `{ "coin_id": "all" }`.

---

## WP-Cron Batch (`class-cna-core.php`)

```
cna_batch_analysis (daily at cna_batch_time_est, EST)
    └── schedules cna_analyze_single_coin [index=0] immediately
            └── processes coin[0], schedules coin[1] at time() + 180
                    └── ... chains through all top 10 coins, 3 min apart
```

~30 minutes total for 10 coins. Rate-limit safe for CoinGecko free tier.

On each coin: CoinGecko fetch → AI call → store transient + meta.

**Rescheduling on settings save:** If `cna_batch_time_est` changes, deregister the
existing event and register a new one at the updated time.

**Real cron recommendation** (document in readme + settings page):
```
30 9 * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```
(9:30 UTC = 4:30am EST)

---

## Frontend (`assets/js/crypto-analysis.js`)

Pure vanilla JS, no jQuery. Loaded only on pages with the shortcode.

Localized via `wp_localize_script()`:
```php
wp_localize_script( 'cna-frontend', 'CNA', array(
    'apiEndpoint' => esc_url_raw( rest_url( 'crypto-nerd/v1/analyze' ) ),
    'top10Coins'  => array_map( 'sanitize_key', $top10 ),
) );
```

**Modes:** Top 100 / Trending / Custom (all coin list calls are client-side to CoinGecko)

**Flow:**
1. User selects coin + clicks Analyze
2. Fetch TradingView chart symbol via CoinGecko `/coins/{id}/tickers`
3. Render TradingView iframe immediately
4. POST `{ coin_id }` to REST endpoint
5. Inject `data.html` directly into result div (pre-formatted HTML from AI)
6. Show cache dot: blue = cached, green = fresh
7. Top 10 coins marked with ⚡ in dropdown
8. 120s frontend timeout via `AbortController`
9. Dark mode toggle persisted in `localStorage` key `cna_dark_mode`

---

## Shortcode

```
[crypto_analysis]
[crypto_analysis title="My Title" subtitle="My subtitle"]
```

CSS and JS enqueued as versioned files. Version constant: `CRYPTO_NERD_ANALYSIS_VERSION`.

---

## Security Checklist

- API keys never reach the browser (not in JS config, not in HTML source)
- `coin_id` validated with regex before any use
- Status + flush endpoints require `manage_options`
- Settings page uses `check_admin_referer()`
- All shortcode attribute output escaped with `esc_html()`
- AI-generated HTML is trusted output — stored and rendered as-is
- `uninstall.php` removes all `cna_*` options and `cna_analysis_*` transients
- No direct SQL queries

---

## WordPress.org Compliance

- No hardcoded API keys
- All strings use text domain `crypto-nerd-analysis`
- Assets versioned with plugin version constant
- Plugin headers include license fields
- `readme.txt` complete with all required sections
- No `eval()`, no obfuscated code

---

## Error Log Reference

```
[CNA]            # General
[CNA CoinGecko]  # CoinGecko fetch issues
[CNA AI]         # AI provider issues
[CNA Batch]      # Cron batch progress
[CNA Cache]      # Cache hits/misses/flushes
```

---

## What This Plugin Does NOT Do

- No n8n, webhooks, or external middleware
- No AI or CoinGecko calls from the browser — all PHP server-side
- No jQuery
- No custom database tables — transients + wp_options only
- No user data stored, no accounts required
- Does not call any service not listed in this document
