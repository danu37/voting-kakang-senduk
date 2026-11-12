<?php
declare(strict_types=1);

/**
 * Tambah Kontestan Baru + Upload Foto Aman
 */

require_once dirname(__DIR__, 2) . '/config/koneksi.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__, 2) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);

$errors = [];
$old = [
    'nomor_peserta' => '',
    'nama'          => '',
    'kategori'      => 'Kakang',
    'asal_instansi' => '',
    'tempat_lahir'  => '',
    'tanggal_lahir' => '',
    'visi'          => '',
    'misi'          => '',
    'prestasi'      => '',
    'deskripsi'     => '',
    'status'        => 'Aktif',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'Ukuran data atau foto yang dikirim melebihi batas unggahan server.';
    } else {
        $adminController = new AdminController($pdo);
        $result = $adminController->storeKontestan($_POST, $_FILES);

        if ($result['success']) {
            setFlash('success', 'Berhasil', 'Data kontestan baru berhasil ditambahkan.');
            redirect('admin/kontestan/index.php');
        }

        $errors = $result['errors'];
        $old = array_merge($old, $result['old']);
    }
}

$pageTitle = 'Tambah Kontestan';
$adminMenu = 'kontestan';
$adminHeading = 'Tambah Kontestan Baru';
$extraCss = ['css/admin.css'];
$extraJs = ['js/admin.js'];

require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
?>

<div class="mb-3">
    <a href="<?= e(baseUrl('admin/kontestan/index.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Kontestan
    </a>
</div>

<section class="admin-panel-card p-3 p-md-4">
    <form method="POST" action="<?= e(baseUrl('admin/kontestan/tambah.php')) ?>" enctype="multipart/form-data" data-loading-form>
        <?= csrfField() ?>

        <div class="row g-4">
            <!-- Kolom Kiri: Upload Foto & Preview 4:5 -->
            <div class="col-12 col-lg-4">
                <div class="border rounded-3 p-3 bg-light bg-opacity-50 text-center">
                    <label for="foto" class="form-label fw-semibold d-block mb-3">
                        Foto Kontestan <span class="text-danger">*</span>
                    </label>
                    <img
                        id="fotoPreview"
                        src="<?= e(assetUrl('img/kontestan-placeholder.svg')) ?>"
                        alt="Pratinjau foto kontestan"
                        class="admin-preview-4x5 mb-3"
                    >
                    <input
                        type="file"
                        class="form-control form-control-sm"
                        id="foto"
                        name="foto"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        required
                    >
                    <div class="form-text small text-muted mt-2 text-start">
                        <i class="bi bi-info-circle me-1"></i>
                        Format wajib: <strong>JPEG, PNG, atau WebP</strong>. Ukuran maksimal <strong>2 MB</strong>. Disarankan rasio 4:5 (potret).
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Identitas & Profil Kontestan -->
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
                            placeholder="Misal: K-01"
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
                            placeholder="Nama lengkap kontestan"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="kategori" class="form-label fw-medium">Kategori <span class="text-danger">*</span></label>
                        <select class="form-select" id="kategori" name="kategori" required>
                            <option value="Kakang" <?= $old['kategori'] === 'Kakang' ? 'selected' : '' ?>>Kakang</option>
                            <option value="Senduk" <?= $old['kategori'] === 'Senduk' ? 'selected' : '' ?>>Senduk</option>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="status" class="form-label fw-medium">Status Tampil <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Aktif" <?= $old['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif (Tampil &amp; Dapat Dipilih)</option>
                            <option value="Nonaktif" <?= $old['status'] === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif (Disembunyikan)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="asal_instansi" class="form-label fw-medium">Asal Instansi / Sekolah / Kampus <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="asal_instansi"
                            name="asal_instansi"
                            value="<?= e($old['asal_instansi']) ?>"
                            placeholder="Contoh: SMAN 1 Ponorogo / Universitas Muhammadiyah Ponorogo"
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
                            placeholder="Contoh: Ponorogo"
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
                        <textarea class="form-control" id="visi" name="visi" rows="2" required placeholder="Tuliskan visi sebagai Duta Wisata Ponorogo..."><?= e($old['visi']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="misi" class="form-label fw-medium">Misi <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="misi" name="misi" rows="3" required placeholder="Tuliskan misi atau program kerja utama..."><?= e($old['misi']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="prestasi" class="form-label fw-medium">Prestasi (Opsional)</label>
                        <textarea class="form-control" id="prestasi" name="prestasi" rows="2" placeholder="Daftar prestasi akademik atau non-akademik..."><?= e($old['prestasi']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="deskripsi" class="form-label fw-medium">Deskripsi Profil Lengkap <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4" required placeholder="Profil singkat, minat budaya, dan motivasi mengikuti pemilihan..."><?= e($old['deskripsi']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end mt-4 pt-3 border-top">
                    <a href="<?= e(baseUrl('admin/kontestan/index.php')) ?>" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-emerald px-4" data-loading-text="Menyimpan...">
                        <i class="bi bi-save me-1"></i> Simpan Kontestan
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

