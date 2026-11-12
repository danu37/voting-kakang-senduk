<?php
declare(strict_types=1);

/**
 * Dashboard Administrator
 * Menampilkan ringkasan statistik, status periode, grafik perolehan suara,
 * kontestan dengan suara tertinggi, dan aktivitas terbaru.
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);

$adminController = new AdminController($pdo);
$data = $adminController->getDashboardData();

$periodeAcuan = $data['periode_acuan'];
$overallTotals = $data['overall_totals'] ?? ['total' => 0, 'kakang' => 0, 'senduk' => 0];
$klasemenKakang = $data['klasemen_kakang']['items'] ?? [];
$klasemenSenduk = $data['klasemen_senduk']['items'] ?? [];
$topKakang = $data['top_kakang'];
$topSenduk = $data['top_senduk'];
$aktivitasTerbaru = $data['aktivitas_terbaru'];

$chartKakangJson = json_encode([
    'type'         => 'bar',
    'datasetLabel' => 'Suara Kakang',
    'labels'       => array_map(
        static fn (array $r): string => ($r['nomor_peserta'] ?? '') . ' ' . ($r['nama'] ?? ''),
        $klasemenKakang
    ),
    'values'       => array_map(static fn (array $r): int => (int) ($r['jumlah_suara'] ?? 0), $klasemenKakang),
    'colors'       => '#047857',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$chartSendukJson = json_encode([
    'type'         => 'bar',
    'datasetLabel' => 'Suara Senduk',
    'labels'       => array_map(
        static fn (array $r): string => ($r['nomor_peserta'] ?? '') . ' ' . ($r['nama'] ?? ''),
        $klasemenSenduk
    ),
    'values'       => array_map(static fn (array $r): int => (int) ($r['jumlah_suara'] ?? 0), $klasemenSenduk),
    'colors'       => '#D4AF37',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$jadwalBadgeClass = 'text-bg-secondary';
if ($periodeAcuan !== null) {
    $jadwalBadgeClass = match ($periodeAcuan['jadwal_status'] ?? '') {
        'Sedang Berlangsung' => 'text-bg-success',
        'Belum Dimulai'      => 'text-bg-warning',
        'Sudah Berakhir'     => 'text-bg-dark',
        default              => 'text-bg-secondary',
    };
}

$pageTitle = 'Dashboard Administrator';
$adminMenu = 'dashboard';
$adminHeading = 'Dashboard Administrator';
$extraCss = ['css/admin.css'];
$extraJs = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
    'js/admin.js',
];
$errors = [];

require_once dirname(__DIR__) . '/views/layouts/header.php';
require_once dirname(__DIR__) . '/views/layouts/admin-sidebar.php';
?>

<!-- Banner Status Periode Voting -->
<section class="admin-panel-card p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="small fw-semibold text-uppercase text-muted" style="letter-spacing: 0.05em;">Status Periode Voting</span>
                <?php if ($periodeAcuan !== null): ?>
                    <span class="badge rounded-pill <?= e($jadwalBadgeClass) ?>">
                        <?= e($periodeAcuan['jadwal_status']) ?>
                    </span>
                <?php else: ?>
                    <span class="badge rounded-pill text-bg-secondary">Belum Ada Periode</span>
                <?php endif; ?>
            </div>
            <?php if ($periodeAcuan !== null): ?>
                <h2 class="h5 fw-bold mb-1"><?= e($periodeAcuan['nama_periode']) ?></h2>
                <p class="small text-muted mb-0">
                    <i class="bi bi-calendar3 me-1 text-emerald"></i>
                    <?= e(formatTanggalIndonesia($periodeAcuan['tanggal_mulai'], true)) ?>
                    &ndash;
                    <?= e(formatTanggalIndonesia($periodeAcuan['tanggal_selesai'], true)) ?>
                </p>
            <?php else: ?>
                <h2 class="h6 fw-bold mb-1">Periode voting belum dikonfigurasi</h2>
                <p class="small text-muted mb-0">Tambahkan dan aktifkan periode voting agar pemilih dapat memberikan suara.</p>
            <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap gap-2 flex-shrink-0">
            <a href="<?= e(baseUrl('admin/periode/index.php')) ?>" class="btn btn-outline-emerald btn-sm">
                <i class="bi bi-calendar-event me-1"></i> Kelola Periode
            </a>
            <a href="<?= e(baseUrl('admin/kontestan/tambah.php')) ?>" class="btn btn-emerald btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Tambah Kontestan
            </a>
        </div>
    </div>
</section>

<!-- Ringkasan Statistik Peserta & Pemilih -->
<section class="row g-3 mb-3" aria-label="Ringkasan statistik peserta dan pemilih">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="admin-stat-card d-flex align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted mb-1">Total Kontestan</div>
                <div class="h3 fw-bold mb-1"><?= number_format((int) $data['total_kontestan'], 0, ',', '.') ?></div>
                <div class="small text-muted"><?= number_format((int) $data['kontestan_aktif'], 0, ',', '.') ?> berstatus aktif</div>
            </div>
            <span class="admin-stat-icon" style="background: rgba(4, 120, 87, 0.12); color: #047857;">
                <i class="bi bi-people-fill"></i>
            </span>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="admin-stat-card d-flex align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted mb-1">Total Kakang</div>
                <div class="h3 fw-bold mb-1"><?= number_format((int) $data['total_kakang'], 0, ',', '.') ?></div>
                <div class="small text-muted">Kontestan kategori Kakang</div>
            </div>
            <span class="admin-stat-icon" style="background: rgba(6, 78, 59, 0.12); color: #064E3B;">
                <i class="bi bi-person-standing"></i>
            </span>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="admin-stat-card d-flex align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted mb-1">Total Senduk</div>
                <div class="h3 fw-bold mb-1"><?= number_format((int) $data['total_senduk'], 0, ',', '.') ?></div>
                <div class="small text-muted">Kontestan kategori Senduk</div>
            </div>
            <span class="admin-stat-icon" style="background: rgba(212, 175, 55, 0.16); color: #856404;">
                <i class="bi bi-person-standing-dress"></i>
            </span>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="admin-stat-card d-flex align-items-start justify-content-between gap-3">
            <div>
                <div class="small text-muted mb-1">Total Pemilih</div>
                <div class="h3 fw-bold mb-1"><?= number_format((int) $data['total_pemilih'], 0, ',', '.') ?></div>
                <div class="small text-muted">Akun pemilih terdaftar</div>
            </div>
            <span class="admin-stat-icon" style="background: rgba(4, 120, 87, 0.12); color: #047857;">
                <i class="bi bi-person-check-fill"></i>
            </span>
        </div>
    </div>
</section>

<!-- Ringkasan Statistik Suara Masuk -->
<section class="admin-panel-card p-3 p-md-4 mb-4" aria-label="Ringkasan statistik perolehan suara">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
        <h2 class="h6 fw-bold mb-0">Statistik Perolehan Suara</h2>
        <span class="small text-muted">
            <?= $periodeAcuan !== null ? 'Periode acuan: ' . e($periodeAcuan['nama_periode']) : 'Seluruh periode' ?>
        </span>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                <div class="small text-muted mb-1">Total Suara Kakang</div>
                <div class="h3 fw-bold text-emerald mb-0"><?= number_format((int) $data['total_suara_kakang'], 0, ',', '.') ?></div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                <div class="small text-muted mb-1">Total Suara Senduk</div>
                <div class="h3 fw-bold mb-0" style="color: #856404;"><?= number_format((int) $data['total_suara_senduk'], 0, ',', '.') ?></div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                <div class="small text-muted mb-1">Total Suara Keseluruhan</div>
                <div class="h3 fw-bold mb-0"><?= number_format((int) $data['total_suara_semua'], 0, ',', '.') ?></div>
                <?php if ((int) ($overallTotals['total'] ?? 0) !== (int) $data['total_suara_semua']): ?>
                    <div class="small text-muted mt-1">
                        Total semua periode: <?= number_format((int) $overallTotals['total'], 0, ',', '.') ?> suara
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Grafik Perolehan Suara -->
<section class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="admin-panel-card p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h6 fw-bold mb-0">Grafik Perolehan Suara Kakang</h2>
                    <small class="text-muted">Perbandingan suara kontestan Kakang aktif</small>
                </div>
                <span class="badge rounded-pill ks-badge-kakang">Kakang</span>
            </div>

            <?php if (!empty($klasemenKakang)): ?>
                <div class="admin-chart-wrap">
                    <canvas data-chart-json="chart-dashboard-kakang" aria-label="Grafik perolehan suara Kakang"></canvas>
                </div>
                <script id="chart-dashboard-kakang" type="application/json"><?= $chartKakangJson ?></script>
            <?php else: ?>
                <div class="ks-empty-state py-4">
                    <i class="bi bi-bar-chart fs-3 text-muted d-block mb-2"></i>
                    <p class="small text-muted mb-0">Belum ada kontestan Kakang aktif untuk ditampilkan pada grafik.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="admin-panel-card p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h6 fw-bold mb-0">Grafik Perolehan Suara Senduk</h2>
                    <small class="text-muted">Perbandingan suara kontestan Senduk aktif</small>
                </div>
                <span class="badge rounded-pill ks-badge-senduk">Senduk</span>
            </div>

            <?php if (!empty($klasemenSenduk)): ?>
                <div class="admin-chart-wrap">
                    <canvas data-chart-json="chart-dashboard-senduk" aria-label="Grafik perolehan suara Senduk"></canvas>
                </div>
                <script id="chart-dashboard-senduk" type="application/json"><?= $chartSendukJson ?></script>
            <?php else: ?>
                <div class="ks-empty-state py-4">
                    <i class="bi bi-bar-chart fs-3 text-muted d-block mb-2"></i>
                    <p class="small text-muted mb-0">Belum ada kontestan Senduk aktif untuk ditampilkan pada grafik.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Pimpinan Klasemen & Aktivitas Terbaru -->
<section class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="admin-panel-card p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">Kontestan Suara Tertinggi</h2>
                <a href="<?= e(baseUrl('admin/hasil-voting.php')) ?>" class="small ks-link-accent">Lihat Hasil Lengkap</a>
            </div>

            <div class="d-flex flex-column gap-3 min-w-0">
                <?php
                    $leaders = [
                        ['label' => 'Kakang Teratas', 'badgeClass' => 'ks-badge-kakang', 'row' => $topKakang],
                        ['label' => 'Senduk Teratas', 'badgeClass' => 'ks-badge-senduk', 'row' => $topSenduk],
                    ];
                ?>
                <?php foreach ($leaders as $leader): ?>
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 min-w-0 overflow-hidden">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mb-2">
                            <span class="badge rounded-pill <?= e($leader['badgeClass']) ?>"><?= e($leader['label']) ?></span>
                            <?php if ($leader['row'] !== null): ?>
                                <span class="small fw-semibold text-emerald">
                                    <?= number_format((int) $leader['row']['jumlah_suara'], 0, ',', '.') ?> suara
                                    (<?= number_format((float) $leader['row']['persentase'], 1, ',', '.') ?>%)
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($leader['row'] !== null): ?>
                            <?php $r = $leader['row']; ?>
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <img
                                    src="<?= e(kontestanFotoUrl($r['foto'] ?? null)) ?>"
                                    alt="Foto <?= e($r['nama']) ?>"
                                    class="admin-thumb-4x5"
                                >
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-semibold text-truncate"><?= e($r['nama']) ?></div>
                                    <div class="small text-muted text-truncate">
                                        <?= e($r['nomor_peserta']) ?> &bull; <?= e($r['asal_instansi']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="small text-muted mb-0">Belum ada suara masuk pada kategori ini.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="admin-panel-card p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-bold mb-0">Aktivitas Terbaru</h2>
                <a href="<?= e(baseUrl('admin/pemilih/index.php')) ?>" class="small ks-link-accent">Lihat Data Pemilih</a>
            </div>

            <?php if (!empty($aktivitasTerbaru)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Aktivitas</th>
                                <th>Keterangan</th>
                                <th class="text-nowrap">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aktivitasTerbaru as $act): ?>
                                <tr>
                                    <td>
                                        <?php if (($act['tipe'] ?? '') === 'voting'): ?>
                                            <i class="bi bi-check2-circle text-emerald me-1"></i>
                                        <?php else: ?>
                                            <i class="bi bi-person-plus text-warning me-1"></i>
                                        <?php endif; ?>
                                        <span class="fw-medium"><?= e($act['judul'] ?? '-') ?></span>
                                    </td>
                                    <td class="text-muted"><?= e($act['keterangan'] ?? '-') ?></td>
                                    <td class="text-muted text-nowrap"><?= e(formatTanggalIndonesia($act['waktu'] ?? null, true)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="ks-empty-state py-4">
                    <i class="bi bi-clock-history fs-3 text-muted d-block mb-2"></i>
                    <p class="small text-muted mb-0">Belum ada aktivitas pendaftaran pemilih maupun pemungutan suara yang tercatat.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

</main>
</div>
</div>
<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>
