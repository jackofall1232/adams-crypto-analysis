
# Adam's Crypto Analysis

AI-powered cryptocurrency technical analysis for WordPress.

Adam's Crypto Analysis allows you to add professional-grade cryptocurrency technical analysis directly to your WordPress website using a simple shortcode. The plugin fetches market data from CoinGecko and generates a structured technical analysis report using AI.

Visitors can view charts, indicators, and a clear **BUY / HOLD / SELL** signal for any supported cryptocurrency.

---

# Features

### AI-Powered Technical Analysis
Generate full cryptocurrency market reports using modern AI models.

Includes analysis of:

- RSI (Relative Strength Index)
- MACD
- Bollinger Bands
- 20 and 50 period Moving Averages
- Market Structure
- Support and Resistance Levels
- Risk / Reward setups
- BUY / HOLD / SELL trading signals

---

### Interactive Crypto Selection

Users can:

- Select from the **Top 100 cryptocurrencies**
- Browse **Trending coins**
- Enter any **CoinGecko coin ID**

---

### TradingView Chart Integration

Each analysis includes an embedded TradingView chart so users can visually inspect the market before reading the analysis.

---

### AI Provider Flexibility

The plugin works with multiple AI providers. You choose which one to use.

Supported providers:

- OpenAI (GPT-4o, GPT-5, etc.)
- xAI / Grok
- Anthropic / Claude

You simply enter your API key in the plugin settings.

---

### On-Demand Smart Caching

Analysis results are cached to reduce API usage and improve performance.

Default cache duration:12H

Cache duration can be adjusted in plugin settings.

---

### Light / Dark Mode

Users can toggle between light and dark interface modes.

---

# How It Works

1. The plugin fetches cryptocurrency market data from **CoinGecko**
2. 30-day OHLC candles and 90-day trend data are collected
3. Data is sent to your configured **AI provider**
4. The AI generates a structured technical analysis report
5. The result is displayed on your page along with a TradingView chart
6. The result is cached to minimize API usage

All processing happens **inside your WordPress site**.

No external middleware is required.

---

# Requirements

- WordPress **6.0+**
- PHP **7.4+**
- API key for one supported AI provider

Optional:

- CoinGecko Pro API key (for higher rate limits)

---

# Installation

1. Upload the plugin folder to:

2. /wp-content/plugins/adams-crypto-analysis

3. 2. Activate the plugin from the WordPress admin panel

3. Go to:Settings → Adams Crypto Analysis

   4. Enter your AI provider API key

5. Select your preferred AI model

6. Save settings

---

# Shortcode Usage

Add the shortcode to any page or post:[crypto_analysis]

This will display the cryptocurrency analysis interface.

Users can select a cryptocurrency and generate a full AI-powered analysis report.

---

# Settings

The plugin includes a settings panel where you can configure:

- AI provider
- AI model
- API key
- Cache duration
- CoinGecko API key (optional)

---

# Free vs Pro

The free version includes:

- Full AI analysis functionality
- TradingView chart integration
- Multiple AI provider support
- Cryptocurrency selection interface

The free version displays **Ask Adam branding** within the analysis output.

The premium version adds:

- Custom branding / white labeling
- Additional configuration options
- Future advanced features

Learn more:

https://askadamit.com

---

# Disclaimer

This plugin generates AI-assisted technical analysis for educational and informational purposes only.

Cryptocurrency trading involves risk. Always conduct your own research before making investment decisions.

This plugin does **not provide financial advice**.

---

# Roadmap

Planned future improvements include:

- Additional indicators
- Advanced signal configuration
- Multiple timeframe analysis
- Portfolio analysis tools
- Custom report templates

---

# License

GPLv2 or later

https://www.gnu.org/licenses/gpl-2.0.html

---

# Author

Created by **Ask Adam**

https://askadamit.com

