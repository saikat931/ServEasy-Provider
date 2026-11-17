// ServEasy Direct Backend API Bridge
(function(window) {
    'use strict';
    
    // 1. Use independent portal config.js if loaded
    var config = window.SERVEASY_CONFIG || {};
    var apiBase = config.API_BASE;

    // 2. Safe auto-detection fallback if config.js is not loaded
    if (!apiBase) {
        var path = window.location.pathname;
        var m = path.match(/^(.*?\/)(?:Frontend|frontend-[a-z]+)\//i) || path.match(/^(.*?\/ServEasy-v2)\//i);
        apiBase = (m ? m[1] : '/ServEasy-v2/') + 'backend/api/';
    }

    if (!apiBase.endsWith('/')) {
        apiBase += '/';
    }

    window.SERVEASY_API_BASE = apiBase;

    window.serveasyApiUrl = function(endpoint) {
        endpoint = (endpoint || '').replace(/^\/+/, '');
        endpoint = endpoint.replace(/^(\.\.\/)+/g, '');
        endpoint = endpoint.replace(/^(backend\/)?api\//i, '');
        return window.SERVEASY_API_BASE + endpoint;
    };

    window.serveasyFetch = async function(endpoint, options) {
        options = options || {};
        options.headers = options.headers || {};
        if (!options.headers['Accept']) {
            options.headers['Accept'] = 'application/json';
        }
        var url = window.serveasyApiUrl(endpoint);
        return fetch(url, options);
    };
})(window);
