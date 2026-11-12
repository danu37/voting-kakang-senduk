<?php
declare(strict_types=1);

/**
 * Helper Respons, Escaping Output HTML, URL, Flash Message, dan Format Tanggal
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

if (!function_exists('e')) {
    /**
     * Melakukan escaping output HTML untuk mencegah serangan XSS.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('eMultiline')) {
    /**
     * Escaping HTML dengan mempertahankan baris baru (<br>).
     */
    function eMultiline(mixed $value): string
    {
        return nl2br(e($value));
    }
}

if (!function_exists('baseUrl')) {
    /**
     * Menghasilkan URL lengkap relatif terhadap BASE_URL aplikasi.
     */
    function baseUrl(string $path = ''): string
    {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $cleanPath = ltrim($path, '/');

        if ($cleanPath === '') {
            return $base !== '' ? $base . '/' : '/';
        }

        return ($base !== '' ? $base : '') . '/' . $cleanPath;
    }
}

if (!function_exists('assetUrl')) {
    /**
     * Menghasilkan URL ke folder assets/ disertai cache-busting filemtime otomatis untuk produksi.
     */
    function assetUrl(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $url = baseUrl('assets/' . $cleanPath);

        if (defined('BASE_PATH') && !str_contains($cleanPath, '?')) {
            $diskPath = BASE_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);
            if (is_file($diskPath)) {
                $mtime = @filemtime($diskPath);
                if (is_int($mtime) && $mtime > 0) {
                    $url .= '?v=' . $mtime;
                }
            }
        }

        return $url;
    }
}

if (!function_exists('uploadUrl')) {
    /**
     * Menghasilkan URL file upload kontestan yang aman.
     */
    function uploadUrl(?string $filename, string $subfolder = 'kontestan'): string
    {
        $cleanName = basename((string) ($filename ?? ''));
        if ($cleanName === '') {
            return '';
        }

        return baseUrl('uploads/' . trim($subfolder, '/') . '/' . rawurlencode($cleanName));
    }
}

if (!function_exists('kontestanFotoUrl')) {
    /**
     * Menghasilkan URL foto kontestan dengan fallback otomatis ke placeholder bila file tidak ada.
     */
    function kontestanFotoUrl(?string $filename): string
    {
        $cleanName = basename(trim((string) ($filename ?? '')));
        if ($cleanName !== '' && defined('UPLOAD_KONTESTAN_DIR')) {
            $diskPath = UPLOAD_KONTESTAN_DIR . DIRECTORY_SEPARATOR . $cleanName;
            if (is_file($diskPath)) {
                return uploadUrl($cleanName, 'kontestan');
            }
        }

        return assetUrl('img/kontestan-placeholder.svg');
    }
}

if (!function_exists('redirect')) {
    /**
     * Mengalihkan pengguna ke path internal aplikasi secara aman.
     */
    function redirect(string $path): never
    {
        $target = baseUrl($path);
        if (str_starts_with($path, '/') && defined('BASE_URL') && BASE_URL !== '' && str_starts_with($path, BASE_URL . '/')) {
            $target = $path;
        }

        header('Location: ' . $target);
        exit;
    }
}

if (!function_exists('jsonResponse')) {
    /**
     * Mengirimkan respons JSON terstruktur beserta status HTTP.
     */
    function jsonResponse(array $payload, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('setFlash')) {
    /**
     * Menyimpan pesan notifikasi sementara (flash message) di session.
     */
    function setFlash(string $type, string $title, string $message): void
    {
        if (function_exists('startSecureSession')) {
            startSecureSession();
        } elseif (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $_SESSION['_flash'] = [
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
        ];
    }
}

if (!function_exists('getFlash')) {
    /**
     * Mengambil dan menghapus flash message dari session.
     */
    function getFlash(): ?array
    {
        if (function_exists('startSecureSession')) {
            startSecureSession();
        } elseif (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            return null;
        }

        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);

        return $flash;
    }
}

if (!function_exists('formatTanggalIndonesia')) {
    /**
     * Memformat tanggal/waktu ke format Bahasa Indonesia (contoh: 12 November 2026).
     */
    function formatTanggalIndonesia(?string $tanggal, bool $denganWaktu = false): string
    {
        if ($tanggal === null || trim($tanggal) === '') {
            return '-';
        }

        try {
            $dt = new DateTimeImmutable($tanggal, new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta'));
        } catch (Exception $e) {
            return '-';
        }

        $namaBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $hari = $dt->format('j');
        $bulan = $namaBulan[(int) $dt->format('n')] ?? $dt->format('m');
        $tahun = $dt->format('Y');

        $hasil = "{$hari} {$bulan} {$tahun}";
        if ($denganWaktu) {
            $hasil .= ', ' . $dt->format('H:i') . ' WIB';
        }

        return $hasil;
    }
}

