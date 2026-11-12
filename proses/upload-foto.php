<?php
declare(strict_types=1);

/**
 * Modul Upload & Pengelolaan Foto Kontestan yang Aman
 * Validasi ukuran (maks 2 MB), ekstensi, MIME type (finfo), dan getimagesize().
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

if (!function_exists('prosesUploadFotoKontestan')) {
    /**
     * Memvalidasi dan menyimpan foto kontestan ke folder uploads/kontestan/.
     *
     * @return array{success: bool, filename: ?string, error: ?string}
     */
    function prosesUploadFotoKontestan(array $file, bool $required = true): array
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                return [
                    'success'  => false,
                    'filename' => null,
                    'error'    => 'Foto kontestan wajib diunggah.',
                ];
            }

            return [
                'success'  => true,
                'filename' => null,
                'error'    => null,
            ];
        }

        if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Ukuran file foto melebihi batas maksimal 2 MB.',
            ];
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Terjadi kesalahan saat mengunggah foto. Silakan coba lagi.',
            ];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $originalName = str_replace("\0", '', (string) ($file['name'] ?? ''));
        $reportedSize = (int) ($file['size'] ?? 0);

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'File unggahan tidak valid.',
            ];
        }

        // Tolak nama file dengan pola ekstensi skrip/eksekusi ganda (misal: shell.php.jpg)
        if (preg_match('/\.(php[0-9]?|phtml|phar|cgi|pl|py|jsp|asp|aspx|exe|sh|bat|cmd|dll|com|msi|vbs|svg|htaccess)(\.|$)/i', $originalName) === 1) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Nama file mengandung ekstensi berbahaya yang tidak diperbolehkan.',
            ];
        }

        $actualSize = (int) (@filesize($tmpPath) ?: 0);
        $maxSize = defined('UPLOAD_MAX_SIZE') ? (int) UPLOAD_MAX_SIZE : (2 * 1024 * 1024);

        if ($reportedSize <= 0 || $actualSize <= 0 || $reportedSize > $maxSize || $actualSize > $maxSize) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Ukuran foto tidak valid atau melebihi batas maksimal 2 MB.',
            ];
        }

        // 1. Validasi ekstensi file asli
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExts = defined('ALLOWED_IMAGE_EXTENSIONS') ? ALLOWED_IMAGE_EXTENSIONS : ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Format foto harus berupa JPEG, PNG, atau WebP.',
            ];
        }

        // 2. Validasi MIME sebenarnya menggunakan finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($tmpPath);
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($mimeToExt[$detectedMime])) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Tipe file tidak valid. Hanya gambar JPEG, PNG, atau WebP yang diperbolehkan.',
            ];
        }

        // Pastikan ekstensi dan MIME selaras (jpg/jpeg cocok dengan image/jpeg)
        $canonicalExt = $mimeToExt[$detectedMime];
        $extValidForMime = ($canonicalExt === 'jpg' && in_array($ext, ['jpg', 'jpeg'], true)) || ($ext === $canonicalExt);
        if (!$extValidForMime) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Ekstensi file tidak sesuai dengan isi gambar sebenarnya.',
            ];
        }

        // 3. Verifikasi file benar-benar gambar valid dan tipe gambarnya cocok
        $imageInfo = @getimagesize($tmpPath);
        $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
        if (defined('IMAGETYPE_WEBP')) {
            $allowedImageTypes[] = IMAGETYPE_WEBP;
        }
        $infoMime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
        $infoType = is_array($imageInfo) ? (int) ($imageInfo[2] ?? 0) : 0;

        if (
            !is_array($imageInfo)
            || empty($imageInfo[0])
            || empty($imageInfo[1])
            || $infoMime !== $detectedMime
            || !in_array($infoType, $allowedImageTypes, true)
        ) {
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'File gambar rusak atau bukan gambar JPEG/PNG/WebP yang valid.',
            ];
        }

        // 4. Pastikan direktori tujuan tersedia
        $targetDir = defined('UPLOAD_KONTESTAN_DIR')
            ? UPLOAD_KONTESTAN_DIR
            : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kontestan');

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            error_log('[UPLOAD_DIR_ERROR] Gagal membuat direktori upload kontestan.');
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Direktori penyimpanan foto tidak tersedia.',
            ];
        }

        // 5. Buat nama file acak yang unik
        $newFilename = 'kontestan_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $canonicalExt;
        $destPath = $targetDir . DIRECTORY_SEPARATOR . $newFilename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            error_log('[UPLOAD_MOVE_ERROR] Gagal memindahkan file upload ke ' . $destPath);
            return [
                'success'  => false,
                'filename' => null,
                'error'    => 'Gagal menyimpan file foto ke server.',
            ];
        }

        // 6. Optimasi ukuran dimensi & kompresi gambar otomatis jika ekstensi GD aktif (skala maks 1000x1250 rasio 4:5)
        optimasiFotoKontestanIfSupported($destPath, $detectedMime);

        return [
            'success'  => true,
            'filename' => $newFilename,
            'error'    => null,
        ];
    }
}

if (!function_exists('optimasiFotoKontestanIfSupported')) {
    /**
     * Mengoptimalkan dimensi maksimal dan kompresi foto kontestan tanpa merusak transparansi.
     * Menjaga foto asli jika ekstensi GD tidak tersedia atau file sudah lebih kecil/optimal.
     */
    function optimasiFotoKontestanIfSupported(string $filePath, string $mimeType, int $maxWidth = 1000, int $maxHeight = 1250): void
    {
        if (!extension_loaded('gd') || !is_file($filePath)) {
            return;
        }

        $info = @getimagesize($filePath);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return;
        }

        $origWidth = (int) $info[0];
        $origHeight = (int) $info[1];

        // Jangan re-encode ikon sangat kecil (<= 16px) agar struktur uji minimal tetap utuh
        if ($origWidth <= 16 && $origHeight <= 16) {
            return;
        }

        $needsResize = ($origWidth > $maxWidth || $origHeight > $maxHeight);
        $origSize = (int) (@filesize($filePath) ?: 0);

        $srcImg = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($filePath) : false,
            'image/png'  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($filePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
            default      => false,
        };

        if ($srcImg === false) {
            return;
        }

        $targetWidth = $origWidth;
        $targetHeight = $origHeight;
        if ($needsResize) {
            $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
            $targetWidth = max(1, (int) round($origWidth * $ratio));
            $targetHeight = max(1, (int) round($origHeight * $ratio));
        }

        $dstImg = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($dstImg === false) {
            imagedestroy($srcImg);
            return;
        }

        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
            imagefill($dstImg, 0, 0, $transparent);
        }

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);
        imagedestroy($srcImg);

        $tmpOpt = $filePath . '.opt.tmp';
        $saved = match ($mimeType) {
            'image/jpeg' => function_exists('imagejpeg') && @imagejpeg($dstImg, $tmpOpt, 84),
            'image/png'  => function_exists('imagepng') && @imagepng($dstImg, $tmpOpt, 6),
            'image/webp' => function_exists('imagewebp') && @imagewebp($dstImg, $tmpOpt, 84),
            default      => false,
        };
        imagedestroy($dstImg);

        if ($saved && is_file($tmpOpt)) {
            $newSize = (int) (@filesize($tmpOpt) ?: 0);
            if ($newSize > 0 && ($needsResize || $origSize === 0 || $newSize < $origSize)) {
                @rename($tmpOpt, $filePath);
                return;
            }
        }

        if (is_file($tmpOpt)) {
            @unlink($tmpOpt);
        }
    }
}

if (!function_exists('hapusFotoKontestan')) {
    /**
     * Menghapus file foto kontestan secara aman dari direktori uploads/kontestan/.
     */
    function hapusFotoKontestan(?string $filename): void
    {
        $cleanName = basename(trim((string) ($filename ?? '')));
        if ($cleanName === '') {
            return;
        }

        $targetDir = defined('UPLOAD_KONTESTAN_DIR')
            ? UPLOAD_KONTESTAN_DIR
            : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kontestan');

        $realBaseDir = realpath($targetDir);
        $realFile = realpath($targetDir . DIRECTORY_SEPARATOR . $cleanName);

        if (
            $realBaseDir !== false
            && $realFile !== false
            && str_starts_with($realFile, $realBaseDir . DIRECTORY_SEPARATOR)
            && is_file($realFile)
        ) {
            @unlink($realFile);
        }
    }
}

