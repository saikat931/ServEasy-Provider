<?php
/**
 * Standalone Database Connection Fallback for Split-Domain Service Provider Portal
 * Used automatically when ../backend/config/db_connection.php is not present (e.g. separate Hostinger domain).
 */
date_default_timezone_set('Asia/Kolkata');

if (!class_exists('Database')) {
    class Database
    {
        private static ?PDO $pdo = null;

        private static function loadEnvFiles(array &$checkedPaths = []): array
        {
            $vars = [];
            $rootDir = dirname(__DIR__);
            $parentDir = dirname($rootDir);
            $envFiles = [
                $rootDir . '/.env',
                $rootDir . '/host.env',
                $rootDir . '/db.env',
                $rootDir . '/.env.local',
                $parentDir . '/.env',
                $parentDir . '/host.env',
                $parentDir . '/db.env',
            ];
            foreach ($envFiles as $envFile) {
                $exists = @file_exists($envFile);
                $checkedPaths[] = basename(dirname($envFile)) . '/' . basename($envFile) . ($exists ? ' (found)' : ' (missing)');
                if (!$exists || !@is_readable($envFile)) {
                    continue;
                }
                $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (!is_array($lines)) {
                    continue;
                }
                foreach ($lines as $line) {
                    $trimmed = ltrim(trim($line), "\xEF\xBB\xBF");
                    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                        continue;
                    }
                    if (str_contains($trimmed, '=')) {
                        [$k, $v] = explode('=', $trimmed, 2);
                        $key = ltrim(trim($k), "\xEF\xBB\xBF");
                        $val = trim($v, " \t\n\r\0\x0B\"'");
                        if ($key !== '' && (!isset($vars[$key]) || $vars[$key] === '')) {
                            $vars[$key] = $val;
                            $_ENV[$key] = $val;
                        }
                    }
                }
            }
            return $vars;
        }

        private static function getVar(array $vars, array $keys, string $default = ''): string
        {
            foreach ($keys as $k) {
                if (!empty($vars[$k])) return (string)$vars[$k];
                if (!empty($_ENV[$k])) return (string)$_ENV[$k];
                if (!empty($_SERVER[$k])) return (string)$_SERVER[$k];
                $g = getenv($k);
                if ($g !== false && $g !== '') return (string)$g;
            }
            return $default;
        }

        public static function connect(): PDO
        {
            if (self::$pdo !== null) {
                return self::$pdo;
            }

            $checkedPaths = [];
            $vars = self::loadEnvFiles($checkedPaths);

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1']) || str_starts_with($host, 'localhost:');

            $dbHost = self::getVar($vars, ['DB_HOST'], $isLocal ? '127.0.0.1' : 'localhost');
            $dbPort = self::getVar($vars, ['DB_PORT'], '3306');
            $dbName = self::getVar($vars, ['DB_DATABASE', 'DB_NAME'], $isLocal ? 'serveasy_v2' : '');
            $dbUser = self::getVar($vars, ['DB_USERNAME', 'DB_USER'], $isLocal ? 'root' : '');
            $dbPass = self::getVar($vars, ['DB_PASSWORD', 'DB_PASS'], '');

            if (empty($dbName) || empty($dbUser)) {
                throw new \PDOException(
                    "Database credentials are not configured for this standalone Provider domain. " .
                    "Please create a .env or host.env file inside public_html (or one folder above public_html) with DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD. " .
                    "[Checked: " . implode(', ', $checkedPaths) . "]"
                );
            }

            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            return self::$pdo;
        }
    }
}
