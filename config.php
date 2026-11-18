<?php
/**
 * ==============================================================================
 * ServEasy Portal Configuration (Independent per Portal)
 * Portal: Service Provider
 * ==============================================================================
 * 
 * INSTRUCTIONS FOR HOSTING:
 * 1. On Localhost: It connects to local backend automatically.
 * 2. On Live Hosting: If backend is hosted on a separate domain,
 *    set $liveBackendApiUrl below (e.g. 'https://api.yourdomain.com').
 */

namespace ServEasy\Provider;

class PortalConfig {
    // Live Central Backend API Domain
    public static ?string $liveBackendApiUrl = 'https://beige-curlew-610555.hostingersite.com/api'; 

    public static function getApiUrl(): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1']) || str_starts_with($host, 'localhost:');

        if (!$isLocal) {
            $envFile = file_exists(__DIR__ . '/host.env') ? __DIR__ . '/host.env' : (file_exists(__DIR__ . '/.env') ? __DIR__ . '/.env' : null);
            if ($envFile) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), '#')) continue;
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                    }
                }
            }

            if (!empty($_ENV['BACKEND_API_URL'])) {
                return rtrim($_ENV['BACKEND_API_URL'], '/');
            }

            if (self::$liveBackendApiUrl !== null) {
                return rtrim(self::$liveBackendApiUrl, '/');
            }
        }

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

        return "{$scheme}://{$host}{$base}";
    }

    public static function callApi(string $endpoint, string $method = 'GET', $data = null): ?array {
        $endpoint = ltrim($endpoint, '/');
        $endpoint = preg_replace('/^(backend\/)?api\//i', '', $endpoint);
        $url = self::getApiUrl() . '/' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: ServEasy-Provider/1.0'
        ]);

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
}
