<?php
declare(strict_types=1);

/**
 * Konfigurasi Utama Aplikasi (Mendukung Pemisahan Development & Production)
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ke file konfigurasi ditolak.');
}

if (defined('APP_CONFIG_LOADED')) {
    return;
}
define('APP_CONFIG_LOADED', true);

// Path Dasar Proyek
define('BASE_PATH', dirname(__DIR__));

// Muat konfigurasi environment opsional dari file .env dan/atau config/config.local.php
$ksDotEnv = [];
$dotEnvPath = BASE_PATH . DIRECTORY_SEPARATOR . '.env';
if (is_file($dotEnvPath) && is_readable($dotEnvPath)) {
    $lines = @file($dotEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false || $eqPos === 0) {
                continue;
            }
            $envKey = trim(substr($line, 0, $eqPos));
            $envVal = trim(substr($line, $eqPos + 1));
            if (
                strlen($envVal) >= 2
                && (
                    ($envVal[0] === '"' && $envVal[strlen($envVal) - 1] === '"')
                    || ($envVal[0] === "'" && $envVal[strlen($envVal) - 1] === "'")
                )
            ) {
                $envVal = substr($envVal, 1, -1);
            }
            $ksDotEnv[$envKey] = $envVal;
        }
    }
}

$ksLocalConfig = [];
$localConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localConfigPath) && is_readable($localConfigPath)) {
    $loadedLocal = require $localConfigPath;
    if (is_array($loadedLocal)) {
        $ksLocalConfig = $loadedLocal;
    }
}

if (!function_exists('appEnvValue')) {
    /**
     * Mengambil nilai konfigurasi dengan prioritas:
     * config.local.php -> OS/Web Server Env -> .env -> $default.
     */
    function appEnvValue(string $key, mixed $default = null): mixed
    {
        global $ksLocalConfig, $ksDotEnv;

        if (is_array($ksLocalConfig) && array_key_exists($key, $ksLocalConfig)) {
            return $ksLocalConfig[$key];
        }

        $sysVal = getenv($key);
        if ($sysVal !== false) {
            return $sysVal;
        }

        if (is_array($ksDotEnv) && array_key_exists($key, $ksDotEnv)) {
            return $ksDotEnv[$key];
        }

        return $default;
    }
}

if (!function_exists('isHttpsRequest')) {
    /**
     * Mendeteksi apakah request saat ini menggunakan koneksi HTTPS (langsung maupun di balik reverse proxy).
     */
    function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
            return true;
        }
        return false;
    }
}

// Informasi & Environment Aplikasi
define('APP_NAME', 'Voting Pemilihan Kakang Senduk Ponorogo');
define('APP_SHORT_NAME', 'Kakang Senduk Ponorogo');
define('APP_REGION', 'Kabupaten Ponorogo');

$rawEnv = strtolower(trim((string) appEnvValue('APP_ENV', 'production')));
define('APP_ENV', $rawEnv === 'development' ? 'development' : 'production');

$rawForceHttps = appEnvValue('FORCE_HTTPS', false);
$forceHttps = is_bool($rawForceHttps)
    ? $rawForceHttps
    : in_array(strtolower(trim((string) $rawForceHttps)), ['1', 'true', 'yes', 'on'], true);
define('FORCE_HTTPS', $forceHttps);

// Zona Waktu Server (Ponorogo - WIB)
define('APP_TIMEZONE', (string) appEnvValue('APP_TIMEZONE', 'Asia/Jakarta'));
define('APP_TIMEZONE_OFFSET', (string) appEnvValue('APP_TIMEZONE_OFFSET', '+07:00'));
date_default_timezone_set(APP_TIMEZONE);

// Direktori & File Logging Error (Di luar akses publik via .htaccess)
define('STORAGE_LOG_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs');
if (!is_dir(STORAGE_LOG_DIR)) {
    @mkdir(STORAGE_LOG_DIR, 0750, true);
}
$appErrorLogFile = STORAGE_LOG_DIR . DIRECTORY_SEPARATOR . 'php_error.log';
if (is_dir(STORAGE_LOG_DIR) && is_writable(STORAGE_LOG_DIR)) {
    ini_set('error_log', $appErrorLogFile);
}

// Pengaturan Error Reporting (Detail error disembunyikan dari pengguna publik)
ini_set('log_errors', '1');
if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
}

// Penanganan Global Exception agar pesan error teknis tidak bocor ke pengguna
if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e): void {
        error_log('[UNCAUGHT_EXCEPTION] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        if (!headers_sent()) {
            http_response_code(500);
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isAjax = (
            strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains($accept, 'application/json')
        );

        if ($isAjax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode([
                'success' => false,
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Terjadi gangguan pada server. Silakan coba beberapa saat lagi.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Gangguan Sistem</title></head>'
            . '<body style="font-family:sans-serif;background:#F8FAF7;color:#1E293B;padding:3rem;text-align:center;">'
            . '<h1 style="font-size:1.4rem;color:#022C22;">Terjadi Gangguan Sistem</h1>'
            . '<p style="color:#64748B;">Permintaan tidak dapat diproses saat ini. Administrator telah menerima catatan gangguan ini.</p>'
            . '</body></html>';
        exit;
    });
}

// Paksa HTTPS di lingkungan produksi (kecuali localhost/loopback) & kirim header keamanan HTTP
if (PHP_SAPI !== 'cli') {
    $rawHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $hostOnly = strtolower((string) preg_replace('/:\d+$/', '', $rawHost));
    $isLocalHost = in_array($hostOnly, ['', 'localhost', '127.0.0.1', '[::1]'], true);

    if (
        APP_ENV === 'production'
        && FORCE_HTTPS === true
        && !$isLocalHost
        && !isHttpsRequest()
        && preg_match('/^[a-z0-9.\-:\[\]]+$/i', $rawHost) === 1
    ) {
        $reqUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if (!str_starts_with($reqUri, '/')) {
            $reqUri = '/' . ltrim($reqUri, '/');
        }
        header('Location: https://' . $rawHost . $reqUri, true, 301);
        exit;
    }

    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: frame-ancestors 'self'");
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        if (APP_ENV === 'production' && isHttpsRequest()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

// Deteksi / Konfigurasi Base URL (Mendukung root domain '' maupun subfolder '/voting-kakang-senduk')
$configuredBaseUrl = appEnvValue('APP_BASE_URL', null);
$trimmedBaseUrl = is_string($configuredBaseUrl) ? trim($configuredBaseUrl) : '';
if ($trimmedBaseUrl !== '' && strtolower($trimmedBaseUrl) !== 'auto') {
    $detectedBaseUrl = '/' . trim($trimmedBaseUrl, '/');
    if ($detectedBaseUrl === '/') {
        $detectedBaseUrl = '';
    }
} else {
    $detectedBaseUrl = '/voting-kakang-senduk';
    $rawDocRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $docRoot = $rawDocRoot !== '' ? str_replace('\\', '/', realpath($rawDocRoot) ?: '') : '';
    $appRoot = str_replace('\\', '/', realpath(BASE_PATH) ?: BASE_PATH);
    if ($docRoot !== '' && str_starts_with(strtolower($appRoot), strtolower($docRoot))) {
        $relativePath = substr($appRoot, strlen($docRoot));
        $detectedBaseUrl = '/' . trim($relativePath, '/');
        if ($detectedBaseUrl === '/') {
            $detectedBaseUrl = '';
        }
    }
}
define('BASE_URL', $detectedBaseUrl);

// Konfigurasi Database MySQL 8 / MariaDB (PDO) - Tidak menyimpan password produksi di repository
define('DB_HOST', (string) appEnvValue('DB_HOST', '127.0.0.1'));
define('DB_PORT', (string) appEnvValue('DB_PORT', '3306'));
define('DB_NAME', (string) appEnvValue('DB_NAME', 'db_voting_kakang_senduk'));
define('DB_USER', (string) appEnvValue('DB_USER', 'root'));
define('DB_PASS', (string) appEnvValue('DB_PASS', ''));
define('DB_CHARSET', (string) appEnvValue('DB_CHARSET', 'utf8mb4'));

// Konfigurasi Keamanan Session & Autentikasi
define('SESSION_NAME', 'ks_ponorogo_sid');
define('CSRF_TOKEN_KEY', '_csrf_token');
define('REMEMBER_COOKIE_NAME', 'ks_ponorogo_rem');
define('REMEMBER_TOKEN_LIFETIME', 60 * 60 * 24 * 30); // 30 hari
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 300);

// Konfigurasi Upload Foto Kontestan
define('UPLOAD_KONTESTAN_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kontestan');
define('UPLOAD_KONTESTAN_URL', BASE_URL . '/uploads/kontestan');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // Maksimal 2 MB
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/png', 'image/webp']);

// Konfigurasi Pagination & Kategori
define('PAGINATION_PER_PAGE', 9);
define('KATEGORI_KONTESTAN', ['Kakang', 'Senduk']);
define('STATUS_KONTESTAN', ['Aktif', 'Nonaktif']);
define('STATUS_PERIODE', ['Aktif', 'Nonaktif']);
define('ROLE_PENGGUNA', ['admin', 'pemilih']);
