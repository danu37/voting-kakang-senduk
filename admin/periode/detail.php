<?php
declare(strict_types=1);

/**
 * Detail Periode Voting Administrator
 * Menampilkan detail jadwal, verifikasi status waktu server (Belum Dimulai / Sedang Berlangsung / Sudah Berakhir / Nonaktif),
 * jumlah suara per periode, serta rincian suara per kategori.
 */

require_once dirname(__DIR__, 2) . '/config/koneksi.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__, 2) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);
$adminController = new AdminController($pdo);

$detail = $adminController->getPeriodeDetail($_GET['id'] ?? null);
$errors = [];
$extraCss = ['css/admin.css'];

if ($detail === null) {
    http_response_code(404);
    $pageTitle = 'Periode Tidak Ditemukan';
    $adminMenu = 'periode';
    $adminHeading = 'Detail Periode Voting';

    require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
    require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
    ?>
    <div class="ks-empty-state">
        <i class="bi bi-calendar-x fs-2 text-warning d-block mb-2"></i>
        <h2 class="h5 fw-bold mb-1">Periode Voting Tidak Ditemukan</h2>
        <p class="small text-muted mb-3">
            ID periode tidak valid atau periode tersebut belum tersedia / sudah dihapus.
        </p>
        <a href="<?= e(baseUrl('admin/periode/index.php')) ?>" class="btn btn-emerald btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Periode
        </a>
    </div>
    </main>
    </div>
    </div>
    <?php
    require_once dirname(__DIR__, 2) . '/views/layouts/footer.php';
    exit;
}

$periode = $detail['periode'];
$pid = (int) $periode['id'];
$isAktif = ($periode['status'] ?? '') === 'Aktif';
$jadwalStatus = (string) ($periode['jadwal_status'] ?? 'Nonaktif');
$bisaVoting = !empty($periode['bisa_voting']);
$totalSuara = (int) ($periode['total_suara'] ?? 0);
$suaraKakang = (int) ($periode['suara_kakang'] ?? 0);
$suaraSenduk = (int) ($periode['suara_senduk'] ?? 0);
$totalPemilihUnik = (int) ($periode['total_pemilih_unik'] ?? 0);

$hasilKakang = $detail['hasil_kakang']['items'] ?? [];
$hasilSenduk = $detail['hasil_senduk']['items'] ?? [];

$jadwalBadgeClass = match ($jadwalStatus) {
    'Sedang Berlangsung' => 'text-bg-success',
    'Belum Dimulai'      => 'text-bg-warning',
    'Sudah Berakhir'     => 'text-bg-dark',
    default              => 'text-bg-secondary',
};

$pageTitle = 'Detail Periode: ' . (string) $periode['nama_periode'];
$adminMenu = 'periode';
$adminHeading = 'Detail Periode Voting';

require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= e(baseUrl('admin/periode/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Periode
    </a>

    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(baseUrl('admin/hasil-voting.php?periode_id=' . $pid)) ?>" class="btn btn-outline-emerald btn-sm">
            <i class="bi bi-bar-chart-line me-1"></i> Hasil Voting Periode Ini
        </a>
        <a href="<?= e(baseUrl('pages/klasemen.php?periode_id=' . $pid)) ?>" class="btn btn-emerald btn-sm">
            <i class="bi bi-trophy me-1"></i> Klasemen Publik
        </a>
    </div>
</div>

<!-- Banner Evaluasi Status & Logika Waktu Server -->
<section class="admin-panel-card p-3 p-md-4 mb-4 min-w-0">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 pb-3 mb-3 border-bottom">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge rounded-pill <?= $isAktif ? 'text-bg-success' : 'text-bg-secondary' ?> px-3 py-1">
                    Status Database: <?= e($periode['status']) ?>
                </span>
                <span class="badge rounded-pill <?= e($jadwalBadgeClass) ?> px-3 py-1">
                    <?= e($jadwalStatus) ?>
                </span>
            </div>
            <h2 class="h4 fw-bold mb-1"><?= e($periode['nama_periode']) ?></h2>
            <p class="small text-muted mb-0">
                <?= e($periode['alasan_status'] ?? '') ?>
                (Waktu server: <strong><?= e($detail['waktu_server_text']) ?></strong>)
            </p>
        </div>

        <div class="flex-shrink-0">
            <?php if ($bisaVoting): ?>
                <div class="badge rounded-pill text-bg-success fs-6 px-3 py-2">
                    <i class="bi bi-unlock-fill me-1"></i> Voting Dibuka
                </div>
            <?php else: ?>
                <div class="badge rounded-pill text-bg-danger fs-6 px-3 py-2">
                    <i class="bi bi-lock-fill me-1"></i> Voting Ditutup
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 small">
        <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                <div class="text-muted mb-1">Syarat 1: Status Periode Aktif</div>
                <?php if (!empty($periode['syarat_status_aktif'])): ?>
                    <span class="fw-semibold text-emerald"><i class="bi bi-check-circle-fill me-1"></i>Terpenuhi (Aktif)</span>
                <?php else: ?>
                    <span class="fw-semibold text-danger"><i class="bi bi-x-circle-fill me-1"></i>Tidak Terpenuhi (Nonaktif)</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                <div class="text-muted mb-1">Syarat 2: Waktu Server &ge; Mulai</div>
                <?php if (!empty($periode['syarat_sudah_mulai'])): ?>
                    <span class="fw-semibold text-emerald"><i class="bi bi-check-circle-fill me-1"></i>Sudah Memasuki Jadwal Mulai</span>
                <?php else: ?>
                    <span class="fw-semibold text-warning"><i class="bi bi-clock-fill me-1"></i>Belum Dimulai</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                <div class="text-muted mb-1">Syarat 3: Waktu Server &le; Selesai</div>
                <?php if (!empty($periode['syarat_belum_selesai'])): ?>
                    <span class="fw-semibold text-emerald"><i class="bi bi-check-circle-fill me-1"></i>Belum Melewati Batas Selesai</span>
                <?php else: ?>
                    <span class="fw-semibold text-danger"><i class="bi bi-calendar-x-fill me-1"></i>Sudah Berakhir</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Ringkasan Jumlah Suara & Informasi Detail Periode -->
<div class="row g-4 mb-4">
    <div class="col-12 col-lg-5 min-w-0">
        <section class="admin-panel-card p-3 p-md-4 h-100 d-flex flex-column min-w-0">
            <h3 class="h6 fw-bold pb-2 mb-3 border-bottom">Informasi &amp; Kontrol Periode</h3>

            <dl class="row g-2 small mb-4">
                <dt class="col-5 text-muted fw-normal">ID Periode</dt>
                <dd class="col-7 fw-semibold mb-1">#<?= $pid ?></dd>

                <dt class="col-5 text-muted fw-normal">Nama Periode</dt>
                <dd class="col-7 fw-semibold mb-1"><?= e($periode['nama_periode']) ?></dd>

                <dt class="col-5 text-muted fw-normal">Tanggal Mulai</dt>
                <dd class="col-7 mb-1"><?= e(formatTanggalIndonesia($periode['tanggal_mulai'], true)) ?></dd>

                <dt class="col-5 text-muted fw-normal">Tanggal Selesai</dt>
                <dd class="col-7 mb-1"><?= e(formatTanggalIndonesia($periode['tanggal_selesai'], true)) ?></dd>

                <dt class="col-5 text-muted fw-normal">Dibuat Pada</dt>
                <dd class="col-7 mb-1"><?= e(formatTanggalIndonesia($periode['created_at'] ?? null, true)) ?></dd>

                <dt class="col-5 text-muted fw-normal">Terakhir Diperbarui</dt>
                <dd class="col-7 mb-0"><?= e(formatTanggalIndonesia($periode['updated_at'] ?? null, true)) ?></dd>
            </dl>

            <div class="mt-auto pt-3 border-top d-flex flex-column gap-2">
                <form
                    method="POST"
                    action="<?= e(baseUrl('admin/periode/index.php')) ?>"
                    data-confirm-submit
                    data-confirm-title="<?= $isAktif ? 'Nonaktifkan Periode?' : 'Aktifkan Periode?' ?>"
                    data-confirm-text="<?= $isAktif ? 'Voting pada periode ' . e($periode['nama_periode']) . ' akan dihentikan.' : 'Periode ' . e($periode['nama_periode']) . ' akan dijadikan periode aktif.' ?>"
                    data-confirm-btn="<?= $isAktif ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' ?>"
                >
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= $pid ?>">
                    <input type="hidden" name="target_status" value="<?= $isAktif ? 'Nonaktif' : 'Aktif' ?>">
                    <button type="submit" class="btn <?= $isAktif ? 'btn-outline-danger' : 'btn-emerald' ?> btn-sm w-100">
                        <i class="bi <?= $isAktif ? 'bi-pause-circle' : 'bi-play-circle' ?> me-1"></i>
                        <?= $isAktif ? 'Nonaktifkan Periode' : 'Aktifkan Periode' ?>
                    </button>
                </form>

                <?php if ($totalSuara > 0): ?>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="bi bi-shield-lock text-emerald me-1"></i>
                        <strong>Riwayat Terlindungi:</strong> Periode ini sudah memiliki <?= number_format($totalSuara, 0, ',', '.') ?> suara tercatat sehingga nama, tanggal jadwal, dan penghapusan periode dikunci untuk menjaga keaslian riwayat voting.
                    </div>
                <?php else: ?>
                    <a href="<?= e(baseUrl('admin/periode/index.php?edit_id=' . $pid)) ?>" class="btn btn-outline-emerald btn-sm w-100">
                        <i class="bi bi-pencil-square me-1"></i> Edit Nama &amp; Jadwal Periode
                    </a>

                    <?php if (!$isAktif): ?>
                        <form
                            method="POST"
                            action="<?= e(baseUrl('admin/periode/hapus.php')) ?>"
                            data-confirm-submit
                            data-confirm-title="Hapus Periode Voting?"
                            data-confirm-text="Periode <?= e($periode['nama_periode']) ?> akan dihapus permanen karena belum memiliki suara."
                            data-confirm-btn="Ya, Hapus Periode"
                        >
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $pid ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                <i class="bi bi-trash me-1"></i> Hapus Periode Kosong
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-12 col-lg-7 min-w-0">
        <section class="admin-panel-card p-3 p-md-4 h-100 min-w-0">
            <h3 class="h6 fw-bold pb-2 mb-3 border-bottom">Statistik Suara Periode Ini</h3>

            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-6">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="small text-muted mb-1">Total Suara Masuk</div>
                        <div class="h3 fw-bold mb-0"><?= number_format($totalSuara, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-6 col-sm-6">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="small text-muted mb-1">Pemilih Berpartisipasi</div>
                        <div class="h3 fw-bold mb-0"><?= number_format($totalPemilihUnik, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-6 col-sm-6">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="small text-muted mb-1">Total Suara Kakang</div>
                        <div class="h3 fw-bold text-emerald mb-0"><?= number_format($suaraKakang, 0, ',', '.') ?></div>
                    </div>
                </div>
                <div class="col-6 col-sm-6">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="small text-muted mb-1">Total Suara Senduk</div>
                        <div class="h3 fw-bold mb-0" style="color: #856404;"><?= number_format($suaraSenduk, 0, ',', '.') ?></div>
                    </div>
                </div>
            </div>

            <div class="small text-muted">
                <i class="bi bi-info-circle text-emerald me-1"></i>
                Setiap suara pada tabel <code>voting</code> terikat langsung dengan <code>periode_id = #<?= $pid ?></code> dan dilindungi pembatasan relasi database agar riwayat pemilihan tetap akurat.
            </div>
        </section>
    </div>
</div>

<!-- Rincian Perolehan Suara per Kategori pada Periode Ini -->
<?php
$kategoriBreakdown = [
    ['title' => 'Perolehan Suara Kategori Kakang', 'badge' => 'ks-badge-kakang', 'items' => $hasilKakang],
    ['title' => 'Perolehan Suara Kategori Senduk', 'badge' => 'ks-badge-senduk', 'items' => $hasilSenduk],
];
?>
<section class="row g-4">
    <?php foreach ($kategoriBreakdown as $kat): ?>
        <div class="col-12 col-xl-6 min-w-0">
            <div class="admin-panel-card p-3 p-md-4 h-100 min-w-0">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0"><?= e($kat['title']) ?></h3>
                    <span class="badge rounded-pill <?= e($kat['badge']) ?>"><?= count($kat['items']) ?> Kontestan</span>
                </div>

                <?php if (!empty($kat['items'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 58px;">Rank</th>
                                    <th>Kontestan</th>
                                    <th class="text-end">Suara</th>
                                    <th class="text-end">Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kat['items'] as $row): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= (int) $row['ranking'] ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= e($row['nama']) ?></div>
                                            <div class="text-muted"><?= e($row['nomor_peserta']) ?></div>
                                        </td>
                                        <td class="text-end fw-semibold"><?= number_format((int) $row['jumlah_suara'], 0, ',', '.') ?></td>
                                        <td class="text-end text-muted"><?= number_format((float) $row['persentase'], 1, ',', '.') ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="ks-empty-state py-4">
                        <p class="small text-muted mb-0">Belum ada kontestan aktif pada kategori ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

</main>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>

