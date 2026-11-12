<?php
declare(strict_types=1);

/**
 * Helper Validasi Input Server-Side
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

if (!function_exists('sanitizeText')) {
    /**
     * Membersihkan spasi berlebih dan null-byte dari input teks.
     * Escaping HTML dilakukan saat output melalui helper e().
     */
    function sanitizeText(mixed $value): string
    {
        $text = str_replace("\0", '', (string) ($value ?? ''));
        return trim($text);
    }
}

if (!function_exists('isRequired')) {
    function isRequired(mixed $value): bool
    {
        return sanitizeText($value) !== '';
    }
}

if (!function_exists('isValidEmail')) {
    function isValidEmail(?string $email): bool
    {
        $cleaned = sanitizeText($email);
        if ($cleaned === '' || mb_strlen($cleaned, 'UTF-8') > 100) {
            return false;
        }

        return filter_var($cleaned, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('normalizePhone')) {
    function normalizePhone(?string $phone): string
    {
        $cleaned = preg_replace('/[\s\-\(\)]+/', '', sanitizeText($phone));
        return (string) $cleaned;
    }
}

if (!function_exists('isValidPhone')) {
    /**
     * Memvalidasi format nomor HP Indonesia (minimal 9, maksimal 15 digit, opsional awalan +).
     */
    function isValidPhone(?string $phone): bool
    {
        $normalized = normalizePhone($phone);
        return preg_match('/^(\+62|62|0)[0-9]{8,13}$/', $normalized) === 1;
    }
}

if (!function_exists('minLength')) {
    function minLength(?string $value, int $min): bool
    {
        return mb_strlen(sanitizeText($value), 'UTF-8') >= $min;
    }
}

if (!function_exists('maxLength')) {
    function maxLength(?string $value, int $max): bool
    {
        return mb_strlen(sanitizeText($value), 'UTF-8') <= $max;
    }
}

if (!function_exists('isInEnum')) {
    function isInEnum(?string $value, array $allowed): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array($value, $allowed, true);
    }
}

if (!function_exists('isPositiveInt')) {
    function isPositiveInt(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value > 0;
        }

        return false;
    }
}

if (!function_exists('isValidDate')) {
    function isValidDate(?string $date, string $format = 'Y-m-d'): bool
    {
        $cleaned = sanitizeText($date);
        if ($cleaned === '') {
            return false;
        }

        $dt = DateTimeImmutable::createFromFormat($format, $cleaned);
        return $dt !== false && $dt->format($format) === $cleaned;
    }
}

if (!function_exists('isValidDateTime')) {
    function isValidDateTime(?string $dateTime, string $format = 'Y-m-d H:i:s'): bool
    {
        $cleaned = sanitizeText($dateTime);
        if ($cleaned === '') {
            return false;
        }

        $dt = DateTimeImmutable::createFromFormat($format, $cleaned);
        return $dt !== false && $dt->format($format) === $cleaned;
    }
}

if (!function_exists('validatePasswordInput')) {
    /**
     * Memvalidasi password dan konfirmasi password.
     *
     * @return string[] Daftar pesan error dalam Bahasa Indonesia (kosong jika valid).
     */
    function validatePasswordInput(?string $password, ?string $konfirmasi = null, int $minLength = 8): array
    {
        $errors = [];
        $pwd = (string) ($password ?? '');

        if ($pwd === '') {
            $errors[] = 'Password wajib diisi.';
            return $errors;
        }

        if (mb_strlen($pwd, 'UTF-8') < $minLength) {
            $errors[] = "Password minimal harus {$minLength} karakter.";
        }

        if ($konfirmasi !== null && !hash_equals($pwd, $konfirmasi)) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }

        return $errors;
    }
}

