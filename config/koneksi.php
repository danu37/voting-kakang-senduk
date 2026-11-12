<?php
declare(strict_types=1);

/**
 * Koneksi Database PDO & Pembantu Transaksi
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ke file koneksi ditolak.');
}

require_once __DIR__ . '/app.php';

if (!function_exists('getKoneksi')) {
    /**
     * Mengembalikan instance singleton koneksi PDO.
     *
     * @throws RuntimeException Jika koneksi database gagal dibuat.
     */
    function getKoneksi(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $stmtTz = $pdo->prepare("SET time_zone = :tz");
            $stmtTz->execute([':tz' => APP_TIMEZONE_OFFSET]);

            return $pdo;
        } catch (PDOException $e) {
            // Catat detail teknis hanya ke log server, jangan tampilkan ke pengguna
            error_log('[DB_CONNECTION_ERROR] ' . $e->getMessage());
            throw new RuntimeException('Koneksi ke basis data gagal. Silakan hubungi administrator.');
        }
    }
}

if (!function_exists('jalankanTransaksi')) {
    /**
     * Menjalankan operasi penting di dalam transaksi database PDO.
     * Otomatis melakukan commit jika berhasil, dan rollBack jika terjadi error.
     *
     * @param PDO      $pdo
     * @param callable $callback Menerima parameter (PDO $pdo)
     * @return mixed Nilai kembalian dari callback
     * @throws Throwable
     */
    function jalankanTransaksi(PDO $pdo, callable $callback): mixed
    {
        $mulaiTransaksiBaru = !$pdo->inTransaction();

        if ($mulaiTransaksiBaru) {
            $pdo->beginTransaction();
        }

        try {
            $hasil = $callback($pdo);

            if ($mulaiTransaksiBaru && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $hasil;
        } catch (Throwable $e) {
            if ($mulaiTransaksiBaru && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

// Inisialisasi koneksi otomatis kecuali dinonaktifkan oleh skrip diagnostik
if (!defined('SKIP_AUTO_DB_CONNECT')) {
    try {
        $pdo = getKoneksi();
    } catch (RuntimeException $e) {
        http_response_code(500);
        exit('Terjadi gangguan sistem. Layanan basis data sedang tidak tersedia.');
    }
}
