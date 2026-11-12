<?php
declare(strict_types=1);

/**
 * Utilitas Backup Database Aman (Khusus CLI, Hemat Memori / Streaming)
 * Voting Pemilihan Kakang Senduk Ponorogo
 *
 * Cara menjalankan dari terminal:
 *   php database/backup.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip backup hanya dapat dijalankan melalui CLI server.');
}

require_once dirname(__DIR__) . '/config/koneksi.php';

$backupDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Gagal membuat direktori backup: {$backupDir}\n");
    exit(1);
}

$filename = 'backup_' . DB_NAME . '_' . date('Ymd_His') . '.sql';
$outputPath = $backupDir . DIRECTORY_SEPARATOR . $filename;

$handle = @fopen($outputPath, 'wb');
if ($handle === false) {
    fwrite(STDERR, "Gagal membuka file output: {$outputPath}\n");
    exit(1);
}

$tables = ['users', 'kontestan', 'periode_voting', 'voting', 'remember_tokens'];

try {
    fwrite($handle, "-- ============================================================================\n");
    fwrite($handle, "-- Backup Database: " . DB_NAME . "\n");
    fwrite($handle, "-- Aplikasi       : " . APP_NAME . "\n");
    fwrite($handle, "-- Waktu Backup   : " . date('Y-m-d H:i:s T') . "\n");
    fwrite($handle, "-- ============================================================================\n\n");
    fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
    fwrite($handle, "START TRANSACTION;\n");
    fwrite($handle, "SET time_zone = \"" . APP_TIMEZONE_OFFSET . "\";\n\n");

    $stmtExists = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl"
    );

    foreach ($tables as $table) {
        $stmtExists->execute([':db' => DB_NAME, ':tbl' => $table]);
        if ((int) $stmtExists->fetchColumn() === 0) {
            continue;
        }

        $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $createSql = is_array($createRow) ? (string) ($createRow[1] ?? '') : '';
        if ($createSql !== '') {
            fwrite($handle, "-- ----------------------------------------------------------------------------\n");
            fwrite($handle, "-- Struktur & Data Tabel `{$table}`\n");
            fwrite($handle, "-- ----------------------------------------------------------------------------\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createSql . ";\n\n");
        }

        $stmtData = $pdo->query("SELECT * FROM `{$table}`");
        $colList = null;
        $batch = [];

        $flushBatch = static function () use ($handle, $table, &$colList, &$batch): void {
            if ($colList === null || empty($batch)) {
                return;
            }
            fwrite($handle, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n\n");
            $batch = [];
        };

        while ($row = $stmtData->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_keys($row);
            if ($colList === null) {
                $colList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
            }

            $vals = [];
            foreach ($columns as $col) {
                $val = $row[$col] ?? null;
                if ($val === null) {
                    $vals[] = 'NULL';
                } elseif (is_int($val) || is_float($val)) {
                    $vals[] = (string) $val;
                } else {
                    $vals[] = $pdo->quote((string) $val);
                }
            }
            $batch[] = '(' . implode(', ', $vals) . ')';

            if (count($batch) >= 100) {
                $flushBatch();
            }
        }
        $flushBatch();
    }

    fwrite($handle, "COMMIT;\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($handle);

    echo "Backup berhasil disimpan ke: {$outputPath} (" . filesize($outputPath) . " bytes)\n";
    exit(0);
} catch (Throwable $e) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    @unlink($outputPath);
    error_log('[DB_BACKUP_ERROR] ' . $e->getMessage());
    fwrite(STDERR, "Backup gagal: " . $e->getMessage() . "\n");
    exit(1);
}
