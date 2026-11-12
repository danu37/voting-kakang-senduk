<?php
declare(strict_types=1);

/**
 * Manajemen Kontestan Administrator (Daftar, Filter, Pencarian, Pagination)
 */

require_once dirname(__DIR__, 2) . '/config/koneksi.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__, 2) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);

$adminController = new AdminController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeText($_POST['action'] ?? '');
    if ($action === 'toggle_status') {
        $result = $adminController->toggleKontestanStatus($_POST);
        if ($result['success']) {
            setFlash('success', 'Berhasil', (string) $result['message']);
        } else {
            setFlash('error', 'Gagal Memperbarui Status', (string) $result['message']);
        }
    } else {
        setFlash('warning', 'Aksi Tidak Dikenal', 'Permintaan perubahan data kontestan tidak valid.');
    }

    $redirectParams = [];
    foreach (['kategori', 'status', 'cari', 'halaman'] as $paramKey) {
        $paramVal = trim((string) ($_GET[$paramKey] ?? ''));
        if ($paramVal !== '') {
            $redirectParams[$paramKey] = $paramVal;
        }
    }
    $redirectTarget = 'admin/kontestan/index.php'
        . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');
    redirect($redirectTarget);
}

$data = $adminController->getDaftarKontestan($_GET);

$items = $data['items'];
$total = (int) $data['total'];
$page = (int) $data['page'];
$perPage = (int) $data['per_page'];
$totalPages = (int) $data['total_pages'];

$pageTitle = 'Manajemen Kontestan';
$adminMenu = 'kontestan';
$adminHeading = 'Manajemen Kontestan';
$extraCss = ['css/admin.css'];
$errors = [];

require_once dirname(__DIR__, 2) . '/views/layouts/header.php';
require_once dirname(__DIR__, 2) . '/views/layouts/admin-sidebar.php';
?>

<section class="admin-panel-card p-3 p-md-4 mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3 pb-3 border-bottom">
        <div>
            <h2 class="h5 fw-bold mb-1">Daftar Kontestan Kakang &amp; Senduk</h2>
            <p class="small text-muted mb-0">
                Total <?= number_format($total, 0, ',', '.') ?> kontestan ditemukan.
            </p>
        </div>

        <a href="<?= e(baseUrl('admin/kontestan/tambah.php')) ?>" class="btn btn-emerald">
            <i class="bi bi-plus-circle me-1"></i> Tambah Kontestan
        </a>
    </div>

    <!-- Form Filter & Pencarian -->
    <form method="GET" action="<?= e(baseUrl('admin/kontestan/index.php')) ?>" class="row g-2 align-items-end mb-4" data-loading-form>
        <div class="col-12 col-md-4">
            <label for="cari" class="form-label small fw-medium mb-1">Cari Nama / Nomor / Instansi</label>
            <input
                type="text"
                class="form-control form-control-sm"
                id="cari"
                name="cari"
                value="<?= e($data['cari']) ?>"
                placeholder="Contoh: K-01 atau nama kontestan"
                maxlength="100"
            >
        </div>

        <div class="col-6 col-md-3">
            <label for="kategori" class="form-label small fw-medium mb-1">Kategori</label>
            <select name="kategori" id="kategori" class="form-select form-select-sm">
                <option value="">Semua Kategori</option>
                <option value="Kakang" <?= $data['kategori'] === 'Kakang' ? 'selected' : '' ?>>Kakang</option>
                <option value="Senduk" <?= $data['kategori'] === 'Senduk' ? 'selected' : '' ?>>Senduk</option>
            </select>
        </div>

        <div class="col-6 col-md-3">
            <label for="status" class="form-label small fw-medium mb-1">Status</label>
            <select name="status" id="status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="Aktif" <?= $data['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="Nonaktif" <?= $data['status'] === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-emerald btn-sm flex-grow-1">
                <i class="bi bi-search me-1"></i> Filter
            </button>
            <?php if ($data['cari'] !== '' || $data['kategori'] !== '' || $data['status'] !== ''): ?>
                <a href="<?= e(baseUrl('admin/kontestan/index.php')) ?>" class="btn btn-outline-secondary btn-sm" title="Reset filter">
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
                        <th>Foto</th>
                        <th>No. Peserta</th>
                        <th>Nama Lengkap</th>
                        <th>Kategori</th>
                        <th>Asal Instansi</th>
                        <th>Status</th>
                        <th class="text-end">Suara</th>
                        <th class="text-end" style="min-width: 230px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $id = (int) $item['id'];
                            $suara = (int) ($item['total_suara'] ?? 0);
                            $isAktif = ($item['status'] ?? '') === 'Aktif';
                            $badgeKat = ($item['kategori'] ?? '') === 'Senduk' ? 'ks-badge-senduk' : 'ks-badge-kakang';
                            $actionQueryParams = array_filter([
                                'kategori' => $data['kategori'],
                                'status'   => $data['status'],
                                'cari'     => $data['cari'],
                                'halaman'  => $page > 1 ? (string) $page : '',
                            ], static fn ($v) => $v !== '' && $v !== null);
                            $actionPostUrl = 'admin/kontestan/index.php'
                                . (!empty($actionQueryParams) ? '?' . http_build_query($actionQueryParams) : '');
                        ?>
                        <tr>
                            <td>
                                <img
                                    src="<?= e(kontestanFotoUrl($item['foto'] ?? null)) ?>"
                                    alt="Foto <?= e($item['nama']) ?>"
                                    class="admin-thumb-4x5"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </td>
                            <td class="fw-semibold text-nowrap"><?= e($item['nomor_peserta']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($item['nama']) ?></div>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= e($badgeKat) ?>"><?= e($item['kategori']) ?></span>
                            </td>
                            <td class="text-muted"><?= e($item['asal_instansi']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= $isAktif ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= $isAktif ? 'Aktif' : 'Diarsipkan (Nonaktif)' ?>
                                </span>
                            </td>
                            <td class="text-end fw-semibold"><?= number_format($suara, 0, ',', '.') ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1 align-items-center justify-content-end">
                                    <?php if ($isAktif): ?>
                                        <a
                                            href="<?= e(baseUrl('pages/detail-kontestan.php?id=' . $id)) ?>"
                                            class="btn btn-outline-secondary btn-sm"
                                            title="Lihat halaman publik"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        href="<?= e(baseUrl('admin/kontestan/edit.php?id=' . $id)) ?>"
                                        class="btn btn-outline-emerald btn-sm"
                                    >
                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                    </a>

                                    <?php if ($isAktif): ?>
                                        <form
                                            method="POST"
                                            action="<?= e(baseUrl($actionPostUrl)) ?>"
                                            class="d-inline"
                                            data-confirm-submit
                                            data-confirm-title="Arsipkan Kontestan?"
                                            data-confirm-text="<?= $suara > 0
                                                ? 'Kontestan ' . e($item['nama']) . ' (' . e($item['nomor_peserta']) . ') memiliki ' . $suara . ' suara tercatat sehingga tidak dapat dihapus. Mengarsipkan akan mengubah status ke Nonaktif sambil menjaga seluruh riwayat voting.'
                                                : 'Arsipkan kontestan ' . e($item['nama']) . ' (' . e($item['nomor_peserta']) . ') ke status Nonaktif agar tidak tampil di pemilihan?' ?>"
                                            data-confirm-btn="Ya, Arsipkan"
                                        >
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <input type="hidden" name="target_status" value="Nonaktif">
                                            <button type="submit" class="btn btn-outline-warning btn-sm" title="Arsipkan (Nonaktifkan) kontestan">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form
                                            method="POST"
                                            action="<?= e(baseUrl($actionPostUrl)) ?>"
                                            class="d-inline"
                                            data-confirm-submit
                                            data-confirm-title="Aktifkan Kontestan?"
                                            data-confirm-text="Aktifkan kembali kontestan <?= e($item['nama']) ?> (<?= e($item['nomor_peserta']) ?>) ke status Aktif?"
                                            data-confirm-btn="Ya, Aktifkan"
                                        >
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <input type="hidden" name="target_status" value="Aktif">
                                            <button type="submit" class="btn btn-outline-success btn-sm" title="Aktifkan kembali kontestan">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($suara > 0): ?>
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            disabled
                                            title="Hapus permanen terkunci karena memiliki <?= $suara ?> suara. Gunakan tombol Arsipkan."
                                        >
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    <?php else: ?>
                                        <form
                                            method="POST"
                                            action="<?= e(baseUrl('admin/kontestan/hapus.php')) ?>"
                                            class="d-inline"
                                            data-confirm-submit
                                            data-confirm-title="Hapus Kontestan?"
                                            data-confirm-text="Data kontestan <?= e($item['nama']) ?> (<?= e($item['nomor_peserta']) ?>) dan fotonya akan dihapus permanen."
                                            data-confirm-btn="Ya, Hapus"
                                        >
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus kontestan">
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
            $paginationBasePath = 'admin/kontestan/index.php';
            $paginationQuery = [
                'kategori' => $data['kategori'],
                'status'   => $data['status'],
                'cari'     => $data['cari'],
            ];
            require dirname(__DIR__, 2) . '/views/components/pagination.php';
        ?>
    <?php else: ?>
        <div class="ks-empty-state">
            <i class="bi bi-people fs-2 text-muted d-block mb-2"></i>
            <h3 class="h6 fw-bold mb-1">Belum Ada Kontestan yang Sesuai</h3>
            <p class="small text-muted mb-3">
                <?php if ($data['cari'] !== '' || $data['kategori'] !== '' || $data['status'] !== ''): ?>
                    Coba ubah kata kunci pencarian atau reset filter untuk melihat seluruh data kontestan.
                <?php else: ?>
                    Silakan tambahkan kontestan Kakang dan Senduk pertama Anda.
                <?php endif; ?>
            </p>
            <?php if ($data['cari'] !== '' || $data['kategori'] !== '' || $data['status'] !== ''): ?>
                <a href="<?= e(baseUrl('admin/kontestan/index.php')) ?>" class="btn btn-outline-emerald btn-sm">
                    Reset Filter
                </a>
            <?php else: ?>
                <a href="<?= e(baseUrl('admin/kontestan/tambah.php')) ?>" class="btn btn-emerald btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Kontestan
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

</main>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/views/layouts/footer.php'; ?>

