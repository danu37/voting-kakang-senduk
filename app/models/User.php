<?php
declare(strict_types=1);

/**
 * Model User
 * Mengelola operasi database untuk tabel users menggunakan PDO prepared statements.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mencari pengguna berdasarkan ID tanpa menyertakan kolom password.
     */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nama, email, no_hp, role, created_at, updated_at
                FROM users
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch();

            return is_array($user) ? $user : null;
        } catch (PDOException $e) {
            error_log('[USER_FIND_BY_ID_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mencari pengguna berdasarkan email beserta password hash untuk proses autentikasi.
     */
    public function findByEmailForAuth(string $email): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nama, email, no_hp, password, role
                FROM users
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => mb_strtolower(trim($email), 'UTF-8')]);
            $user = $stmt->fetch();

            return is_array($user) ? $user : null;
        } catch (PDOException $e) {
            error_log('[USER_FIND_BY_EMAIL_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Memeriksa apakah email sudah terdaftar di database.
     */
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        try {
            if ($excludeUserId !== null) {
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM users WHERE email = :email AND id <> :exclude_id LIMIT 1
                ");
                $stmt->execute([
                    ':email'      => mb_strtolower(trim($email), 'UTF-8'),
                    ':exclude_id' => $excludeUserId,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM users WHERE email = :email LIMIT 1
                ");
                $stmt->execute([':email' => mb_strtolower(trim($email), 'UTF-8')]);
            }

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log('[USER_EMAIL_EXISTS_ERROR] ' . $e->getMessage());
            throw new RuntimeException('Terjadi gangguan saat memeriksa ketersediaan email.');
        }
    }

    /**
     * Mendaftarkan akun baru dengan role yang selalu dikunci sebagai 'pemilih'.
     *
     * @throws PDOException Jika terjadi duplicate email pada level database.
     */
    public function createPemilih(array $data): int
    {
        return (int) jalankanTransaksi($this->pdo, static function (PDO $conn) use ($data): int {
            $passwordHash = password_hash((string) $data['password'], PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (nama, email, no_hp, password, role)
                VALUES (:nama, :email, :no_hp, :password, 'pemilih')
            ");

            $stmt->execute([
                ':nama'     => (string) $data['nama'],
                ':email'    => mb_strtolower(trim((string) $data['email']), 'UTF-8'),
                ':no_hp'    => (string) $data['no_hp'],
                ':password' => $passwordHash,
            ]);

            return (int) $conn->lastInsertId();
        });
    }

    /**
     * Memverifikasi password mentah terhadap hash tersimpan.
     */
    public function verifyPassword(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }

    /**
     * Menyimpan token Remember Me baru (selector + SHA-256 hash validator).
     */
    public function createRememberToken(int $userId, string $selector, string $validator, int $expiresTimestamp): void
    {
        jalankanTransaksi($this->pdo, static function (PDO $conn) use ($userId, $selector, $validator, $expiresTimestamp): void {
            // Bersihkan token kadaluarsa milik pengguna ini
            $stmtClean = $conn->prepare("
                DELETE FROM remember_tokens WHERE user_id = :user_id AND expires_at <= NOW()
            ");
            $stmtClean->execute([':user_id' => $userId]);

            $stmt = $conn->prepare("
                INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
                VALUES (:user_id, :selector, :token_hash, :expires_at)
            ");
            $stmt->execute([
                ':user_id'    => $userId,
                ':selector'   => $selector,
                ':token_hash' => hash('sha256', $validator),
                ':expires_at' => date('Y-m-d H:i:s', $expiresTimestamp),
            ]);
        });
    }

    /**
     * Mengambil data token Remember Me aktif beserta profil user terkait.
     */
    public function findActiveRememberToken(string $selector): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT rt.id AS token_id, rt.user_id, rt.selector, rt.token_hash, rt.expires_at,
                       u.nama, u.email, u.role
                FROM remember_tokens rt
                INNER JOIN users u ON u.id = rt.user_id
                WHERE rt.selector = :selector
                  AND rt.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([':selector' => $selector]);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (PDOException $e) {
            error_log('[USER_FIND_REMEMBER_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Merotasi token Remember Me setelah auto-login berhasil.
     */
    public function rotateRememberToken(int $oldTokenId, int $userId, string $newSelector, string $newValidator, int $expiresTimestamp): void
    {
        jalankanTransaksi($this->pdo, static function (PDO $conn) use ($oldTokenId, $userId, $newSelector, $newValidator, $expiresTimestamp): void {
            $stmtDel = $conn->prepare("DELETE FROM remember_tokens WHERE id = :id");
            $stmtDel->execute([':id' => $oldTokenId]);

            $stmtIns = $conn->prepare("
                INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
                VALUES (:user_id, :selector, :token_hash, :expires_at)
            ");
            $stmtIns->execute([
                ':user_id'    => $userId,
                ':selector'   => $newSelector,
                ':token_hash' => hash('sha256', $newValidator),
                ':expires_at' => date('Y-m-d H:i:s', $expiresTimestamp),
            ]);
        });
    }

    /**
     * Menghapus token Remember Me berdasarkan selector dan/atau user ID saat logout atau indikasi penyalahgunaan.
     */
    public function deleteRememberTokens(?int $userId = null, ?string $selector = null): void
    {
        try {
            if ($userId !== null && $selector !== null) {
                $stmt = $this->pdo->prepare("
                    DELETE FROM remember_tokens WHERE user_id = :user_id OR selector = :selector
                ");
                $stmt->execute([':user_id' => $userId, ':selector' => $selector]);
            } elseif ($userId !== null) {
                $stmt = $this->pdo->prepare("DELETE FROM remember_tokens WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
            } elseif ($selector !== null) {
                $stmt = $this->pdo->prepare("DELETE FROM remember_tokens WHERE selector = :selector");
                $stmt->execute([':selector' => $selector]);
            }
        } catch (PDOException $e) {
            error_log('[USER_DELETE_REMEMBER_ERROR] ' . $e->getMessage());
        }
    }

    /**
     * Menghitung total akun pemilih terdaftar.
     */
    public function countPemilih(): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'pemilih'");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[USER_COUNT_PEMILIH_ERROR] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mengambil daftar pemilih beserta status voting Kakang & Senduk pada periode terpilih,
     * mendukung pencarian (nama/email/no_hp), filter status voting, dan pagination.
     * Kolom password TIDAK pernah diambil atau dikirim.
     */
    public function getPemilihWithVotingStatus(
        ?int $periodeId,
        string $cari = '',
        int $page = 1,
        int $perPage = 12,
        string $statusVoting = ''
    ): array {
        $safePage = max(1, $page);
        $safePerPage = max(1, min(50, $perPage));
        $hasPeriode = ($periodeId !== null && $periodeId > 0);

        $whereParts = ["u.role = 'pemilih'"];
        $strParams = [];
        $intParams = [];

        $cleanSearch = trim($cari);
        if ($cleanSearch !== '') {
            $whereParts[] = '(u.nama LIKE :cari_nama OR u.email LIKE :cari_email OR u.no_hp LIKE :cari_hp)';
            $likeTerm = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cleanSearch) . '%';
            $strParams[':cari_nama'] = $likeTerm;
            $strParams[':cari_email'] = $likeTerm;
            $strParams[':cari_hp'] = $likeTerm;
        }

        if ($hasPeriode) {
            if ($statusVoting === 'sudah_kakang') {
                $whereParts[] = "EXISTS (SELECT 1 FROM voting vf1 WHERE vf1.user_id = u.id AND vf1.periode_id = :pid_f1 AND vf1.kategori = 'Kakang')";
                $intParams[':pid_f1'] = $periodeId;
            } elseif ($statusVoting === 'sudah_senduk') {
                $whereParts[] = "EXISTS (SELECT 1 FROM voting vf1 WHERE vf1.user_id = u.id AND vf1.periode_id = :pid_f1 AND vf1.kategori = 'Senduk')";
                $intParams[':pid_f1'] = $periodeId;
            } elseif ($statusVoting === 'sudah_lengkap') {
                $whereParts[] = "EXISTS (SELECT 1 FROM voting vf1 WHERE vf1.user_id = u.id AND vf1.periode_id = :pid_f1 AND vf1.kategori = 'Kakang') AND EXISTS (SELECT 1 FROM voting vf2 WHERE vf2.user_id = u.id AND vf2.periode_id = :pid_f2 AND vf2.kategori = 'Senduk')";
                $intParams[':pid_f1'] = $periodeId;
                $intParams[':pid_f2'] = $periodeId;
            } elseif ($statusVoting === 'belum_memilih') {
                $whereParts[] = "NOT EXISTS (SELECT 1 FROM voting vf1 WHERE vf1.user_id = u.id AND vf1.periode_id = :pid_f1)";
                $intParams[':pid_f1'] = $periodeId;
            } elseif ($statusVoting === 'belum_lengkap') {
                $whereParts[] = "(NOT EXISTS (SELECT 1 FROM voting vf1 WHERE vf1.user_id = u.id AND vf1.periode_id = :pid_f1 AND vf1.kategori = 'Kakang') OR NOT EXISTS (SELECT 1 FROM voting vf2 WHERE vf2.user_id = u.id AND vf2.periode_id = :pid_f2 AND vf2.kategori = 'Senduk'))";
                $intParams[':pid_f1'] = $periodeId;
                $intParams[':pid_f2'] = $periodeId;
            }
        } elseif (in_array($statusVoting, ['sudah_kakang', 'sudah_senduk', 'sudah_lengkap'], true)) {
            $whereParts[] = '1 = 0';
        }

        $whereSql = implode(' AND ', $whereParts);

        try {
            $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
            foreach ($strParams as $k => $v) {
                $stmtCount->bindValue($k, $v, PDO::PARAM_STR);
            }
            foreach ($intParams as $k => $v) {
                $stmtCount->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmtCount->execute();
            $total = (int) $stmtCount->fetchColumn();

            $totalPages = max(1, (int) ceil($total / $safePerPage));
            if ($safePage > $totalPages) {
                $safePage = $totalPages;
            }
            $offset = ($safePage - 1) * $safePerPage;

            if ($hasPeriode) {
                $sql = "
                    SELECT
                        u.id,
                        u.nama,
                        u.email,
                        u.no_hp,
                        u.created_at,
                        MAX(CASE WHEN v.kategori = 'Kakang' THEN 1 ELSE 0 END) AS status_kakang,
                        MAX(CASE WHEN v.kategori = 'Senduk' THEN 1 ELSE 0 END) AS status_senduk,
                        (SELECT COUNT(*) FROM voting va WHERE va.user_id = u.id) AS total_riwayat_voting
                    FROM users u
                    LEFT JOIN voting v
                        ON v.user_id = u.id
                       AND v.periode_id = :periode_join
                    WHERE {$whereSql}
                    GROUP BY u.id, u.nama, u.email, u.no_hp, u.created_at
                    ORDER BY u.created_at DESC, u.id DESC
                    LIMIT :limit OFFSET :offset
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':periode_join', $periodeId, PDO::PARAM_INT);
            } else {
                $sql = "
                    SELECT
                        u.id,
                        u.nama,
                        u.email,
                        u.no_hp,
                        u.created_at,
                        0 AS status_kakang,
                        0 AS status_senduk,
                        (SELECT COUNT(*) FROM voting va WHERE va.user_id = u.id) AS total_riwayat_voting
                    FROM users u
                    WHERE {$whereSql}
                    ORDER BY u.created_at DESC, u.id DESC
                    LIMIT :limit OFFSET :offset
                ";
                $stmt = $this->pdo->prepare($sql);
            }

            foreach ($strParams as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
            foreach ($intParams as $k => $v) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $safePerPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'items'       => $stmt->fetchAll(),
                'total'       => $total,
                'page'        => $safePage,
                'per_page'    => $safePerPage,
                'total_pages' => $totalPages,
            ];
        } catch (PDOException $e) {
            error_log('[USER_PEMILIH_LIST_ERROR] ' . $e->getMessage());
            return [
                'items'       => [],
                'total'       => 0,
                'page'        => 1,
                'per_page'    => $safePerPage,
                'total_pages' => 1,
            ];
        }
    }

    /**
     * Mengambil detail lengkap satu akun pemilih (tanpa password hash) beserta riwayat partisipasi voting.
     */
    public function findPemilihDetailById(int $id, ?int $periodeId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nama, email, no_hp, role, created_at, updated_at
                FROM users
                WHERE id = :id AND role = 'pemilih'
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $pemilih = $stmt->fetch();

            if (!is_array($pemilih)) {
                return null;
            }

            // Ambil riwayat partisipasi voting lintas periode (read-only)
            $stmtHistory = $this->pdo->prepare("
                SELECT
                    v.periode_id,
                    v.kategori,
                    v.created_at AS waktu_voting,
                    p.nama_periode,
                    p.status AS status_periode
                FROM voting v
                INNER JOIN periode_voting p ON p.id = v.periode_id
                WHERE v.user_id = :user_id
                ORDER BY v.created_at DESC, v.id DESC
            ");
            $stmtHistory->execute([':user_id' => $id]);
            $riwayat = $stmtHistory->fetchAll();

            $statusKakang = false;
            $statusSenduk = false;
            if ($periodeId !== null && $periodeId > 0) {
                foreach ($riwayat as $r) {
                    if ((int) ($r['periode_id'] ?? 0) === $periodeId) {
                        if (($r['kategori'] ?? '') === 'Kakang') {
                            $statusKakang = true;
                        } elseif (($r['kategori'] ?? '') === 'Senduk') {
                            $statusSenduk = true;
                        }
                    }
                }
            }

            return [
                'pemilih'              => $pemilih,
                'status_kakang'        => $statusKakang,
                'status_senduk'        => $statusSenduk,
                'total_riwayat_voting' => count($riwayat),
                'riwayat_voting'       => $riwayat,
            ];
        } catch (PDOException $e) {
            error_log('[USER_PEMILIH_DETAIL_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Menghapus akun pemilih secara aman di dalam transaksi.
     * Menolak keras jika akun merupakan admin atau sudah memiliki riwayat voting (ON DELETE RESTRICT).
     *
     * @return array{deleted: bool, nama: ?string, email: ?string, reason: ?string}
     */
    public function deletePemilihSafely(int $id): array
    {
        return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($id): array {
            $stmtUser = $conn->prepare("
                SELECT id, nama, email, role
                FROM users
                WHERE id = :id
                LIMIT 1 FOR UPDATE
            ");
            $stmtUser->execute([':id' => $id]);
            $user = $stmtUser->fetch();

            if (!is_array($user)) {
                return [
                    'deleted' => false,
                    'nama'    => null,
                    'email'   => null,
                    'reason'  => 'Akun pemilih tidak ditemukan.',
                ];
            }

            if (($user['role'] ?? '') !== 'pemilih') {
                return [
                    'deleted' => false,
                    'nama'    => null,
                    'email'   => null,
                    'reason'  => 'Akun Administrator tidak dapat dihapus melalui manajemen pemilih.',
                ];
            }

            $stmtVotes = $conn->prepare("SELECT COUNT(*) FROM voting WHERE user_id = :uid");
            $stmtVotes->execute([':uid' => $id]);
            $jumlahSuara = (int) $stmtVotes->fetchColumn();

            if ($jumlahSuara > 0) {
                return [
                    'deleted' => false,
                    'nama'    => (string) ($user['nama'] ?? ''),
                    'email'   => (string) ($user['email'] ?? ''),
                    'reason'  => "Akun pemilih tidak dapat dihapus karena sudah memiliki {$jumlahSuara} riwayat suara tercatat. Riwayat voting dan integritas data harus tetap dipertahankan.",
                ];
            }

            $stmtRem = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = :uid");
            $stmtRem->execute([':uid' => $id]);

            $stmtDel = $conn->prepare("DELETE FROM users WHERE id = :id AND role = 'pemilih'");
            $stmtDel->execute([':id' => $id]);

            return [
                'deleted' => true,
                'nama'    => (string) ($user['nama'] ?? ''),
                'email'   => (string) ($user['email'] ?? ''),
                'reason'  => null,
            ];
        });
    }
}
