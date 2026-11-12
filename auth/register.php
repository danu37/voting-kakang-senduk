<?php
declare(strict_types=1);

/**
 * Halaman Registrasi Pemilih Baru
 * Role publik dikunci otomatis sebagai 'pemilih'.
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';

AuthMiddleware::guestOnly($pdo);

$errors = [];
$old = [
    'nama'  => '',
    'email' => '',
    'no_hp' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new AuthController($pdo);
    $result = $authController->register($_POST);

    if ($result['success']) {
        setFlash('success', 'Registrasi Berhasil', 'Akun pemilih Anda telah terdaftar. Silakan masuk menggunakan email dan password Anda.');
        redirect('auth/login.php');
    }

    $errors = $result['errors'] ?? ['Registrasi gagal diproses.'];
    $old = $result['old'] ?? $old;
}

$pageTitle = 'Daftar Akun Pemilih';
$bodyClass = 'auth-wrapper d-flex align-items-center py-4 py-md-5';

require_once dirname(__DIR__) . '/views/layouts/header.php';
?>
<main class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-9">
            <div class="auth-card row g-0">
                <!-- Panel Identitas Ponorogo -->
                <div class="col-lg-5 auth-brand-panel ks-motif-subtle p-4 p-md-5 d-flex flex-column justify-content-between">
                    <div>
                        <span class="auth-badge-ponorogo mb-3">
                            <i class="bi bi-award-fill"></i> Duta Wisata Kabupaten Ponorogo
                        </span>
                        <h1 class="h3 fw-bold text-white mb-2">Registrasi Pemilih</h1>
                        <div class="auth-ornament-line mb-3"></div>
                        <p class="text-white-50 small mb-0">
                            Daftarkan akun pemilih resmi Anda untuk berpartisipasi dalam Pemilihan Kakang Senduk Ponorogo secara aman, jujur, dan transparan.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-top border-white border-opacity-10 small text-white-50">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-shield-check text-gold"></i>
                            <span>1 Pemilih: 1 Suara Kakang &amp; 1 Suara Senduk</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-lock-fill text-gold"></i>
                            <span>Password terenkripsi &amp; data terlindungi</span>
                        </div>
                    </div>
                </div>

                <!-- Panel Form Registrasi -->
                <div class="col-lg-7 p-4 p-md-5 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Buat Akun Baru</h2>
                        <a href="<?= e(baseUrl('index.php')) ?>" class="small ks-link-accent">
                            <i class="bi bi-arrow-left me-1"></i>Beranda
                        </a>
                    </div>

                    <?php require dirname(__DIR__) . '/views/components/alert.php'; ?>

                    <form method="POST" action="<?= e(baseUrl('auth/register.php')) ?>" data-loading-form novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="nama" class="form-label fw-medium small">Nama Lengkap <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="nama"
                                name="nama"
                                value="<?= e($old['nama'] ?? '') ?>"
                                placeholder="Masukkan nama lengkap Anda"
                                minlength="3"
                                maxlength="100"
                                required
                                autocomplete="name"
                            >
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium small">Alamat Email <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?= e($old['email'] ?? '') ?>"
                                placeholder="nama@contoh.com"
                                maxlength="100"
                                required
                                autocomplete="email"
                            >
                        </div>

                        <div class="mb-3">
                            <label for="no_hp" class="form-label fw-medium small">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
                            <input
                                type="tel"
                                class="form-control"
                                id="no_hp"
                                name="no_hp"
                                value="<?= e($old['no_hp'] ?? '') ?>"
                                placeholder="Contoh: 081234567890"
                                maxlength="20"
                                required
                                autocomplete="tel"
                            >
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label fw-medium small">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        placeholder="Min. 8 karakter"
                                        minlength="8"
                                        required
                                        autocomplete="new-password"
                                    >
                                    <button class="btn btn-toggle-password" type="button" data-toggle-password="password" aria-label="Tampilkan password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="konfirmasi_password" class="form-label fw-medium small">Konfirmasi Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="konfirmasi_password"
                                        name="konfirmasi_password"
                                        placeholder="Ulangi password"
                                        minlength="8"
                                        required
                                        autocomplete="new-password"
                                    >
                                    <button class="btn btn-toggle-password" type="button" data-toggle-password="konfirmasi_password" aria-label="Tampilkan konfirmasi password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-emerald py-2" data-loading-text="Mendaftarkan akun...">
                                <i class="bi bi-person-plus me-1"></i> Daftar Sebagai Pemilih
                            </button>
                        </div>
                    </form>

                    <p class="text-center text-muted small mb-0">
                        Sudah memiliki akun?
                        <a href="<?= e(baseUrl('auth/login.php')) ?>" class="ks-link-accent">Masuk di sini</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>
<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>

