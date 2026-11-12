<?php
declare(strict_types=1);

/**
 * Model Kontestan
 * Mengelola pembacaan data kontestan Kakang & Senduk menggunakan PDO prepared statements.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

class Kontestan
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mengambil daftar kontestan unggulan (status Aktif) untuk Landing Page.
     */
    public function getFeatured(int $limit = 6): array
    {
        try {
            $safeLimit = max(1, min(12, $limit));
            $stmt = $this->pdo->prepare("
                SELECT id, nomor_peserta, nama, kategori, foto, asal_instansi, deskripsi, status
                FROM kontestan
                WHERE status = 'Aktif'
                ORDER BY kategori ASC, nomor_peserta ASC, id ASC
                LIMIT :batas
            ");
            $stmt->bindValue(':batas', $safeLimit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('[KONTESTAN_FEATURED_ERROR] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengambil daftar kontestan aktif dengan filter kategori, pencarian nama, dan pagination.
     */
    public function getPaginatedPublic(?string $kategori = null, string $cari = '', int $page = 1, int $perPage = 9): array
    {
        $safePage = max(1, $page);
        $safePerPage = max(1, min(50, $perPage));

        $whereParts = ["status = 'Aktif'"];
        $params = [];

        if ($kategori !== null && in_array($kategori, ['Kakang', 'Senduk'], true)) {
            $whereParts[] = 'kategori = :kategori';
            $params[':kategori'] = $kategori;
        }

        $cleanSearch = trim($cari);
        if ($cleanSearch !== '') {
            $whereParts[] = '(nama LIKE :cari_nama OR nomor_peserta LIKE :cari_nomor)';
            $likeTerm = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cleanSearch) . '%';
            $params[':cari_nama'] = $likeTerm;
            $params[':cari_nomor'] = $likeTerm;
        }

        $whereSql = implode(' AND ', $whereParts);

        try {
            $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM kontestan WHERE {$whereSql}");
            foreach ($params as $key => $val) {
                $stmtCount->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmtCount->execute();
            $total = (int) $stmtCount->fetchColumn();

            $totalPages = max(1, (int) ceil($total / $safePerPage));
            if ($safePage > $totalPages) {
                $safePage = $totalPages;
            }
            $offset = ($safePage - 1) * $safePerPage;

            $stmtList = $this->pdo->prepare("
                SELECT id, nomor_peserta, nama, kategori, foto, asal_instansi, deskripsi, status, created_at
                FROM kontestan
                WHERE {$whereSql}
                ORDER BY kategori ASC, nomor_peserta ASC, id ASC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($params as $key => $val) {
                $stmtList->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmtList->bindValue(':limit', $safePerPage, PDO::PARAM_INT);
            $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtList->execute();

            return [
                'items'       => $stmtList->fetchAll(),
                'total'       => $total,
                'page'        => $safePage,
                'per_page'    => $safePerPage,
                'total_pages' => $totalPages,
            ];
        } catch (PDOException $e) {
            error_log('[KONTESTAN_PAGINATED_ERROR] ' . $e->getMessage());
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
     * Mengambil detail lengkap kontestan aktif berdasarkan ID.
     */
    public function findActiveById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nomor_peserta, nama, kategori, foto, asal_instansi,
                       tempat_lahir, tanggal_lahir, visi, misi, prestasi, deskripsi,
                       status, created_at, updated_at
                FROM kontestan
                WHERE id = :id AND status = 'Aktif'
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (PDOException $e) {
            error_log('[KONTESTAN_FIND_ACTIVE_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengambil seluruh kontestan aktif berdasarkan kategori ('Kakang' / 'Senduk') untuk bilik suara.
     */
    public function getActiveByKategori(string $kategori): array
    {
        if (!in_array($kategori, ['Kakang', 'Senduk'], true)) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nomor_peserta, nama, kategori, foto, asal_instansi, deskripsi
                FROM kontestan
                WHERE status = 'Aktif' AND kategori = :kategori
                ORDER BY nomor_peserta ASC, id ASC
            ");
            $stmt->execute([':kategori' => $kategori]);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('[KONTESTAN_BY_KATEGORI_ERROR] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Menghitung ringkasan jumlah kontestan aktif per kategori.
     */
    public function getActiveStats(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN kategori = 'Kakang' THEN 1 ELSE 0 END) AS total_kakang,
                    SUM(CASE WHEN kategori = 'Senduk' THEN 1 ELSE 0 END) AS total_senduk
                FROM kontestan
                WHERE status = 'Aktif'
            ");
            $stmt->execute();
            $row = $stmt->fetch();

            return [
                'total'        => (int) ($row['total'] ?? 0),
                'total_kakang' => (int) ($row['total_kakang'] ?? 0),
                'total_senduk' => (int) ($row['total_senduk'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('[KONTESTAN_STATS_ERROR] ' . $e->getMessage());
            return ['total' => 0, 'total_kakang' => 0, 'total_senduk' => 0];
        }
    }

    /**
     * Menghitung total seluruh kontestan (Aktif, Nonaktif, Kakang, dan Senduk) untuk dashboard admin.
     */
    public function getAdminCounts(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Aktif' THEN 1 ELSE 0 END) AS aktif,
                    SUM(CASE WHEN status = 'Nonaktif' THEN 1 ELSE 0 END) AS nonaktif,
                    SUM(CASE WHEN kategori = 'Kakang' THEN 1 ELSE 0 END) AS total_kakang,
                    SUM(CASE WHEN kategori = 'Senduk' THEN 1 ELSE 0 END) AS total_senduk
                FROM kontestan
            ");
            $stmt->execute();
            $row = $stmt->fetch();

            return [
                'total'        => (int) ($row['total'] ?? 0),
                'aktif'        => (int) ($row['aktif'] ?? 0),
                'nonaktif'     => (int) ($row['nonaktif'] ?? 0),
                'total_kakang' => (int) ($row['total_kakang'] ?? 0),
                'total_senduk' => (int) ($row['total_senduk'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('[KONTESTAN_ADMIN_COUNTS_ERROR] ' . $e->getMessage());
            return [
                'total'        => 0,
                'aktif'        => 0,
                'nonaktif'     => 0,
                'total_kakang' => 0,
                'total_senduk' => 0,
            ];
        }
    }

    /**
     * Mengambil daftar kontestan lengkap untuk manajemen admin (termasuk jumlah suara).
     */
    public function getPaginatedAdmin(?string $kategori = null, ?string $status = null, string $cari = '', int $page = 1, int $perPage = 10): array
    {
        $safePage = max(1, $page);
        $safePerPage = max(1, min(50, $perPage));

        $whereParts = ['1 = 1'];
        $params = [];

        if ($kategori !== null && in_array($kategori, ['Kakang', 'Senduk'], true)) {
            $whereParts[] = 'k.kategori = :kategori';
            $params[':kategori'] = $kategori;
        }

        if ($status !== null && in_array($status, ['Aktif', 'Nonaktif'], true)) {
            $whereParts[] = 'k.status = :status';
            $params[':status'] = $status;
        }

        $cleanSearch = trim($cari);
        if ($cleanSearch !== '') {
            $whereParts[] = '(k.nama LIKE :cari_nama OR k.nomor_peserta LIKE :cari_nomor OR k.asal_instansi LIKE :cari_instansi)';
            $likeTerm = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cleanSearch) . '%';
            $params[':cari_nama'] = $likeTerm;
            $params[':cari_nomor'] = $likeTerm;
            $params[':cari_instansi'] = $likeTerm;
        }

        $whereSql = implode(' AND ', $whereParts);

        try {
            $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM kontestan k WHERE {$whereSql}");
            foreach ($params as $key => $val) {
                $stmtCount->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmtCount->execute();
            $total = (int) $stmtCount->fetchColumn();

            $totalPages = max(1, (int) ceil($total / $safePerPage));
            if ($safePage > $totalPages) {
                $safePage = $totalPages;
            }
            $offset = ($safePage - 1) * $safePerPage;

            $stmtList = $this->pdo->prepare("
                SELECT k.id, k.nomor_peserta, k.nama, k.kategori, k.foto, k.asal_instansi,
                       k.status, k.created_at, COUNT(v.id) AS total_suara
                FROM kontestan k
                LEFT JOIN voting v ON v.kontestan_id = k.id
                WHERE {$whereSql}
                GROUP BY k.id, k.nomor_peserta, k.nama, k.kategori, k.foto, k.asal_instansi, k.status, k.created_at
                ORDER BY k.kategori ASC, k.nomor_peserta ASC, k.id DESC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($params as $key => $val) {
                $stmtList->bindValue($key, $val, PDO::PARAM_STR);
            }
            $stmtList->bindValue(':limit', $safePerPage, PDO::PARAM_INT);
            $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtList->execute();

            return [
                'items'       => $stmtList->fetchAll(),
                'total'       => $total,
                'page'        => $safePage,
                'per_page'    => $safePerPage,
                'total_pages' => $totalPages,
            ];
        } catch (PDOException $e) {
            error_log('[KONTESTAN_ADMIN_LIST_ERROR] ' . $e->getMessage());
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
     * Mengambil data kontestan berdasarkan ID (Aktif maupun Nonaktif) untuk admin.
     */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, nomor_peserta, nama, kategori, foto, asal_instansi,
                       tempat_lahir, tanggal_lahir, visi, misi, prestasi, deskripsi,
                       status, created_at, updated_at
                FROM kontestan
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (PDOException $e) {
            error_log('[KONTESTAN_FIND_BY_ID_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Memeriksa keunikan nomor peserta.
     */
    public function nomorPesertaExists(string $nomorPeserta, ?int $excludeId = null): bool
    {
        try {
            if ($excludeId !== null) {
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM kontestan WHERE nomor_peserta = :nomor AND id <> :exclude_id LIMIT 1
                ");
                $stmt->execute([
                    ':nomor'      => trim($nomorPeserta),
                    ':exclude_id' => $excludeId,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM kontestan WHERE nomor_peserta = :nomor LIMIT 1
                ");
                $stmt->execute([':nomor' => trim($nomorPeserta)]);
            }

            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log('[KONTESTAN_NOMOR_EXISTS_ERROR] ' . $e->getMessage());
            throw new RuntimeException('Terjadi gangguan saat memeriksa nomor peserta.');
        }
    }

    /**
     * Menghitung jumlah suara yang dimiliki kontestan tertentu.
     */
    public function countVotes(int $kontestanId): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM voting WHERE kontestan_id = :id");
            $stmt->execute([':id' => $kontestanId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[KONTESTAN_COUNT_VOTES_ERROR] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Menyimpan kontestan baru di dalam transaksi database.
     */
    public function create(array $data): int
    {
        return (int) jalankanTransaksi($this->pdo, static function (PDO $conn) use ($data): int {
            $stmt = $conn->prepare("
                INSERT INTO kontestan (
                    nomor_peserta, nama, kategori, foto, asal_instansi,
                    tempat_lahir, tanggal_lahir, visi, misi, prestasi, deskripsi, status
                ) VALUES (
                    :nomor_peserta, :nama, :kategori, :foto, :asal_instansi,
                    :tempat_lahir, :tanggal_lahir, :visi, :misi, :prestasi, :deskripsi, :status
                )
            ");
            $stmt->execute([
                ':nomor_peserta' => $data['nomor_peserta'],
                ':nama'          => $data['nama'],
                ':kategori'      => $data['kategori'],
                ':foto'          => $data['foto'],
                ':asal_instansi' => $data['asal_instansi'],
                ':tempat_lahir'  => $data['tempat_lahir'],
                ':tanggal_lahir' => $data['tanggal_lahir'],
                ':visi'          => $data['visi'],
                ':misi'          => $data['misi'],
                ':prestasi'      => $data['prestasi'],
                ':deskripsi'     => $data['deskripsi'],
                ':status'        => $data['status'],
            ]);

            return (int) $conn->lastInsertId();
        });
    }

    /**
     * Memperbarui data kontestan di dalam transaksi database.
     * Menolak perubahan kategori secara atomik jika kontestan sudah memiliki suara tercatat.
     *
     * @return array{success: bool, error: ?string}
     */
    public function update(int $id, array $data): array
    {
        return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($id, $data): array {
            $stmtExisting = $conn->prepare("
                SELECT id, kategori
                FROM kontestan
                WHERE id = :id
                LIMIT 1 FOR UPDATE
            ");
            $stmtExisting->execute([':id' => $id]);
            $existing = $stmtExisting->fetch();

            if (!is_array($existing)) {
                return [
                    'success' => false,
                    'error'   => 'Data kontestan tidak ditemukan.',
                ];
            }

            $oldKategori = (string) ($existing['kategori'] ?? '');
            $newKategori = (string) ($data['kategori'] ?? '');

            if ($oldKategori !== $newKategori) {
                $stmtVotes = $conn->prepare("SELECT COUNT(*) FROM voting WHERE kontestan_id = :id");
                $stmtVotes->execute([':id' => $id]);
                $jumlahSuara = (int) $stmtVotes->fetchColumn();

                if ($jumlahSuara > 0) {
                    return [
                        'success' => false,
                        'error'   => "Kategori kontestan tidak dapat diubah dari {$oldKategori} ke {$newKategori} karena kontestan sudah memiliki {$jumlahSuara} suara tercatat.",
                    ];
                }
            }

            $stmt = $conn->prepare("
                UPDATE kontestan
                SET nomor_peserta = :nomor_peserta,
                    nama          = :nama,
                    kategori      = :kategori,
                    foto          = :foto,
                    asal_instansi = :asal_instansi,
                    tempat_lahir  = :tempat_lahir,
                    tanggal_lahir = :tanggal_lahir,
                    visi          = :visi,
                    misi          = :misi,
                    prestasi      = :prestasi,
                    deskripsi     = :deskripsi,
                    status        = :status
                WHERE id = :id
            ");
            $stmt->execute([
                ':nomor_peserta' => $data['nomor_peserta'],
                ':nama'          => $data['nama'],
                ':kategori'      => $data['kategori'],
                ':foto'          => $data['foto'],
                ':asal_instansi' => $data['asal_instansi'],
                ':tempat_lahir'  => $data['tempat_lahir'],
                ':tanggal_lahir' => $data['tanggal_lahir'],
                ':visi'          => $data['visi'],
                ':misi'          => $data['misi'],
                ':prestasi'      => $data['prestasi'],
                ':deskripsi'     => $data['deskripsi'],
                ':status'        => $data['status'],
                ':id'            => $id,
            ]);

            return [
                'success' => true,
                'error'   => null,
            ];
        });
    }

    /**
     * Menghapus kontestan secara aman dengan validasi relasi suara di dalam transaksi.
     *
     * @return array{deleted: bool, foto: ?string, reason: ?string}
     */
    public function deleteSafely(int $id): array
    {
        return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($id): array {
            $stmtRow = $conn->prepare("SELECT id, foto FROM kontestan WHERE id = :id FOR UPDATE");
            $stmtRow->execute([':id' => $id]);
            $row = $stmtRow->fetch();

            if (!is_array($row)) {
                return ['deleted' => false, 'foto' => null, 'reason' => 'Kontestan tidak ditemukan.'];
            }

            $stmtVotes = $conn->prepare("SELECT COUNT(*) FROM voting WHERE kontestan_id = :id");
            $stmtVotes->execute([':id' => $id]);
            $jumlahSuara = (int) $stmtVotes->fetchColumn();

            if ($jumlahSuara > 0) {
                return [
                    'deleted' => false,
                    'foto'    => null,
                    'reason'  => "Kontestan tidak dapat dihapus karena sudah memiliki {$jumlahSuara} suara tercatat. Nonaktifkan status kontestan jika ingin menyembunyikannya.",
                ];
            }

            $stmtDel = $conn->prepare("DELETE FROM kontestan WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            return [
                'deleted' => true,
                'foto'    => $row['foto'] ?? null,
                'reason'  => null,
            ];
        });
    }

    /**
     * Mengubah status kontestan (Aktif / Nonaktif) sebagai mekanisme pengarsipan yang aman di dalam transaksi.
     *
     * @return array{success: bool, nama: ?string, nomor_peserta: ?string, status: ?string, reason: ?string}
     */
    public function updateStatus(int $id, string $targetStatus): array
    {
        if (!in_array($targetStatus, ['Aktif', 'Nonaktif'], true)) {
            return [
                'success'       => false,
                'nama'          => null,
                'nomor_peserta' => null,
                'status'        => null,
                'reason'        => 'Status kontestan tidak valid.',
            ];
        }

        return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($id, $targetStatus): array {
            $stmtRow = $conn->prepare("
                SELECT id, nama, nomor_peserta, status
                FROM kontestan
                WHERE id = :id
                LIMIT 1 FOR UPDATE
            ");
            $stmtRow->execute([':id' => $id]);
            $row = $stmtRow->fetch();

            if (!is_array($row)) {
                return [
                    'success'       => false,
                    'nama'          => null,
                    'nomor_peserta' => null,
                    'status'        => null,
                    'reason'        => 'Data kontestan tidak ditemukan.',
                ];
            }

            $stmtUpd = $conn->prepare("UPDATE kontestan SET status = :status WHERE id = :id");
            $stmtUpd->execute([
                ':status' => $targetStatus,
                ':id'     => $id,
            ]);

            return [
                'success'       => true,
                'nama'          => (string) ($row['nama'] ?? ''),
                'nomor_peserta' => (string) ($row['nomor_peserta'] ?? ''),
                'status'        => $targetStatus,
                'reason'        => null,
            ];
        });
    }
}

