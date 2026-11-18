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

        private static function loadEnvFiles(): void
        {
            $rootDir = dirname(__DIR__);
            $envFiles = [$rootDir . '/.env', $rootDir . '/db.env', $rootDir . '/host.env'];
            foreach ($envFiles as $envFile) {
                if (!file_exists($envFile)) {
                    continue;
                }
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                        continue;
                    }
                    if (str_contains($trimmed, '=')) {
                        [$k, $v] = explode('=', $trimmed, 2);
                        $key = trim($k);
                        $val = trim($v, " \t\n\r\0\x0B\"'");
                        if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
                            $_ENV[$key] = $val;
                        }
                    }
                }
            }
        }

        public static function connect(): PDO
        {
            if (self::$pdo !== null) {
                return self::$pdo;
            }

            self::loadEnvFiles();

            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1']) || str_starts_with($host, 'localhost:');

            $dbHost = $_ENV['DB_HOST'] ?? ($isLocal ? '127.0.0.1' : 'localhost');
            $dbPort = $_ENV['DB_PORT'] ?? '3306';
            $dbName = $_ENV['DB_DATABASE'] ?? ($isLocal ? 'serveasy_v2' : '');
            $dbUser = $_ENV['DB_USERNAME'] ?? ($isLocal ? 'root' : '');
            $dbPass = $_ENV['DB_PASSWORD'] ?? '';

            if (empty($dbName) || empty($dbUser)) {
                throw new \PDOException(
                    "Database credentials are not configured for this standalone Provider domain. " .
                    "Please create a .env file inside public_html with DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD."
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
