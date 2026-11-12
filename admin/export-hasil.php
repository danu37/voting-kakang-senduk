<?php
declare(strict_types=1);

/**
 * Endpoint Ekspor Laporan Hasil Voting Administrator (Excel .xlsx & PDF .pdf)
 * Dilindungi penuh oleh AdminMiddleware dan mengambil data langsung dari MySQL.
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ExportHelper.php';
require_once dirname(__DIR__) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';

$adminUser = AdminMiddleware::handle($pdo);

$formatRaw = strtolower(sanitizeText($_GET['format'] ?? ''));
if ($formatRaw === 'excel') {
    $formatRaw = 'xlsx';
}

if (!in_array($formatRaw, ['xlsx', 'pdf'], true)) {
    setFlash('warning', 'Format Tidak Didukung', 'Pilih format ekspor yang valid (Excel .xlsx atau PDF .pdf).');
    redirect('admin/hasil-voting.php');
}

$adminController = new AdminController($pdo);
$data = $adminController->getHasilVotingData($_GET);
$periode = $data['periode_terpilih'] ?? null;

$periodeSlug = 'semua-periode';
if (is_array($periode) && !empty($periode['nama_periode'])) {
    $rawSlug = strtolower((string) $periode['nama_periode']);
    $rawSlug = preg_replace('/[^a-z0-9]+/', '-', $rawSlug) ?? 'periode';
    $rawSlug = trim($rawSlug, '-');
    if ($rawSlug !== '') {
        $periodeSlug = mb_substr($rawSlug, 0, 48, 'UTF-8');
    }
}

$stamp = (new DateTimeImmutable('now', new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta')))
    ->format('Ymd-His');
$filename = "hasil-voting-{$periodeSlug}-{$stamp}.{$formatRaw}";

if ($formatRaw === 'xlsx') {
    $binary = buildVotingHasilXlsxBinary($data);
    $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
} else {
    $binary = buildVotingHasilPdfBinary($data);
    $mime = 'application/pdf';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($binary));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: public');

echo $binary;
exit;

