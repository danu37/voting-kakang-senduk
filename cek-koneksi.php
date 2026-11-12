<?php
declare(strict_types=1);

/**
 * Halaman Pemeriksaan Koneksi Database & Inisialisasi Sistem (Tahap 01)
 * Hanya aktif pada environment 'development'.
 */

define('SKIP_AUTO_DB_CONNECT', true);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/app/helpers/ResponseHelper.php';
require_once __DIR__ . '/app/helpers/AuthHelper.php';
require_once __DIR__ . '/app/helpers/ValidationHelper.php';

if (PHP_SAPI !== 'cli') {
    $isLoopback = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (APP_ENV !== 'development' || !$isLoopback) {
        http_response_code(403);
        exit('Halaman diagnostik ini dinonaktifkan pada lingkungan produksi dan hanya tersedia bagi pengembangan lokal.');
    }
}

startSecureSession();

$phpVersionOk = version_compare(PHP_VERSION, '8.2.0', '>=');
$pdoDriverOk = extension_loaded('PDO') && extension_loaded('pdo_mysql');
$uploadDirWritable = is_dir(UPLOAD_KONTESTAN_DIR) && is_writable(UPLOAD_KONTESTAN_DIR);

$dbConnected = false;
$dbErrorMessage = null;
$dbInfo = [
    'versi'      => '-',
    'nama_db'    => DB_NAME,
    'zona_waktu' => '-',
];
$tablesStatus = [];
$uniqueStatus = [];
$fkStatus = [];
$transaksiOk = false;

$tabelWajib = ['users', 'kontestan', 'periode_voting', 'voting'];
$uniqueWajib = [
    'users.email'                          => ['table' => 'users', 'constraint' => 'uq_users_email'],
    'kontestan.nomor_peserta'              => ['table' => 'kontestan', 'constraint' => 'uq_kontestan_nomor_peserta'],
    'voting(user_id, periode_id, kategori)' => ['table' => 'voting', 'constraint' => 'uq_voting_user_periode_kategori'],
];
$fkWajib = [
    'fk_voting_user'      => ['kolom' => 'user_id', 'ref' => 'users.id'],
    'fk_voting_kontestan' => ['kolom' => 'kontestan_id', 'ref' => 'kontestan.id'],
    'fk_voting_periode'   => ['kolom' => 'periode_id', 'ref' => 'periode_voting.id'],
];

try {
    $pdo = getKoneksi();
    $dbConnected = true;

    $stmtInfo = $pdo->query("SELECT VERSION() AS versi, DATABASE() AS nama_db, @@session.time_zone AS zona_waktu");
    $infoRow = $stmtInfo->fetch();
    if (is_array($infoRow)) {
        $dbInfo = [
            'versi'      => (string) ($infoRow['versi'] ?? '-'),
            'nama_db'    => (string) ($infoRow['nama_db'] ?? DB_NAME),
            'zona_waktu' => (string) ($infoRow['zona_waktu'] ?? '-'),
        ];
    }

    // Periksa tabel, engine InnoDB, dan collation utf8mb4
    $stmtTables = $pdo->prepare("
        SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :db_name
    ");
    $stmtTables->execute([':db_name' => DB_NAME]);
    $foundTables = [];
    foreach ($stmtTables->fetchAll() as $row) {
        $foundTables[$row['TABLE_NAME']] = $row;
    }

    foreach ($tabelWajib as $namaTabel) {
        $row = $foundTables[$namaTabel] ?? null;
        $ada = $row !== null;
        $engineOk = $ada && strtoupper((string) $row['ENGINE']) === 'INNODB';
        $charsetOk = $ada && str_starts_with(strtolower((string) $row['TABLE_COLLATION']), 'utf8mb4');
        $tablesStatus[$namaTabel] = [
            'ada'        => $ada,
            'engine'     => $ada ? (string) $row['ENGINE'] : '-',
            'collation'  => $ada ? (string) $row['TABLE_COLLATION'] : '-',
            'ok'         => $ada && $engineOk && $charsetOk,
        ];
    }

    // Periksa unique constraints
    $stmtUnique = $pdo->prepare("
        SELECT TABLE_NAME, CONSTRAINT_NAME
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = :db_name
          AND CONSTRAINT_TYPE = 'UNIQUE'
    ");
    $stmtUnique->execute([':db_name' => DB_NAME]);
    $foundUniques = [];
    foreach ($stmtUnique->fetchAll() as $row) {
        $foundUniques[$row['TABLE_NAME'] . ':' . $row['CONSTRAINT_NAME']] = true;
    }
    foreach ($uniqueWajib as $label => $meta) {
        $uniqueStatus[$label] = isset($foundUniques[$meta['table'] . ':' . $meta['constraint']]);
    }

    // Periksa foreign keys
    $stmtFk = $pdo->prepare("
        SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = :db_name
          AND TABLE_NAME = 'voting'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmtFk->execute([':db_name' => DB_NAME]);
    $foundFks = [];
    foreach ($stmtFk->fetchAll() as $row) {
        $foundFks[$row['CONSTRAINT_NAME']] = $row;
    }
    foreach ($fkWajib as $fkName => $expected) {
        $actual = $foundFks[$fkName] ?? null;
        $fkStatus[$fkName] = [
            'ok'     => $actual !== null && ($actual['REFERENCED_TABLE_NAME'] . '.' . $actual['REFERENCED_COLUMN_NAME']) === $expected['ref'],
            'detail' => 'voting.' . $expected['kolom'] . ' -> ' . $expected['ref'],
        ];
    }

    // Uji mekanisme transaksi PDO (hanya uji baca aman)
    $transaksiOk = (bool) jalankanTransaksi($pdo, static function (PDO $conn): bool {
        $stmtPing = $conn->prepare('SELECT :nilai AS ping');
        $stmtPing->execute([':nilai' => 1]);
        $pingRow = $stmtPing->fetch();
        return isset($pingRow['ping']) && (int) $pingRow['ping'] === 1;
    });
} catch (Throwable $e) {
    $dbConnected = false;
    $dbErrorMessage = 'Koneksi ke database belum dapat dijalin. Periksa layanan MySQL/MariaDB dan konfigurasi database.';
    error_log('[CEK_KONEKSI_ERROR] ' . $e->getMessage());
}

$semuaTabelOk = !empty($tablesStatus) && !in_array(false, array_column($tablesStatus, 'ok'), true);
$semuaUniqueOk = !empty($uniqueStatus) && !in_array(false, $uniqueStatus, true);
$semuaFkOk = !empty($fkStatus) && !in_array(false, array_column($fkStatus, 'ok'), true);
$keseluruhanOk = $phpVersionOk && $pdoDriverOk && $dbConnected && $semuaTabelOk && $semuaUniqueOk && $semuaFkOk && $transaksiOk && $uploadDirWritable;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemeriksaan Sistem &amp; Koneksi Database — <?= e(APP_SHORT_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --ks-primary: #047857;
            --ks-secondary: #064E3B;
            --ks-dark: #022C22;
            --ks-accent: #D4AF37;
            --ks-bg: #F8FAF7;
            --ks-surface: #FFFFFF;
            --ks-text: #1E293B;
            --ks-muted: #64748B;
            --ks-border: #E5E7EB;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--ks-bg);
            color: var(--ks-text);
        }
        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
        }
        .ks-header {
            background-color: var(--ks-dark);
            border-bottom: 3px solid var(--ks-accent);
        }
        .ks-card {
            background: var(--ks-surface);
            border: 1px solid var(--ks-border);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(2, 44, 34, 0.04);
        }
        .ks-badge-gold {
            background-color: rgba(212, 175, 55, 0.16);
            color: #9A7B16;
            border: 1px solid rgba(212, 175, 55, 0.45);
        }
        .ks-status-pill {
            font-size: 0.85rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <header class="ks-header text-white py-4 mb-4">
        <div class="container">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div>
                    <span class="badge ks-badge-gold mb-2">Tahap 01 &bull; Inisialisasi Proyek</span>
                    <h1 class="h4 mb-1"><?= e(APP_NAME) ?></h1>
                    <p class="mb-0 small text-white-50">Pemeriksaan Lingkungan PHP, Koneksi PDO, dan Integritas Skema MySQL</p>
                </div>
                <div>
                    <?php if ($keseluruhanOk): ?>
                        <span class="badge bg-success px-3 py-2 ks-status-pill">
                            <i class="bi bi-check-circle-fill me-1"></i> Seluruh Komponen Siap
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger px-3 py-2 ks-status-pill">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Ada Pemeriksaan yang Gagal
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="container pb-5">
        <?php if (!$dbConnected && $dbErrorMessage !== null): ?>
            <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-x-octagon-fill me-2"></i><?= e($dbErrorMessage) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Lingkungan & Koneksi -->
            <div class="col-lg-6">
                <div class="ks-card p-4 h-100">
                    <h2 class="h6 fw-bold text-uppercase mb-3" style="color: var(--ks-secondary);">
                        <i class="bi bi-hdd-rack me-2"></i>Lingkungan &amp; Koneksi PDO
                    </h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Versi PHP (Min. 8.2)</td>
                                    <td class="fw-semibold"><?= e(PHP_VERSION) ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= $phpVersionOk ? 'text-bg-success' : 'text-bg-danger' ?>">
                                            <?= $phpVersionOk ? 'Sesuai' : 'Tidak Sesuai' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Ekstensi PDO &amp; pdo_mysql</td>
                                    <td class="fw-semibold"><?= $pdoDriverOk ? 'Aktif' : 'Tidak Aktif' ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= $pdoDriverOk ? 'text-bg-success' : 'text-bg-danger' ?>">
                                            <?= $pdoDriverOk ? 'Aktif' : 'Gagal' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status Koneksi PDO</td>
                                    <td class="fw-semibold"><?= $dbConnected ? 'Terhubung' : 'Gagal' ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= $dbConnected ? 'text-bg-success' : 'text-bg-danger' ?>">
                                            <?= $dbConnected ? 'Berhasil' : 'Error' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Nama Database</td>
                                    <td class="fw-semibold"><?= e($dbInfo['nama_db']) ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= ($dbConnected && $dbInfo['nama_db'] === DB_NAME) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= ($dbConnected && $dbInfo['nama_db'] === DB_NAME) ? 'Valid' : '-' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Versi Server Database</td>
                                    <td class="fw-semibold"><?= e($dbInfo['versi']) ?></td>
                                    <td class="text-end"><span class="badge text-bg-light border">Info</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Zona Waktu PHP / DB</td>
                                    <td class="fw-semibold"><?= e(date_default_timezone_get()) ?> / <?= e($dbInfo['zona_waktu']) ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= ($dbConnected && $dbInfo['zona_waktu'] === APP_TIMEZONE_OFFSET) ? 'text-bg-success' : 'text-bg-warning' ?>">
                                            <?= ($dbConnected && $dbInfo['zona_waktu'] === APP_TIMEZONE_OFFSET) ? 'Sinkron' : 'Cek' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Uji Transaksi Database (PDO)</td>
                                    <td class="fw-semibold"><?= $transaksiOk ? 'Commit &amp; Prepared Statement OK' : 'Belum Teruji' ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= $transaksiOk ? 'text-bg-success' : 'text-bg-danger' ?>">
                                            <?= $transaksiOk ? 'Berhasil' : 'Gagal' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Folder Upload Kontestan</td>
                                    <td class="fw-semibold"><code>uploads/kontestan</code></td>
                                    <td class="text-end">
                                        <span class="badge <?= $uploadDirWritable ? 'text-bg-success' : 'text-bg-danger' ?>">
                                            <?= $uploadDirWritable ? 'Writable' : 'Tidak Writable' ?>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tabel, Unique & Foreign Keys -->
            <div class="col-lg-6">
                <div class="ks-card p-4 h-100">
                    <h2 class="h6 fw-bold text-uppercase mb-3" style="color: var(--ks-secondary);">
                        <i class="bi bi-diagram-3 me-2"></i>Tabel, Unique Constraint &amp; Relasi FK
                    </h2>

                    <h3 class="fs-6 fw-semibold mb-2">Tabel Utama (InnoDB &amp; utf8mb4)</h3>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="table-light">
                                    <th>Tabel</th>
                                    <th>Engine</th>
                                    <th>Collation</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tabelWajib as $tbl): ?>
                                    <?php $st = $tablesStatus[$tbl] ?? ['ada' => false, 'engine' => '-', 'collation' => '-', 'ok' => false]; ?>
                                    <tr>
                                        <td class="fw-semibold"><code><?= e($tbl) ?></code></td>
                                        <td><?= e($st['engine']) ?></td>
                                        <td><small><?= e($st['collation']) ?></small></td>
                                        <td class="text-end">
                                            <span class="badge <?= $st['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                                                <?= $st['ok'] ? 'Valid' : 'Tidak Lengkap' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="fs-6 fw-semibold mb-2">Unique Constraints</h3>
                    <ul class="list-group list-group-flush mb-3 small">
                        <?php foreach ($uniqueWajib as $label => $meta): ?>
                            <?php $ok = $uniqueStatus[$label] ?? false; ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><code><?= e($label) ?></code></span>
                                <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= $ok ? 'Aktif' : 'Belum Ada' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h3 class="fs-6 fw-semibold mb-2">Foreign Keys Tabel Voting</h3>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($fkWajib as $fkName => $meta): ?>
                            <?php $fkRow = $fkStatus[$fkName] ?? ['ok' => false, 'detail' => 'voting.' . $meta['kolom'] . ' -> ' . $meta['ref']]; ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><code><?= e($fkRow['detail']) ?></code></span>
                                <span class="badge <?= $fkRow['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= $fkRow['ok'] ? 'Terhubung' : 'Gagal' ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="ks-card p-3 mt-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
            <div class="small text-muted">
                <i class="bi bi-shield-lock me-1 text-success"></i>
                Helper tersedia: <code>ResponseHelper</code>, <code>AuthHelper</code>, <code>ValidationHelper</code> &bull; Waktu server: <strong><?= e(formatTanggalIndonesia(date('Y-m-d H:i:s'), true)) ?></strong>
            </div>
            <div class="small text-muted">
                Mode: <code><?= e(APP_ENV) ?></code>
            </div>
        </div>
    </main>
</body>
</html>

