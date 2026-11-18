// ServEasy Direct Backend API Bridge (Hostinger Multi-Domain Edition - Provider Portal)
(function(window) {
    'use strict';

    var isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    var LIVE_API = 'https://beige-curlew-610555.hostingersite.com/api/';

    var config = window.SERVEASY_CONFIG || {};
    var apiBase = config.API_BASE || window.SERVEASY_API_BASE || '';

    if (!isLocal && (!apiBase || apiBase.indexOf('ServEasy-v2') !== -1 || apiBase.indexOf(window.location.origin) === 0)) {
        apiBase = LIVE_API;
    }

    if (!apiBase) {
        var path = window.location.pathname;
        var m = path.match(/^(.*?\/)(?:Frontend|frontend-[a-z]+)\//i) || path.match(/^(.*?\/ServEasy-v2)\//i);
        apiBase = (m ? m[1] : '/ServEasy-v2/') + 'backend/api/';
    }

    if (!apiBase.endsWith('/')) {
        apiBase += '/';
    }

    window.SERVEASY_CONFIG = window.SERVEASY_CONFIG || { PORTAL_NAME: 'Service Provider' };
    window.SERVEASY_CONFIG.API_BASE = apiBase;
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
        try {
            var token = localStorage.getItem('provider_access_token');
            if (token && !options.headers['Authorization']) {
                options.headers['Authorization'] = 'Bearer ' + token;
            }
        } catch (e) {}
        var url = window.serveasyApiUrl(endpoint);
        return fetch(url, options);
    };
})(window);
