<?php
declare(strict_types=1);

/**
 * Endpoint Hapus Periode Voting Administrator
 * Hanya diizinkan melalui POST + CSRF + Nonaktif + 0 riwayat suara.
 */

require_once dirname(__DIR__, 2) . '/config/koneksi.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__, 2) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__, 2) . '/app/middleware/AdminMiddleware.php';
require_once dirname(__DIR__, 2) . '/app/controllers/AdminController.php';

AdminMiddleware::handle($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('warning', 'Metode Tidak Diizinkan', 'Penghapusan periode voting hanya dapat dilakukan melalui konfirmasi form.');
    redirect('admin/periode/index.php');
}

$adminController = new AdminController($pdo);
$result = $adminController->deletePeriode($_POST);

if ($result['success']) {
    setFlash('success', 'Berhasil', $result['message']);
} else {
    setFlash('error', 'Penghapusan Ditolak', $result['message']);
}

redirect('admin/periode/index.php');

