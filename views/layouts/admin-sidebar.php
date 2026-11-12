<?php
declare(strict_types=1);

/**
 * Layout Sidebar & Topbar Administrator
 * Mengharapkan: $adminMenu (string), $adminUser (array), $pageTitle (string).
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$adminMenu = $adminMenu ?? 'dashboard';
$adminUser = $adminUser ?? (currentUser() ?? ['nama' => 'Administrator', 'email' => '']);

$renderSidebarMenu = static function (string $activeKey): void {
    $items = [
        ['key' => 'dashboard',    'label' => 'Dashboard',       'icon' => 'bi-speedometer2',  'path' => 'admin/dashboard.php'],
        ['key' => 'kontestan',    'label' => 'Data Kontestan',  'icon' => 'bi-people-fill',   'path' => 'admin/kontestan/index.php'],
        ['key' => 'pemilih',      'label' => 'Data Pemilih',    'icon' => 'bi-person-check',  'path' => 'admin/pemilih/index.php'],
        ['key' => 'periode',      'label' => 'Periode Voting',  'icon' => 'bi-calendar-event','path' => 'admin/periode/index.php'],
        ['key' => 'hasil',        'label' => 'Hasil Voting',    'icon' => 'bi-bar-chart-line','path' => 'admin/hasil-voting.php'],
    ];
    ?>
    <ul class="nav flex-column gap-1">
        <?php foreach ($items as $item): ?>
            <li class="nav-item">
                <a class="nav-link <?= $activeKey === $item['key'] ? 'active' : '' ?>" href="<?= e(baseUrl($item['path'])) ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <hr class="border-white border-opacity-10 my-3">

    <ul class="nav flex-column gap-1">
        <li class="nav-item">
            <a class="nav-link" href="<?= e(baseUrl('index.php')) ?>">
                <i class="bi bi-globe2"></i>
                <span>Lihat Website</span>
            </a>
        </li>
        <li class="nav-item mt-1">
            <form
                method="POST"
                action="<?= e(baseUrl('auth/logout.php')) ?>"
                data-confirm-submit
                data-confirm-title="Konfirmasi Logout"
                data-confirm-text="Apakah Anda yakin ingin keluar dari panel Administrator?"
                data-confirm-btn="Ya, Logout"
            >
                <?= csrfField() ?>
                <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent text-danger-emphasis">
                    <i class="bi bi-box-arrow-right text-warning"></i>
                    <span class="text-white">Logout</span>
                </button>
            </form>
        </li>
    </ul>
    <?php
};
?>
<div class="d-lg-flex admin-shell">
    <!-- Sidebar Desktop -->
    <aside class="admin-sidebar d-none d-lg-flex flex-column p-3">
        <div class="d-flex align-items-center gap-2 px-2 py-2 mb-3 border-bottom border-white border-opacity-10 pb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 border border-warning text-warning" style="width:38px;height:38px;background:rgba(212,175,55,0.12);">
                <i class="bi bi-award-fill"></i>
            </span>
            <div class="lh-sm">
                <div class="font-heading fw-bold text-white small">Admin Panel</div>
                <div class="text-white-50" style="font-size: 0.72rem;">Kakang Senduk Ponorogo</div>
            </div>
        </div>

        <div class="flex-grow-1">
            <?php $renderSidebarMenu($adminMenu); ?>
        </div>

        <div class="pt-3 mt-3 border-top border-white border-opacity-10 px-2 small text-white-50">
            <div class="fw-semibold text-white text-truncate"><?= e($adminUser['nama'] ?? 'Admin') ?></div>
            <div class="text-truncate" style="font-size: 0.75rem;"><?= e($adminUser['email'] ?? '') ?></div>
        </div>
    </aside>

    <!-- Offcanvas Sidebar Mobile/Tablet -->
    <div class="offcanvas offcanvas-start bg-dark text-white d-lg-none" tabindex="-1" id="adminMobileSidebar" aria-labelledby="adminMobileSidebarLabel" style="background-color: #022C22 !important; max-width: 280px;">
        <div class="offcanvas-header border-bottom border-white border-opacity-10">
            <h2 class="offcanvas-title h6 text-white mb-0" id="adminMobileSidebarLabel">
                <i class="bi bi-award-fill text-warning me-2"></i>Admin Panel
            </h2>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body admin-sidebar w-100 border-0 p-3 d-flex flex-column">
            <div class="flex-grow-1">
                <?php $renderSidebarMenu($adminMenu); ?>
            </div>
            <div class="pt-3 mt-3 border-top border-white border-opacity-10 px-2 small text-white-50">
                <div class="fw-semibold text-white text-truncate"><?= e($adminUser['nama'] ?? 'Admin') ?></div>
                <div class="text-truncate" style="font-size: 0.75rem;"><?= e($adminUser['email'] ?? '') ?></div>
            </div>
        </div>
    </div>

    <!-- Area Konten Utama -->
    <div class="flex-grow-1 d-flex flex-column min-w-0">
        <header class="admin-topbar px-3 px-lg-4 py-3 d-flex align-items-center justify-content-between gap-2 overflow-hidden">
            <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                <button
                    class="btn btn-outline-secondary btn-sm d-lg-none flex-shrink-0"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#adminMobileSidebar"
                    aria-controls="adminMobileSidebar"
                    aria-label="Buka menu admin"
                >
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h1 class="fw-bold mb-0 text-truncate" style="min-width: 0; font-size: clamp(1rem, 3.6vw, 1.25rem);"><?= e($adminHeading ?? 'Dashboard') ?></h1>
            </div>

            <div class="d-flex align-items-center gap-2 small text-muted flex-shrink-0">
                <i class="bi bi-person-badge text-emerald"></i>
                <span class="d-none d-sm-inline"><?= e($adminUser['nama'] ?? 'Administrator') ?></span>
                <span class="badge text-bg-success">Admin</span>
            </div>
        </header>

        <main class="p-3 p-lg-4 flex-grow-1 min-w-0">
            <?php
                $errors = isset($errors) && is_array($errors) ? $errors : [];
                require dirname(__DIR__) . '/components/alert.php';
            ?>

