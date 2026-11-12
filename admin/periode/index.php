<?php
declare(strict_types=1);

/**
 * Manajemen Periode Voting Administrator
 * Tambah, edit, aktifkan, nonaktifkan, lihat ringkasan suara per periode, dan hapus aman periode kosong.
 */

require_once dirname(__DIR__, 2) . '/config/koneksi.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__, 2) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);
$adminController = new AdminController($pdo);

$errors = [];
$formMode = 'create';
$editId = 0;
$formValues = [
    'nama_periode'    => '',
    'tanggal_mulai'   => '',
    'tanggal_selesai' => '',
    'status'          => 'Nonaktif',
];

$formatInputDateTime = static function (?string $sqlDateTime): string {
    if ($sqlDateTime === null || trim($sqlDateTime) === '') {
        return '';
    }
    try {
        $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
        return (new DateTimeImmutable($sqlDateTime, $tz))->format('Y-m-d\TH:i');
    } catch (Exception $e) {
        return '';
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = $adminController->handlePeriodePost($_POST);
    $action = sanitizeText($_POST['action'] ?? '');

    if ($res['success']) {
        setFlash('success', 'Berhasil', (string) ($res['message'] ?? 'Perubahan periode berhasil disimpan.'));
        redirect('admin/periode/index.php');
    }

    if ($action === 'toggle_status') {
        setFlash('error', 'Perubahan Status Ditolak', implode(' ', $res['errors'] ?? ['Gagal mengubah status periode.']));
        redirect('admin/periode/index.php');
    }

    $errors = $res['errors'] ?? ['Gagal memproses periode voting.'];
    if ($action === 'update' && isPositiveInt($_POST['id'] ?? null)) {
        $targetEdit = $adminController->getPeriodeById((int) $_POST['id']);
        if ($targetEdit !== null && (int) ($targetEdit['total_suara'] ?? 0) === 0) {
            $formMode = 'update';
            $editId = (int) $_POST['id'];
            $formValues = [
                'nama_periode'    => sanitizeText($_POST['nama_periode'] ?? ''),
                'tanggal_mulai'   => sanitizeText($_POST['tanggal_mulai'] ?? ''),
                'tanggal_selesai' => sanitizeText($_POST['tanggal_selesai'] ?? ''),
                'status'          => sanitizeText($_POST['status'] ?? 'Nonaktif'),
            ];
        }
    } else {
        $formValues = [
            'nama_periode'    => sanitizeText($_POST['nama_periode'] ?? ''),
            'tanggal_mulai'   => sanitizeText($_POST['tanggal_mulai'] ?? ''),
            'tanggal_selesai' => sanitizeText($_POST['tanggal_selesai'] ?? ''),
            'status'          => sanitizeText($_POST['status'] ?? 'Nonaktif'),
        ];
    }
} elseif (isset($_GET['edit_id'])) {
    $editData = $adminController->getPeriodeById($_GET['edit_id']);
    if ($editData === null) {
        $errors[] = 'Data periode yang ingin diedit tidak ditemukan.';
    } elseif ((int) ($editData['total_suara'] ?? 0) > 0) {
        $errors[] = 'Periode "' . (string) $editData['nama_periode'] . '" sudah memiliki ' . (int) $editData['total_suara'] . ' suara tercatat sehingga nama dan jadwalnya terkunci. Gunakan tombol Aktifkan/Nonaktifkan untuk mengatur status periode.';
    } else {
        $formMode = 'update';
        $editId = (int) $editData['id'];
        $formValues = [
            'nama_periode'    => (string) $editData['nama_periode'],
            'tanggal_mulai'   => $formatInputDateTime((string) $editData['tanggal_mulai']),
            'tanggal_selesai' => $formatInputDateTime((string) $editData['tanggal_selesai']),
            'status'          => (string) $editData['status'],
        ];
    }
}

$daftarPeriode = $adminController->getDaftarPeriode();
$waktuServerText = formatTanggalIndonesia(date('Y-m-d H:i:s'), true);

$pageTitle = 'Manajemen Periode Voting';
$adminMenu = 'periode';
$adminHeading = 'Manajemen Periode Voting';
$extraCss = ['css/admin.css'];

require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
?>

<div class="row g-4">
    <!-- Form Tambah / Edit Periode -->
    <div class="col-12 col-xl-4 min-w-0">
        <section class="admin-panel-card p-3 p-md-4 min-w-0">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <h2 class="h6 fw-bold mb-0">
                    <?= $formMode === 'update' ? 'Edit Periode Voting' : 'Tambah Periode Voting' ?>
                </h2>
                <?php if ($formMode === 'update'): ?>
                    <a href="<?= e(baseUrl('admin/periode/index.php')) ?>" class="small text-muted text-decoration-none">
                        <i class="bi bi-x-circle me-1"></i>Batal
                    </a>
                <?php endif; ?>
            </div>

            <form method="POST" action="<?= e(baseUrl('admin/periode/index.php')) ?>" data-loading-form>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= e($formMode) ?>">
                <?php if ($formMode === 'update'): ?>
                    <input type="hidden" name="id" value="<?= $editId ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="nama_periode" class="form-label small fw-medium">Nama Periode <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        class="form-control"
                        id="nama_periode"
                        name="nama_periode"
                        value="<?= e($formValues['nama_periode']) ?>"
                        placeholder="Contoh: Pemilihan Kakang Senduk 2026"
                        minlength="3"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="tanggal_mulai" class="form-label small fw-medium">Tanggal &amp; Jam Mulai <span class="text-danger">*</span></label>
                    <input
                        type="datetime-local"
                        class="form-control"
                        id="tanggal_mulai"
                        name="tanggal_mulai"
                        value="<?= e($formValues['tanggal_mulai']) ?>"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="tanggal_selesai" class="form-label small fw-medium">Tanggal &amp; Jam Selesai <span class="text-danger">*</span></label>
                    <input
                        type="datetime-local"
                        class="form-control"
                        id="tanggal_selesai"
                        name="tanggal_selesai"
                        value="<?= e($formValues['tanggal_selesai']) ?>"
                        required
                    >
                    <div class="form-text small text-muted">
                        Tanggal mulai wajib lebih awal daripada tanggal selesai (menggunakan waktu server WIB).
                    </div>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label small fw-medium">Status Periode <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="Nonaktif" <?= $formValues['status'] === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        <option value="Aktif" <?= $formValues['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                    </select>
                    <div class="form-text small text-muted">
                        Hanya satu periode yang boleh berstatus <strong>Aktif</strong> agar tidak terjadi jadwal bertentangan. Status Aktif tetap hanya membuka voting selama waktu server berada dalam rentang jadwal.
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-emerald" data-loading-text="Menyimpan...">
                        <i class="bi bi-save me-1"></i>
                        <?= $formMode === 'update' ? 'Simpan Perubahan Periode' : 'Simpan Periode Baru' ?>
                    </button>
                </div>
            </form>
        </section>
    </div>

    <!-- Daftar Periode Voting -->
    <div class="col-12 col-xl-8 min-w-0">
        <section class="admin-panel-card p-3 p-md-4 min-w-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                <div>
                    <h2 class="h6 fw-bold mb-0">Daftar Periode Pemilihan</h2>
                    <small class="text-muted">Waktu server saat ini: <strong><?= e($waktuServerText) ?></strong></small>
                </div>
                <span class="badge text-bg-light border text-dark"><?= count($daftarPeriode) ?> Periode</span>
            </div>

            <?php if (!empty($daftarPeriode)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Periode &amp; Status Jadwal</th>
                                <th>Jadwal Pelaksanaan</th>
                                <th class="text-center">Jumlah Suara</th>
                                <th class="text-end" style="min-width: 190px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftarPeriode as $periode): ?>
                                <?php
                                    $pid = (int) $periode['id'];
                                    $isAktif = ($periode['status'] ?? '') === 'Aktif';
                                    $jadwalStatus = (string) ($periode['jadwal_status'] ?? 'Nonaktif');
                                    $bisaVoting = !empty($periode['bisa_voting']);
                                    $totalSuara = (int) ($periode['total_suara'] ?? 0);
                                    $suaraKakang = (int) ($periode['suara_kakang'] ?? 0);
                                    $suaraSenduk = (int) ($periode['suara_senduk'] ?? 0);

                                    $jadwalBadge = match ($jadwalStatus) {
                                        'Sedang Berlangsung' => 'text-bg-success',
                                        'Belum Dimulai'      => 'text-bg-warning',
                                        'Sudah Berakhir'     => 'text-bg-dark',
                                        default              => 'text-bg-secondary',
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($periode['nama_periode']) ?></div>
                                        <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                            <span class="badge rounded-pill <?= $isAktif ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                Status: <?= e($periode['status']) ?>
                                            </span>
                                            <span class="badge rounded-pill <?= e($jadwalBadge) ?>">
                                                <?= e($jadwalStatus) ?>
                                            </span>
                                        </div>
                                        <div class="text-muted mt-1" style="font-size: 0.76rem;">
                                            <?php if ($bisaVoting): ?>
                                                <span class="text-emerald fw-semibold"><i class="bi bi-unlock me-1"></i>Voting Terbuka</span>
                                            <?php else: ?>
                                                <span><i class="bi bi-lock me-1"></i>Voting Ditutup</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-muted">
                                        <div><strong>Mulai:</strong> <?= e(formatTanggalIndonesia($periode['tanggal_mulai'], true)) ?></div>
                                        <div><strong>Selesai:</strong> <?= e(formatTanggalIndonesia($periode['tanggal_selesai'], true)) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold text-dark"><?= number_format($totalSuara, 0, ',', '.') ?> suara</div>
                                        <div class="text-muted" style="font-size: 0.76rem;">
                                            K: <?= number_format($suaraKakang, 0, ',', '.') ?> &bull; S: <?= number_format($suaraSenduk, 0, ',', '.') ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1 align-items-center justify-content-end flex-wrap">
                                            <a
                                                href="<?= e(baseUrl('admin/periode/detail.php?id=' . $pid)) ?>"
                                                class="btn btn-outline-secondary btn-sm"
                                                title="Lihat detail periode dan jumlah suara"
                                            >
                                                <i class="bi bi-eye me-1"></i>Detail
                                            </a>

                                            <form
                                                method="POST"
                                                action="<?= e(baseUrl('admin/periode/index.php')) ?>"
                                                class="d-inline"
                                                data-confirm-submit
                                                data-confirm-title="<?= $isAktif ? 'Nonaktifkan Periode?' : 'Aktifkan Periode?' ?>"
                                                data-confirm-text="<?= $isAktif ? 'Pemilih tidak dapat melakukan voting setelah periode ' . e($periode['nama_periode']) . ' dinonaktifkan.' : 'Periode ' . e($periode['nama_periode']) . ' akan dijadikan periode aktif sistem.' ?>"
                                                data-confirm-btn="<?= $isAktif ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' ?>"
                                            >
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $pid ?>">
                                                <input type="hidden" name="target_status" value="<?= $isAktif ? 'Nonaktif' : 'Aktif' ?>">
                                                <button type="submit" class="btn <?= $isAktif ? 'btn-outline-danger' : 'btn-emerald' ?> btn-sm">
                                                    <?= $isAktif ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>

                                            <?php if ($totalSuara === 0): ?>
                                                <a
                                                    href="<?= e(baseUrl('admin/periode/index.php?edit_id=' . $pid)) ?>"
                                                    class="btn btn-outline-emerald btn-sm"
                                                    title="Edit jadwal dan data periode"
                                                >
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>

                                                <?php if (!$isAktif): ?>
                                                    <form
                                                        method="POST"
                                                        action="<?= e(baseUrl('admin/periode/hapus.php')) ?>"
                                                        class="d-inline"
                                                        data-confirm-submit
                                                        data-confirm-title="Hapus Periode Voting?"
                                                        data-confirm-text="Periode <?= e($periode['nama_periode']) ?> berstatus Nonaktif dan belum memiliki suara sehingga akan dihapus permanen."
                                                        data-confirm-btn="Ya, Hapus"
                                                    >
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="id" value="<?= $pid ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus periode kosong">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    disabled
                                                    title="Terkunci: periode memiliki <?= $totalSuara ?> riwayat suara sehingga tidak dapat diubah atau dihapus"
                                                >
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="ks-empty-state">
                    <i class="bi bi-calendar-x fs-2 text-muted d-block mb-2"></i>
                    <h3 class="h6 fw-bold mb-1">Belum Ada Periode Voting</h3>
                    <p class="small text-muted mb-0">Gunakan formulir di samping untuk menambahkan periode pemilihan pertama.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

</main>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>
