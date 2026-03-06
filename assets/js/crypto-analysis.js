/**
 * Adams Crypto Analysis — Frontend App
 * Vanilla JS, no jQuery.
 *
 * @package AdamsCryptoAnalysis
 */
(function () {
    'use strict';

    var appContainer   = document.getElementById('adamca-app');
    if (!appContainer) {
        return;
    }

    var coinSelect     = document.getElementById('adamca-coin-select');
    var customInput    = document.getElementById('adamca-custom-input');
    var analyzeButton  = document.getElementById('adamca-analyze-btn');
    var chartArea      = document.getElementById('adamca-chart-area');
    var loadingArea    = document.getElementById('adamca-loading');
    var cacheInfo      = document.getElementById('adamca-cache-info');
    var cacheDot       = document.getElementById('adamca-cache-dot');
    var cacheText      = document.getElementById('adamca-cache-text');
    var resultArea     = document.getElementById('adamca-result');
    var errorArea      = document.getElementById('adamca-error');
    var darkToggle     = document.getElementById('adamca-dark-toggle');
    var modeTabs       = document.querySelectorAll('.adamca-tab');

    var currentMode    = 'top100';
    var coinListData   = [];
    var apiEndpoint    = (typeof ADAMCA !== 'undefined') ? ADAMCA.apiEndpoint : '';
    var topTenCoins    = (typeof ADAMCA !== 'undefined') ? ADAMCA.top10Coins : [];

    // ── Dark Mode ──────────────────────────────────────────────
    function initDarkMode() {
        var savedDark = localStorage.getItem('adamca_dark_mode');
        if (savedDark === 'true') {
            appContainer.classList.add('adamca-dark-mode');
        }
    }

    function toggleDarkMode() {
        var isDarkNow = appContainer.classList.toggle('adamca-dark-mode');
        localStorage.setItem('adamca_dark_mode', isDarkNow ? 'true' : 'false');
    }

    darkToggle.addEventListener('click', toggleDarkMode);
    initDarkMode();

    // ── Mode Tabs ──────────────────────────────────────────────
    modeTabs.forEach(function (tabButton) {
        tabButton.addEventListener('click', function () {
            modeTabs.forEach(function (otherTab) {
                otherTab.classList.remove('active');
            });
            tabButton.classList.add('active');
            currentMode = tabButton.dataset.mode;

            if (currentMode === 'custom') {
                coinSelect.style.display = 'none';
                customInput.style.display = 'block';
            } else {
                coinSelect.style.display = 'block';
                customInput.style.display = 'none';
                loadCoinList();
            }
        });
    });

    // ── Coin List Loading ──────────────────────────────────────
    function loadCoinList() {
        var requestUrl = '';

        if (currentMode === 'top100') {
            requestUrl = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=100&page=1';
        } else if (currentMode === 'trending') {
            requestUrl = 'https://api.coingecko.com/api/v3/search/trending';
        } else {
            return;
        }

        coinSelect.innerHTML = '<option value="">Loading...</option>';

        fetch(requestUrl)
            .then(function (fetchResponse) { return fetchResponse.json(); })
            .then(function (responseData) {
                coinListData = [];

                if (currentMode === 'top100' && Array.isArray(responseData)) {
                    coinListData = responseData.map(function (coinItem) {
                        return { coinId: coinItem.id, coinName: coinItem.name, coinSymbol: coinItem.symbol };
                    });
                } else if (currentMode === 'trending' && responseData.coins) {
                    coinListData = responseData.coins.map(function (coinWrapper) {
                        var coinItem = coinWrapper.item;
                        return { coinId: coinItem.id, coinName: coinItem.name, coinSymbol: coinItem.symbol };
                    });
                }

                populateCoinSelect();
            })
            .catch(function (fetchError) {
                coinSelect.innerHTML = '<option value="">Failed to load coins</option>';
                console.error('[ADAMCA] Coin list fetch error:', fetchError);
            });
    }

    function populateCoinSelect() {
        coinSelect.innerHTML = '<option value="">Select a coin...</option>';

        coinListData.forEach(function (coinEntry) {
            var optionElement = document.createElement('option');
            optionElement.value = coinEntry.coinId;

            var labelText = coinEntry.coinName + ' (' + coinEntry.coinSymbol.toUpperCase() + ')';
            if (topTenCoins.indexOf(coinEntry.coinId) !== -1) {
                labelText = '\u26A1 ' + labelText;
            }

            optionElement.textContent = labelText;
            coinSelect.appendChild(optionElement);
        });
    }

    // ── TradingView Chart ──────────────────────────────────────
    function loadTradingViewChart(coinId) {
        var tickerUrl = 'https://api.coingecko.com/api/v3/coins/' + encodeURIComponent(coinId) + '/tickers';

        fetch(tickerUrl)
            .then(function (fetchResponse) { return fetchResponse.json(); })
            .then(function (responseData) {
                var tickers   = responseData.tickers || [];
                var chartSymbol = findTradingViewSymbol(tickers);

                if (!chartSymbol) {
                    chartArea.style.display = 'none';
                    return;
                }

                var isDarkMode  = appContainer.classList.contains('adamca-dark-mode');
                var themeValue  = isDarkMode ? 'dark' : 'light';
                var widgetUrl   = 'https://s.tradingview.com/widgetembed/?frameElementId=adamca-tv&symbol='
                    + encodeURIComponent(chartSymbol)
                    + '&interval=240&theme=' + themeValue
                    + '&style=1&locale=en&enable_publishing=false&hide_top_toolbar=false&hide_side_toolbar=false&allow_symbol_change=true';

                chartArea.innerHTML = '<iframe src="' + widgetUrl + '" allowtransparency="true" frameborder="0"></iframe>';
                chartArea.style.display = 'block';
            })
            .catch(function () {
                chartArea.style.display = 'none';
            });
    }

    function findTradingViewSymbol(tickersList) {
        // Prefer Binance USDT pair.
        var preferredExchanges = ['binance', 'coinbase', 'kraken', 'okx', 'bybit'];
        var preferredTargets   = ['USDT', 'USD', 'BUSD'];

        for (var exIndex = 0; exIndex < preferredExchanges.length; exIndex++) {
            for (var tgIndex = 0; tgIndex < preferredTargets.length; tgIndex++) {
                for (var tkIndex = 0; tkIndex < tickersList.length; tkIndex++) {
                    var tickerEntry = tickersList[tkIndex];
                    var exchangeId  = (tickerEntry.market && tickerEntry.market.identifier) || '';
                    var targetCoin  = tickerEntry.target || '';

                    if (exchangeId.toLowerCase() === preferredExchanges[exIndex] && targetCoin === preferredTargets[tgIndex]) {
                        return exchangeId.toUpperCase() + ':' + tickerEntry.base + targetCoin;
                    }
                }
            }
        }

        // Fallback: first USDT pair from any exchange.
        for (var fallbackIdx = 0; fallbackIdx < tickersList.length; fallbackIdx++) {
            if (tickersList[fallbackIdx].target === 'USDT' && tickersList[fallbackIdx].market) {
                var fallbackExchange = tickersList[fallbackIdx].market.identifier || '';
                return fallbackExchange.toUpperCase() + ':' + tickersList[fallbackIdx].base + 'USDT';
            }
        }

        return null;
    }

    // ── Analysis Request ───────────────────────────────────────
    function runAnalysis() {
        var selectedCoinId = '';

        if (currentMode === 'custom') {
            selectedCoinId = customInput.value.trim().toLowerCase();
        } else {
            selectedCoinId = coinSelect.value;
        }

        if (!selectedCoinId) {
            showError('Please select or enter a coin ID.');
            return;
        }

        // Reset UI.
        hideError();
        resultArea.innerHTML = '';
        cacheInfo.style.display = 'none';
        loadingArea.style.display = 'block';
        analyzeButton.disabled = true;

        // Load TradingView chart immediately.
        loadTradingViewChart(selectedCoinId);

        // POST to analysis endpoint with 120s timeout.
        var abortController = new AbortController();
        var timeoutHandle   = setTimeout(function () {
            abortController.abort();
        }, 120000);

        fetch(apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ coin_id: selectedCoinId }),
            signal: abortController.signal
        })
        .then(function (fetchResponse) { return fetchResponse.json(); })
        .then(function (responseData) {
            clearTimeout(timeoutHandle);
            loadingArea.style.display = 'none';
            analyzeButton.disabled = false;

            if (responseData.success) {
                resultArea.innerHTML = responseData.html;
                showCacheIndicator(responseData.cached, responseData.cache_age_minutes);
            } else {
                showError(responseData.error || 'Analysis failed. Please try again.');
            }
        })
        .catch(function (fetchError) {
            clearTimeout(timeoutHandle);
            loadingArea.style.display = 'none';
            analyzeButton.disabled = false;

            if (fetchError.name === 'AbortError') {
                showError('Request timed out after 120 seconds. The AI provider may be overloaded. Please try again.');
            } else {
                showError('Network error: ' + fetchError.message);
            }
        });
    }

    // ── Cache Indicator ────────────────────────────────────────
    function showCacheIndicator(isCached, ageMinutes) {
        cacheInfo.style.display = 'flex';

        if (isCached) {
            cacheDot.className = 'adamca-cache-dot cached';
            cacheText.textContent = 'Cached analysis (' + ageMinutes + ' min ago)';
        } else {
            cacheDot.className = 'adamca-cache-dot fresh';
            cacheText.textContent = 'Fresh analysis (just generated)';
        }
    }

    // ── Error Handling ─────────────────────────────────────────
    function showError(errorMessage) {
        errorArea.textContent = errorMessage;
        errorArea.style.display = 'block';
    }

    function hideError() {
        errorArea.textContent = '';
        errorArea.style.display = 'none';
    }

    // ── Event Bindings ─────────────────────────────────────────
    analyzeButton.addEventListener('click', runAnalysis);

    // Load top 100 on init.
    loadCoinList();

})();
