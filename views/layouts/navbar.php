<?php
declare(strict_types=1);

/**
 * Navbar Publik Konsisten & Responsif
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$activeMenu = $activeMenu ?? 'beranda';
$navUser = currentUser();
?>
<nav class="navbar navbar-expand-lg ks-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none" href="<?= e(baseUrl('index.php')) ?>">
            <span class="ks-brand-mark" aria-hidden="true">
                <i class="bi bi-award-fill"></i>
            </span>
            <span class="d-flex flex-column lh-sm">
                <span class="font-heading fw-bold fs-6 text-dark">Kakang Senduk</span>
                <small class="text-muted" style="font-size: 0.72rem;">Kabupaten Ponorogo</small>
            </span>
        </a>

        <button
            class="navbar-toggler border-0 shadow-none"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarPublik"
            aria-controls="navbarPublik"
            aria-expanded="false"
            aria-label="Buka navigasi"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarPublik">
            <ul class="navbar-nav mx-lg-auto mb-2 mb-lg-0 gap-lg-1 mt-3 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link ks-nav-link <?= $activeMenu === 'beranda' ? 'active' : '' ?>" href="<?= e(baseUrl('index.php')) ?>">
                        Beranda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ks-nav-link <?= $activeMenu === 'kontestan' ? 'active' : '' ?>" href="<?= e(baseUrl('pages/kontestan.php')) ?>">
                        Kontestan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ks-nav-link <?= $activeMenu === 'klasemen' ? 'active' : '' ?>" href="<?= e(baseUrl('pages/klasemen.php')) ?>">
                        Klasemen
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ks-nav-link" href="<?= e(baseUrl('index.php#tentang')) ?>">
                        Tentang
                    </a>
                </li>
            </ul>

            <div class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 pt-2 pt-lg-0 mt-2 mt-lg-0">
                <?php if ($navUser !== null): ?>
                    <?php if (($navUser['role'] ?? '') === 'admin'): ?>
                        <a href="<?= e(baseUrl('admin/dashboard.php')) ?>" class="btn btn-outline-emerald btn-sm px-3">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    <?php else: ?>
                        <span class="small text-muted px-2 text-truncate" style="max-width: 180px;">
                            <i class="bi bi-person-circle text-emerald me-1"></i><?= e($navUser['nama']) ?>
                        </span>
                    <?php endif; ?>

                    <a href="<?= e(baseUrl('pages/voting.php')) ?>" class="btn btn-emerald btn-sm px-3">
                        <i class="bi bi-check2-square me-1"></i> Voting
                    </a>

                    <form
                        method="POST"
                        action="<?= e(baseUrl('auth/logout.php')) ?>"
                        class="d-grid d-lg-inline"
                        data-confirm-submit
                        data-confirm-title="Konfirmasi Keluar"
                        data-confirm-text="Apakah Anda yakin ingin keluar dari akun ini?"
                        data-confirm-btn="Ya, Keluar"
                    >
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-outline-secondary btn-sm px-3 w-100">
                            <i class="bi bi-box-arrow-right me-1"></i> Keluar
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(baseUrl('auth/login.php')) ?>" class="btn btn-outline-emerald btn-sm px-3">
                        Masuk
                    </a>
                    <a href="<?= e(baseUrl('auth/register.php')) ?>" class="btn btn-outline-secondary btn-sm px-3">
                        Daftar
                    </a>
                    <a href="<?= e(baseUrl('pages/voting.php')) ?>" class="btn btn-emerald btn-sm px-3">
                        <i class="bi bi-check2-square me-1"></i> Voting
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
