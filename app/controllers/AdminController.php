<?php
declare(strict_types=1);

/**
 * Controller Administrator
 * Mengelola Dashboard, CRUD Kontestan, Manajemen Pemilih, Manajemen Periode, dan Hasil Voting.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

require_once dirname(__DIR__) . '/models/Kontestan.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/models/PeriodeVoting.php';
require_once dirname(__DIR__) . '/models/Voting.php';
require_once dirname(__DIR__, 2) . '/proses/upload-foto.php';

class AdminController
{
    private Kontestan $kontestanModel;
    private User $userModel;
    private PeriodeVoting $periodeModel;
    private Voting $votingModel;

    public function __construct(PDO $pdo)
    {
        $this->kontestanModel = new Kontestan($pdo);
        $this->userModel = new User($pdo);
        $this->periodeModel = new PeriodeVoting($pdo);
        $this->votingModel = new Voting($pdo);
    }

    /**
     * Mengambil data lengkap untuk Dashboard Administrator.
     */
    public function getDashboardData(): array
    {
        $kontestanCounts = $this->kontestanModel->getAdminCounts();
        $totalPemilih = $this->userModel->countPemilih();

        $periodeAktif = $this->periodeModel->getActivePeriod();
        $daftarPeriode = $this->periodeModel->getAllPeriods();
        $periodeAcuan = $periodeAktif ?? ($daftarPeriode[0] ?? null);
        $periodeId = isset($periodeAcuan['id']) ? (int) $periodeAcuan['id'] : null;

        $overallTotals = $this->votingModel->getOverallTotals();
        $suaraPeriode = $periodeId !== null
            ? $this->votingModel->getTotalSuaraByPeriode($periodeId)
            : $overallTotals;

        $klasemenKakang = $this->votingModel->getKlasemenByKategori('Kakang', $periodeId);
        $klasemenSenduk = $this->votingModel->getKlasemenByKategori('Senduk', $periodeId);

        $topKakang = null;
        if (!empty($klasemenKakang['items']) && (int) ($klasemenKakang['items'][0]['jumlah_suara'] ?? 0) > 0) {
            $topKakang = $klasemenKakang['items'][0];
        }

        $topSenduk = null;
        if (!empty($klasemenSenduk['items']) && (int) ($klasemenSenduk['items'][0]['jumlah_suara'] ?? 0) > 0) {
            $topSenduk = $klasemenSenduk['items'][0];
        }

        return [
            'total_kontestan'    => $kontestanCounts['total'],
            'kontestan_aktif'    => $kontestanCounts['aktif'],
            'total_kakang'       => $kontestanCounts['total_kakang'],
            'total_senduk'       => $kontestanCounts['total_senduk'],
            'total_pemilih'      => $totalPemilih,
            'total_suara_kakang' => $suaraPeriode['kakang'],
            'total_suara_senduk' => $suaraPeriode['senduk'],
            'total_suara_semua'  => $suaraPeriode['total'],
            'overall_totals'     => $overallTotals,
            'periode_aktif'      => $periodeAktif,
            'periode_acuan'      => $periodeAcuan,
            'klasemen_kakang'    => $klasemenKakang,
            'klasemen_senduk'    => $klasemenSenduk,
            'top_kakang'         => $topKakang,
            'top_senduk'         => $topSenduk,
            'aktivitas_terbaru'  => $this->votingModel->getRecentActivities(8),
        ];
    }

    /**
     * Mengambil daftar kontestan untuk tabel Manajemen Kontestan.
     */
    public function getDaftarKontestan(array $queryParams): array
    {
        $katRaw = sanitizeText($queryParams['kategori'] ?? '');
        $kategori = in_array($katRaw, ['Kakang', 'Senduk'], true) ? $katRaw : null;

        $statusRaw = sanitizeText($queryParams['status'] ?? '');
        $status = in_array($statusRaw, ['Aktif', 'Nonaktif'], true) ? $statusRaw : null;

        $cari = sanitizeText($queryParams['cari'] ?? '');
        $halamanRaw = $queryParams['halaman'] ?? '1';
        $halaman = isPositiveInt($halamanRaw) ? (int) $halamanRaw : 1;

        $result = $this->kontestanModel->getPaginatedAdmin($kategori, $status, $cari, $halaman, 10);

        return array_merge($result, [
            'kategori' => $kategori ?? '',
            'status'   => $status ?? '',
            'cari'     => $cari,
        ]);
    }

    /**
     * Mengambil data kontestan by ID untuk halaman edit.
     */
    public function getKontestanById(mixed $idParam): ?array
    {
        if (!isPositiveInt($idParam)) {
            return null;
        }

        return $this->kontestanModel->findById((int) $idParam);
    }

    /**
     * Mengambil jumlah suara yang sudah diterima kontestan tertentu.
     */
    public function getKontestanVoteCount(int $kontestanId): int
    {
        if ($kontestanId <= 0) {
            return 0;
        }

        return $this->kontestanModel->countVotes($kontestanId);
    }

    /**
     * Menyimpan kontestan baru beserta validasi dan upload foto.
     */
    public function storeKontestan(array $postData, array $files): array
    {
        if (!verifyCsrfToken(is_string($postData[CSRF_TOKEN_KEY] ?? null) ? $postData[CSRF_TOKEN_KEY] : null)) {
            $sanitized = $this->validateKontestanInput($postData, null, false);
            return [
                'success' => false,
                'errors'  => ['Token keamanan tidak valid. Silakan muat ulang halaman.'],
                'old'     => $sanitized['old'],
            ];
        }

        $parsed = $this->validateKontestanInput($postData, null, true);
        $old = $parsed['old'];
        $errors = $parsed['errors'];

        $uploadedFile = null;
        if (empty($errors)) {
            $uploadResult = prosesUploadFotoKontestan($files['foto'] ?? [], true);
            if (!$uploadResult['success']) {
                $errors[] = (string) $uploadResult['error'];
            } else {
                $uploadedFile = $uploadResult['filename'];
            }
        }

        if (!empty($errors)) {
            if ($uploadedFile !== null) {
                hapusFotoKontestan($uploadedFile);
            }
            return [
                'success' => false,
                'errors'  => $errors,
                'old'     => $old,
            ];
        }

        try {
            $dataInsert = $parsed['clean'];
            $dataInsert['foto'] = $uploadedFile;
            $newId = $this->kontestanModel->create($dataInsert);

            return [
                'success' => true,
                'id'      => $newId,
                'errors'  => [],
                'old'     => [],
            ];
        } catch (PDOException $e) {
            if ($uploadedFile !== null) {
                hapusFotoKontestan($uploadedFile);
            }

            if ((string) $e->getCode() === '23000') {
                return [
                    'success' => false,
                    'errors'  => ['Nomor peserta sudah digunakan oleh kontestan lain.'],
                    'old'     => $old,
                ];
            }

            error_log('[ADMIN_STORE_KONTESTAN_ERROR] ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['Gagal menyimpan data kontestan ke database.'],
                'old'     => $old,
            ];
        } catch (Throwable $e) {
            if ($uploadedFile !== null) {
                hapusFotoKontestan($uploadedFile);
            }

            error_log('[ADMIN_STORE_KONTESTAN_ERROR] ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['Terjadi gangguan sistem saat menambahkan kontestan.'],
                'old'     => $old,
            ];
        }
    }

    /**
     * Memperbarui data kontestan dan menghapus foto lama setelah update DB berhasil.
     */
    public function updateKontestan(int $id, array $postData, array $files): array
    {
        if (!verifyCsrfToken(is_string($postData[CSRF_TOKEN_KEY] ?? null) ? $postData[CSRF_TOKEN_KEY] : null)) {
            $sanitized = $this->validateKontestanInput($postData, $id, false);
            return [
                'success' => false,
                'errors'  => ['Token keamanan tidak valid. Silakan muat ulang halaman.'],
                'old'     => $sanitized['old'],
            ];
        }

        $existing = $this->kontestanModel->findById($id);
        if ($existing === null) {
            return [
                'success' => false,
                'errors'  => ['Data kontestan tidak ditemukan.'],
                'old'     => [],
            ];
        }

        $parsed = $this->validateKontestanInput($postData, $id, true);
        $old = $parsed['old'];
        $errors = $parsed['errors'];

        if (($existing['kategori'] ?? '') !== ($parsed['clean']['kategori'] ?? '')) {
            $jumlahSuara = $this->kontestanModel->countVotes($id);
            if ($jumlahSuara > 0) {
                $errors[] = "Kategori kontestan tidak dapat diubah dari {$existing['kategori']} ke {$parsed['clean']['kategori']} karena kontestan sudah memiliki {$jumlahSuara} suara tercatat.";
                $old['kategori'] = (string) ($existing['kategori'] ?? $old['kategori']);
            }
        }

        $newUploadedFile = null;
        if (empty($errors)) {
            // Foto opsional saat edit; jika tidak diunggah maka pertahankan foto lama
            $uploadResult = prosesUploadFotoKontestan($files['foto'] ?? [], false);
            if (!$uploadResult['success']) {
                $errors[] = (string) $uploadResult['error'];
            } else {
                $newUploadedFile = $uploadResult['filename'];
            }
        }

        if (!empty($errors)) {
            if ($newUploadedFile !== null) {
                hapusFotoKontestan($newUploadedFile);
            }
            return [
                'success' => false,
                'errors'  => $errors,
                'old'     => $old,
            ];
        }

        try {
            $dataUpdate = $parsed['clean'];
            $dataUpdate['foto'] = $newUploadedFile ?? ($existing['foto'] ?? null);

            $updateRes = $this->kontestanModel->update($id, $dataUpdate);
            if (empty($updateRes['success'])) {
                if ($newUploadedFile !== null) {
                    hapusFotoKontestan($newUploadedFile);
                }
                return [
                    'success' => false,
                    'errors'  => [(string) ($updateRes['error'] ?? 'Gagal memperbarui data kontestan.')],
                    'old'     => $old,
                ];
            }

            // Hapus file foto lama hanya setelah pembaruan database berhasil
            if ($newUploadedFile !== null && !empty($existing['foto']) && $existing['foto'] !== $newUploadedFile) {
                hapusFotoKontestan((string) $existing['foto']);
            }

            return [
                'success' => true,
                'errors'  => [],
                'old'     => [],
            ];
        } catch (PDOException $e) {
            if ($newUploadedFile !== null) {
                hapusFotoKontestan($newUploadedFile);
            }

            if ((string) $e->getCode() === '23000') {
                return [
                    'success' => false,
                    'errors'  => ['Nomor peserta sudah digunakan oleh kontestan lain.'],
                    'old'     => $old,
                ];
            }

            error_log('[ADMIN_UPDATE_KONTESTAN_ERROR] ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['Gagal memperbarui data kontestan.'],
                'old'     => $old,
            ];
        } catch (Throwable $e) {
            if ($newUploadedFile !== null) {
                hapusFotoKontestan($newUploadedFile);
            }

            error_log('[ADMIN_UPDATE_KONTESTAN_ERROR] ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['Terjadi gangguan sistem saat memperbarui kontestan.'],
                'old'     => $old,
            ];
        }
    }

    /**
     * Menghapus kontestan secara aman (menolak jika sudah memiliki suara).
     */
    public function deleteKontestan(array $postData): array
    {
        if (!verifyCsrfToken(is_string($postData[CSRF_TOKEN_KEY] ?? null) ? $postData[CSRF_TOKEN_KEY] : null)) {
            return ['success' => false, 'message' => 'Token keamanan tidak valid.'];
        }

        if (!isPositiveInt($postData['id'] ?? null)) {
            return ['success' => false, 'message' => 'ID kontestan tidak valid.'];
        }

        $id = (int) $postData['id'];

        try {
            $result = $this->kontestanModel->deleteSafely($id);
            if (!$result['deleted']) {
                return ['success' => false, 'message' => (string) ($result['reason'] ?? 'Gagal menghapus kontestan.')];
            }

            if (!empty($result['foto'])) {
                hapusFotoKontestan((string) $result['foto']);
            }

            return ['success' => true, 'message' => 'Data kontestan berhasil dihapus.'];
        } catch (Throwable $e) {
            error_log('[ADMIN_DELETE_KONTESTAN_ERROR] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal menghapus kontestan karena relasi data atau gangguan sistem.'];
        }
    }

    /**
     * Mengarsipkan (Nonaktif) atau mengaktifkan kembali kontestan secara aman.
     */
    public function toggleKontestanStatus(array $postData): array
    {
        if (!verifyCsrfToken(is_string($postData[CSRF_TOKEN_KEY] ?? null) ? $postData[CSRF_TOKEN_KEY] : null)) {
            return ['success' => false, 'message' => 'Token keamanan tidak valid. Silakan muat ulang halaman.'];
        }

        if (!isPositiveInt($postData['id'] ?? null)) {
            return ['success' => false, 'message' => 'ID kontestan tidak valid.'];
        }

        $targetStatus = sanitizeText($postData['target_status'] ?? '');
        if (!isInEnum($targetStatus, ['Aktif', 'Nonaktif'])) {
            return ['success' => false, 'message' => 'Status target kontestan tidak valid.'];
        }

        $id = (int) $postData['id'];

        try {
            $res = $this->kontestanModel->updateStatus($id, $targetStatus);
            if (!$res['success']) {
                return ['success' => false, 'message' => (string) ($res['reason'] ?? 'Gagal memperbarui status kontestan.')];
            }

            $identitas = trim((string) ($res['nomor_peserta'] ?? '') . ' — ' . (string) ($res['nama'] ?? ''));
            if ($targetStatus === 'Nonaktif') {
                return [
                    'success' => true,
                    'message' => "Kontestan {$identitas} berhasil diarsipkan (status Nonaktif). Riwayat suara tetap tersimpan aman.",
                ];
            }

            return [
                'success' => true,
                'message' => "Kontestan {$identitas} berhasil diaktifkan kembali.",
            ];
        } catch (Throwable $e) {
            error_log('[ADMIN_TOGGLE_KONTESTAN_STATUS_ERROR] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi gangguan sistem saat memperbarui status kontestan.'];
        }
    }

    /**
     * Mengambil data Manajemen Pemilih dengan filter periode, status voting, pencarian, dan pagination.
     */
    public function getDaftarPemilih(array $queryParams): array
    {
        $daftarPeriode = $this->periodeModel->getAllPeriods();
        $periodeAktif = $this->periodeModel->getActivePeriod();

        $selectedPeriode = null;
        $periodeIdParam = $queryParams['periode_id'] ?? null;
        if (isPositiveInt($periodeIdParam)) {
            $selectedPeriode = $this->periodeModel->findById((int) $periodeIdParam);
        }
        if ($selectedPeriode === null) {
            $selectedPeriode = $periodeAktif ?? ($daftarPeriode[0] ?? null);
        }

        $periodeId = isset($selectedPeriode['id']) ? (int) $selectedPeriode['id'] : null;
        $cari = sanitizeText($queryParams['cari'] ?? '');

        $allowedStatusFilters = ['sudah_kakang', 'sudah_senduk', 'sudah_lengkap', 'belum_memilih', 'belum_lengkap'];
        $statusVotingRaw = sanitizeText($queryParams['status_voting'] ?? '');
        $statusVoting = in_array($statusVotingRaw, $allowedStatusFilters, true) ? $statusVotingRaw : '';

        $halamanRaw = $queryParams['halaman'] ?? '1';
        $halaman = isPositiveInt($halamanRaw) ? (int) $halamanRaw : 1;

        $paginated = $this->userModel->getPemilihWithVotingStatus($periodeId, $cari, $halaman, 12, $statusVoting);

        return array_merge($paginated, [
            'daftar_periode'   => $daftarPeriode,
            'periode_terpilih' => $selectedPeriode,
            'cari'             => $cari,
            'status_voting'    => $statusVoting,
        ]);
    }

    /**
     * Mengambil detail lengkap satu akun pemilih tanpa menyertakan password hash.
     */
    public function getDetailPemilih(mixed $idParam, mixed $periodeIdParam = null): ?array
    {
        if (!isPositiveInt($idParam)) {
            return null;
        }

        $daftarPeriode = $this->periodeModel->getAllPeriods();
        $periodeAktif = $this->periodeModel->getActivePeriod();
        $selectedPeriode = null;

        if (isPositiveInt($periodeIdParam)) {
            $selectedPeriode = $this->periodeModel->findById((int) $periodeIdParam);
        }
        if ($selectedPeriode === null) {
            $selectedPeriode = $periodeAktif ?? ($daftarPeriode[0] ?? null);
        }

        $periodeId = isset($selectedPeriode['id']) ? (int) $selectedPeriode['id'] : null;
        $detail = $this->userModel->findPemilihDetailById((int) $idParam, $periodeId);

        if ($detail === null) {
            return null;
        }

        return array_merge($detail, [
            'periode_terpilih' => $selectedPeriode,
        ]);
    }

    /**
     * Menghapus akun pemilih secara aman (wajib POST, CSRF, dan 0 riwayat voting).
     */
    public function deletePemilih(array $postData): array
    {
        if (!verifyCsrfToken(is_string($postData[CSRF_TOKEN_KEY] ?? null) ? $postData[CSRF_TOKEN_KEY] : null)) {
            return ['success' => false, 'message' => 'Token keamanan tidak valid. Silakan muat ulang halaman.'];
        }

        if (!isPositiveInt($postData['id'] ?? null)) {
            return ['success' => false, 'message' => 'ID pemilih tidak valid.'];
        }

        $id = (int) $postData['id'];

        try {
            $res = $this->userModel->deletePemilihSafely($id);
            if (!$res['deleted']) {
                return ['success' => false, 'message' => (string) ($res['reason'] ?? 'Gagal menghapus akun pemilih.')];
            }

            return [
                'success' => true,
                'message' => 'Akun pemilih "' . (string) ($res['nama'] ?? '') . '" berhasil dihapus.',
            ];
        } catch (Throwable $e) {
            error_log('[ADMIN_DELETE_PEMILIH_ERROR] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal menghapus akun pemilih karena relasi data atau gangguan sistem.'];
        }
    }

    /**
     * Mengambil daftar seluruh periode voting beserta ringkasan suara per periode.
     */
    public function getDaftarPeriode(): array
    {
        return $this->periodeModel->getAllPeriods();
    }

    public function getPeriodeById(mixed $idParam): ?array
    {
        if (!isPositiveInt($idParam)) {
            return null;
        }
        return $this->periodeModel->findById((int) $idParam);
    }

    /**
     * Mengambil detail lengkap satu periode voting beserta jumlah suara per periode dan peringkat kontestan.
     */
    public function getPeriodeDetail(mixed $idParam): ?array
    {
        if (!isPositiveInt($idParam)) {
            return null;
        }

        $id = (int) $idParam;
        $periode = $this->periodeModel->findById($id);
        if ($periode === null) {
            return null;
        }

        $hasilKakang = $this->votingModel->getKlasemenByKategori('Kakang', $id);
        $hasilSenduk = $this->votingModel->getKlasemenByKategori('Senduk', $id);

        return [
            'periode'           => $periode,
            'hasil_kakang'      => $hasilKakang,
            'hasil_senduk'      => $hasilSenduk,
            'waktu_server_text' => formatTanggalIndonesia((string) ($periode['waktu_server'] ?? date('Y-m-d H:i:s')), true),
        ];
    }

    /**
     * Menghapus periode voting yang nonaktif dan belum memiliki riwayat suara (wajib POST + CSRF).
     */
    public function deletePeriode(array $postData): array
    {
        if (!verifyCsrfToken(is_string($postData[CSRF_TOKEN_KEY] ?? null) ? $postData[CSRF_TOKEN_KEY] : null)) {
            return ['success' => false, 'message' => 'Token keamanan tidak valid. Silakan muat ulang halaman.'];
        }

        if (!isPositiveInt($postData['id'] ?? null)) {
            return ['success' => false, 'message' => 'ID periode tidak valid.'];
        }

        $id = (int) $postData['id'];

        try {
            $res = $this->periodeModel->deleteSafely($id);
            if (!$res['deleted']) {
                return ['success' => false, 'message' => (string) ($res['reason'] ?? 'Gagal menghapus periode voting.')];
            }

            return [
                'success' => true,
                'message' => 'Periode "' . (string) ($res['nama_periode'] ?? '') . '" berhasil dihapus.',
            ];
        } catch (Throwable $e) {
            error_log('[ADMIN_DELETE_PERIODE_ERROR] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal menghapus periode voting karena relasi data atau gangguan sistem.'];
        }
    }

    public function handlePeriodePost(array $postData): array
    {
        if (!verifyCsrfToken(is_string($postData[CSRF_TOKEN_KEY] ?? null) ? $postData[CSRF_TOKEN_KEY] : null)) {
            return ['success' => false, 'errors' => ['Token keamanan tidak valid. Silakan muat ulang halaman.']];
        }

        $action = sanitizeText($postData['action'] ?? '');

        if ($action === 'toggle_status') {
            if (!isPositiveInt($postData['id'] ?? null)) {
                return ['success' => false, 'errors' => ['ID periode tidak valid.']];
            }
            $targetStatus = sanitizeText($postData['target_status'] ?? '');
            $res = $this->periodeModel->setStatus((int) $postData['id'], $targetStatus);

            if (!$res['success']) {
                return ['success' => false, 'errors' => [(string) $res['error']]];
            }

            $label = $targetStatus === 'Aktif' ? 'diaktifkan' : 'dinonaktifkan';
            return ['success' => true, 'message' => "Periode voting berhasil {$label}."];
        }

        if ($action === 'create' || $action === 'update') {
            $namaPeriode = sanitizeText($postData['nama_periode'] ?? '');
            $mulaiRaw = sanitizeText($postData['tanggal_mulai'] ?? '');
            $selesaiRaw = sanitizeText($postData['tanggal_selesai'] ?? '');
            $status = sanitizeText($postData['status'] ?? 'Nonaktif');

            $errors = [];
            if (!isRequired($namaPeriode) || !minLength($namaPeriode, 3) || !maxLength($namaPeriode, 100)) {
                $errors[] = 'Nama periode wajib diisi (3–100 karakter).';
            }

            $mulaiDt = $this->parseDateTimeObject($mulaiRaw);
            $selesaiDt = $this->parseDateTimeObject($selesaiRaw);

            if ($mulaiDt === null) {
                $errors[] = 'Format tanggal mulai tidak valid.';
            }
            if ($selesaiDt === null) {
                $errors[] = 'Format tanggal selesai tidak valid.';
            }
            if ($mulaiDt !== null && $selesaiDt !== null && $mulaiDt >= $selesaiDt) {
                $errors[] = 'Tanggal mulai harus sebelum tanggal selesai.';
            }
            if (!isInEnum($status, ['Aktif', 'Nonaktif'])) {
                $errors[] = 'Status periode tidak valid.';
            }

            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            $payload = [
                'nama_periode'    => $namaPeriode,
                'tanggal_mulai'   => $mulaiDt->format('Y-m-d H:i:s'),
                'tanggal_selesai' => $selesaiDt->format('Y-m-d H:i:s'),
                'status'          => $status,
            ];

            try {
                if ($action === 'create') {
                    $res = $this->periodeModel->create($payload);
                    if (!$res['success']) {
                        return ['success' => false, 'errors' => [(string) $res['error']]];
                    }
                    return ['success' => true, 'message' => 'Periode voting baru berhasil ditambahkan.'];
                }

                if (!isPositiveInt($postData['id'] ?? null)) {
                    return ['success' => false, 'errors' => ['ID periode tidak valid.']];
                }

                $res = $this->periodeModel->update((int) $postData['id'], $payload);
                if (!$res['success']) {
                    return ['success' => false, 'errors' => [(string) $res['error']]];
                }
                return ['success' => true, 'message' => 'Periode voting berhasil diperbarui.'];
            } catch (Throwable $e) {
                error_log('[ADMIN_PERIODE_ERROR] ' . $e->getMessage());
                return ['success' => false, 'errors' => ['Terjadi gangguan saat menyimpan periode voting.']];
            }
        }

        return ['success' => false, 'errors' => ['Aksi periode tidak dikenal.']];
    }

    /**
     * Mengambil data lengkap Hasil Voting Admin (filter periode, ranking, grafik, statistik kategori).
     */
    public function getHasilVotingData(array $queryParams): array
    {
        $daftarPeriode = $this->periodeModel->getAllPeriods();
        $periodeAktif = $this->periodeModel->getActivePeriod();

        $selectedPeriode = null;
        if (isPositiveInt($queryParams['periode_id'] ?? null)) {
            $selectedPeriode = $this->periodeModel->findById((int) $queryParams['periode_id']);
        }
        if ($selectedPeriode === null) {
            $selectedPeriode = $periodeAktif ?? ($daftarPeriode[0] ?? null);
        }

        $periodeId = isset($selectedPeriode['id']) ? (int) $selectedPeriode['id'] : null;

        $hasilKakang = $this->votingModel->getKlasemenByKategori('Kakang', $periodeId);
        $hasilSenduk = $this->votingModel->getKlasemenByKategori('Senduk', $periodeId);

        $totalKakang = (int) ($hasilKakang['total_suara'] ?? 0);
        $totalSenduk = (int) ($hasilSenduk['total_suara'] ?? 0);
        $totalSemua = $totalKakang + $totalSenduk;

        $itemsKakang = $hasilKakang['items'] ?? [];
        $itemsSenduk = $hasilSenduk['items'] ?? [];

        $pemimpinKakang = (!empty($itemsKakang) && (int) ($itemsKakang[0]['jumlah_suara'] ?? 0) > 0)
            ? $itemsKakang[0]
            : null;
        $pemimpinSenduk = (!empty($itemsSenduk) && (int) ($itemsSenduk[0]['jumlah_suara'] ?? 0) > 0)
            ? $itemsSenduk[0]
            : null;

        $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
        $now = new DateTimeImmutable('now', $tz);
        $nowSql = $now->format('Y-m-d H:i:s');

        $statistikKategori = [
            'Kakang' => [
                'kategori'               => 'Kakang',
                'jumlah_kontestan'       => count($itemsKakang),
                'total_suara'            => $totalKakang,
                'persentase_keseluruhan' => $totalSemua > 0 ? round(($totalKakang / $totalSemua) * 100, 2) : 0.0,
                'pemimpin'               => $pemimpinKakang,
            ],
            'Senduk' => [
                'kategori'               => 'Senduk',
                'jumlah_kontestan'       => count($itemsSenduk),
                'total_suara'            => $totalSenduk,
                'persentase_keseluruhan' => $totalSemua > 0 ? round(($totalSenduk / $totalSemua) * 100, 2) : 0.0,
                'pemimpin'               => $pemimpinSenduk,
            ],
        ];

        return [
            'daftar_periode'        => $daftarPeriode,
            'periode_terpilih'      => $selectedPeriode,
            'hasil_kakang'          => $hasilKakang,
            'hasil_senduk'          => $hasilSenduk,
            'statistik_kategori'    => $statistikKategori,
            'pemimpin_kakang'       => $pemimpinKakang,
            'pemimpin_senduk'       => $pemimpinSenduk,
            'total_kakang'          => $totalKakang,
            'total_senduk'          => $totalSenduk,
            'total_semua'           => $totalSemua,
            'total_pemilih_unik'    => $selectedPeriode !== null ? (int) ($selectedPeriode['total_pemilih_unik'] ?? 0) : 0,
            'waktu_pembaruan'       => formatTanggalIndonesia($nowSql, true),
            'waktu_pembaruan_detik' => formatTanggalIndonesia($nowSql, false) . ', ' . $now->format('H:i:s') . ' WIB',
        ];
    }

    /**
     * Validasi terpusat input kontestan untuk tambah & edit.
     */
    private function validateKontestanInput(array $postData, ?int $excludeId, bool $checkDatabase = true): array
    {
        $nomor = strtoupper(sanitizeText($postData['nomor_peserta'] ?? ''));
        $nama = sanitizeText($postData['nama'] ?? '');
        $kategori = sanitizeText($postData['kategori'] ?? '');
        $instansi = sanitizeText($postData['asal_instansi'] ?? '');
        $tempatLahir = sanitizeText($postData['tempat_lahir'] ?? '');
        $tanggalLahir = sanitizeText($postData['tanggal_lahir'] ?? '');
        $visi = sanitizeText($postData['visi'] ?? '');
        $misi = sanitizeText($postData['misi'] ?? '');
        $prestasi = sanitizeText($postData['prestasi'] ?? '');
        $deskripsi = sanitizeText($postData['deskripsi'] ?? '');
        $status = sanitizeText($postData['status'] ?? 'Aktif');

        $old = [
            'nomor_peserta' => $nomor,
            'nama'          => $nama,
            'kategori'      => $kategori,
            'asal_instansi' => $instansi,
            'tempat_lahir'  => $tempatLahir,
            'tanggal_lahir' => $tanggalLahir,
            'visi'          => $visi,
            'misi'          => $misi,
            'prestasi'      => $prestasi,
            'deskripsi'     => $deskripsi,
            'status'        => $status,
        ];

        $errors = [];

        if (!isRequired($nomor) || !maxLength($nomor, 10)) {
            $errors[] = 'Nomor peserta wajib diisi (maksimal 10 karakter).';
        } elseif (preg_match('/^[A-Za-z0-9\-]+$/', $nomor) !== 1) {
            $errors[] = 'Nomor peserta hanya boleh berisi huruf, angka, atau tanda hubung (-).';
        }

        if (!isRequired($nama) || !minLength($nama, 3) || !maxLength($nama, 100)) {
            $errors[] = 'Nama lengkap wajib diisi (3–100 karakter).';
        }

        if (!isInEnum($kategori, ['Kakang', 'Senduk'])) {
            $errors[] = 'Kategori kontestan wajib dipilih (Kakang atau Senduk).';
        }

        if (!isRequired($instansi) || !maxLength($instansi, 150)) {
            $errors[] = 'Asal instansi wajib diisi (maksimal 150 karakter).';
        }

        if ($tempatLahir !== '' && !maxLength($tempatLahir, 100)) {
            $errors[] = 'Tempat lahir maksimal 100 karakter.';
        }

        if ($tanggalLahir !== '') {
            if (!isValidDate($tanggalLahir, 'Y-m-d')) {
                $errors[] = 'Format tanggal lahir tidak valid.';
            } elseif ($tanggalLahir > date('Y-m-d') || $tanggalLahir < '1945-01-01') {
                $errors[] = 'Tanggal lahir tidak masuk akal atau melebihi tanggal hari ini.';
            }
        }

        if (!isRequired($visi)) {
            $errors[] = 'Visi kontestan wajib diisi.';
        }

        if (!isRequired($misi)) {
            $errors[] = 'Misi kontestan wajib diisi.';
        }

        if (!isRequired($deskripsi)) {
            $errors[] = 'Deskripsi kontestan wajib diisi.';
        }

        if (!isInEnum($status, ['Aktif', 'Nonaktif'])) {
            $errors[] = 'Status kontestan tidak valid.';
        }

        if ($checkDatabase && empty($errors)) {
            try {
                if ($this->kontestanModel->nomorPesertaExists($nomor, $excludeId)) {
                    $errors[] = 'Nomor peserta sudah digunakan oleh kontestan lain.';
                }
            } catch (RuntimeException $e) {
                $errors[] = 'Gagal memvalidasi nomor peserta.';
            }
        }

        return [
            'errors' => $errors,
            'old'    => $old,
            'clean'  => [
                'nomor_peserta' => $nomor,
                'nama'          => $nama,
                'kategori'      => $kategori,
                'asal_instansi' => $instansi,
                'tempat_lahir'  => $tempatLahir !== '' ? $tempatLahir : null,
                'tanggal_lahir' => $tanggalLahir !== '' ? $tanggalLahir : null,
                'visi'          => $visi,
                'misi'          => $misi,
                'prestasi'      => $prestasi !== '' ? $prestasi : null,
                'deskripsi'     => $deskripsi,
                'status'        => $status,
            ],
        ];
    }

    /**
     * Mengonversi dan memvalidasi ketat input datetime-local ('Y-m-d\TH:i' / 'Y-m-d H:i:s').
     * Menolak tanggal kalender yang meluap (misalnya 30 Februari).
     */
    private function parseDateTimeObject(string $raw): ?DateTimeImmutable
    {
        if ($raw === '') {
            return null;
        }

        $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $raw, $tz);
            $lastErr = DateTimeImmutable::getLastErrors();
            $hasWarnings = is_array($lastErr) && (($lastErr['warning_count'] ?? 0) > 0 || ($lastErr['error_count'] ?? 0) > 0);
            if ($dt !== false && !$hasWarnings && $dt->format($fmt) === $raw) {
                return $dt;
            }
        }

        return null;
    }
}

