<?php
declare(strict_types=1);

/**
 * Model Voting
 * Mengelola pembacaan data perolehan suara dan klasemen menggunakan PDO prepared statements.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

class Voting
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Mengambil klasemen kontestan aktif berdasarkan kategori dan periode.
     * Menggunakan COUNT(), GROUP BY, LEFT JOIN agar kontestan 0 suara tetap tampil,
     * serta tie-breaker konsisten berdasarkan nomor peserta dan ID.
     */
    public function getKlasemenByKategori(string $kategori, ?int $periodeId): array
    {
        $emptyResult = [
            'items'       => [],
            'total_suara' => 0,
            'podium'      => [],
        ];

        if (!in_array($kategori, ['Kakang', 'Senduk'], true)) {
            return $emptyResult;
        }

        try {
            if ($periodeId !== null && $periodeId > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT
                        k.id,
                        k.nomor_peserta,
                        k.nama,
                        k.kategori,
                        k.foto,
                        k.asal_instansi,
                        COUNT(v.id) AS jumlah_suara
                    FROM kontestan k
                    LEFT JOIN voting v
                        ON v.kontestan_id = k.id
                       AND v.periode_id = :periode_id
                       AND v.kategori = k.kategori
                    WHERE k.kategori = :kategori
                      AND k.status = 'Aktif'
                    GROUP BY k.id, k.nomor_peserta, k.nama, k.kategori, k.foto, k.asal_instansi
                    ORDER BY jumlah_suara DESC, LENGTH(k.nomor_peserta) ASC, k.nomor_peserta ASC, k.id ASC
                ");
                $stmt->execute([
                    ':periode_id' => $periodeId,
                    ':kategori'   => $kategori,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT
                        k.id,
                        k.nomor_peserta,
                        k.nama,
                        k.kategori,
                        k.foto,
                        k.asal_instansi,
                        0 AS jumlah_suara
                    FROM kontestan k
                    WHERE k.kategori = :kategori
                      AND k.status = 'Aktif'
                    ORDER BY LENGTH(k.nomor_peserta) ASC, k.nomor_peserta ASC, k.id ASC
                ");
                $stmt->execute([':kategori' => $kategori]);
            }

            $rows = $stmt->fetchAll();
            $totalSuaraKategori = 0;
            $voteFrequency = [];
            foreach ($rows as $row) {
                $suara = (int) ($row['jumlah_suara'] ?? 0);
                $totalSuaraKategori += $suara;
                $voteFrequency[$suara] = ($voteFrequency[$suara] ?? 0) + 1;
            }

            $ranking = 1;
            foreach ($rows as &$row) {
                $suara = (int) ($row['jumlah_suara'] ?? 0);
                $row['jumlah_suara'] = $suara;
                $row['ranking'] = $ranking++;
                $row['is_seri'] = ($suara > 0 && ($voteFrequency[$suara] ?? 0) > 1);
                $row['persentase'] = $totalSuaraKategori > 0
                    ? round(($suara / $totalSuaraKategori) * 100, 2)
                    : 0.0;
            }
            unset($row);

            $podiumCandidates = array_values(array_filter(
                $rows,
                static fn (array $r): bool => (int) ($r['jumlah_suara'] ?? 0) > 0
            ));

            return [
                'items'       => $rows,
                'total_suara' => $totalSuaraKategori,
                'podium'      => array_slice($podiumCandidates, 0, 3),
            ];
        } catch (PDOException $e) {
            error_log('[VOTING_KLASEMEN_ERROR] ' . $e->getMessage());
            return $emptyResult;
        }
    }

    /**
     * Menghitung total suara masuk pada periode tertentu.
     */
    public function getTotalSuaraByPeriode(?int $periodeId): array
    {
        if ($periodeId === null || $periodeId <= 0) {
            return ['total' => 0, 'kakang' => 0, 'senduk' => 0];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN kategori = 'Kakang' THEN 1 ELSE 0 END) AS kakang,
                    SUM(CASE WHEN kategori = 'Senduk' THEN 1 ELSE 0 END) AS senduk
                FROM voting
                WHERE periode_id = :periode_id
            ");
            $stmt->execute([':periode_id' => $periodeId]);
            $row = $stmt->fetch();

            return [
                'total'  => (int) ($row['total'] ?? 0),
                'kakang' => (int) ($row['kakang'] ?? 0),
                'senduk' => (int) ($row['senduk'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('[VOTING_TOTAL_PERIODE_ERROR] ' . $e->getMessage());
            return ['total' => 0, 'kakang' => 0, 'senduk' => 0];
        }
    }

    /**
     * Mengambil total suara keseluruhan sepanjang seluruh periode atau periode terpilih.
     */
    public function getOverallTotals(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN kategori = 'Kakang' THEN 1 ELSE 0 END) AS kakang,
                    SUM(CASE WHEN kategori = 'Senduk' THEN 1 ELSE 0 END) AS senduk
                FROM voting
            ");
            $stmt->execute();
            $row = $stmt->fetch();

            return [
                'total'  => (int) ($row['total'] ?? 0),
                'kakang' => (int) ($row['kakang'] ?? 0),
                'senduk' => (int) ($row['senduk'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('[VOTING_OVERALL_TOTALS_ERROR] ' . $e->getMessage());
            return ['total' => 0, 'kakang' => 0, 'senduk' => 0];
        }
    }

    /**
     * Mengambil aktivitas terbaru (registrasi pemilih dan pemungutan suara) untuk dashboard admin.
     * Dioptimalkan dengan LIMIT per sub-query agar tidak memindai seluruh tabel pada skala data besar.
     */
    public function getRecentActivities(int $limit = 8): array
    {
        try {
            $safeLimit = max(1, min(20, $limit));
            $stmt = $this->pdo->prepare("
                SELECT tipe, judul, keterangan, waktu
                FROM (
                    (
                        SELECT
                            'voting' AS tipe,
                            CONCAT(u.nama, ' memberikan suara ', v.kategori) AS judul,
                            p.nama_periode AS keterangan,
                            v.created_at AS waktu
                        FROM voting v
                        INNER JOIN users u ON u.id = v.user_id
                        INNER JOIN periode_voting p ON p.id = v.periode_id
                        ORDER BY v.created_at DESC, v.id DESC
                        LIMIT :batas_vote
                    )
                    UNION ALL
                    (
                        SELECT
                            'registrasi' AS tipe,
                            CONCAT(u.nama, ' mendaftar sebagai pemilih') AS judul,
                            u.email AS keterangan,
                            u.created_at AS waktu
                        FROM users u
                        WHERE u.role = 'pemilih'
                        ORDER BY u.created_at DESC, u.id DESC
                        LIMIT :batas_reg
                    )
                ) AS aktivitas
                ORDER BY waktu DESC
                LIMIT :batas_total
            ");
            $stmt->bindValue(':batas_vote', $safeLimit, PDO::PARAM_INT);
            $stmt->bindValue(':batas_reg', $safeLimit, PDO::PARAM_INT);
            $stmt->bindValue(':batas_total', $safeLimit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('[VOTING_RECENT_ACTIVITIES_ERROR] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengambil status & detail suara pemilih pada periode tertentu (dipisah per kategori Kakang dan Senduk).
     *
     * @return array{
     *     sudah_kakang: bool,
     *     sudah_senduk: bool,
     *     detail_kakang: ?array,
     *     detail_senduk: ?array,
     *     total_suara_user: int
     * }
     */
    public function getUserVotingStatusInPeriode(int $userId, int $periodeId): array
    {
        $result = [
            'sudah_kakang'     => false,
            'sudah_senduk'     => false,
            'detail_kakang'    => null,
            'detail_senduk'    => null,
            'total_suara_user' => 0,
        ];

        if ($userId <= 0 || $periodeId <= 0) {
            return $result;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    v.id AS voting_id,
                    v.kategori,
                    v.created_at AS waktu_voting,
                    k.id AS kontestan_id,
                    k.nomor_peserta,
                    k.nama,
                    k.foto,
                    k.asal_instansi
                FROM voting v
                INNER JOIN kontestan k ON k.id = v.kontestan_id
                WHERE v.user_id = :user_id
                  AND v.periode_id = :periode_id
                ORDER BY v.id ASC
            ");
            $stmt->execute([
                ':user_id'    => $userId,
                ':periode_id' => $periodeId,
            ]);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $kat = (string) ($row['kategori'] ?? '');
                if ($kat === 'Kakang' && !$result['sudah_kakang']) {
                    $result['sudah_kakang'] = true;
                    $result['detail_kakang'] = $row;
                    $result['total_suara_user']++;
                } elseif ($kat === 'Senduk' && !$result['sudah_senduk']) {
                    $result['sudah_senduk'] = true;
                    $result['detail_senduk'] = $row;
                    $result['total_suara_user']++;
                }
            }

            return $result;
        } catch (PDOException $e) {
            error_log('[VOTING_USER_STATUS_ERROR] ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * Menyimpan suara pemilih secara atomik dengan transaksi PDO, penguncian baris (FOR UPDATE),
     * validasi waktu server, verifikasi kategori dari DB, serta penanganan aman duplicate key / race condition.
     *
     * @return array{success: bool, code: string, message: string, kategori: ?string, periode_id: ?int}
     */
    public function castVote(int $userId, int $kontestanId, string $requestedKategori): array
    {
        if ($userId <= 0) {
            return [
                'success'    => false,
                'code'       => 'UNAUTHENTICATED',
                'message'    => 'Sesi pengguna tidak valid. Silakan login kembali.',
                'kategori'   => null,
                'periode_id' => null,
            ];
        }

        if ($kontestanId <= 0) {
            return [
                'success'    => false,
                'code'       => 'INVALID_CONTESTANT',
                'message'    => 'ID kontestan tidak valid.',
                'kategori'   => null,
                'periode_id' => null,
            ];
        }

        try {
            return jalankanTransaksi($this->pdo, static function (PDO $conn) use ($userId, $kontestanId, $requestedKategori): array {
                // 1. Verifikasi akun & role pengguna langsung dari database
                $stmtUser = $conn->prepare("
                    SELECT id, role
                    FROM users
                    WHERE id = :id
                    LIMIT 1 FOR UPDATE
                ");
                $stmtUser->execute([':id' => $userId]);
                $dbUser = $stmtUser->fetch();

                if (!is_array($dbUser)) {
                    return [
                        'success'    => false,
                        'code'       => 'USER_NOT_FOUND',
                        'message'    => 'Akun pengguna tidak ditemukan.',
                        'kategori'   => null,
                        'periode_id' => null,
                    ];
                }

                if (($dbUser['role'] ?? '') !== 'pemilih') {
                    return [
                        'success'    => false,
                        'code'       => 'ROLE_FORBIDDEN',
                        'message'    => 'Hanya akun pemilih yang dapat memberikan suara.',
                        'kategori'   => null,
                        'periode_id' => null,
                    ];
                }

                // 2. Verifikasi periode voting aktif & jadwal sesuai waktu server langsung dari database
                $stmtPeriode = $conn->prepare("
                    SELECT id, nama_periode, tanggal_mulai, tanggal_selesai, status
                    FROM periode_voting
                    WHERE status = 'Aktif'
                    ORDER BY tanggal_mulai DESC, id DESC
                    LIMIT 1 FOR UPDATE
                ");
                $stmtPeriode->execute();
                $periode = $stmtPeriode->fetch();

                if (!is_array($periode)) {
                    return [
                        'success'    => false,
                        'code'       => 'NO_ACTIVE_PERIOD',
                        'message'    => 'Voting ditutup karena belum ada periode voting yang aktif saat ini.',
                        'kategori'   => null,
                        'periode_id' => null,
                    ];
                }

                $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
                $now = new DateTimeImmutable('now', $tz);
                $mulai = new DateTimeImmutable((string) $periode['tanggal_mulai'], $tz);
                $selesai = new DateTimeImmutable((string) $periode['tanggal_selesai'], $tz);

                if ($now < $mulai) {
                    return [
                        'success'    => false,
                        'code'       => 'PERIOD_NOT_STARTED',
                        'message'    => 'Masa voting belum dimulai. Pemilihan baru dibuka pada ' . formatTanggalIndonesia((string) $periode['tanggal_mulai'], true) . '.',
                        'kategori'   => null,
                        'periode_id' => (int) $periode['id'],
                    ];
                }

                if ($now > $selesai) {
                    return [
                        'success'    => false,
                        'code'       => 'PERIOD_ENDED',
                        'message'    => 'Masa voting telah berakhir pada ' . formatTanggalIndonesia((string) $periode['tanggal_selesai'], true) . '.',
                        'kategori'   => null,
                        'periode_id' => (int) $periode['id'],
                    ];
                }

                $periodeId = (int) $periode['id'];

                // 3. Verifikasi kontestan aktif dan kategori kontestan dari database
                $stmtKontestan = $conn->prepare("
                    SELECT id, nomor_peserta, nama, kategori, status
                    FROM kontestan
                    WHERE id = :id
                    LIMIT 1 FOR UPDATE
                ");
                $stmtKontestan->execute([':id' => $kontestanId]);
                $kontestan = $stmtKontestan->fetch();

                if (!is_array($kontestan)) {
                    return [
                        'success'    => false,
                        'code'       => 'CONTESTANT_NOT_FOUND',
                        'message'    => 'Kontestan yang dipilih tidak ditemukan.',
                        'kategori'   => null,
                        'periode_id' => $periodeId,
                    ];
                }

                if (($kontestan['status'] ?? '') !== 'Aktif') {
                    return [
                        'success'    => false,
                        'code'       => 'CONTESTANT_INACTIVE',
                        'message'    => 'Kontestan berstatus Nonaktif dan tidak dapat menerima suara.',
                        'kategori'   => null,
                        'periode_id' => $periodeId,
                    ];
                }

                $dbKategori = (string) ($kontestan['kategori'] ?? '');
                if (!in_array($dbKategori, ['Kakang', 'Senduk'], true)) {
                    return [
                        'success'    => false,
                        'code'       => 'INVALID_CATEGORY',
                        'message'    => 'Kategori kontestan pada sistem tidak valid.',
                        'kategori'   => null,
                        'periode_id' => $periodeId,
                    ];
                }

                if ($requestedKategori !== $dbKategori) {
                    return [
                        'success'    => false,
                        'code'       => 'CATEGORY_MISMATCH',
                        'message'    => "Kategori tidak sesuai. Kontestan {$kontestan['nama']} terdaftar di kategori {$dbKategori}, bukan {$requestedKategori}.",
                        'kategori'   => $dbKategori,
                        'periode_id' => $periodeId,
                    ];
                }

                // 4. Cek apakah pemilih sudah memberikan suara untuk kategori & periode ini
                $stmtExisting = $conn->prepare("
                    SELECT id
                    FROM voting
                    WHERE user_id = :user_id
                      AND periode_id = :periode_id
                      AND kategori = :kategori
                    LIMIT 1 FOR UPDATE
                ");
                $stmtExisting->execute([
                    ':user_id'    => $userId,
                    ':periode_id' => $periodeId,
                    ':kategori'   => $dbKategori,
                ]);

                if ($stmtExisting->fetch() !== false) {
                    return [
                        'success'    => false,
                        'code'       => 'ALREADY_VOTED',
                        'message'    => "Anda sudah menggunakan hak suara untuk kategori {$dbKategori} pada periode ini. Suara yang masuk bersifat permanen dan tidak dapat diubah.",
                        'kategori'   => $dbKategori,
                        'periode_id' => $periodeId,
                    ];
                }

                // 5. Simpan suara dengan nilai terverifikasi (user_id session, periode_id DB, kategori DB, waktu server)
                $stmtInsert = $conn->prepare("
                    INSERT INTO voting (user_id, kontestan_id, periode_id, kategori, created_at)
                    VALUES (:user_id, :kontestan_id, :periode_id, :kategori, :created_at)
                ");
                $stmtInsert->execute([
                    ':user_id'      => $userId,
                    ':kontestan_id' => (int) $kontestan['id'],
                    ':periode_id'   => $periodeId,
                    ':kategori'     => $dbKategori,
                    ':created_at'   => $now->format('Y-m-d H:i:s'),
                ]);

                return [
                    'success'    => true,
                    'code'       => 'VOTE_RECORDED',
                    'message'    => "Terima kasih! Suara Anda untuk kategori {$dbKategori} kepada {$kontestan['nomor_peserta']} — {$kontestan['nama']} berhasil disimpan secara permanen.",
                    'kategori'   => $dbKategori,
                    'periode_id' => $periodeId,
                ];
            });
        } catch (PDOException $e) {
            $sqlState = (string) $e->getCode();
            $mysqlCode = (int) ($e->errorInfo[1] ?? 0);

            if ($sqlState === '23000' || $mysqlCode === 1062) {
                error_log('[VOTING_DUPLICATE_KEY] user=' . $userId . ' kontestan=' . $kontestanId);
                return [
                    'success'    => false,
                    'code'       => 'DUPLICATE_VOTE',
                    'message'    => 'Suara Anda untuk kategori ini pada periode berjalan sudah tercatat. Pengiriman suara ganda ditolak.',
                    'kategori'   => null,
                    'periode_id' => null,
                ];
            }

            if ($sqlState === '40001' || $mysqlCode === 1213 || $mysqlCode === 1205) {
                error_log('[VOTING_CONCURRENT_LOCK] user=' . $userId . ' msg=' . $e->getMessage());
                return [
                    'success'    => false,
                    'code'       => 'CONCURRENT_CONFLICT',
                    'message'    => 'Terdeteksi pengiriman suara bersamaan. Silakan muat ulang halaman untuk memeriksa status suara Anda.',
                    'kategori'   => null,
                    'periode_id' => null,
                ];
            }

            error_log('[VOTING_CAST_ERROR] ' . $e->getMessage());
            return [
                'success'    => false,
                'code'       => 'SYSTEM_ERROR',
                'message'    => 'Terjadi gangguan saat menyimpan suara Anda. Silakan coba beberapa saat lagi.',
                'kategori'   => null,
                'periode_id' => null,
            ];
        } catch (Throwable $e) {
            error_log('[VOTING_UNEXPECTED_ERROR] ' . $e->getMessage());
            return [
                'success'    => false,
                'code'       => 'SYSTEM_ERROR',
                'message'    => 'Terjadi kesalahan tak terduga saat memproses suara.',
                'kategori'   => null,
                'periode_id' => null,
            ];
        }
    }
}

