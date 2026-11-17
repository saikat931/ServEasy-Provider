/**
 * ==============================================================================
 * ServEasy Portal Configuration (Independent per Portal)
 * Portal: Service Provider
 * ==============================================================================
 * 
 * INSTRUCTIONS FOR HOSTING:
 * 1. On Localhost: You don't need to change anything! It connects to local backend automatically.
 * 2. On Live Hosting: If your backend is hosted on a separate domain/subdomain,
 *    set LIVE_BACKEND_API_URL below (e.g. 'https://api.yourdomain.com' or 'https://backend.hostingersite.com').
 *    If backend is inside the same site under /backend/api, leave it empty for auto-detection.
 */
(function(window) {
    'use strict';

    // Live Central Backend API Domain (reads window.SERVEASY_API_BASE from host.env if defined, else defaults to URL below)
    var LIVE_BACKEND_API_URL = window.SERVEASY_API_BASE || 'https://beige-curlew-610555.hostingersite.com/api'; 

    // Auto-detection logic for Localhost vs Live Hosting
    var isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    var path = window.location.pathname;
    var m = path.match(/^(.*?\/)(?:Frontend|frontend-[a-z]+)\//i) || path.match(/^(.*?\/ServEasy-v2)\//i);
    var defaultLocal = (m ? m[1] : '/ServEasy-v2/') + 'backend/api/';

    var resolvedApiBase = isLocal 
        ? defaultLocal 
        : (LIVE_BACKEND_API_URL || (window.location.origin + '/backend/api/'));

    if (!resolvedApiBase.endsWith('/')) {
        resolvedApiBase += '/';
    }

    window.SERVEASY_CONFIG = {
        PORTAL_NAME: 'Service Provider',
        API_BASE: resolvedApiBase
    };

    window.SERVEASY_API_BASE = window.SERVEASY_CONFIG.API_BASE;

    window.serveasyApiUrl = function(endpoint) {
        endpoint = (endpoint || '').replace(/^\/+/, '');
        endpoint = endpoint.replace(/^(\.\.\/)+/g, '');
        endpoint = endpoint.replace(/^(backend\/)?api\//i, '');
        return window.SERVEASY_CONFIG.API_BASE + endpoint;
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
