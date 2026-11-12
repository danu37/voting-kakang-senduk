<?php
declare(strict_types=1);

/**
 * Komponen Pagination Reusable
 * Mengharapkan variabel: $paginationPage, $paginationTotalPages, $paginationBasePath, $paginationQuery (array).
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$curPage = max(1, (int) ($paginationPage ?? 1));
$totPages = max(1, (int) ($paginationTotalPages ?? 1));
$basePath = (string) ($paginationBasePath ?? 'pages/kontestan.php');
$extraQuery = is_array($paginationQuery ?? null) ? $paginationQuery : [];

if ($totPages <= 1) {
    return;
}

$buildPageUrl = static function (int $targetPage) use ($basePath, $extraQuery): string {
    $params = array_filter($extraQuery, static fn ($v) => $v !== '' && $v !== null);
    $params['halaman'] = $targetPage;
    return baseUrl($basePath . '?' . http_build_query($params));
};
?>
<nav aria-label="Navigasi halaman" class="mt-4">
    <ul class="pagination justify-content-center flex-wrap gap-1 mb-0">
        <li class="page-item <?= $curPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($buildPageUrl(max(1, $curPage - 1))) ?>" aria-label="Sebelumnya">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>

        <?php for ($p = 1; $p <= $totPages; $p++): ?>
            <li class="page-item <?= $p === $curPage ? 'active' : '' ?>">
                <a
                    class="page-link"
                    href="<?= e($buildPageUrl($p)) ?>"
                    <?= $p === $curPage ? 'aria-current="page"' : '' ?>
                >
                    <?= $p ?>
                </a>
            </li>
        <?php endfor; ?>

        <li class="page-item <?= $curPage >= $totPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($buildPageUrl(min($totPages, $curPage + 1))) ?>" aria-label="Berikutnya">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>

