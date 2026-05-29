<?php
declare(strict_types=1);

if (!defined('TRANSLINK_CONFIG_LOADED')) {
    define('TRANSLINK_CONFIG_LOADED', true);

    date_default_timezone_set('UTC');

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        session_start();
    }

    function cfgEnv(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string)$value;
    }

    function cfgEnvBool(string $key, bool $default = false): bool
    {
        $raw = cfgEnv($key, null);
        if ($raw === null) {
            return $default;
        }
        $normalized = strtolower(trim($raw));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    function cfgEnvInt(string $key, int $default): int
    {
        $raw = cfgEnv($key, null);
        if ($raw === null) {
            return $default;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        return $value === false ? $default : (int)$value;
    }

    function cfgList(string $key, array $default): array
    {
        $raw = cfgEnv($key, null);
        if ($raw === null) {
            return $default;
        }
        $parts = array_filter(array_map('trim', explode(',', $raw)), static function ($v) {
            return $v !== '';
        });
        $parts = array_values(array_unique(array_map('strtolower', $parts)));
        return empty($parts) ? $default : $parts;
    }

    $driver = strtolower((string)cfgEnv('DB_DRIVER', 'mysql'));
    $host = cfgEnv('DB_HOST', '127.0.0.1');
    $port = cfgEnvInt('DB_PORT', $driver === 'pgsql' ? 5432 : 3306);
    $name = cfgEnv('DB_NAME', 'translink_gps');
    $user = cfgEnv('DB_USER', $driver === 'pgsql' ? 'postgres' : 'root');
    $pass = cfgEnv('DB_PASS', '');

    $databaseUrl = cfgEnv('DATABASE_URL', null);
    if ($databaseUrl !== null) {
        $parts = parse_url($databaseUrl);
        if (is_array($parts)) {
            $scheme = strtolower((string)($parts['scheme'] ?? ''));
            if ($scheme === 'postgres' || $scheme === 'postgresql' || $scheme === 'pgsql') {
                $driver = 'pgsql';
            } elseif ($scheme === 'mysql') {
                $driver = 'mysql';
            }

            if (!empty($parts['host'])) {
                $host = (string)$parts['host'];
            }
            if (!empty($parts['port'])) {
                $port = (int)$parts['port'];
            }
            if (isset($parts['path']) && $parts['path'] !== '') {
                $name = ltrim((string)$parts['path'], '/');
            }
            if (isset($parts['user'])) {
                $user = (string)$parts['user'];
            }
            if (isset($parts['pass'])) {
                $pass = (string)$parts['pass'];
            }
        }
    }

    define('DB_DRIVER', $driver);
    define('DB_HOST', $host);
    define('DB_PORT', (int)$port);
    define('DB_NAME', $name);
    define('DB_USER', $user);
    define('DB_PASS', $pass);
    define('DB_MAINTENANCE_DB', cfgEnv('DB_MAINTENANCE_DB', DB_DRIVER === 'pgsql' ? 'postgres' : 'mysql'));

    define('DB_READ_HOST', cfgEnv('DB_READ_HOST', ''));
    define('DB_READ_PORT', cfgEnvInt('DB_READ_PORT', DB_PORT));
    define('DB_CONNECT_TIMEOUT', cfgEnvInt('DB_CONNECT_TIMEOUT', 5));
    define('DB_RETRY_ATTEMPTS', max(1, cfgEnvInt('DB_RETRY_ATTEMPTS', 3)));
    define('DB_RETRY_DELAY_MS', max(10, cfgEnvInt('DB_RETRY_DELAY_MS', 120)));
    define('DB_PERSISTENT', cfgEnvBool('DB_PERSISTENT', false));

    define('SITE_NAME', cfgEnv('SITE_NAME', 'Translink File Library'));

    $explicitSiteUrl = cfgEnv('SITE_URL', '');
    if ($explicitSiteUrl !== '') {
        $siteUrl = $explicitSiteUrl;
    } else {
        $https = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        );
        $scheme = $https ? 'https' : 'http';
        $hostHeader = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000');
        $siteUrl = $scheme . '://' . $hostHeader;
    }
    define('SITE_URL', rtrim($siteUrl, '/'));

    define('DEBUG', cfgEnvBool('DEBUG', false));

    $uploadPath = cfgEnv('UPLOAD_PATH', __DIR__ . '/uploads');
    define('UPLOAD_PATH', rtrim(str_replace('\\', '/', $uploadPath), '/'));
    if (!is_dir(UPLOAD_PATH)) {
        @mkdir(UPLOAD_PATH, 0755, true);
    }
    foreach (['configs', 'firmware', 'manuals', 'software', 'brands', 'models', 'users'] as $uploadDir) {
        $fullDir = UPLOAD_PATH . '/' . $uploadDir;
        if (!is_dir($fullDir)) {
            @mkdir($fullDir, 0755, true);
        }
    }

    define('MAX_FILE_SIZE', cfgEnvInt('MAX_FILE_SIZE', 268435456));

    define('ALLOWED_CONFIG_EXT', cfgList('ALLOWED_CONFIG_EXT', ['cfg', 'txt', 'conf', 'ini', 'csv', 'xls', 'xlsx']));
    define('ALLOWED_FIRMWARE_EXT', cfgList('ALLOWED_FIRMWARE_EXT', ['fw', 'bin', 'hex', 'dfu', 'xim', 'cfw']));
    define('ALLOWED_MANUAL_EXT', cfgList('ALLOWED_MANUAL_EXT', ['pdf', 'doc', 'docx', 'txt']));
    define('ALLOWED_SOFTWARE_EXT', cfgList('ALLOWED_SOFTWARE_EXT', ['exe', 'msi', 'zip', 'rar', '7z', 'gz', 'xim', 'cif']));

    define('SEARCH_PAGE_SIZE', max(6, cfgEnvInt('SEARCH_PAGE_SIZE', 24)));
    define('ADMIN_TABLE_PAGE_SIZE', max(20, cfgEnvInt('ADMIN_TABLE_PAGE_SIZE', 200)));

    define('DASH_TIMEZONE', cfgEnv('DASH_TIMEZONE', 'America/Adak'));
    define('DASH_TIMEZONE_LABEL', cfgEnv('DASH_TIMEZONE_LABEL', 'ET'));

    define('CACHE_DRIVER', cfgEnv('CACHE_DRIVER', 'memory'));
    define('CACHE_HOST', cfgEnv('CACHE_HOST', '127.0.0.1'));
    define('CACHE_PORT', cfgEnvInt('CACHE_PORT', 6379));
    define('CACHE_PREFIX', cfgEnv('CACHE_PREFIX', 'translink:'));
    define('CACHE_TTL', max(1, cfgEnvInt('CACHE_TTL', 120)));
    define('CACHE_TTL_QUERY', max(1, cfgEnvInt('CACHE_TTL_QUERY', 60)));
    define('CACHE_TTL_STATS', max(1, cfgEnvInt('CACHE_TTL_STATS', 300)));

    define('STORAGE_DRIVER', cfgEnv('STORAGE_DRIVER', 'local'));
    define('S3_BUCKET', cfgEnv('S3_BUCKET', ''));
    define('S3_REGION', cfgEnv('S3_REGION', 'us-east-1'));
    define('S3_KEY', cfgEnv('S3_KEY', ''));
    define('S3_SECRET', cfgEnv('S3_SECRET', ''));
    define('S3_ENDPOINT', cfgEnv('S3_ENDPOINT', ''));

    define('QUEUE_DRIVER', cfgEnv('QUEUE_DRIVER', 'sync'));
    define('QUEUE_HOST', cfgEnv('QUEUE_HOST', CACHE_HOST));
    define('QUEUE_PORT', cfgEnvInt('QUEUE_PORT', CACHE_PORT));
}
