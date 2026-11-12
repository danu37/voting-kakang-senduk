<?php
declare(strict_types=1);

/**
 * Utilitas Pembuatan Akun Administrator Secara Aman (Khusus CLI)
 * Voting Pemilihan Kakang Senduk Ponorogo
 *
 * Contoh penggunaan:
 *   php database/create-admin.php "Nama Admin" "admin@domain.go.id" "081234567890" "PasswordKuatMinimal12!"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Pembuatan akun administrator hanya diperbolehkan melalui CLI server.');
}

if ($argc < 5) {
    echo "Penggunaan:\n";
    echo "  php database/create-admin.php \"<nama>\" \"<email>\" \"<no_hp>\" \"<password>\"\n\n";
    echo "Catatan:\n";
    echo "  - Password minimal 8 karakter (disarankan >= 12 karakter kombinasi huruf besar, kecil, angka, simbol).\n";
    echo "  - Email harus unik dan valid.\n";
    exit($argc === 1 ? 0 : 1);
}

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';

$nama = sanitizeText((string) $argv[1]);
$email = mb_strtolower(trim((string) $argv[2]), 'UTF-8');
$noHp = normalizePhoneNumber((string) $argv[3]);
$password = (string) $argv[4];

if ($nama === '' || mb_strlen($nama, 'UTF-8') < 3) {
    fwrite(STDERR, "Gagal: Nama administrator minimal 3 karakter.\n");
    exit(1);
}

if (!isValidEmailFormat($email)) {
    fwrite(STDERR, "Gagal: Format email tidak valid.\n");
    exit(1);
}

if (!isValidPhoneNumber($noHp)) {
    fwrite(STDERR, "Gagal: Nomor HP tidak valid (8-15 digit angka).\n");
    exit(1);
}

if (mb_strlen($password, 'UTF-8') < 8) {
    fwrite(STDERR, "Gagal: Password minimal 8 karakter.\n");
    exit(1);
}

try {
    $stmtCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmtCheck->execute([':email' => $email]);
    if ($stmtCheck->fetchColumn() !== false) {
        fwrite(STDERR, "Gagal: Email {$email} sudah terdaftar di database.\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmtInsert = $pdo->prepare("
        INSERT INTO users (nama, email, no_hp, password, role)
        VALUES (:nama, :email, :no_hp, :password, 'admin')
    ");
    $stmtInsert->execute([
        ':nama'     => $nama,
        ':email'    => $email,
        ':no_hp'    => $noHp,
        ':password' => $hash,
    ]);

    echo "Berhasil: Akun administrator {$email} dibuat (ID: " . $pdo->lastInsertId() . ").\n";
    exit(0);
} catch (Throwable $e) {
    error_log('[CLI_CREATE_ADMIN_ERROR] ' . $e->getMessage());
    fwrite(STDERR, "Terjadi gangguan saat menyimpan akun administrator: " . $e->getMessage() . "\n");
    exit(1);
}

