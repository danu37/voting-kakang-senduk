<?php
declare(strict_types=1);

/**
 * Layout Header HTML Global
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== ''
    ? $pageTitle . ' — ' . APP_SHORT_NAME
    : APP_NAME;
$bodyClass = isset($bodyClass) && is_string($bodyClass) ? $bodyClass : '';
$extraCss = isset($extraCss) && is_array($extraCss) ? $extraCss : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(assetUrl('css/style.css')) ?>" rel="stylesheet">
    <?php foreach ($extraCss as $cssPath): ?>
        <link href="<?= e(assetUrl($cssPath)) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">

