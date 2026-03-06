=== Adams Crypto Analysis ===
Contributors: jackofall1232
Tags: cryptocurrency, bitcoin, technical analysis, trading, ai
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Plugin URI: https://askadamit.com

AI-powered cryptocurrency technical analysis for WordPress with BUY / HOLD / SELL trading signals using a simple shortcode.

== Description ==

Adam's Crypto Analysis adds AI-powered cryptocurrency technical analysis to WordPress using a simple shortcode. The plugin fetches market data from CoinGecko and generates a professional technical analysis report including RSI, MACD, Bollinger Bands, support and resistance levels, market structure, and a clear BUY / HOLD / SELL recommendation.

Simply add the `[crypto_analysis]` shortcode to any page or post and your visitors can analyze cryptocurrencies directly on your website.

Visitors can:

* Select from the Top 100 cryptocurrencies by market cap
* View trending cryptocurrencies
* Enter any CoinGecko coin ID
* View an interactive TradingView chart
* Receive a complete AI-generated technical analysis report
* See indicators including RSI, MACD, Bollinger Bands, and moving averages
* Review support and resistance levels and market structure
* Toggle between light and dark mode

**How it works**

1. The plugin fetches 30-day OHLC candle data and 90-day price history from CoinGecko (server-side).
2. The data is sent to your configured AI provider with a detailed technical analysis prompt.
3. The AI returns a structured HTML report including indicators, trading signals, and market interpretation.
4. Results are cached (default 12 hours) to minimize API usage and improve performance.

**Supported AI Providers**

* OpenAI (GPT-4o, GPT-5, and compatible models)
* xAI / Grok
* Anthropic / Claude

No external middleware is required. All analysis runs directly within your WordPress installation.

Documentation and the premium version are available at:
https://askadamit.com

The free version displays Ask Adam branding within the analysis output. A premium version is available that allows custom branding and additional configuration options.

**Disclaimer**

This plugin generates AI-assisted technical analysis and trading signals for informational and educational purposes only. It should not be considered financial or investment advice. Always conduct your own research before making investment decisions.

== Installation ==

1. Upload the `adams-crypto-analysis` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to **Settings → Adams Crypto Analysis**
4. Enter your AI provider API key and select your preferred model
5. (Optional) Enter a CoinGecko Pro API key for higher rate limits
6. Add the shortcode `[crypto_analysis]` to any page or post

Example shortcode:

[crypto_analysis]

This will render the interactive cryptocurrency analysis interface where visitors can select a coin and generate a technical analysis report.

== Frequently Asked Questions ==

= Do I need a CoinGecko API key? =

No. The plugin works with the free CoinGecko API tier. A Pro API key is only required if you expect high traffic and need higher rate limits.

= Which AI provider should I use? =

OpenAI, xAI/Grok, and Anthropic/Claude are all supported. Choose whichever provider you prefer or already have an API key for.

= How long does an analysis take? =

The first analysis for a cryptocurrency typically takes 15–60 seconds depending on the AI provider. Subsequent requests for the same coin are served from cache (default 12 hours).

= Can I customize the cache duration? =

Yes. Go to **Settings → Adams Crypto Analysis** and adjust the Cache Expiry value (in seconds). The minimum allowed value is 3600 seconds (1 hour).

= Is this financial advice? =

No. This plugin generates AI-powered technical analysis for informational purposes only. Always do your own research before making investment decisions.

== Screenshots ==

1. Cryptocurrency selection interface
2. AI-generated technical analysis report
3. Admin settings panel

== Changelog ==

= 1.0.0 =
* Initial release
* Support for OpenAI, xAI/Grok, and Anthropic AI providers
* CoinGecko data fetching with 30-day OHLC candles and 90-day market history
* On-demand caching with configurable TTL
* TradingView chart integration
* Dark mode toggle
* Top 100, Trending, and Custom coin selection modes
* Admin settings page with connection testing and cache management tools

== Upgrade Notice ==

= 1.0.0 =
Initial release of Adam's Crypto Analysis.
