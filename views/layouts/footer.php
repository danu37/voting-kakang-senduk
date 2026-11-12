<?php
declare(strict_types=1);

/**
 * Layout Footer HTML Global (Opsional menyertakan Footer Publik)
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$extraJs = isset($extraJs) && is_array($extraJs) ? $extraJs : [];
$showPublicFooter = isset($showPublicFooter) && $showPublicFooter === true;
?>
<?php if ($showPublicFooter): ?>
    <footer class="ks-footer pt-5 pb-4 mt-auto">
        <div class="container">
            <div class="row g-4 pb-4 border-bottom border-white border-opacity-10">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="ks-brand-mark" aria-hidden="true">
                            <i class="bi bi-award-fill"></i>
                        </span>
                        <div>
                            <h2 class="h6 text-white mb-0">Kakang Senduk Ponorogo</h2>
                            <small class="text-white-50">Pemilihan Duta Wisata Daerah</small>
                        </div>
                    </div>
                    <p class="small mb-0" style="max-width: 44ch;">
                        Platform pemungutan suara resmi Pemilihan Kakang Senduk Kabupaten Ponorogo untuk mendukung duta wisata berprestasi, berbudaya, dan berintegritas.
                    </p>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <h3 class="h6 text-white mb-3">Navigasi</h3>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a class="ks-footer-link" href="<?= e(baseUrl('index.php')) ?>">Beranda</a></li>
                        <li><a class="ks-footer-link" href="<?= e(baseUrl('pages/kontestan.php')) ?>">Daftar Kontestan</a></li>
                        <li><a class="ks-footer-link" href="<?= e(baseUrl('pages/klasemen.php')) ?>">Klasemen Sementara</a></li>
                        <li><a class="ks-footer-link" href="<?= e(baseUrl('index.php#tentang')) ?>">Tentang Pemilihan</a></li>
                    </ul>
                </div>

                <div class="col-12 col-sm-6 col-lg-4">
                    <h3 class="h6 text-white mb-3">Partisipasi Pemilih</h3>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a class="ks-footer-link" href="<?= e(baseUrl('pages/voting.php')) ?>">Mulai Voting</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a class="ks-footer-link" href="<?= e(baseUrl('auth/logout.php')) ?>">Keluar Akun</a></li>
                        <?php else: ?>
                            <li><a class="ks-footer-link" href="<?= e(baseUrl('auth/login.php')) ?>">Masuk Akun</a></li>
                            <li><a class="ks-footer-link" href="<?= e(baseUrl('auth/register.php')) ?>">Daftar Akun Pemilih</a></li>
                        <?php endif; ?>
                        <li class="text-white-50 pt-1">
                            <i class="bi bi-geo-alt me-1 text-gold"></i> Kabupaten Ponorogo, Jawa Timur
                        </li>
                    </ul>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 pt-3 small text-white-50">
                <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>.</span>
                <span>Melestarikan Budaya Reog &amp; Pesona Wisata Ponorogo</span>
            </div>
        </div>
    </footer>
<?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= e(assetUrl('js/app.js')) ?>"></script>
    <?php foreach ($extraJs as $jsPath): ?>
        <?php
            $jsSrc = (str_starts_with($jsPath, 'https://') || str_starts_with($jsPath, 'http://'))
                ? $jsPath
                : assetUrl($jsPath);
        ?>
        <script src="<?= e($jsSrc) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
