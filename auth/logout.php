<?php
declare(strict_types=1);

/**
 * Halaman & Proses Logout Aman (POST + CSRF)
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new AuthController($pdo);
    $result = $authController->logout($_POST);

    if ($result['success']) {
        header('Location: ' . baseUrl('auth/login.php?keluar=1'));
        exit;
    }

    setFlash('danger', 'Gagal Keluar', $result['errors'][0] ?? 'Permintaan logout tidak valid.');
    redirect(isAdmin() ? 'admin/dashboard.php' : 'index.php');
}

// Jika diakses lewat GET, pastikan user memang login dan tampilkan konfirmasi POST aman
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$user = currentUser();
$backPath = isAdmin() ? 'admin/dashboard.php' : 'index.php';
$pageTitle = 'Konfirmasi Keluar';
$bodyClass = 'auth-wrapper d-flex align-items-center py-5';

require_once dirname(__DIR__) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-5">
            <div class="ks-card p-4 p-md-5 text-center">
                <div class="mb-3 text-emerald">
                    <i class="bi bi-box-arrow-right" style="font-size: 2.75rem;"></i>
                </div>
                <h1 class="h5 fw-bold mb-2">Keluar dari Sistem?</h1>
                <p class="text-muted small mb-4">
                    Anda saat ini masuk sebagai <strong><?= e($user['nama'] ?? '') ?></strong> (<?= e($user['email'] ?? '') ?>). Klik tombol di bawah untuk mengakhiri sesi Anda dengan aman.
                </p>

                <form method="POST" action="<?= e(baseUrl('auth/logout.php')) ?>" data-loading-form>
                    <?= csrfField() ?>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-emerald" data-loading-text="Mengakhiri sesi...">
                            <i class="bi bi-check-circle me-1"></i> Ya, Keluar Sekarang
                        </button>
                        <a href="<?= e(baseUrl($backPath)) ?>" class="btn btn-outline-secondary">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>

