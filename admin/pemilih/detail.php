<?php
declare(strict_types=1);

/**
 * Detail Akun Pemilih Administrator
 * Menampilkan informasi profil (tanpa password hash) serta status & riwayat partisipasi voting (read-only).
 */

require_once dirname(__DIR__, 2) . '/config/koneksi.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__, 2) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);

$adminController = new AdminController($pdo);
$idParam = $_GET['id'] ?? null;
$periodeParam = $_GET['periode_id'] ?? null;
$detail = $adminController->getDetailPemilih($idParam, $periodeParam);

$errors = [];
$extraCss = ['css/admin.css'];

if ($detail === null) {
    http_response_code(404);
    $pageTitle = 'Pemilih Tidak Ditemukan';
    $adminMenu = 'pemilih';
    $adminHeading = 'Detail Akun Pemilih';

    require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
    require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
    ?>
    <div class="ks-empty-state">
        <i class="bi bi-person-x fs-2 text-warning d-block mb-2"></i>
        <h2 class="h5 fw-bold mb-1">Akun Pemilih Tidak Ditemukan</h2>
        <p class="small text-muted mb-3">
            ID pemilih tidak valid, akun telah dihapus, atau akun tersebut bukan merupakan akun pemilih.
        </p>
        <a href="<?= e(baseUrl('admin/pemilih/index.php')) ?>" class="btn btn-emerald btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Pemilih
        </a>
    </div>
    </main>
    </div>
    </div>
    <?php
    require_once dirname(__DIR__, 2) . '/views/layouts/footer.php';
    exit;
}

$pemilih = $detail['pemilih'];
$pemilihId = (int) $pemilih['id'];
$periodeTerpilih = $detail['periode_terpilih'];
$statusKakang = (bool) ($detail['status_kakang'] ?? false);
$statusSenduk = (bool) ($detail['status_senduk'] ?? false);
$totalRiwayat = (int) ($detail['total_riwayat_voting'] ?? 0);
$riwayatVoting = is_array($detail['riwayat_voting'] ?? null) ? $detail['riwayat_voting'] : [];

$pageTitle = 'Detail Pemilih: ' . (string) $pemilih['nama'];
$adminMenu = 'pemilih';
$adminHeading = 'Detail Akun Pemilih';

require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= e(baseUrl('admin/pemilih/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Pemilih
    </a>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Profil & Keamanan Akun (Tanpa Password) -->
    <div class="col-12 col-lg-5">
        <section class="admin-panel-card p-3 p-md-4 h-100 d-flex flex-column">
            <div class="d-flex align-items-center gap-3 pb-3 mb-3 border-bottom">
                <span class="admin-stat-icon bg-success bg-opacity-10 text-emerald">
                    <i class="bi bi-person-badge"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="h5 fw-bold mb-0 text-truncate"><?= e($pemilih['nama']) ?></h2>
                    <span class="badge rounded-pill ks-badge-kakang mt-1">Akun Pemilih Terdaftar</span>
                </div>
            </div>

            <dl class="row g-2 small mb-4">
                <dt class="col-5 text-muted fw-normal">ID Pemilih</dt>
                <dd class="col-7 fw-semibold mb-1">#<?= $pemilihId ?></dd>

                <dt class="col-5 text-muted fw-normal">Email</dt>
                <dd class="col-7 fw-medium text-break mb-1"><?= e($pemilih['email']) ?></dd>

                <dt class="col-5 text-muted fw-normal">Nomor HP</dt>
                <dd class="col-7 fw-medium mb-1"><?= e($pemilih['no_hp']) ?></dd>

                <dt class="col-5 text-muted fw-normal">Tanggal Registrasi</dt>
                <dd class="col-7 mb-1"><?= e(formatTanggalIndonesia($pemilih['created_at'] ?? null, true)) ?></dd>

                <dt class="col-5 text-muted fw-normal">Terakhir Diperbarui</dt>
                <dd class="col-7 mb-1"><?= e(formatTanggalIndonesia($pemilih['updated_at'] ?? null, true)) ?></dd>

                <dt class="col-5 text-muted fw-normal">Total Partisipasi</dt>
                <dd class="col-7 fw-semibold mb-0"><?= number_format($totalRiwayat, 0, ',', '.') ?> suara tercatat</dd>
            </dl>

            <div class="mt-auto pt-3 border-top">
                <?php if ($totalRiwayat > 0): ?>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="bi bi-shield-lock text-emerald me-1"></i>
                        <strong>Akun Terlindungi:</strong> Akun ini sudah memiliki <?= number_format($totalRiwayat, 0, ',', '.') ?> riwayat suara sehingga tidak dapat dihapus demi menjaga integritas dan relasi riwayat voting.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <p class="small text-muted mb-1">
                            Akun ini belum memiliki riwayat voting dan dapat dihapus jika diperlukan.
                        </p>
                        <form
                            method="POST"
                            action="<?= e(baseUrl('admin/pemilih/hapus.php')) ?>"
                            data-confirm-submit
                            data-confirm-title="Hapus Akun Pemilih?"
                            data-confirm-text="Akun <?= e($pemilih['nama']) ?> (<?= e($pemilih['email']) ?>) akan dihapus permanen dari sistem."
                            data-confirm-btn="Ya, Hapus Akun"
                        >
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $pemilihId ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                <i class="bi bi-trash me-1"></i> Hapus Akun Pemilih
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Kolom Kanan: Status Voting Periode Acuan & Riwayat Lintas Periode -->
    <div class="col-12 col-lg-7">
        <section class="admin-panel-card p-3 p-md-4 mb-4">
            <h2 class="h6 fw-bold mb-1">Status Hak Suara Periode Acuan</h2>
            <p class="small text-muted mb-3">
                <?php if ($periodeTerpilih !== null): ?>
                    Mengacu pada periode <strong><?= e($periodeTerpilih['nama_periode']) ?></strong> (<?= e($periodeTerpilih['jadwal_status']) ?>).
                <?php else: ?>
                    Belum ada periode pemilihan yang tersedia pada sistem.
                <?php endif; ?>
            </p>

            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 d-flex flex-column align-items-start gap-2 h-100">
                        <div>
                            <div class="small text-muted">Kategori Kakang</div>
                            <div class="fw-semibold">Hak Suara Kakang</div>
                        </div>
                        <?php if ($statusKakang): ?>
                            <span class="badge rounded-pill text-bg-success px-3 py-2">
                                <i class="bi bi-check-circle me-1"></i>Sudah Memilih
                            </span>
                        <?php else: ?>
                            <span class="badge rounded-pill text-bg-secondary px-3 py-2">
                                Belum Memilih
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 d-flex flex-column align-items-start gap-2 h-100">
                        <div>
                            <div class="small text-muted">Kategori Senduk</div>
                            <div class="fw-semibold">Hak Suara Senduk</div>
                        </div>
                        <?php if ($statusSenduk): ?>
                            <span class="badge rounded-pill text-bg-success px-3 py-2">
                                <i class="bi bi-check-circle me-1"></i>Sudah Memilih
                            </span>
                        <?php else: ?>
                            <span class="badge rounded-pill text-bg-secondary px-3 py-2">
                                Belum Memilih
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-panel-card p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h6 fw-bold mb-1">Riwayat Partisipasi Voting</h2>
                    <p class="small text-muted mb-0">Catatan bersifat baca-saja (read-only) untuk menjaga keaslian suara.</p>
                </div>
            </div>

            <?php if (!empty($riwayatVoting)): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Periode Pemilihan</th>
                                <th>Kategori</th>
                                <th>Waktu Partisipasi</th>
                                <th class="text-end">Status Periode</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayatVoting as $rw): ?>
                                <?php $badgeKat = ($rw['kategori'] ?? '') === 'Senduk' ? 'ks-badge-senduk' : 'ks-badge-kakang'; ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($rw['nama_periode']) ?></td>
                                    <td>
                                        <span class="badge rounded-pill <?= e($badgeKat) ?>"><?= e($rw['kategori']) ?></span>
                                    </td>
                                    <td class="text-muted text-nowrap"><?= e(formatTanggalIndonesia($rw['waktu_voting'] ?? null, true)) ?></td>
                                    <td class="text-end">
                                        <span class="badge rounded-pill <?= ($rw['status_periode'] ?? '') === 'Aktif' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= e($rw['status_periode']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="ks-empty-state py-4">
                    <i class="bi bi-clock-history fs-3 text-muted d-block mb-2"></i>
                    <p class="small text-muted mb-0">Pemilih ini belum pernah mengirimkan suara pada periode manapun.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

</main>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>

