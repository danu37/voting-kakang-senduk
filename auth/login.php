<?php
declare(strict_types=1);

/**
 * Halaman Login (Pemilih & Administrator)
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';

AuthMiddleware::guestOnly($pdo);

$errors = [];
$old = ['email' => ''];
$logoutNotif = null;

if (isset($_GET['keluar']) && $_GET['keluar'] === '1') {
    $logoutNotif = [
        'type'    => 'success',
        'title'   => 'Berhasil Keluar',
        'message' => 'Anda telah keluar dari sistem dengan aman.',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new AuthController($pdo);
    $result = $authController->login($_POST);

    if ($result['success']) {
        $namaPengguna = (string) ($result['user']['nama'] ?? 'Pengguna');
        setFlash('success', 'Login Berhasil', "Selamat datang kembali, {$namaPengguna}.");
        redirect((string) ($result['redirect'] ?? 'index.php'));
    }

    $errors = $result['errors'] ?? ['Email atau password yang Anda masukkan salah.'];
    $old = $result['old'] ?? $old;
}

$pageTitle = 'Masuk Akun';
$bodyClass = 'auth-wrapper d-flex align-items-center py-4 py-md-5';

require_once dirname(__DIR__) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
            <div class="auth-card row g-0">
                <!-- Panel Identitas Ponorogo -->
                <div class="col-lg-5 auth-brand-panel ks-motif-subtle p-4 p-md-5 d-flex flex-column justify-content-between">
                    <div>
                        <span class="auth-badge-ponorogo mb-3">
                            <i class="bi bi-stars"></i> Kakang Senduk Ponorogo
                        </span>
                        <h1 class="h3 fw-bold text-white mb-2">Selamat Datang</h1>
                        <div class="auth-ornament-line mb-3"></div>
                        <p class="text-white-50 small mb-0">
                            Masuk ke portal resmi Pemilihan Duta Wisata Kabupaten Ponorogo untuk menyalurkan suara Anda atau mengelola jalannya pemilihan.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-top border-white border-opacity-10 small text-white-50">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock-fill text-gold"></i>
                            <span>Sesi terenkripsi &amp; dilindungi CSRF token</span>
                        </div>
                    </div>
                </div>

                <!-- Panel Form Login -->
                <div class="col-lg-7 p-4 p-md-5 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Masuk ke Akun Anda</h2>
                        <a href="<?= e(baseUrl('index.php')) ?>" class="small ks-link-accent">
                            <i class="bi bi-arrow-left me-1"></i>Beranda
                        </a>
                    </div>

                    <?php if ($logoutNotif !== null): ?>
                        <?php
                            $logoutJson = json_encode($logoutNotif, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                        ?>
                        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-3" role="alert">
                            <strong class="me-1"><?= e($logoutNotif['title']) ?>:</strong>
                            <span><?= e($logoutNotif['message']) ?></span>
                        </div>
                        <script id="ks-flash-data" type="application/json"><?= $logoutJson ?></script>
                    <?php endif; ?>

                    <?php require dirname(__DIR__) . '/views/components/alert.php'; ?>

                    <form method="POST" action="<?= e(baseUrl('auth/login.php')) ?>" data-loading-form novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium small">Alamat Email</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?= e($old['email'] ?? '') ?>"
                                placeholder="nama@contoh.com"
                                required
                                autocomplete="email"
                                autofocus
                            >
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium small">Password</label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Masukkan password Anda"
                                    required
                                    autocomplete="current-password"
                                >
                                <button class="btn btn-toggle-password" type="button" data-toggle-password="password" aria-label="Tampilkan password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="remember_me" name="remember_me">
                                <label class="form-check-label small text-muted" for="remember_me">
                                    Ingat saya selama 30 hari
                                </label>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-emerald py-2" data-loading-text="Memverifikasi...">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                            </button>
                        </div>
                    </form>

                    <p class="text-center text-muted small mb-0">
                        Belum punya akun pemilih?
                        <a href="<?= e(baseUrl('auth/register.php')) ?>" class="ks-link-accent">Daftar sekarang</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>

