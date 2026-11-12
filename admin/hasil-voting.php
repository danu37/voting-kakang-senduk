<?php
declare(strict_types=1);

/**
 * Halaman Hasil Voting Administrator
 * Menampilkan perolehan suara Kakang & Senduk, statistik kategori, ranking lengkap,
 * grafik Chart.js, filter periode, serta ekspor laporan Excel (.xlsx) dan PDF (.pdf).
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);

$adminController = new AdminController($pdo);
$data = $adminController->getHasilVotingData($_GET);

$daftarPeriode = $data['daftar_periode'];
$periodeTerpilih = $data['periode_terpilih'];
$hasilKakang = $data['hasil_kakang'];
$hasilSenduk = $data['hasil_senduk'];
$statKategori = $data['statistik_kategori'] ?? [];
$itemsKakang = $hasilKakang['items'] ?? [];
$itemsSenduk = $hasilSenduk['items'] ?? [];

$periodeQuery = $periodeTerpilih && isset($periodeTerpilih['id'])
    ? '&periode_id=' . (int) $periodeTerpilih['id']
    : '';
$periodeQueryFirst = $periodeTerpilih && isset($periodeTerpilih['id'])
    ? '?periode_id=' . (int) $periodeTerpilih['id']
    : '';

$chartKakangJson = json_encode([
    'type'         => 'bar',
    'datasetLabel' => 'Suara Kakang',
    'labels'       => array_map(static fn (array $r): string => ($r['nomor_peserta'] ?? '') . ' ' . ($r['nama'] ?? ''), $itemsKakang),
    'values'       => array_map(static fn (array $r): int => (int) ($r['jumlah_suara'] ?? 0), $itemsKakang),
    'colors'       => '#047857',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$chartSendukJson = json_encode([
    'type'         => 'bar',
    'datasetLabel' => 'Suara Senduk',
    'labels'       => array_map(static fn (array $r): string => ($r['nomor_peserta'] ?? '') . ' ' . ($r['nama'] ?? ''), $itemsSenduk),
    'values'       => array_map(static fn (array $r): int => (int) ($r['jumlah_suara'] ?? 0), $itemsSenduk),
    'colors'       => '#D4AF37',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$chartKategoriJson = json_encode([
    'type'         => 'doughnut',
    'datasetLabel' => 'Proporsi Suara Kategori',
    'labels'       => ['Kakang', 'Senduk'],
    'values'       => [(int) $data['total_kakang'], (int) $data['total_senduk']],
    'colors'       => ['#047857', '#D4AF37'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$pageTitle = 'Hasil Voting';
$adminMenu = 'hasil';
$adminHeading = 'Hasil & Rekapitulasi Voting';
$extraCss = ['css/admin.css'];
$extraJs = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
    'js/admin.js',
];
$errors = [];

require_once dirname(__DIR__) . '/views/layouts/header.php';
require_once dirname(__DIR__) . '/views/layouts/admin-sidebar.php';
?>

<!-- Filter Periode, Ekspor Laporan & Ringkasan -->
<section class="admin-panel-card p-3 p-md-4 mb-4">
    <div class="row g-3 align-items-start justify-content-between">
        <div class="col-12 col-lg-6 min-w-0">
            <h2 class="h5 fw-bold mb-1">
                <?= $periodeTerpilih !== null ? e($periodeTerpilih['nama_periode']) : 'Belum Ada Periode Voting' ?>
            </h2>
            <p class="small text-muted mb-0">
                Pembaruan data: <strong><?= e($data['waktu_pembaruan_detik'] ?? $data['waktu_pembaruan']) ?></strong>
                <?php if ($periodeTerpilih !== null): ?>
                    &bull; Status Jadwal: <span class="fw-semibold text-dark"><?= e($periodeTerpilih['jadwal_status']) ?></span>
                <?php endif; ?>
            </p>
        </div>

        <div class="col-12 col-lg-6">
            <form method="GET" action="<?= e(baseUrl('admin/hasil-voting.php')) ?>" class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 justify-content-lg-end mb-2" data-loading-form>
                <label for="periode_id" class="visually-hidden">Filter Periode Voting</label>
                <select name="periode_id" id="periode_id" class="form-select form-select-sm flex-grow-1" style="max-width: 340px;">
                    <?php if (empty($daftarPeriode)): ?>
                        <option value="">Belum ada periode tersedia</option>
                    <?php else: ?>
                        <?php foreach ($daftarPeriode as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= ($periodeTerpilih && (int) $periodeTerpilih['id'] === (int) $p['id']) ? 'selected' : '' ?>>
                                <?= e($p['nama_periode']) ?> (<?= e($p['jadwal_status']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="submit" class="btn btn-emerald btn-sm text-nowrap flex-shrink-0">
                    <i class="bi bi-funnel me-1"></i> Tampilkan
                </button>
            </form>

            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <a href="<?= e(baseUrl('admin/export-hasil.php?format=xlsx' . $periodeQuery)) ?>" class="btn btn-outline-success btn-sm text-nowrap">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                </a>
                <a href="<?= e(baseUrl('admin/export-hasil.php?format=pdf' . $periodeQuery)) ?>" class="btn btn-outline-danger btn-sm text-nowrap">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                </a>
                <a href="<?= e(baseUrl('pages/klasemen.php' . $periodeQueryFirst)) ?>" class="btn btn-outline-emerald btn-sm text-nowrap">
                    <i class="bi bi-trophy me-1"></i> Klasemen Publik
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2 pt-3 border-top">
        <div class="col-6 col-md-3">
            <div class="small text-muted">Total Suara Masuk</div>
            <div class="h4 fw-bold mb-0"><?= number_format((int) $data['total_semua'], 0, ',', '.') ?></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="small text-muted">Total Suara Kakang</div>
            <div class="h4 fw-bold text-emerald mb-0"><?= number_format((int) $data['total_kakang'], 0, ',', '.') ?></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="small text-muted">Total Suara Senduk</div>
            <div class="h4 fw-bold mb-0" style="color: #856404;"><?= number_format((int) $data['total_senduk'], 0, ',', '.') ?></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="small text-muted">Pemilih Berpartisipasi</div>
            <div class="h4 fw-bold text-dark mb-0"><?= number_format((int) ($data['total_pemilih_unik'] ?? 0), 0, ',', '.') ?></div>
        </div>
    </div>
</section>

<!-- Statistik Kategori & Grafik Proporsi Kategori -->
<section class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
        <div class="admin-panel-card p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">Statistik per Kategori</h3>
                <span class="badge rounded-pill text-bg-light border text-dark">Periode Terpilih</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kategori</th>
                            <th class="text-center">Kontestan Aktif</th>
                            <th class="text-end">Total Suara</th>
                            <th class="text-end">Porsi Total</th>
                            <th style="min-width: 155px;">Pemimpin Sementara</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['Kakang', 'Senduk'] as $katKey): ?>
                            <?php
                                $st = $statKategori[$katKey] ?? [];
                                $leader = $st['pemimpin'] ?? null;
                                $katBadge = $katKey === 'Senduk' ? 'ks-badge-senduk' : 'ks-badge-kakang';
                            ?>
                            <tr>
                                <td><span class="badge rounded-pill <?= e($katBadge) ?>"><?= e($katKey) ?></span></td>
                                <td class="text-center fw-semibold"><?= (int) ($st['jumlah_kontestan'] ?? 0) ?></td>
                                <td class="text-end fw-semibold"><?= number_format((int) ($st['total_suara'] ?? 0), 0, ',', '.') ?></td>
                                <td class="text-end"><?= number_format((float) ($st['persentase_keseluruhan'] ?? 0.0), 2, ',', '.') ?>%</td>
                                <td>
                                    <?php if (is_array($leader)): ?>
                                        <span class="fw-semibold text-dark"><?= e($leader['nomor_peserta']) ?> &mdash; <?= e($leader['nama']) ?></span>
                                        <span class="text-muted">(<?= number_format((int) $leader['jumlah_suara'], 0, ',', '.') ?> suara)</span>
                                    <?php else: ?>
                                        <span class="text-muted">Belum ada suara</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="admin-panel-card p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">Proporsi Suara Kakang vs Senduk</h3>
                <span class="badge rounded-pill text-bg-light border text-dark">
                    <?= number_format((int) $data['total_semua'], 0, ',', '.') ?> suara
                </span>
            </div>
            <?php if ((int) $data['total_semua'] > 0): ?>
                <div class="admin-chart-wrap" style="height: 220px;">
                    <canvas data-chart-json="hasil-chart-kategori" aria-label="Grafik proporsi suara Kakang vs Senduk"></canvas>
                </div>
                <script id="hasil-chart-kategori" type="application/json"><?= $chartKategoriJson ?></script>
            <?php else: ?>
                <div class="ks-empty-state py-4">
                    <p class="small text-muted mb-0">Grafik proporsi kategori akan tampil setelah suara masuk pada periode ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Grafik Kakang & Senduk -->
<section class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="admin-panel-card p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">Grafik Perolehan Kakang</h3>
                <span class="badge rounded-pill ks-badge-kakang">Total <?= number_format((int) $data['total_kakang'], 0, ',', '.') ?> suara</span>
            </div>
            <?php if (!empty($itemsKakang)): ?>
                <div class="admin-chart-wrap">
                    <canvas data-chart-json="hasil-chart-kakang" aria-label="Grafik hasil voting Kakang"></canvas>
                </div>
                <script id="hasil-chart-kakang" type="application/json"><?= $chartKakangJson ?></script>
            <?php else: ?>
                <div class="ks-empty-state py-4">
                    <p class="small text-muted mb-0">Belum ada kontestan Kakang aktif.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="admin-panel-card p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">Grafik Perolehan Senduk</h3>
                <span class="badge rounded-pill ks-badge-senduk">Total <?= number_format((int) $data['total_senduk'], 0, ',', '.') ?> suara</span>
            </div>
            <?php if (!empty($itemsSenduk)): ?>
                <div class="admin-chart-wrap">
                    <canvas data-chart-json="hasil-chart-senduk" aria-label="Grafik hasil voting Senduk"></canvas>
                </div>
                <script id="hasil-chart-senduk" type="application/json"><?= $chartSendukJson ?></script>
            <?php else: ?>
                <div class="ks-empty-state py-4">
                    <p class="small text-muted mb-0">Belum ada kontestan Senduk aktif.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Tabel Hasil Lengkap per Kategori -->
<?php
$kategoriTables = [
    ['title' => 'Peringkat Kategori Kakang', 'badge' => 'ks-badge-kakang', 'items' => $itemsKakang],
    ['title' => 'Peringkat Kategori Senduk', 'badge' => 'ks-badge-senduk', 'items' => $itemsSenduk],
];
?>
<section class="row g-4">
    <?php foreach ($kategoriTables as $katTable): ?>
        <div class="col-12">
            <div class="admin-panel-card p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0"><?= e($katTable['title']) ?></h3>
                    <span class="badge rounded-pill <?= e($katTable['badge']) ?>"><?= count($katTable['items']) ?> Kontestan</span>
                </div>

                <?php if (!empty($katTable['items'])): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 86px;">Peringkat</th>
                                    <th>Kontestan</th>
                                    <th>Asal Instansi</th>
                                    <th class="text-end">Suara</th>
                                    <th style="min-width: 170px;">Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($katTable['items'] as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?= (int) $row['ranking'] <= 3 && (int) $row['jumlah_suara'] > 0 ? 'text-bg-warning' : 'text-bg-light border text-dark' ?>">
                                                #<?= (int) $row['ranking'] ?>
                                            </span>
                                            <?php if (!empty($row['is_seri'])): ?>
                                                <span class="badge text-bg-light border text-muted fw-normal">Seri</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img
                                                    src="<?= e(kontestanFotoUrl($row['foto'] ?? null)) ?>"
                                                    alt="Foto <?= e($row['nama']) ?>"
                                                    class="admin-thumb-4x5"
                                                    loading="lazy"
                                                    decoding="async"
                                                >
                                                <div>
                                                    <div class="fw-semibold"><?= e($row['nama']) ?></div>
                                                    <div class="text-muted"><?= e($row['nomor_peserta']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-muted"><?= e($row['asal_instansi']) ?></td>
                                        <td class="text-end fw-semibold"><?= number_format((int) $row['jumlah_suara'], 0, ',', '.') ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress ks-progress flex-grow-1">
                                                    <div
                                                        class="progress-bar ks-progress-bar"
                                                        role="progressbar"
                                                        style="width: <?= e((string) min(100, (float) $row['persentase'])) ?>%;"
                                                        aria-valuenow="<?= e((string) $row['persentase']) ?>"
                                                        aria-valuemin="0"
                                                        aria-valuemax="100"
                                                    ></div>
                                                </div>
                                                <span class="text-muted text-nowrap" style="width: 58px; text-align: right;">
                                                    <?= number_format((float) $row['persentase'], 2, ',', '.') ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="ks-empty-state py-4">
                        <p class="small text-muted mb-0">Belum ada data kontestan aktif untuk kategori ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

</main>
</div>
</div>
<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>
