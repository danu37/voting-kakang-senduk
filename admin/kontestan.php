<?php
declare(strict_types=1);

/**
 * Pengalihan aman rute lama ke admin/kontestan/index.php
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/middleware/AdminMiddleware.php';

AdminMiddleware::handle($pdo);
redirect('admin/kontestan/index.php');

