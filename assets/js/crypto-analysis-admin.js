(function() {
    'use strict';

    /* --- Model dropdown sync with provider --- */
    var modelsByProvider = {
        openai:    ['gpt-5', 'gpt-5-mini', 'gpt-4o', 'gpt-4o-mini'],
        xai:       ['grok-4-fast-non-reasoning', 'grok-4', 'grok-3'],
        anthropic: ['claude-opus-4-5', 'claude-sonnet-4-5']
    };
    var providerSelect = document.querySelector('select[name="adamca_ai_provider"]');
    var modelSelect = document.getElementById('adamca-ai-model-select');
    var savedModel = ( typeof adamcaAdmin !== 'undefined' && adamcaAdmin.savedModel ) ? adamcaAdmin.savedModel : '';

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
        if ( typeof adamcaAdmin === 'undefined' ) return;
        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', adamcaAdmin.nonce);
        if (extraData) {
            Object.keys(extraData).forEach(function(dataKey) {
                formData.append(dataKey, extraData[dataKey]);
            });
        }
        resultElement.textContent = 'Working...';
        fetch(adamcaAdmin.ajaxUrl, {
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
