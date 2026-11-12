<?php
declare(strict_types=1);

/**
 * Komponen Kartu Kontestan Reusable
 * Mengharapkan variabel $kontestan (array) dan opsional $showVoteButton (bool).
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$kontestanId = (int) ($kontestan['id'] ?? 0);
$fotoName = basename((string) ($kontestan['foto'] ?? ''));
$fotoPathDisk = UPLOAD_KONTESTAN_DIR . DIRECTORY_SEPARATOR . $fotoName;
$fotoSrc = ($fotoName !== '' && is_file($fotoPathDisk))
    ? uploadUrl($fotoName, 'kontestan')
    : assetUrl('img/kontestan-placeholder.svg');

$kategori = (string) ($kontestan['kategori'] ?? 'Kakang');
$badgeClass = $kategori === 'Senduk' ? 'ks-badge-senduk' : 'ks-badge-kakang';
$showVoteButton = $showVoteButton ?? true;
$deskripsiSingkat = mb_strimwidth(trim((string) ($kontestan['deskripsi'] ?? '')), 0, 95, '...', 'UTF-8');
?>
<article class="ks-contestant-item">
    <div class="ks-contestant-photo-wrap">
        <img
            src="<?= e($fotoSrc) ?>"
            alt="Foto <?= e($kontestan['nama'] ?? 'Kontestan') ?>"
            class="ks-contestant-photo"
            loading="lazy"
        >
        <span class="ks-contestant-number"><?= e($kontestan['nomor_peserta'] ?? '-') ?></span>
    </div>

    <div class="p-3 p-md-4 d-flex flex-column flex-grow-1">
        <div class="mb-2">
            <span class="badge rounded-pill <?= e($badgeClass) ?> fw-medium px-2 py-1">
                <?= e($kategori) ?>
            </span>
        </div>

        <h3 class="h6 fw-bold mb-1"><?= e($kontestan['nama'] ?? '-') ?></h3>
        <p class="small text-muted mb-2">
            <i class="bi bi-building me-1"></i><?= e($kontestan['asal_instansi'] ?? '-') ?>
        </p>

        <?php if ($deskripsiSingkat !== ''): ?>
            <p class="small text-muted mb-3 flex-grow-1"><?= e($deskripsiSingkat) ?></p>
        <?php else: ?>
            <div class="flex-grow-1"></div>
        <?php endif; ?>

        <div class="d-flex gap-2 pt-2 border-top">
            <a href="<?= e(baseUrl('pages/detail-kontestan.php?id=' . $kontestanId)) ?>" class="btn btn-outline-emerald btn-sm flex-grow-1">
                Lihat Detail
            </a>
            <?php if ($showVoteButton): ?>
                <a href="<?= e(baseUrl('pages/voting.php?kategori=' . urlencode($kategori) . '&kontestan_id=' . $kontestanId)) ?>" class="btn btn-emerald btn-sm flex-grow-1">
                    Vote
                </a>
            <?php endif; ?>
        </div>
    </div>
</article>

