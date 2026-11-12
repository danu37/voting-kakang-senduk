<?php
declare(strict_types=1);

/**
 * Halaman Klasemen Sementara Pemilihan Kakang & Senduk
 * Memisahkan kategori Kakang dan Senduk, menampilkan podium 1-3, statistik total suara,
 * persentase suara, tie-breaker konsisten, filter periode, dan polling AJAX berkala.
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/KlasemenController.php';

startSecureSession();
if (!isLoggedIn()) {
    (new AuthController($pdo))->attemptRememberLogin();
}

$klasemenController = new KlasemenController($pdo);

if (strtolower(sanitizeText($_GET['format'] ?? '')) === 'json') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    jsonResponse($klasemenController->getKlasemenJsonPayload($_GET), 200);
}

$data = $klasemenController->getKlasemenData($_GET);

$daftarPeriode = $data['daftar_periode'];
$periodeTerpilih = $data['periode_terpilih'];
$klasemenKakang = $data['klasemen_kakang'];
$klasemenSenduk = $data['klasemen_senduk'];

$periodeIdParam = is_array($periodeTerpilih) && isset($periodeTerpilih['id'])
    ? '&periode_id=' . (int) $periodeTerpilih['id']
    : '';
$pollEndpointUrl = baseUrl('pages/klasemen.php?format=json' . $periodeIdParam);

$renderKategoriSection = static function (string $judul, array $klasemenData): void {
    $key = strtolower($judul);
    $items = $klasemenData['items'] ?? [];
    $podium = $klasemenData['podium'] ?? [];
    $totalSuara = (int) ($klasemenData['total_suara'] ?? 0);
    ?>
    <section class="mb-5" aria-label="Klasemen kategori <?= e($judul) ?>">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h4 fw-bold mb-0">Klasemen Kategori <?= e($judul) ?></h2>
                <small class="text-muted">
                    Total suara kategori <?= e($judul) ?>:
                    <strong id="ks-total-label-<?= e($key) ?>"><?= number_format($totalSuara, 0, ',', '.') ?> suara</strong>
                </small>
            </div>
        </div>

        <!-- Podium Juara 1, 2, 3 -->
        <div id="ks-podium-container-<?= e($key) ?>">
            <?php if (!empty($podium)): ?>
                <div class="row g-3 mb-4 align-items-end justify-content-center">
                    <?php
                        $orderMap = [1 => 'order-md-2', 2 => 'order-md-1', 3 => 'order-md-3'];
                        $medalMap = [1 => 'Juara 1', 2 => 'Juara 2', 3 => 'Juara 3'];
                    ?>
                    <?php foreach ($podium as $row): ?>
                        <?php
                            $rank = (int) $row['ranking'];
                            $orderClass = $orderMap[$rank] ?? '';
                            $rankClass = $rank === 1 ? 'ks-podium-rank-1' : '';
                        ?>
                        <div class="col-12 col-md-4 <?= e($orderClass) ?>">
                            <div class="ks-podium-slot <?= e($rankClass) ?>">
                                <span class="badge rounded-pill <?= $rank === 1 ? 'text-bg-warning' : 'text-bg-secondary' ?> mb-2 px-3 py-1">
                                    <i class="bi bi-trophy-fill me-1"></i><?= e($medalMap[$rank] ?? "Peringkat {$rank}") ?>
                                </span>
                                <img src="<?= e(kontestanFotoUrl($row['foto'] ?? null)) ?>" alt="Foto <?= e($row['nama']) ?>" class="ks-podium-avatar" loading="lazy" decoding="async">
                                <div class="small text-muted fw-semibold"><?= e($row['nomor_peserta']) ?></div>
                                <h3 class="h6 fw-bold mb-1"><?= e($row['nama']) ?></h3>
                                <div class="small text-muted mb-1"><?= e($row['asal_instansi']) ?></div>
                                <div class="small text-emerald fw-bold">
                                    <?= number_format((int) $row['jumlah_suara'], 0, ',', '.') ?> suara (<?= e(number_format((float) $row['persentase'], 2, ',', '.')) ?>%)
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border small text-muted mb-4">
                    <i class="bi bi-info-circle me-1"></i>Belum ada suara yang masuk untuk kategori <?= e($judul) ?> pada periode ini. Podium akan tampil setelah suara masuk.
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabel Klasemen Lengkap (termasuk 0 suara via LEFT JOIN) -->
        <div id="ks-table-container-<?= e($key) ?>">
            <?php if (!empty($items)): ?>
                <div class="ks-card overflow-hidden">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th style="width: 72px;">Rank</th>
                                    <th>Kontestan</th>
                                    <th>No. Peserta</th>
                                    <th>Asal Instansi</th>
                                    <th class="text-end">Jumlah Suara</th>
                                    <th style="min-width: 170px;">Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $row): ?>
                                    <tr>
                                        <td class="fw-bold">
                                            #<?= (int) $row['ranking'] ?>
                                            <?php if (!empty($row['is_seri'])): ?>
                                                <span class="badge text-bg-light border text-muted ms-1 fw-normal" title="Urutan tie-breaker berdasarkan nomor peserta">Seri</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?= e(kontestanFotoUrl($row['foto'] ?? null)) ?>" alt="Foto <?= e($row['nama']) ?>" class="ks-avatar-thumb" loading="lazy" decoding="async">
                                                <a href="<?= e(baseUrl('pages/detail-kontestan.php?id=' . (int) $row['id'])) ?>" class="fw-semibold text-dark text-decoration-none">
                                                    <?= e($row['nama']) ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><span class="badge text-bg-light border"><?= e($row['nomor_peserta']) ?></span></td>
                                        <td class="small text-muted"><?= e($row['asal_instansi']) ?></td>
                                        <td class="text-end fw-semibold"><?= number_format((int) $row['jumlah_suara'], 0, ',', '.') ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress ks-progress flex-grow-1" role="progressbar" aria-valuenow="<?= e((string) $row['persentase']) ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar ks-progress-bar" style="width: <?= e((string) min(100, (float) $row['persentase'])) ?>%;"></div>
                                                </div>
                                                <span class="small fw-semibold text-nowrap"><?= e(number_format((float) $row['persentase'], 2, ',', '.')) ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="ks-empty-state">
                    <p class="small text-muted mb-0">Belum ada kontestan aktif pada kategori <?= e($judul) ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
};

$pageTitle = 'Klasemen Sementara';
$activeMenu = 'klasemen';
$bodyClass = 'd-flex flex-column min-vh-100';
$extraCss = ['css/landing.css'];
$extraJs = ['js/klasemen.js'];
$showPublicFooter = true;

require_once dirname(__DIR__) . '/views/layouts/header.php';
require_once dirname(__DIR__) . '/views/layouts/navbar.php';
?>

<main class="flex-grow-1 py-4 py-lg-5" id="ks-klasemen-root" data-poll-url="<?= e($pollEndpointUrl) ?>">
    <div class="container">
        <!-- Header & Filter Periode -->
        <div class="row align-items-end g-3 mb-4 pb-3 border-bottom">
            <div class="col-lg-6 min-w-0">
                <span class="ks-section-kicker">Perolehan Suara Real-Time</span>
                <h1 class="h3 fw-bold mb-1">Klasemen Sementara Kakang &amp; Senduk</h1>
                <p class="text-muted small mb-0">
                    Periode:
                    <strong><?= $periodeTerpilih !== null ? e($periodeTerpilih['nama_periode']) : 'Belum Ada Periode' ?></strong>
                    <?php if ($periodeTerpilih !== null): ?>
                        &bull; Status: <strong><?= e($periodeTerpilih['jadwal_status']) ?></strong>
                    <?php endif; ?>
                </p>
            </div>

            <div class="col-lg-6">
                <form method="GET" action="<?= e(baseUrl('pages/klasemen.php')) ?>" class="d-flex flex-column flex-sm-row gap-2 justify-content-lg-end">
                    <select name="periode_id" class="form-select" style="max-width: 420px;" aria-label="Pilih periode voting">
                        <?php if (empty($daftarPeriode)): ?>
                            <option value="">Belum ada periode tersedia</option>
                        <?php else: ?>
                            <?php foreach ($daftarPeriode as $per): ?>
                                <?php $selected = ($periodeTerpilih !== null && (int) $per['id'] === (int) $periodeTerpilih['id']); ?>
                                <option value="<?= (int) $per['id'] ?>" <?= $selected ? 'selected' : '' ?>>
                                    <?= e($per['nama_periode']) ?> (<?= e($per['jadwal_status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <button type="submit" class="btn btn-emerald text-nowrap">
                        Tampilkan
                    </button>
                </form>
            </div>
        </div>

        <!-- Ringkasan Statistik Suara & Waktu Pembaruan -->
        <section class="ks-card p-3 p-md-4 mb-5" aria-label="Statistik perolehan suara">
            <div class="row g-3 align-items-center">
                <div class="col-6 col-md-3">
                    <div class="small text-muted">Total Suara Kakang</div>
                    <div class="h4 fw-bold text-emerald mb-0" id="ks-stat-kakang">
                        <?= number_format((int) $data['total_kakang'], 0, ',', '.') ?>
                    </div>
                </div>
                <div class="col-6 col-md-3 ks-stat-divider">
                    <div class="small text-muted">Total Suara Senduk</div>
                    <div class="h4 fw-bold mb-0" style="color: #856404;" id="ks-stat-senduk">
                        <?= number_format((int) $data['total_senduk'], 0, ',', '.') ?>
                    </div>
                </div>
                <div class="col-6 col-md-3 ks-stat-divider">
                    <div class="small text-muted">Total Keseluruhan</div>
                    <div class="h4 fw-bold mb-0" id="ks-stat-total">
                        <?= number_format((int) $data['total_semua'], 0, ',', '.') ?>
                    </div>
                </div>
                <div class="col-6 col-md-3 text-md-end">
                    <div class="small text-muted">Waktu Pembaruan</div>
                    <div class="small fw-semibold text-dark" id="ks-klasemen-updated-at">
                        <?= e($data['waktu_pembaruan_detik']) ?>
                    </div>
                    <div class="d-flex flex-wrap justify-content-md-end align-items-center gap-2 mt-1">
                        <span class="badge rounded-pill text-bg-light border text-muted fw-normal">
                            <i class="bi bi-arrow-repeat text-emerald me-1"></i>Polling 15 dtk
                        </span>
                        <button type="button" id="ks-btn-refresh-klasemen" class="btn btn-outline-emerald btn-sm py-0 px-2">
                            <i class="bi bi-arrow-clockwise me-1"></i>Segarkan
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <?php
            $renderKategoriSection('Kakang', $klasemenKakang);
            $renderKategoriSection('Senduk', $klasemenSenduk);
        ?>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>
