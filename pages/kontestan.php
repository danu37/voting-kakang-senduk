<?php
declare(strict_types=1);

/**
 * Halaman Daftar Kontestan Publik
 * Mendukung filter kategori (Kakang/Senduk), pencarian nama, dan pagination.
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/KontestanController.php';

startSecureSession();
if (!isLoggedIn()) {
    (new AuthController($pdo))->attemptRememberLogin();
}

$kontestanController = new KontestanController($pdo);
$data = $kontestanController->getDaftarKontestan($_GET);

$items = $data['items'];
$total = (int) $data['total'];
$page = (int) $data['page'];
$totalPages = (int) $data['total_pages'];
$kategoriAktif = (string) $data['kategori'];
$cari = (string) $data['cari'];

$pageTitle = 'Daftar Kontestan';
$activeMenu = 'kontestan';
$bodyClass = 'd-flex flex-column min-vh-100';
$extraCss = ['css/landing.css'];
$showPublicFooter = true;

require_once dirname(__DIR__) . '/views/layouts/header.php';
require_once dirname(__DIR__) . '/views/layouts/navbar.php';
?>

<main class="flex-grow-1 py-4 py-lg-5">
    <div class="container">
        <!-- Header & Filter -->
        <div class="row align-items-end g-3 mb-4">
            <div class="col-lg-6">
                <span class="ks-section-kicker">Profil Peserta</span>
                <h1 class="h3 fw-bold mb-1">Daftar Kontestan Kakang &amp; Senduk</h1>
                <p class="text-muted small mb-0">
                    Menampilkan <?= count($items) ?> dari <?= $total ?> kontestan aktif<?= $kategoriAktif !== '' ? ' pada kategori ' . e($kategoriAktif) : '' ?><?= $totalPages > 1 ? ' &bull; Halaman ' . $page . ' dari ' . $totalPages : '' ?>.
                </p>
            </div>

            <div class="col-lg-6">
                <form method="GET" action="<?= e(baseUrl('pages/kontestan.php')) ?>" class="row g-2 justify-content-lg-end" data-loading-form>
                    <div class="col-sm-4">
                        <select name="kategori" class="form-select" aria-label="Filter kategori">
                            <option value="">Semua Kategori</option>
                            <option value="Kakang" <?= $kategoriAktif === 'Kakang' ? 'selected' : '' ?>>Kakang</option>
                            <option value="Senduk" <?= $kategoriAktif === 'Senduk' ? 'selected' : '' ?>>Senduk</option>
                        </select>
                    </div>
                    <div class="col-sm-5">
                        <input
                            type="search"
                            name="cari"
                            class="form-control"
                            value="<?= e($cari) ?>"
                            placeholder="Cari nama atau nomor..."
                            maxlength="100"
                            aria-label="Cari kontestan"
                        >
                    </div>
                    <div class="col-sm-3 d-grid">
                        <button type="submit" class="btn btn-emerald" data-loading-text="Mencari...">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab Filter Cepat -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 pb-2 border-bottom">
            <div class="d-flex flex-wrap gap-2">
                <?php
                    $buildFilterUrl = static function (string $kat) use ($cari): string {
                        $q = array_filter(['kategori' => $kat, 'cari' => $cari], static fn ($v) => $v !== '');
                        return baseUrl('pages/kontestan.php' . (!empty($q) ? '?' . http_build_query($q) : ''));
                    };
                ?>
                <a href="<?= e($buildFilterUrl('')) ?>" class="btn btn-sm <?= $kategoriAktif === '' ? 'btn-emerald' : 'btn-outline-emerald' ?>">
                    Semua
                </a>
                <a href="<?= e($buildFilterUrl('Kakang')) ?>" class="btn btn-sm <?= $kategoriAktif === 'Kakang' ? 'btn-emerald' : 'btn-outline-emerald' ?>">
                    Kakang
                </a>
                <a href="<?= e($buildFilterUrl('Senduk')) ?>" class="btn btn-sm <?= $kategoriAktif === 'Senduk' ? 'btn-emerald' : 'btn-outline-emerald' ?>">
                    Senduk
                </a>
            </div>

            <?php if ($kategoriAktif !== '' || $cari !== ''): ?>
                <a href="<?= e(baseUrl('pages/kontestan.php')) ?>" class="small ks-link-accent">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                </a>
            <?php endif; ?>
        </div>

        <!-- Grid Kontestan -->
        <?php if (!empty($items)): ?>
            <div class="row g-4">
                <?php foreach ($items as $kontestan): ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <?php
                            $showVoteButton = true;
                            require dirname(__DIR__) . '/views/components/kontestan-card.php';
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
                $paginationPage = $page;
                $paginationTotalPages = $totalPages;
                $paginationBasePath = 'pages/kontestan.php';
                $paginationQuery = [
                    'kategori' => $kategoriAktif,
                    'cari'     => $cari,
                ];
                require dirname(__DIR__) . '/views/components/pagination.php';
            ?>
        <?php else: ?>
            <div class="ks-empty-state">
                <i class="bi bi-search text-muted fs-1 d-block mb-2"></i>
                <h2 class="h6 fw-bold mb-1">
                    <?= ($kategoriAktif !== '' || $cari !== '') ? 'Kontestan Tidak Ditemukan' : 'Belum Ada Kontestan Terdaftar' ?>
                </h2>
                <p class="small text-muted mb-3">
                    <?php if ($kategoriAktif !== '' || $cari !== ''): ?>
                        Tidak ada kontestan aktif yang cocok dengan filter atau kata kunci pencarian Anda.
                    <?php else: ?>
                        Data kontestan Kakang dan Senduk belum tersedia di database saat ini.
                    <?php endif; ?>
                </p>
                <?php if ($kategoriAktif !== '' || $cari !== ''): ?>
                    <a href="<?= e(baseUrl('pages/kontestan.php')) ?>" class="btn btn-emerald btn-sm">
                        Tampilkan Semua Kontestan
                    </a>
                <?php else: ?>
                    <a href="<?= e(baseUrl('index.php')) ?>" class="btn btn-outline-emerald btn-sm">
                        Kembali ke Beranda
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>

