=== Adams Crypto Analysis ===
Contributors: jackofall1232
Tags: cryptocurrency, bitcoin, technical analysis, AI, trading
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered cryptocurrency technical analysis with BUY/SELL/HOLD signals via a simple shortcode.

== Description ==

Adams Crypto Analysis provides professional-grade AI-powered technical analysis for any cryptocurrency available on CoinGecko. Simply add the `[crypto_analysis]` shortcode to any page or post and your visitors can:

* Select from the Top 100 coins by market cap, trending coins, or enter any CoinGecko coin ID
* View a TradingView chart for the selected cryptocurrency
* Receive a complete technical analysis with RSI, MACD, Bollinger Bands, SMA, support/resistance levels, and a justified BUY/SELL/HOLD recommendation
* Toggle between light and dark mode

**How it works:**

1. The plugin fetches 30-day OHLC candles and 90-day price history from CoinGecko (server-side)
2. It sends the data to your configured AI provider with a detailed technical analysis prompt
3. The AI returns a structured HTML report with indicators, signals, and a trading recommendation
4. Results are cached on-demand (default 12 hours) to minimize API usage

**Supported AI Providers:**

* OpenAI (GPT-4o, etc.)
* xAI / Grok
* Anthropic / Claude

**No external middleware required.** All logic runs inside your WordPress installation. No n8n, no webhooks, no external servers.

== Installation ==

1. Upload the `adams-crypto-analysis` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Settings → Adams Crypto Analysis
4. Enter your AI provider API key and select your preferred model
5. (Optional) Enter a CoinGecko Pro API key for higher rate limits
6. Add `[crypto_analysis]` to any page or post

== Frequently Asked Questions ==

= Do I need a CoinGecko API key? =

No. The plugin works with the free CoinGecko API tier. A Pro key is only needed if you expect high traffic and need higher rate limits.

= Which AI provider should I use? =

All three providers (OpenAI, xAI/Grok, Anthropic) work well. Choose based on your preference and existing API keys.

= How long does an analysis take? =

The first analysis for a coin typically takes 15-60 seconds depending on the AI provider. Subsequent requests for the same coin are served from cache (default 12 hours).

= Can I customize the cache duration? =

Yes. Go to Settings → Adams Crypto Analysis and adjust the Cache Expiry value (in seconds). Minimum is 3600 (1 hour).

= Is this financial advice? =

No. This plugin generates AI-powered technical analysis for educational and informational purposes only. Always do your own research before making investment decisions.

== Changelog ==

= 1.0.0 =
* Initial release
* Support for OpenAI, xAI/Grok, and Anthropic AI providers
* CoinGecko data fetching with 30-day OHLC and 90-day market chart
* On-demand caching with configurable TTL
* TradingView chart integration
* Dark mode toggle
* Top 100, Trending, and Custom coin selection modes
* Admin settings page with test connection buttons and cache management

== Upgrade Notice ==

= 1.0.0 =
Initial release.
