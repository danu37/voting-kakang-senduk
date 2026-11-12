<?php
declare(strict_types=1);

/**
 * Helper Autentikasi Dasar, Session Aman, CSRF Token, Role, dan Pembatasan Percobaan Login
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

if (!function_exists('startSecureSession')) {
    /**
     * Memulai session PHP dengan konfigurasi cookie yang aman.
     */
    function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $isHttps = function_exists('isHttpsRequest')
            ? isHttpsRequest()
            : (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            );

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        $sessionName = defined('SESSION_NAME') ? SESSION_NAME : 'ks_ponorogo_sid';
        session_name($sessionName);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

if (!function_exists('generateCsrfToken')) {
    /**
     * Membuat atau mengambil CSRF token aktif dari session.
     */
    function generateCsrfToken(): string
    {
        startSecureSession();
        $key = defined('CSRF_TOKEN_KEY') ? CSRF_TOKEN_KEY : '_csrf_token';

        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }
}

if (!function_exists('verifyCsrfToken')) {
    /**
     * Memverifikasi CSRF token dengan perbandingan konstan (hash_equals).
     */
    function verifyCsrfToken(?string $token): bool
    {
        startSecureSession();
        $key = defined('CSRF_TOKEN_KEY') ? CSRF_TOKEN_KEY : '_csrf_token';

        if ($token === null || $token === '' || empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            return false;
        }

        return hash_equals($_SESSION[$key], $token);
    }
}

if (!function_exists('csrfField')) {
    /**
     * Menghasilkan elemen input hidden CSRF untuk form HTML.
     */
    function csrfField(): string
    {
        $token = generateCsrfToken();
        $key = defined('CSRF_TOKEN_KEY') ? CSRF_TOKEN_KEY : '_csrf_token';
        $escapedKey = htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedToken = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<input type="hidden" name="' . $escapedKey . '" value="' . $escapedToken . '">';
    }
}

if (!function_exists('setAuthenticatedUser')) {
    /**
     * Menyimpan data pengguna terautentikasi ke session dan melakukan regenerasi session ID.
     */
    function setAuthenticatedUser(array $user): void
    {
        startSecureSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['auth_user'] = [
            'id'    => (int) ($user['id'] ?? 0),
            'nama'  => (string) ($user['nama'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role'  => (string) ($user['role'] ?? 'pemilih'),
        ];
    }
}

if (!function_exists('logoutSession')) {
    /**
     * Menghapus data autentikasi dan mengakhiri session secara menyeluruh.
     */
    function logoutSession(): void
    {
        startSecureSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

if (!function_exists('currentUser')) {
    /**
     * Mengembalikan data pengguna yang sedang login, atau null jika belum login.
     */
    function currentUser(): ?array
    {
        startSecureSession();
        if (empty($_SESSION['auth_user']) || !is_array($_SESSION['auth_user'])) {
            return null;
        }

        $user = $_SESSION['auth_user'];
        if (empty($user['id']) || empty($user['role'])) {
            return null;
        }

        return $user;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return currentUser() !== null;
    }
}

if (!function_exists('currentUserId')) {
    function currentUserId(): ?int
    {
        $user = currentUser();
        return $user !== null ? (int) $user['id'] : null;
    }
}

if (!function_exists('currentUserRole')) {
    function currentUserRole(): ?string
    {
        $user = currentUser();
        return $user !== null ? (string) $user['role'] : null;
    }
}

if (!function_exists('hasRole')) {
    function hasRole(string $role): bool
    {
        return currentUserRole() === $role;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        return hasRole('admin');
    }
}

if (!function_exists('isPemilih')) {
    function isPemilih(): bool
    {
        return hasRole('pemilih');
    }
}

if (!function_exists('getLoginAttemptKey')) {
    function getLoginAttemptKey(string $identifier): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        return hash('sha256', strtolower(trim($identifier)) . '|' . $ip);
    }
}

if (!function_exists('getLoginAttemptFilePath')) {
    /**
     * Mengembalikan path file penyimpanan pembatasan percobaan login di sisi server.
     * Disimpan di direktori temp sistem di luar webroot agar tidak dapat dibypass dengan menghapus cookie.
     */
    function getLoginAttemptFilePath(string $key): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $key)) {
            return null;
        }

        $baseDir = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'ks_ponorogo_ratelimit';
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0700, true) && !is_dir($baseDir)) {
            return null;
        }

        if (!is_writable($baseDir)) {
            return null;
        }

        return $baseDir . DIRECTORY_SEPARATOR . 'la_' . $key . '.json';
    }
}

if (!function_exists('readServerLoginAttemptRecord')) {
    function readServerLoginAttemptRecord(string $key): ?array
    {
        $path = getLoginAttemptFilePath($key);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return null;
        }

        $record = null;
        if (@flock($fp, LOCK_SH)) {
            $raw = stream_get_contents($fp);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $record = $decoded;
                }
            }
            @flock($fp, LOCK_UN);
        }
        @fclose($fp);

        return $record;
    }
}

if (!function_exists('isLoginBlocked')) {
    /**
     * Memeriksa apakah percobaan login untuk identifier tertentu sedang dibatasi.
     */
    function isLoginBlocked(string $identifier = 'global'): bool
    {
        return remainingLoginLockoutSeconds($identifier) > 0;
    }
}

if (!function_exists('remainingLoginLockoutSeconds')) {
    function remainingLoginLockoutSeconds(string $identifier = 'global'): int
    {
        startSecureSession();
        $key = getLoginAttemptKey($identifier);
        $now = time();

        $serverRecord = readServerLoginAttemptRecord($key);
        if (is_array($serverRecord) && !empty($serverRecord['blocked_until'])) {
            $sisaServer = (int) $serverRecord['blocked_until'] - $now;
            if ($sisaServer > 0) {
                return $sisaServer;
            }
            // Masa blokir server telah habis, bersihkan catatan lama
            clearLoginAttempts($identifier);
            return 0;
        }

        $record = $_SESSION['_login_attempts'][$key] ?? null;
        if (!is_array($record) || empty($record['blocked_until'])) {
            return 0;
        }

        $sisa = (int) $record['blocked_until'] - $now;
        if ($sisa <= 0) {
            unset($_SESSION['_login_attempts'][$key]);
            return 0;
        }

        return $sisa;
    }
}

if (!function_exists('recordFailedLoginAttempt')) {
    function recordFailedLoginAttempt(string $identifier = 'global', ?int $maxAttemptsOverride = null): void
    {
        startSecureSession();
        $key = getLoginAttemptKey($identifier);
        $defaultMax = defined('LOGIN_MAX_ATTEMPTS') ? (int) LOGIN_MAX_ATTEMPTS : 5;
        $maxAttempts = ($maxAttemptsOverride !== null && $maxAttemptsOverride > 0) ? $maxAttemptsOverride : $defaultMax;
        $lockoutSeconds = defined('LOGIN_LOCKOUT_SECONDS') ? (int) LOGIN_LOCKOUT_SECONDS : 300;
        $now = time();
        $windowSeconds = max($lockoutSeconds * 2, 600);

        $updatedRecord = null;
        $path = getLoginAttemptFilePath($key);
        if ($path !== null) {
            $fp = @fopen($path, 'c+b');
            if ($fp !== false) {
                if (@flock($fp, LOCK_EX)) {
                    $raw = stream_get_contents($fp);
                    $decoded = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
                    $record = is_array($decoded) ? $decoded : ['count' => 0, 'blocked_until' => 0, 'last_attempt' => 0];

                    // Reset hitungan bila jendela kegagalan sudah kedaluwarsa
                    $lastAttempt = (int) ($record['last_attempt'] ?? 0);
                    $blockedUntil = (int) ($record['blocked_until'] ?? 0);
                    if (($blockedUntil > 0 && $blockedUntil <= $now) || ($lastAttempt > 0 && ($now - $lastAttempt) > $windowSeconds)) {
                        $record = ['count' => 0, 'blocked_until' => 0, 'last_attempt' => 0];
                    }

                    $record['count'] = (int) ($record['count'] ?? 0) + 1;
                    $record['last_attempt'] = $now;
                    if ($record['count'] >= $maxAttempts) {
                        $record['blocked_until'] = $now + $lockoutSeconds;
                    }

                    rewind($fp);
                    ftruncate($fp, 0);
                    fwrite($fp, (string) json_encode($record));
                    fflush($fp);
                    @flock($fp, LOCK_UN);
                    $updatedRecord = $record;
                }
                @fclose($fp);
            }
        }

        if ($updatedRecord === null) {
            $sessionRec = $_SESSION['_login_attempts'][$key] ?? ['count' => 0, 'blocked_until' => 0, 'last_attempt' => 0];
            $sessionRec['count'] = (int) ($sessionRec['count'] ?? 0) + 1;
            $sessionRec['last_attempt'] = $now;
            if ($sessionRec['count'] >= $maxAttempts) {
                $sessionRec['blocked_until'] = $now + $lockoutSeconds;
            }
            $_SESSION['_login_attempts'][$key] = $sessionRec;
        } else {
            $_SESSION['_login_attempts'][$key] = $updatedRecord;
        }
    }
}

if (!function_exists('clearLoginAttempts')) {
    function clearLoginAttempts(string $identifier = 'global'): void
    {
        startSecureSession();
        $key = getLoginAttemptKey($identifier);
        unset($_SESSION['_login_attempts'][$key]);

        $path = getLoginAttemptFilePath($key);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('setRememberCookie')) {
    /**
     * Mengatur cookie "Ingat Saya" dengan flag HttpOnly dan SameSite=Lax.
     */
    function setRememberCookie(string $selector, string $validator, int $expiresTimestamp): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $cookieName = defined('REMEMBER_COOKIE_NAME') ? REMEMBER_COOKIE_NAME : 'ks_ponorogo_rem';
        $isHttps = function_exists('isHttpsRequest')
            ? isHttpsRequest()
            : (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            );

        setcookie($cookieName, $selector . ':' . $validator, [
            'expires'  => $expiresTimestamp,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('clearRememberCookie')) {
    /**
     * Menghapus cookie "Ingat Saya" dari browser.
     */
    function clearRememberCookie(): void
    {
        $cookieName = defined('REMEMBER_COOKIE_NAME') ? REMEMBER_COOKIE_NAME : 'ks_ponorogo_rem';
        unset($_COOKIE[$cookieName]);

        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $isHttps = function_exists('isHttpsRequest')
            ? isHttpsRequest()
            : (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            );

        setcookie($cookieName, '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('parseRememberCookie')) {
    /**
     * Memecah dan memvalidasi format cookie "Ingat Saya" (selector:validator).
     *
     * @return array{selector: string, validator: string}|null
     */
    function parseRememberCookie(): ?array
    {
        $cookieName = defined('REMEMBER_COOKIE_NAME') ? REMEMBER_COOKIE_NAME : 'ks_ponorogo_rem';
        $raw = $_COOKIE[$cookieName] ?? null;

        if (!is_string($raw) || !str_contains($raw, ':')) {
            return null;
        }

        $parts = explode(':', $raw, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$selector, $validator] = $parts;
        if (
            strlen($selector) !== 32
            || strlen($validator) !== 64
            || !ctype_xdigit($selector)
            || !ctype_xdigit($validator)
        ) {
            return null;
        }

        return [
            'selector'  => $selector,
            'validator' => $validator,
        ];
    }
}

