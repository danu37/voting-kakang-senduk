<?php
declare(strict_types=1);

/**
 * Komponen Alert & Flash Notification
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$errors = isset($errors) && is_array($errors) ? $errors : [];
$flash = getFlash();
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-3" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-circle-fill mt-1 flex-shrink-0"></i>
            <div>
                <?php if (count($errors) === 1): ?>
                    <span><?= e($errors[0]) ?></span>
                <?php else: ?>
                    <strong class="d-block mb-1">Periksa kembali data yang Anda masukkan:</strong>
                    <ul class="mb-0 ps-3 small">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($flash !== null): ?>
    <?php
        $alertMap = [
            'success' => 'alert-success',
            'warning' => 'alert-warning',
            'danger'  => 'alert-danger',
            'error'   => 'alert-danger',
            'info'    => 'alert-info',
        ];
        $bsClass = $alertMap[$flash['type'] ?? 'info'] ?? 'alert-info';
        $flashJson = json_encode([
            'type'    => (string) ($flash['type'] ?? 'info'),
            'title'   => (string) ($flash['title'] ?? 'Informasi'),
            'message' => (string) ($flash['message'] ?? ''),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    ?>
    <div class="alert <?= e($bsClass) ?> border-0 shadow-sm rounded-3 mb-3" role="alert">
        <strong class="me-1"><?= e($flash['title'] ?? 'Informasi') ?>:</strong>
        <span><?= e($flash['message'] ?? '') ?></span>
    </div>
    <script id="ks-flash-data" type="application/json"><?= $flashJson ?></script>
<?php endif; ?>

