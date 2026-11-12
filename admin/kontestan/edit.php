<?php
declare(strict_types=1);

/**
 * Edit Data Kontestan Administrator (termasuk Pembaruan Foto & Penghapusan Foto Lama)
 */

require_once dirname(__DIR__, 2) . '/config/koneksi.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__, 2) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);

$adminController = new AdminController($pdo);
$kontestan = $adminController->getKontestanById($_GET['id'] ?? null);

$errors = [];

if ($kontestan === null) {
    http_response_code(404);
    $pageTitle = 'Kontestan Tidak Ditemukan';
    $adminMenu = 'kontestan';
    $adminHeading = 'Kontestan Tidak Ditemukan';
    $extraCss = ['css/admin.css'];

    require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
    require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
    ?>
    <div class="ks-empty-state">
        <i class="bi bi-exclamation-triangle fs-2 text-warning d-block mb-2"></i>
        <h2 class="h5 fw-bold mb-1">Data Kontestan Tidak Ditemukan</h2>
        <p class="small text-muted mb-3">ID kontestan tidak valid atau data telah dihapus dari sistem.</p>
        <a href="<?= e(baseUrl('admin/kontestan/index.php')) ?>" class="btn btn-emerald btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Kontestan
        </a>
    </div>
    </main>
    </div>
    </div>
    <?php
    require_once dirname(__DIR__, 2) . '/views/layouts/footer.php';
    exit;
}

$kontestanId = (int) $kontestan['id'];
$jumlahSuaraKontestan = $adminController->getKontestanVoteCount($kontestanId);
$kategoriTerkunci = $jumlahSuaraKontestan > 0;
$old = [
    'nomor_peserta' => (string) ($kontestan['nomor_peserta'] ?? ''),
    'nama'          => (string) ($kontestan['nama'] ?? ''),
    'kategori'      => (string) ($kontestan['kategori'] ?? 'Kakang'),
    'asal_instansi' => (string) ($kontestan['asal_instansi'] ?? ''),
    'tempat_lahir'  => (string) ($kontestan['tempat_lahir'] ?? ''),
    'tanggal_lahir' => (string) ($kontestan['tanggal_lahir'] ?? ''),
    'visi'          => (string) ($kontestan['visi'] ?? ''),
    'misi'          => (string) ($kontestan['misi'] ?? ''),
    'prestasi'      => (string) ($kontestan['prestasi'] ?? ''),
    'deskripsi'     => (string) ($kontestan['deskripsi'] ?? ''),
    'status'        => (string) ($kontestan['status'] ?? 'Aktif'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'Ukuran data atau foto yang dikirim melebihi batas unggahan server.';
    } else {
        $result = $adminController->updateKontestan($kontestanId, $_POST, $_FILES);
        if ($result['success']) {
            setFlash('success', 'Berhasil', 'Perubahan data kontestan berhasil disimpan.');
            redirect('admin/kontestan/index.php');
        }

        $errors = $result['errors'];
        if (!empty($result['old'])) {
            $old = array_merge($old, $result['old']);
        }
    }
}

$pageTitle = 'Edit Kontestan';
$adminMenu = 'kontestan';
$adminHeading = 'Edit Data Kontestan';
$extraCss = ['css/admin.css'];
$extraJs = ['js/admin.js'];

require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= e(baseUrl('admin/kontestan/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Kontestan
    </a>

    <?php if (($kontestan['status'] ?? '') === 'Aktif'): ?>
        <a href="<?= e(baseUrl('pages/detail-kontestan.php?id=' . $kontestanId)) ?>" class="btn btn-outline-emerald btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i> Lihat Halaman Publik
        </a>
    <?php endif; ?>
</div>

<section class="admin-panel-card p-3 p-md-4">
    <form method="POST" action="<?= e(baseUrl('admin/kontestan/edit.php?id=' . $kontestanId)) ?>" enctype="multipart/form-data" data-loading-form>
        <?= csrfField() ?>

        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="border rounded-3 p-3 bg-light bg-opacity-50 text-center">
                    <label for="foto" class="form-label fw-semibold d-block mb-3">
                        Foto Kontestan
                    </label>
                    <img
                        id="fotoPreview"
                        src="<?= e(kontestanFotoUrl($kontestan['foto'] ?? null)) ?>"
                        alt="Foto <?= e($kontestan['nama']) ?>"
                        class="admin-preview-4x5 mb-3"
                    >
                    <input
                        type="file"
                        class="form-control form-control-sm"
                        id="foto"
                        name="foto"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >
                    <div class="form-text small text-muted mt-2 text-start">
                        <i class="bi bi-info-circle me-1"></i>
                        Biarkan kosong jika tidak ingin mengganti foto. Jika diganti, foto lama otomatis dihapus setelah pembaruan berhasil (maks 2 MB: JPEG, PNG, WebP).
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row g-3">
                    <div class="col-12 col-sm-4">
                        <label for="nomor_peserta" class="form-label fw-medium">Nomor Peserta <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="nomor_peserta"
                            name="nomor_peserta"
                            value="<?= e($old['nomor_peserta']) ?>"
                            maxlength="10"
                            required
                        >
                    </div>

                    <div class="col-12 col-sm-8">
                        <label for="nama" class="form-label fw-medium">Nama Lengkap <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="nama"
                            name="nama"
                            value="<?= e($old['nama']) ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="kategori" class="form-label fw-medium">Kategori <span class="text-danger">*</span></label>
                        <?php if ($kategoriTerkunci): ?>
                            <select class="form-select" id="kategori" disabled aria-describedby="kategoriLockNote">
                                <option value="Kakang" <?= ($kontestan['kategori'] ?? '') === 'Kakang' ? 'selected' : '' ?>>Kakang</option>
                                <option value="Senduk" <?= ($kontestan['kategori'] ?? '') === 'Senduk' ? 'selected' : '' ?>>Senduk</option>
                            </select>
                            <input type="hidden" name="kategori" value="<?= e($kontestan['kategori'] ?? 'Kakang') ?>">
                            <div id="kategoriLockNote" class="form-text small text-warning-emphasis">
                                <i class="bi bi-lock me-1"></i>Kategori terkunci karena kontestan sudah memiliki <?= number_format($jumlahSuaraKontestan, 0, ',', '.') ?> suara tercatat.
                            </div>
                        <?php else: ?>
                            <select class="form-select" id="kategori" name="kategori" required>
                                <option value="Kakang" <?= $old['kategori'] === 'Kakang' ? 'selected' : '' ?>>Kakang</option>
                                <option value="Senduk" <?= $old['kategori'] === 'Senduk' ? 'selected' : '' ?>>Senduk</option>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="status" class="form-label fw-medium">Status Tampil <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Aktif" <?= $old['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif (Tampil &amp; Dapat Dipilih)</option>
                            <option value="Nonaktif" <?= $old['status'] === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif (Disembunyikan)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="asal_instansi" class="form-label fw-medium">Asal Instansi <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="asal_instansi"
                            name="asal_instansi"
                            value="<?= e($old['asal_instansi']) ?>"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="tempat_lahir" class="form-label fw-medium">Tempat Lahir</label>
                        <input
                            type="text"
                            class="form-control"
                            id="tempat_lahir"
                            name="tempat_lahir"
                            value="<?= e($old['tempat_lahir']) ?>"
                            maxlength="100"
                        >
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="tanggal_lahir" class="form-label fw-medium">Tanggal Lahir</label>
                        <input
                            type="date"
                            class="form-control"
                            id="tanggal_lahir"
                            name="tanggal_lahir"
                            value="<?= e($old['tanggal_lahir']) ?>"
                        >
                    </div>

                    <div class="col-12">
                        <label for="visi" class="form-label fw-medium">Visi <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="visi" name="visi" rows="2" required><?= e($old['visi']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="misi" class="form-label fw-medium">Misi <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="misi" name="misi" rows="3" required><?= e($old['misi']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="prestasi" class="form-label fw-medium">Prestasi (Opsional)</label>
                        <textarea class="form-control" id="prestasi" name="prestasi" rows="2"><?= e($old['prestasi']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="deskripsi" class="form-label fw-medium">Deskripsi Profil Lengkap <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4" required><?= e($old['deskripsi']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
                    <a href="<?= e(baseUrl('admin/kontestan/index.php')) ?>" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-emerald px-4" data-loading-text="Memperbarui...">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </form>
</section>

</main>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>

