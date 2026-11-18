<?php
/**
 * ServEasy Frontend-to-Backend Direct API Bridge
 * Enables direct communication between all Frontend Portals (Web, Provider, Admin, Super Admin)
 * and the Central Backend Engine (backend/api), respecting each portal's independent config.php.
 */

namespace Frontend;

class ApiBridge {
    private static ?string $cachedApiUrl = null;

    /**
     * Resolves the Central API Base URL directly to the project's backend/api
     */
    public static function getApiBaseUrl(): string {
        if (self::$cachedApiUrl !== null) {
            return self::$cachedApiUrl;
        }

        // Auto-load portal's independent config.php if not already loaded
        $portalConfig = dirname(__DIR__) . '/config.php';
        if (file_exists($portalConfig)) {
            require_once $portalConfig;
        }

        // 1. Check if portal's independent config.php is set
        $possibleClasses = [
            '\ServEasy\Web\PortalConfig',
            '\ServEasy\Provider\PortalConfig',
            '\ServEasy\Admin\PortalConfig',
            '\ServEasy\SuperAdmin\PortalConfig',
            '\PortalConfig'
        ];
        foreach ($possibleClasses as $cls) {
            if (class_exists($cls)) {
                if (method_exists($cls, 'getApiUrl')) {
                    self::$cachedApiUrl = rtrim($cls::getApiUrl(), '/');
                    return self::$cachedApiUrl;
                }
                if (!empty($cls::$liveBackendApiUrl)) {
                    self::$cachedApiUrl = rtrim($cls::$liveBackendApiUrl, '/');
                    return self::$cachedApiUrl;
                }
            }
        }

        // 2. Direct environment variable override if present
        if (!empty($_ENV['API_BASE_URL']) || !empty($_ENV['BACKEND_API_URL'])) {
            self::$cachedApiUrl = rtrim($_ENV['API_BASE_URL'] ?? $_ENV['BACKEND_API_URL'], '/');
            return self::$cachedApiUrl;
        }

        // 3. Auto-detect relative to current server and path
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#^(.*?)/(?:frontend-[a-z]+|Frontend/[^/]+)#i', $uri, $m)) {
            $base = $m[1] . '/backend/api';
        } elseif (preg_match('#^(.*?/ServEasy-v2)#i', $uri, $m)) {
            $base = $m[1] . '/backend/api';
        } else {
            $base = '/ServEasy-v2/backend/api';
        }

        self::$cachedApiUrl = "{$scheme}://{$host}{$base}";
        return self::$cachedApiUrl;
    }

    /**
     * Call backend API directly via cURL with resilient timeout
     */
    public static function callApi(string $endpoint, string $method = 'GET', $data = null): ?array {
        $endpoint = ltrim($endpoint, '/');
        $endpoint = preg_replace('/^(backend\/)?api\//i', '', $endpoint);
        $url = self::getApiBaseUrl() . '/' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = [
            'Accept: application/json',
            'User-Agent: ServEasy-Frontend-Bridge/1.0'
        ];
        if (!empty($_SESSION['access_token'])) {
            $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
            $headers[] = 'X-Auth-Token: ' . $_SESSION['access_token'];
        }
        if (!empty($_SESSION['dealer']['id'])) {
            $headers[] = 'X-Provider-Id: ' . (int)$_SESSION['dealer']['id'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode >= 200 && $httpCode < 300) {
            $json = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        return null;
    }

    /**
     * Transparently proxy an AJAX request (GET, POST, or multipart $_FILES) to the Central Backend API
     */
    public static function proxyRequest(string $endpoint): array {
        $endpoint = ltrim($endpoint, '/');
        $endpoint = preg_replace('/^(backend\/)?api\//i', '', $endpoint);
        $url = self::getApiBaseUrl() . '/' . $endpoint;

        if (!empty($_SERVER['QUERY_STRING'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . $_SERVER['QUERY_STRING'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $headers = [
            'Accept: application/json',
            'User-Agent: ServEasy-Provider-Proxy/1.0'
        ];
        if (!empty($_SESSION['access_token'])) {
            $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
            $headers[] = 'X-Auth-Token: ' . $_SESSION['access_token'];
        }
        if (!empty($_SESSION['dealer']['id'])) {
            $headers[] = 'X-Provider-Id: ' . (int)$_SESSION['dealer']['id'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($_FILES)) {
                $postFields = [];
                $flatten = function ($arr, $prefix = '') use (&$flatten, &$postFields) {
                    foreach ($arr as $k => $v) {
                        $key = $prefix === '' ? $k : "{$prefix}[{$k}]";
                        if (is_array($v)) {
                            $flatten($v, $key);
                        } else {
                            $postFields[$key] = $v;
                        }
                    }
                };
                $flatten($_POST);
                foreach ($_FILES as $fieldName => $fileInfo) {
                    if (is_array($fileInfo['tmp_name'])) {
                        foreach ($fileInfo['tmp_name'] as $idx => $tmpPath) {
                            if (!empty($tmpPath) && is_uploaded_file($tmpPath)) {
                                $postFields["{$fieldName}[{$idx}]"] = new \CURLFile(
                                    $tmpPath,
                                    $fileInfo['type'][$idx] ?? 'application/octet-stream',
                                    $fileInfo['name'][$idx] ?? 'upload'
                                );
                            }
                        }
                    } elseif (!empty($fileInfo['tmp_name']) && is_uploaded_file($fileInfo['tmp_name'])) {
                        $postFields[$fieldName] = new \CURLFile(
                            $fileInfo['tmp_name'],
                            $fileInfo['type'] ?? 'application/octet-stream',
                            $fileInfo['name'] ?? 'upload'
                        );
                    }
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
            }
        }

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
        $err  = curl_error($ch);
        curl_close($ch);

        return [
            'http_code' => $code,
            'body'      => $body !== false ? $body : json_encode(['status' => 'error', 'message' => 'Backend connection failed: ' . $err])
        ];
    }

    /**
     * Get OTP Configuration directly with multi-layer fallback
     */
    public static function getOtpConfig(): array {
        $default = [
            'mode' => 'both',
            'label' => 'Email or Phone Number',
            'placeholder' => 'Please enter your phone number or email',
            'validation_message' => 'Please enter a valid email or 10-digit mobile number.',
            'input_type' => 'text'
        ];

        // 1. Try local PHP class if backend is on local filesystem
        $possiblePaths = [
            __DIR__ . '/../../backend/app/services/OtpConfigService.php',
            __DIR__ . '/../../../backend/app/services/OtpConfigService.php',
            __DIR__ . '/../backend/app/services/OtpConfigService.php',
            __DIR__ . '/backend/app/services/OtpConfigService.php'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                try {
                    require_once $path;
                    if (class_exists('App\Services\OtpConfigService')) {
                        return \App\Services\OtpConfigService::getConfig();
                    }
                } catch (\Throwable $t) {}
            }
        }

        // 2. Query Central Backend API
        try {
            $apiRes = self::callApi('platform-settings.php?action=get_otp_config');
            if (!empty($apiRes['data']) && is_array($apiRes['data'])) {
                return array_merge($default, $apiRes['data']);
            }
        } catch (\Throwable $t) {}

        return $default;
    }

    /**
     * Safely load bootstrap.php if it exists
     */
    public static function loadBootstrap(): bool {
        $possiblePaths = [
            __DIR__ . '/../../backend/api/bootstrap.php',
            __DIR__ . '/../../../backend/api/bootstrap.php',
            __DIR__ . '/../backend/api/bootstrap.php',
            __DIR__ . '/backend/api/bootstrap.php'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                try {
                    require_once $path;
                    return true;
                } catch (\Throwable $t) {}
            }
        }
        return false;
    }
}
