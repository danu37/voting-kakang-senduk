<?php
declare(strict_types=1);

/**
 * Pengalihan aman rute lama ke admin/kontestan/edit.php
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/middleware/AdminMiddleware.php';

AdminMiddleware::handle($pdo);

if (isPositiveInt($_GET['id'] ?? null)) {
    redirect('admin/kontestan/edit.php?id=' . (int) $_GET['id']);
}

redirect('admin/kontestan/index.php');

