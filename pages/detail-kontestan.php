<?php
declare(strict_types=1);

/**
 * Halaman Detail Profil Kontestan
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
$detailData = $kontestanController->getDetailKontestan($_GET['id'] ?? null);
$kontestan = $detailData['kontestan'];

if (!$detailData['found'] || $kontestan === null) {
    http_response_code(404);
}

$pageTitle = $kontestan !== null ? (string) $kontestan['nama'] : 'Kontestan Tidak Ditemukan';
$activeMenu = 'kontestan';
$bodyClass = 'd-flex flex-column min-vh-100';
$extraCss = ['css/landing.css'];
$showPublicFooter = true;

require_once dirname(__DIR__) . '/views/layouts/header.php';
require_once dirname(__DIR__) . '/views/layouts/navbar.php';
?>

<main class="flex-grow-1 py-4 py-lg-5">
    <div class="container">
        <div class="mb-3">
            <a href="<?= e(baseUrl('pages/kontestan.php')) ?>" class="small ks-link-accent">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar Kontestan
            </a>
        </div>

        <?php if ($kontestan === null): ?>
            <div class="ks-empty-state py-5">
                <span class="badge rounded-pill text-bg-secondary mb-3 px-3 py-2">HTTP 404</span>
                <i class="bi bi-person-x text-muted fs-1 d-block mb-2"></i>
                <h1 class="h4 fw-bold mb-2">404 &mdash; Kontestan Tidak Ditemukan</h1>
                <p class="small text-muted mb-4 mx-auto" style="max-width: 48ch;">
                    Maaf, profil kontestan yang Anda cari tidak valid, tidak tersedia di database, atau berstatus nonaktif sehingga tidak dapat menerima suara.
                </p>
                <a href="<?= e(baseUrl('pages/kontestan.php')) ?>" class="btn btn-emerald">
                    <i class="bi bi-people me-1"></i> Lihat Kontestan Aktif
                </a>
            </div>
        <?php else: ?>
            <?php
                $fotoName = basename((string) ($kontestan['foto'] ?? ''));
                $fotoPathDisk = UPLOAD_KONTESTAN_DIR . DIRECTORY_SEPARATOR . $fotoName;
                $fotoSrc = ($fotoName !== '' && is_file($fotoPathDisk))
                    ? uploadUrl($fotoName, 'kontestan')
                    : assetUrl('img/kontestan-placeholder.svg');
                $kategori = (string) ($kontestan['kategori'] ?? 'Kakang');
                $badgeClass = $kategori === 'Senduk' ? 'ks-badge-senduk' : 'ks-badge-kakang';

                $tempatLahir = trim((string) ($kontestan['tempat_lahir'] ?? ''));
                $tanggalLahir = !empty($kontestan['tanggal_lahir'])
                    ? formatTanggalIndonesia((string) $kontestan['tanggal_lahir'], false)
                    : '';
                $ttlText = trim($tempatLahir . ($tempatLahir !== '' && $tanggalLahir !== '' ? ', ' : '') . $tanggalLahir);
                if ($ttlText === '') {
                    $ttlText = '-';
                }
            ?>
            <div class="ks-card p-4 p-lg-5">
                <div class="row g-4 g-lg-5 align-items-start">
                    <!-- Kolom Foto Utama -->
                    <div class="col-lg-4">
                        <div class="ks-detail-photo-wrap mb-3">
                            <img src="<?= e($fotoSrc) ?>" alt="Foto <?= e($kontestan['nama']) ?>" class="ks-detail-photo">
                        </div>

                        <div class="d-grid gap-2">
                            <a
                                href="<?= e(baseUrl('pages/voting.php?kategori=' . urlencode($kategori) . '&kontestan_id=' . (int) $kontestan['id'])) ?>"
                                class="btn btn-emerald btn-lg"
                            >
                                <i class="bi bi-check2-square me-2"></i>Voting Kontestan Ini
                            </a>
                            <a href="<?= e(baseUrl('pages/klasemen.php')) ?>" class="btn btn-outline-emerald">
                                <i class="bi bi-trophy me-1"></i> Lihat Klasemen
                            </a>
                        </div>
                    </div>

                    <!-- Kolom Informasi Lengkap -->
                    <div class="col-lg-8">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge rounded-pill <?= e($badgeClass) ?> px-3 py-2">
                                Kategori <?= e($kategori) ?>
                            </span>
                            <span class="badge rounded-pill text-bg-dark px-3 py-2">
                                Nomor Peserta: <?= e($kontestan['nomor_peserta']) ?>
                            </span>
                            <span class="badge rounded-pill text-bg-success px-3 py-2">
                                <i class="bi bi-check-circle me-1"></i><?= e($kontestan['status']) ?>
                            </span>
                        </div>

                        <h1 class="h2 fw-bold mb-2"><?= e($kontestan['nama']) ?></h1>

                        <div class="row g-3 border-top border-bottom py-3 my-3 small">
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Asal Instansi</span>
                                <strong class="text-dark"><?= e($kontestan['asal_instansi']) ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Tempat, Tanggal Lahir</span>
                                <strong class="text-dark"><?= e($ttlText) ?></strong>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h2 class="h6 fw-bold text-emerald text-uppercase mb-2">Deskripsi</h2>
                            <p class="text-muted mb-0"><?= eMultiline($kontestan['deskripsi']) ?></p>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <h2 class="h6 fw-bold text-emerald text-uppercase mb-2">Visi</h2>
                                <div class="p-3 rounded-3 bg-light border small">
                                    <?= eMultiline($kontestan['visi']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h2 class="h6 fw-bold text-emerald text-uppercase mb-2">Misi</h2>
                                <div class="p-3 rounded-3 bg-light border small">
                                    <?= eMultiline($kontestan['misi']) ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h2 class="h6 fw-bold text-emerald text-uppercase mb-2">Prestasi</h2>
                            <div class="p-3 rounded-3 border bg-white small text-muted">
                                <?= !empty(trim((string) ($kontestan['prestasi'] ?? ''))) ? eMultiline($kontestan['prestasi']) : 'Belum ada catatan prestasi yang ditambahkan.' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>
