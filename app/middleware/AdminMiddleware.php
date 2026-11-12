<?php
declare(strict_types=1);

/**
 * Middleware Otorisasi Administrator
 * Memastikan halaman administrator hanya dapat diakses oleh role 'admin'.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

require_once __DIR__ . '/AuthMiddleware.php';

class AdminMiddleware
{
    /**
     * Memvalidasi autentikasi dan peran administrator.
     */
    public static function handle(?PDO $pdo = null): array
    {
        $user = AuthMiddleware::handle($pdo);

        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            self::renderForbiddenPage();
            exit;
        }

        return $user;
    }

    /**
     * Menampilkan halaman 403 Forbidden berbahasa Indonesia ketika role bukan admin.
     */
    private static function renderForbiddenPage(): void
    {
        $homeUrl = htmlspecialchars(baseUrl('index.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $appName = htmlspecialchars(defined('APP_SHORT_NAME') ? APP_SHORT_NAME : 'Kakang Senduk Ponorogo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Akses Ditolak — {$appName}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #F8FAF7; color: #1E293B; font-family: system-ui, -apple-system, sans-serif; }
        .card-forbidden { max-width: 500px; border: 1px solid #E5E7EB; border-radius: 14px; box-shadow: 0 4px 16px rgba(2,44,34,0.06); }
        .btn-emerald { background-color: #047857; border-color: #047857; color: #fff; }
        .btn-emerald:hover { background-color: #064E3B; border-color: #064E3B; color: #fff; }
    </style>
</head>
<body class="min-vh-100 d-flex align-items-center justify-content-center p-3">
    <div class="card card-forbidden bg-white p-4 p-md-5 text-center">
        <div class="mb-3 text-danger">
            <i class="bi bi-shield-lock-fill" style="font-size: 3rem;"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">403 &mdash; Akses Ditolak</h1>
        <p class="text-muted mb-4">
            Maaf, halaman ini hanya dapat diakses oleh <strong>Administrator</strong>. Akun pemilih tidak memiliki izin untuk membuka area pengelolaan sistem.
        </p>
        <div>
            <a href="{$homeUrl}" class="btn btn-emerald px-4">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

