<?php
declare(strict_types=1);

/**
 * Controller Autentikasi (Registrasi, Login, Logout)
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

require_once dirname(__DIR__) . '/models/User.php';

class AuthController
{
    private User $userModel;

    public function __construct(PDO $pdo)
    {
        $this->userModel = new User($pdo);
    }

    /**
     * Memproses registrasi akun pemilih baru.
     * Role selalu dipaksa menjadi 'pemilih'.
     */
    public function register(array $postData): array
    {
        $nama = sanitizeText($postData['nama'] ?? '');
        $email = mb_strtolower(sanitizeText($postData['email'] ?? ''), 'UTF-8');
        $noHpRaw = sanitizeText($postData['no_hp'] ?? '');
        $noHp = normalizePhone($noHpRaw);
        $password = (string) ($postData['password'] ?? '');
        $konfirmasi = (string) ($postData['konfirmasi_password'] ?? '');
        $csrfToken = $postData[CSRF_TOKEN_KEY] ?? null;

        $old = [
            'nama'  => $nama,
            'email' => $email,
            'no_hp' => $noHpRaw,
        ];

        if (!verifyCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
            return [
                'success' => false,
                'errors'  => ['Permintaan tidak valid atau sesi form telah kedaluwarsa. Silakan muat ulang halaman.'],
                'old'     => $old,
            ];
        }

        $errors = [];

        if (!isRequired($nama)) {
            $errors[] = 'Nama lengkap wajib diisi.';
        } elseif (!minLength($nama, 3) || !maxLength($nama, 100)) {
            $errors[] = 'Nama lengkap harus antara 3 hingga 100 karakter.';
        }

        if (!isRequired($email)) {
            $errors[] = 'Email wajib diisi.';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Format alamat email tidak valid.';
        }

        if (!isRequired($noHpRaw)) {
            $errors[] = 'Nomor HP wajib diisi.';
        } elseif (!isValidPhone($noHpRaw) || mb_strlen($noHp, 'UTF-8') > 20) {
            $errors[] = 'Format nomor HP tidak valid. Gunakan nomor Indonesia yang aktif (contoh: 081234567890).';
        }

        $passwordErrors = validatePasswordInput($password, $konfirmasi, 8);
        if (!empty($passwordErrors)) {
            $errors = array_merge($errors, $passwordErrors);
        }

        if (empty($errors)) {
            try {
                if ($this->userModel->emailExists($email)) {
                    $errors[] = 'Email tersebut sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.';
                }
            } catch (RuntimeException $e) {
                $errors[] = 'Terjadi gangguan saat memvalidasi pendaftaran. Silakan coba lagi.';
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
                'old'     => $old,
            ];
        }

        try {
            $newUserId = $this->userModel->createPemilih([
                'nama'     => $nama,
                'email'    => $email,
                'no_hp'    => $noHp,
                'password' => $password,
            ]);

            return [
                'success' => true,
                'user_id' => $newUserId,
                'errors'  => [],
                'old'     => [],
            ];
        } catch (PDOException $e) {
            // Tangani race condition duplicate key email secara aman tanpa membocorkan detail SQL
            if ((string) $e->getCode() === '23000') {
                return [
                    'success' => false,
                    'errors'  => ['Email tersebut sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.'],
                    'old'     => $old,
                ];
            }

            error_log('[AUTH_REGISTER_ERROR] ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['Pendaftaran gagal diproses saat ini. Silakan coba beberapa saat lagi.'],
                'old'     => $old,
            ];
        } catch (Throwable $e) {
            error_log('[AUTH_REGISTER_ERROR] ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['Terjadi gangguan sistem saat memproses pendaftaran.'],
                'old'     => $old,
            ];
        }
    }

    /**
     * Memproses autentikasi login dengan verifikasi password, pembatasan percobaan,
     * dan regenerasi session ID.
     */
    public function login(array $postData): array
    {
        $email = mb_strtolower(sanitizeText($postData['email'] ?? ''), 'UTF-8');
        $password = (string) ($postData['password'] ?? '');
        $csrfToken = $postData[CSRF_TOKEN_KEY] ?? null;

        $old = ['email' => $email];

        if (!verifyCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
            return [
                'success' => false,
                'errors'  => ['Permintaan tidak valid atau token keamanan kedaluwarsa. Silakan muat ulang halaman.'],
                'old'     => $old,
            ];
        }

        $errors = [];
        if (!isRequired($email) || !isValidEmail($email)) {
            $errors[] = 'Masukkan alamat email yang valid.';
        }
        if ($password === '') {
            $errors[] = 'Password wajib diisi.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
                'old'     => $old,
            ];
        }

        // Periksa pembatasan percobaan login
        $sisaTunggu = max(
            remainingLoginLockoutSeconds($email),
            remainingLoginLockoutSeconds('__global_ip__')
        );
        if ($sisaTunggu > 0) {
            $menit = intdiv($sisaTunggu, 60);
            $detik = $sisaTunggu % 60;
            $waktuTeks = $menit > 0 ? "{$menit} menit {$detik} detik" : "{$detik} detik";

            return [
                'success' => false,
                'errors'  => ["Terlalu banyak percobaan login yang gagal. Silakan coba lagi dalam {$waktuTeks}."],
                'old'     => $old,
            ];
        }

        $user = $this->userModel->findByEmailForAuth($email);

        // Gunakan hash dummy bila user tidak ditemukan untuk konsistensi waktu verifikasi
        $dummyHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $hashToCheck = $user !== null ? (string) $user['password'] : $dummyHash;
        $passwordCocok = $this->userModel->verifyPassword($password, $hashToCheck);

        if ($user === null || !$passwordCocok) {
            $globalIpLimit = (defined('LOGIN_MAX_ATTEMPTS') ? (int) LOGIN_MAX_ATTEMPTS : 5) * 4;
            recordFailedLoginAttempt($email);
            recordFailedLoginAttempt('__global_ip__', $globalIpLimit);

            $sisaBaru = max(
                remainingLoginLockoutSeconds($email),
                remainingLoginLockoutSeconds('__global_ip__')
            );

            if ($sisaBaru > 0) {
                $menit = intdiv($sisaBaru, 60);
                return [
                    'success' => false,
                    'errors'  => ["Percobaan login melebihi batas. Akun sementara dibatasi selama {$menit} menit."],
                    'old'     => $old,
                ];
            }

            return [
                'success' => false,
                'errors'  => ['Email atau password yang Anda masukkan salah.'],
                'old'     => $old,
            ];
        }

        clearLoginAttempts($email);
        clearLoginAttempts('__global_ip__');

        // Simpan session dan lakukan regenerasi session ID
        setAuthenticatedUser($user);

        // Remember Me opsional dengan token aman (selector + hash validator di DB)
        $rememberMe = isset($postData['remember_me']) && (string) $postData['remember_me'] === '1';
        if ($rememberMe) {
            try {
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $lifetime = defined('REMEMBER_TOKEN_LIFETIME') ? (int) REMEMBER_TOKEN_LIFETIME : (60 * 60 * 24 * 30);
                $expiresAt = time() + $lifetime;

                $this->userModel->createRememberToken((int) $user['id'], $selector, $validator, $expiresAt);
                setRememberCookie($selector, $validator, $expiresAt);
            } catch (Throwable $e) {
                error_log('[REMEMBER_TOKEN_ISSUE_ERROR] ' . $e->getMessage());
            }
        }

        $redirectPath = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'index.php';

        return [
            'success'  => true,
            'user'     => currentUser(),
            'redirect' => $redirectPath,
            'errors'   => [],
            'old'      => [],
        ];
    }

    /**
     * Memulihkan sesi login secara aman dari cookie Remember Me jika valid,
     * sekaligus merotasi token untuk mencegah penggunaan ulang.
     */
    public function attemptRememberLogin(): bool
    {
        if (isLoggedIn()) {
            return true;
        }

        $parsed = parseRememberCookie();
        if ($parsed === null) {
            return false;
        }

        $record = $this->userModel->findActiveRememberToken($parsed['selector']);
        if ($record === null) {
            clearRememberCookie();
            return false;
        }

        $expectedHash = (string) $record['token_hash'];
        $actualHash = hash('sha256', $parsed['validator']);

        if (!hash_equals($expectedHash, $actualHash)) {
            // Indikasi token dimodifikasi: cabut seluruh token user dan hapus cookie
            $this->userModel->deleteRememberTokens((int) $record['user_id'], $parsed['selector']);
            clearRememberCookie();
            return false;
        }

        $user = [
            'id'    => (int) $record['user_id'],
            'nama'  => (string) $record['nama'],
            'email' => (string) $record['email'],
            'role'  => (string) $record['role'],
        ];

        setAuthenticatedUser($user);

        // Rotasi token Remember Me setiap kali digunakan
        try {
            $newSelector = bin2hex(random_bytes(16));
            $newValidator = bin2hex(random_bytes(32));
            $lifetime = defined('REMEMBER_TOKEN_LIFETIME') ? (int) REMEMBER_TOKEN_LIFETIME : (60 * 60 * 24 * 30);
            $expiresAt = time() + $lifetime;

            $this->userModel->rotateRememberToken(
                (int) $record['token_id'],
                (int) $record['user_id'],
                $newSelector,
                $newValidator,
                $expiresAt
            );
            setRememberCookie($newSelector, $newValidator, $expiresAt);
        } catch (Throwable $e) {
            error_log('[REMEMBER_TOKEN_ROTATE_ERROR] ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Memproses logout dengan validasi CSRF serta pencabutan session dan Remember Me token.
     */
    public function logout(array $postData): array
    {
        $csrfToken = $postData[CSRF_TOKEN_KEY] ?? null;

        if (!verifyCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
            return [
                'success' => false,
                'errors'  => ['Token keamanan logout tidak valid.'],
            ];
        }

        $userId = currentUserId();
        $parsedRemember = parseRememberCookie();
        $selector = $parsedRemember['selector'] ?? null;

        $this->userModel->deleteRememberTokens($userId, $selector);
        clearRememberCookie();
        logoutSession();

        return [
            'success' => true,
            'errors'  => [],
        ];
    }
}
