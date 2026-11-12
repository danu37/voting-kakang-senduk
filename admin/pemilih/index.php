<?php
declare(strict_types=1);

/**
 * Manajemen Pemilih Administrator
 * Menampilkan akun pemilih terdaftar, tanggal registrasi, dan status voting Kakang & Senduk per periode.
 */

require_once dirname(__DIR__, 2) . '/config/koneksi.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__, 2) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);

$adminController = new AdminController($pdo);
$data = $adminController->getDaftarPemilih($_GET);

$items = $data['items'];
$total = (int) $data['total'];
$page = (int) $data['page'];
$totalPages = (int) $data['total_pages'];
$daftarPeriode = $data['daftar_periode'];
$periodeTerpilih = $data['periode_terpilih'];

$pageTitle = 'Manajemen Pemilih';
$adminMenu = 'pemilih';
$adminHeading = 'Manajemen Data Pemilih';
$extraCss = ['css/admin.css'];
$errors = [];

require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
?>

<section class="admin-panel-card p-3 p-md-4 min-w-0">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3 pb-3 border-bottom">
        <div>
            <h2 class="h5 fw-bold mb-1">Daftar Akun Pemilih</h2>
            <p class="small text-muted mb-0">
                Total <?= number_format($total, 0, ',', '.') ?> pemilih ditemukan.
                <?php if ($periodeTerpilih !== null): ?>
                    Status voting mengacu pada periode <strong><?= e($periodeTerpilih['nama_periode']) ?></strong>.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Filter Periode, Status Voting & Pencarian -->
    <?php
        $hasFilter = ($data['cari'] !== '' || $data['status_voting'] !== '' || isset($_GET['periode_id']));
        $selectedPeriodeIdStr = $periodeTerpilih !== null ? (string) ((int) $periodeTerpilih['id']) : '';
    ?>
    <form method="GET" action="<?= e(baseUrl('admin/pemilih/index.php')) ?>" class="row g-2 align-items-end mb-4" data-loading-form>
        <div class="col-12 col-md-4">
            <label for="cari" class="form-label small fw-medium mb-1">Cari Nama, Email, atau Nomor HP</label>
            <input
                type="text"
                class="form-control form-control-sm"
                id="cari"
                name="cari"
                value="<?= e($data['cari']) ?>"
                placeholder="Ketik nama atau email pemilih..."
                maxlength="100"
            >
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <label for="status_voting" class="form-label small fw-medium mb-1">Filter Status Voting</label>
            <select name="status_voting" id="status_voting" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="sudah_lengkap" <?= $data['status_voting'] === 'sudah_lengkap' ? 'selected' : '' ?>>Sudah Memilih Keduanya</option>
                <option value="sudah_kakang" <?= $data['status_voting'] === 'sudah_kakang' ? 'selected' : '' ?>>Sudah Memilih Kakang</option>
                <option value="sudah_senduk" <?= $data['status_voting'] === 'sudah_senduk' ? 'selected' : '' ?>>Sudah Memilih Senduk</option>
                <option value="belum_lengkap" <?= $data['status_voting'] === 'belum_lengkap' ? 'selected' : '' ?>>Belum Lengkap</option>
                <option value="belum_memilih" <?= $data['status_voting'] === 'belum_memilih' ? 'selected' : '' ?>>Belum Memilih Sama Sekali</option>
            </select>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <label for="periode_id" class="form-label small fw-medium mb-1">Acuan Periode Voting</label>
            <select name="periode_id" id="periode_id" class="form-select form-select-sm">
                <?php if (empty($daftarPeriode)): ?>
                    <option value="">Belum ada periode voting</option>
                <?php else: ?>
                    <?php foreach ($daftarPeriode as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= ($periodeTerpilih && (int) $periodeTerpilih['id'] === (int) $p['id']) ? 'selected' : '' ?>>
                            <?= e($p['nama_periode']) ?> (<?= e($p['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-emerald btn-sm flex-grow-1">
                <i class="bi bi-search me-1"></i> Filter
            </button>
            <?php if ($hasFilter): ?>
                <a href="<?= e(baseUrl('admin/pemilih/index.php')) ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!empty($items)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pemilih</th>
                        <th>No. HP</th>
                        <th>Registrasi</th>
                        <th class="text-center">Kakang</th>
                        <th class="text-center">Senduk</th>
                        <th class="text-end" style="min-width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $pemilih): ?>
                        <?php
                            $pemilihId = (int) $pemilih['id'];
                            $sudahKakang = (int) ($pemilih['status_kakang'] ?? 0) === 1;
                            $sudahSenduk = (int) ($pemilih['status_senduk'] ?? 0) === 1;
                            $riwayatSuara = (int) ($pemilih['total_riwayat_voting'] ?? 0);
                            $detailQuery = 'id=' . $pemilihId . ($selectedPeriodeIdStr !== '' ? '&periode_id=' . urlencode($selectedPeriodeIdStr) : '');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($pemilih['nama']) ?></div>
                                <div class="text-muted text-break"><?= e($pemilih['email']) ?></div>
                            </td>
                            <td class="text-muted text-nowrap"><?= e($pemilih['no_hp']) ?></td>
                            <td class="text-muted"><?= e(formatTanggalIndonesia($pemilih['created_at'] ?? null, true)) ?></td>
                            <td class="text-center">
                                <?php if ($sudahKakang): ?>
                                    <span class="badge rounded-pill text-bg-success">
                                        <i class="bi bi-check-circle me-1"></i>Sudah Memilih
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill text-bg-light border text-muted">Belum Memilih</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($sudahSenduk): ?>
                                    <span class="badge rounded-pill text-bg-success">
                                        <i class="bi bi-check-circle me-1"></i>Sudah Memilih
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill text-bg-light border text-muted">Belum Memilih</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1 align-items-center justify-content-end">
                                    <a
                                        href="<?= e(baseUrl('admin/pemilih/detail.php?' . $detailQuery)) ?>"
                                        class="btn btn-outline-emerald btn-sm"
                                        title="Lihat detail pemilih"
                                    >
                                        <i class="bi bi-eye me-1"></i>Detail
                                    </a>

                                    <?php if ($riwayatSuara > 0): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            disabled
                                            title="Terkunci: pemilih memiliki <?= $riwayatSuara ?> riwayat suara"
                                        >
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    <?php else: ?>
                                        <form
                                            method="POST"
                                            action="<?= e(baseUrl('admin/pemilih/hapus.php')) ?>"
                                            class="d-inline"
                                            data-confirm-submit
                                            data-confirm-title="Hapus Akun Pemilih?"
                                            data-confirm-text="Akun pemilih <?= e($pemilih['nama']) ?> (<?= e($pemilih['email']) ?>) belum memiliki riwayat voting dan akan dihapus permanen."
                                            data-confirm-btn="Ya, Hapus"
                                        >
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $pemilihId ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus akun pemilih">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
            $paginationPage = $page;
            $paginationTotalPages = $totalPages;
            $paginationBasePath = 'admin/pemilih/index.php';
            $paginationQuery = [
                'cari'          => $data['cari'],
                'status_voting' => $data['status_voting'],
                'periode_id'    => $selectedPeriodeIdStr,
            ];
            require dirname(__DIR__, 2) . '/views/components/pagination.php';
        ?>
    <?php else: ?>
        <div class="ks-empty-state">
            <i class="bi bi-person-badge fs-2 text-muted d-block mb-2"></i>
            <h3 class="h6 fw-bold mb-1">Data Pemilih Tidak Ditemukan</h3>
            <p class="small text-muted mb-3">
                <?= $hasFilter ? 'Tidak ada akun pemilih yang sesuai dengan filter atau kata kunci pencarian Anda.' : 'Belum ada pemilih yang mendaftar pada sistem.' ?>
            </p>
            <?php if ($hasFilter): ?>
                <a href="<?= e(baseUrl('admin/pemilih/index.php')) ?>" class="btn btn-outline-emerald btn-sm">
                    Reset Filter
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

</main>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>

