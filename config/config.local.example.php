<?php
declare(strict_types=1);

/**
 * Template Konfigurasi Lokal / Hosting (Alternatif selain .env)
 * Salin file ini menjadi "config/config.local.php" pada server tujuan dan isi kredensial Anda.
 * PERINGATAN: File "config/config.local.php" diabaikan oleh Git (.gitignore) dan tidak boleh di-commit.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

return [
    // Gunakan 'production' saat live, 'development' hanya saat instalasi/debugging lokal
    'APP_ENV'      => 'production',

    // Kosongkan '' untuk deteksi otomatis, isi '/' untuk memaksa root domain,
    // atau isi '/voting-kakang-senduk' untuk subfolder eksplisit
    'APP_BASE_URL' => '',

    // Aktifkan redirect wajib HTTPS dan cookie Secure saat produksi
    'FORCE_HTTPS'  => true,

    // Kredensial Database Produksi / Shared Hosting
    'DB_HOST'      => '127.0.0.1',
    'DB_PORT'      => '3306',
    'DB_NAME'      => 'db_voting_kakang_senduk',
    'DB_USER'      => 'isi_user_database_produksi',
    'DB_PASS'      => 'isi_password_database_yang_kuat',
    'DB_CHARSET'   => 'utf8mb4',
];

