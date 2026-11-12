<?php
declare(strict_types=1);

/**
 * Model PeriodeVoting
 * Mengelola pembacaan data periode pemilihan dari tabel periode_voting.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

class PeriodeVoting
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mengambil periode dengan status 'Aktif' terbaru beserta ringkasan jumlah suara.
     */
    public function getActivePeriod(): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    p.id,
                    p.nama_periode,
                    p.tanggal_mulai,
                    p.tanggal_selesai,
                    p.status,
                    p.created_at,
                    p.updated_at,
                    COUNT(v.id) AS total_suara,
                    COALESCE(SUM(CASE WHEN v.kategori = 'Kakang' THEN 1 ELSE 0 END), 0) AS suara_kakang,
                    COALESCE(SUM(CASE WHEN v.kategori = 'Senduk' THEN 1 ELSE 0 END), 0) AS suara_senduk,
                    COUNT(DISTINCT v.user_id) AS total_pemilih_unik
                FROM periode_voting p
                LEFT JOIN voting v ON v.periode_id = p.id
                WHERE p.status = 'Aktif'
                GROUP BY p.id, p.nama_periode, p.tanggal_mulai, p.tanggal_selesai, p.status, p.created_at, p.updated_at
                ORDER BY p.tanggal_mulai DESC, p.id DESC
                LIMIT 1
            ");
            $stmt->execute();
            $row = $stmt->fetch();

            return is_array($row) ? $this->enrichPeriodState($row) : null;
        } catch (PDOException $e) {
            error_log('[PERIODE_GET_ACTIVE_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengambil periode aktif yang SAAT INI sedang berada dalam rentang jadwal waktu server
     * (status = 'Aktif' AND waktu_server >= tanggal_mulai AND waktu_server <= tanggal_selesai).
     */
    public function getOpenVotingPeriod(?DateTimeImmutable $serverNow = null): ?array
    {
        $active = $this->getActivePeriod();
        if ($active === null) {
            return null;
        }

        $evaluated = $serverNow !== null ? $this->enrichPeriodState($active, $serverNow) : $active;
        return !empty($evaluated['bisa_voting']) ? $evaluated : null;
    }

    /**
     * Memeriksa apakah voting diizinkan pada sebuah record periode berdasarkan waktu server.
     */
    public function isVotingAllowed(array $periode, ?DateTimeImmutable $serverNow = null): bool
    {
        $evaluated = $this->enrichPeriodState($periode, $serverNow);
        return !empty($evaluated['bisa_voting']);
    }

    /**
     * Mengambil seluruh daftar periode voting beserta jumlah suara per periode.
     */
    public function getAllPeriods(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    p.id,
                    p.nama_periode,
                    p.tanggal_mulai,
                    p.tanggal_selesai,
                    p.status,
                    p.created_at,
                    p.updated_at,
                    COUNT(v.id) AS total_suara,
                    COALESCE(SUM(CASE WHEN v.kategori = 'Kakang' THEN 1 ELSE 0 END), 0) AS suara_kakang,
                    COALESCE(SUM(CASE WHEN v.kategori = 'Senduk' THEN 1 ELSE 0 END), 0) AS suara_senduk,
                    COUNT(DISTINCT v.user_id) AS total_pemilih_unik
                FROM periode_voting p
                LEFT JOIN voting v ON v.periode_id = p.id
                GROUP BY p.id, p.nama_periode, p.tanggal_mulai, p.tanggal_selesai, p.status, p.created_at, p.updated_at
                ORDER BY (p.status = 'Aktif') DESC, p.tanggal_mulai DESC, p.id DESC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $now = $this->currentServerTime();
            return array_map(fn (array $row): array => $this->enrichPeriodState($row, $now), $rows);
        } catch (PDOException $e) {
            error_log('[PERIODE_GET_ALL_ERROR] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengambil satu periode berdasarkan ID beserta jumlah suara per periode.
     */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    p.id,
                    p.nama_periode,
                    p.tanggal_mulai,
                    p.tanggal_selesai,
                    p.status,
                    p.created_at,
                    p.updated_at,
                    COUNT(v.id) AS total_suara,
                    COALESCE(SUM(CASE WHEN v.kategori = 'Kakang' THEN 1 ELSE 0 END), 0) AS suara_kakang,
                    COALESCE(SUM(CASE WHEN v.kategori = 'Senduk' THEN 1 ELSE 0 END), 0) AS suara_senduk,
                    COUNT(DISTINCT v.user_id) AS total_pemilih_unik
                FROM periode_voting p
                LEFT JOIN voting v ON v.periode_id = p.id
                WHERE p.id = :id
                GROUP BY p.id, p.nama_periode, p.tanggal_mulai, p.tanggal_selesai, p.status, p.created_at, p.updated_at
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            return is_array($row) ? $this->enrichPeriodState($row) : null;
        } catch (PDOException $e) {
            error_log('[PERIODE_FIND_BY_ID_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mengembalikan waktu server saat ini sesuai zona waktu aplikasi.
     */
    public function currentServerTime(): DateTimeImmutable
    {
        $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
        return new DateTimeImmutable('now', $tz);
    }

    /**
     * Mengevaluasi dan memperkaya status periode berdasarkan waktu server saat ini.
     * Voting hanya bisa dilakukan jika: status = 'Aktif', now >= tanggal_mulai, dan now <= tanggal_selesai.
     */
    public function enrichPeriodState(array $periode, ?DateTimeImmutable $serverNow = null): array
    {
        $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
        $now = $serverNow ?? new DateTimeImmutable('now', $tz);

        $periode['total_suara'] = (int) ($periode['total_suara'] ?? 0);
        $periode['suara_kakang'] = (int) ($periode['suara_kakang'] ?? 0);
        $periode['suara_senduk'] = (int) ($periode['suara_senduk'] ?? 0);
        $periode['total_pemilih_unik'] = (int) ($periode['total_pemilih_unik'] ?? 0);
        $periode['waktu_server'] = $now->format('Y-m-d H:i:s');

        try {
            $mulai = new DateTimeImmutable((string) ($periode['tanggal_mulai'] ?? ''), $tz);
            $selesai = new DateTimeImmutable((string) ($periode['tanggal_selesai'] ?? ''), $tz);
        } catch (Exception $e) {
            $periode['jadwal_status'] = 'Tidak Valid';
            $periode['jadwal_kode'] = 'tidak_valid';
            $periode['sedang_berlangsung'] = false;
            $periode['bisa_voting'] = false;
            $periode['syarat_status_aktif'] = false;
            $periode['syarat_sudah_mulai'] = false;
            $periode['syarat_belum_selesai'] = false;
            $periode['alasan_status'] = 'Format tanggal mulai atau tanggal selesai tidak valid.';
            return $periode;
        }

        $isAktif = (($periode['status'] ?? '') === 'Aktif');
        $sudahMulai = ($now >= $mulai);
        $belumSelesai = ($now <= $selesai);

        $periode['syarat_status_aktif'] = $isAktif;
        $periode['syarat_sudah_mulai'] = $sudahMulai;
        $periode['syarat_belum_selesai'] = $belumSelesai;

        if (!$isAktif) {
            $periode['jadwal_status'] = 'Nonaktif';
            $periode['jadwal_kode'] = 'nonaktif';
            $periode['sedang_berlangsung'] = false;
            $periode['bisa_voting'] = false;
            $periode['alasan_status'] = 'Periode berstatus Nonaktif sehingga pemungutan suara tidak dibuka.';
        } elseif (!$sudahMulai) {
            $periode['jadwal_status'] = 'Belum Dimulai';
            $periode['jadwal_kode'] = 'belum_dimulai';
            $periode['sedang_berlangsung'] = false;
            $periode['bisa_voting'] = false;
            $periode['alasan_status'] = 'Periode berstatus Aktif, namun waktu server belum mencapai jadwal mulai.';
        } elseif (!$belumSelesai) {
            $periode['jadwal_status'] = 'Sudah Berakhir';
            $periode['jadwal_kode'] = 'sudah_berakhir';
            $periode['sedang_berlangsung'] = false;
            $periode['bisa_voting'] = false;
            $periode['alasan_status'] = 'Periode berstatus Aktif, namun batas waktu pemungutan suara telah berakhir.';
        } else {
            $periode['jadwal_status'] = 'Sedang Berlangsung';
            $periode['jadwal_kode'] = 'sedang_berlangsung';
            $periode['sedang_berlangsung'] = true;
            $periode['bisa_voting'] = true;
            $periode['alasan_status'] = 'Periode berstatus Aktif dan waktu server berada di dalam rentang jadwal voting.';
        }

        return $periode;
    }

    /**
     * Membuat periode voting baru (mencegah jadwal bertentangan & dua periode aktif sekaligus).
     *
     * @return array{success: bool, id: ?int, error: ?string}
     */
    public function create(array $data): array
    {
        return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($data): array {
            if ($data['status'] === 'Aktif') {
                $conflict = self::checkActiveConflict($conn, $data['tanggal_mulai'], $data['tanggal_selesai'], null);
                if ($conflict !== null) {
                    return [
                        'success' => false,
                        'id'      => null,
                        'error'   => $conflict,
                    ];
                }
            }

            $stmt = $conn->prepare("
                INSERT INTO periode_voting (nama_periode, tanggal_mulai, tanggal_selesai, status)
                VALUES (:nama_periode, :tanggal_mulai, :tanggal_selesai, :status)
            ");
            $stmt->execute([
                ':nama_periode'    => $data['nama_periode'],
                ':tanggal_mulai'   => $data['tanggal_mulai'],
                ':tanggal_selesai' => $data['tanggal_selesai'],
                ':status'          => $data['status'],
            ]);

            return [
                'success' => true,
                'id'      => (int) $conn->lastInsertId(),
                'error'   => null,
            ];
        });
    }

    /**
     * Memperbarui periode voting.
     * Menolak perubahan data/jadwal apabila periode sudah memiliki suara tercatat (menjaga riwayat voting).
     *
     * @return array{success: bool, error: ?string}
     */
    public function update(int $id, array $data): array
    {
        return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($id, $data): array {
            $stmtTarget = $conn->prepare("SELECT id, nama_periode, status FROM periode_voting WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmtTarget->execute([':id' => $id]);
            $target = $stmtTarget->fetch();
            if (!is_array($target)) {
                return ['success' => false, 'error' => 'Periode voting tidak ditemukan.'];
            }

            $stmtVotes = $conn->prepare("SELECT COUNT(*) FROM voting WHERE periode_id = :id");
            $stmtVotes->execute([':id' => $id]);
            $jumlahSuara = (int) $stmtVotes->fetchColumn();

            if ($jumlahSuara > 0) {
                return [
                    'success' => false,
                    'error'   => "Periode \"{$target['nama_periode']}\" tidak dapat diubah karena sudah memiliki {$jumlahSuara} suara tercatat. Gunakan tombol Aktifkan/Nonaktifkan jika hanya ingin mengatur status periode.",
                ];
            }

            if ($data['status'] === 'Aktif') {
                $conflict = self::checkActiveConflict($conn, $data['tanggal_mulai'], $data['tanggal_selesai'], $id);
                if ($conflict !== null) {
                    return [
                        'success' => false,
                        'error'   => $conflict,
                    ];
                }
            }

            $stmt = $conn->prepare("
                UPDATE periode_voting
                SET nama_periode    = :nama_periode,
                    tanggal_mulai   = :tanggal_mulai,
                    tanggal_selesai = :tanggal_selesai,
                    status          = :status
                WHERE id = :id
            ");
            $stmt->execute([
                ':nama_periode'    => $data['nama_periode'],
                ':tanggal_mulai'   => $data['tanggal_mulai'],
                ':tanggal_selesai' => $data['tanggal_selesai'],
                ':status'          => $data['status'],
                ':id'              => $id,
            ]);

            return ['success' => true, 'error' => null];
        });
    }

    /**
     * Mengaktifkan atau menonaktifkan periode secara atomik.
     *
     * @return array{success: bool, error: ?string}
     */
    public function setStatus(int $id, string $targetStatus): array
    {
        if (!in_array($targetStatus, ['Aktif', 'Nonaktif'], true)) {
            return ['success' => false, 'error' => 'Status periode tidak valid.'];
        }

        return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($id, $targetStatus): array {
            $stmtTarget = $conn->prepare("
                SELECT id, nama_periode, tanggal_mulai, tanggal_selesai
                FROM periode_voting
                WHERE id = :id
                LIMIT 1 FOR UPDATE
            ");
            $stmtTarget->execute([':id' => $id]);
            $target = $stmtTarget->fetch();

            if (!is_array($target)) {
                return ['success' => false, 'error' => 'Periode voting tidak ditemukan.'];
            }

            if ($targetStatus === 'Aktif') {
                $conflict = self::checkActiveConflict(
                    $conn,
                    (string) $target['tanggal_mulai'],
                    (string) $target['tanggal_selesai'],
                    $id
                );
                if ($conflict !== null) {
                    return [
                        'success' => false,
                        'error'   => $conflict,
                    ];
                }
            }

            $stmtUpd = $conn->prepare("UPDATE periode_voting SET status = :status WHERE id = :id");
            $stmtUpd->execute([
                ':status' => $targetStatus,
                ':id'     => $id,
            ]);

            return ['success' => true, 'error' => null];
        });
    }

    /**
     * Menghapus periode voting secara aman di dalam transaksi.
     * Menolak keras jika periode masih Aktif atau sudah memiliki riwayat suara (ON DELETE RESTRICT).
     *
     * @return array{deleted: bool, nama_periode: ?string, reason: ?string}
     */
    public function deleteSafely(int $id): array
    {
        return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($id): array {
            $stmtTarget = $conn->prepare("
                SELECT id, nama_periode, status
                FROM periode_voting
                WHERE id = :id
                LIMIT 1 FOR UPDATE
            ");
            $stmtTarget->execute([':id' => $id]);
            $target = $stmtTarget->fetch();

            if (!is_array($target)) {
                return [
                    'deleted'      => false,
                    'nama_periode' => null,
                    'reason'       => 'Periode voting tidak ditemukan.',
                ];
            }

            $stmtVotes = $conn->prepare("SELECT COUNT(*) FROM voting WHERE periode_id = :id");
            $stmtVotes->execute([':id' => $id]);
            $jumlahSuara = (int) $stmtVotes->fetchColumn();

            if ($jumlahSuara > 0) {
                return [
                    'deleted'      => false,
                    'nama_periode' => (string) ($target['nama_periode'] ?? ''),
                    'reason'       => "Riwayat periode \"{$target['nama_periode']}\" tidak dapat dihapus karena sudah memiliki {$jumlahSuara} suara tercatat.",
                ];
            }

            if (($target['status'] ?? '') === 'Aktif') {
                return [
                    'deleted'      => false,
                    'nama_periode' => (string) ($target['nama_periode'] ?? ''),
                    'reason'       => "Periode \"{$target['nama_periode']}\" masih berstatus Aktif. Nonaktifkan periode terlebih dahulu sebelum menghapus.",
                ];
            }

            $stmtDel = $conn->prepare("DELETE FROM periode_voting WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            return [
                'deleted'      => true,
                'nama_periode' => (string) ($target['nama_periode'] ?? ''),
                'reason'       => null,
            ];
        });
    }

    /**
     * Memastikan tidak ada periode aktif lain yang bertentangan jadwal atau aktif bersamaan.
     */
    private static function checkActiveConflict(
        PDO $conn,
        string $tanggalMulai,
        string $tanggalSelesai,
        ?int $excludeId
    ): ?string {
        $excludeSql = $excludeId !== null ? 'AND id <> :exclude_id' : '';

        // 1. Cek bentrok rentang jadwal dengan periode aktif lain
        $stmtOverlap = $conn->prepare("
            SELECT id, nama_periode, tanggal_mulai, tanggal_selesai
            FROM periode_voting
            WHERE status = 'Aktif'
              {$excludeSql}
              AND tanggal_mulai <= :tanggal_selesai
              AND tanggal_selesai >= :tanggal_mulai
            LIMIT 1 FOR UPDATE
        ");
        $overlapParams = [
            ':tanggal_mulai'   => $tanggalMulai,
            ':tanggal_selesai' => $tanggalSelesai,
        ];
        if ($excludeId !== null) {
            $overlapParams[':exclude_id'] = $excludeId;
        }
        $stmtOverlap->execute($overlapParams);
        $overlapping = $stmtOverlap->fetch();

        if (is_array($overlapping)) {
            return 'Jadwal periode bertentangan (tumpang tindih) dengan periode aktif "' . $overlapping['nama_periode'] . '" ('
                . formatTanggalIndonesia((string) $overlapping['tanggal_mulai'], true)
                . ' s.d. '
                . formatTanggalIndonesia((string) $overlapping['tanggal_selesai'], true) . ').';
        }

        // 2. Cegah dua periode berstatus Aktif sekaligus pada sistem
        $stmtActive = $conn->prepare("
            SELECT id, nama_periode
            FROM periode_voting
            WHERE status = 'Aktif'
              {$excludeSql}
            LIMIT 1 FOR UPDATE
        ");
        $activeParams = $excludeId !== null ? [':exclude_id' => $excludeId] : [];
        $stmtActive->execute($activeParams);
        $existingActive = $stmtActive->fetch();

        if (is_array($existingActive)) {
            return 'Tidak dapat mengaktifkan periode ini karena periode "' . $existingActive['nama_periode'] . '" masih berstatus Aktif. Nonaktifkan periode tersebut terlebih dahulu.';
        }

        return null;
    }
}

