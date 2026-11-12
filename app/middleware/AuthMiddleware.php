<?php
declare(strict_types=1);

/**
 * Middleware Autentikasi Pengguna
 * Memastikan halaman hanya diakses oleh pengguna yang sudah login (atau guest saja).
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/controllers/AuthController.php';

class AuthMiddleware
{
    /**
     * Memastikan pengguna sudah terautentikasi dan akunnya masih valid di database.
     */
    public static function handle(?PDO $pdo = null): array
    {
        startSecureSession();

        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        if (!isLoggedIn() && $pdo instanceof PDO) {
            $authController = new AuthController($pdo);
            $authController->attemptRememberLogin();
        }

        if (!isLoggedIn()) {
            setFlash('warning', 'Login Diperlukan', 'Silakan masuk ke akun Anda terlebih dahulu untuk melanjutkan.');
            redirect('auth/login.php');
        }

        $sessionUser = currentUser();
        if ($pdo instanceof PDO && $sessionUser !== null) {
            $userModel = new User($pdo);
            $freshUser = $userModel->findById((int) $sessionUser['id']);

            if ($freshUser === null) {
                $userModel->deleteRememberTokens((int) $sessionUser['id'], null);
                clearRememberCookie();
                logoutSession();
                startSecureSession();
                setFlash('warning', 'Sesi Tidak Valid', 'Akun tidak ditemukan. Silakan login kembali.');
                redirect('auth/login.php');
            }

            // Sinkronkan data role/nama terbaru tanpa memicu regenerasi berulang
            $_SESSION['auth_user'] = [
                'id'    => (int) $freshUser['id'],
                'nama'  => (string) $freshUser['nama'],
                'email' => (string) $freshUser['email'],
                'role'  => (string) $freshUser['role'],
            ];

            return $_SESSION['auth_user'];
        }

        return $sessionUser ?? [];
    }

    /**
     * Mengalihkan pengguna yang sudah login agar tidak membuka halaman login/register lagi.
     */
    public static function guestOnly(?PDO $pdo = null): void
    {
        startSecureSession();

        if (!isLoggedIn() && $pdo instanceof PDO) {
            $authController = new AuthController($pdo);
            $authController->attemptRememberLogin();
        }

        if (!isLoggedIn()) {
            return;
        }

        if (isAdmin()) {
            redirect('admin/dashboard.php');
        }

        redirect('index.php');
    }
}
