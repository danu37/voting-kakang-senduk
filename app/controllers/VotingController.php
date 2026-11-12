<?php
declare(strict_types=1);

/**
 * Controller Voting Pemilih
 * Mengelola tampilan bilik suara serta validasi & penyimpanan suara Kakang/Senduk per periode.
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

require_once dirname(__DIR__) . '/models/Kontestan.php';
require_once dirname(__DIR__) . '/models/PeriodeVoting.php';
require_once dirname(__DIR__) . '/models/Voting.php';

class VotingController
{
    private Kontestan $kontestanModel;
    private PeriodeVoting $periodeModel;
    private Voting $votingModel;

    public function __construct(PDO $pdo)
    {
        $this->kontestanModel = new Kontestan($pdo);
        $this->periodeModel = new PeriodeVoting($pdo);
        $this->votingModel = new Voting($pdo);
    }

    /**
     * Menyiapkan seluruh data untuk halaman bilik suara (pages/voting.php).
     */
    public function getVotingPageData(array $sessionUser, array $queryParams): array
    {
        $activePeriod = $this->periodeModel->getActivePeriod();
        $displayPeriod = $activePeriod;
        if ($displayPeriod === null) {
            $allPeriods = $this->periodeModel->getAllPeriods();
            $displayPeriod = $allPeriods[0] ?? null;
        }

        $bisaVoting = ($activePeriod !== null && !empty($activePeriod['bisa_voting']));
        $userId = (int) ($sessionUser['id'] ?? 0);
        $isPemilihRole = (($sessionUser['role'] ?? '') === 'pemilih');

        $statusVoting = [
            'sudah_kakang'     => false,
            'sudah_senduk'     => false,
            'detail_kakang'    => null,
            'detail_senduk'    => null,
            'total_suara_user' => 0,
        ];
        if ($isPemilihRole && $activePeriod !== null) {
            $statusVoting = $this->votingModel->getUserVotingStatusInPeriode($userId, (int) $activePeriod['id']);
        }

        $requestedKategori = sanitizeText($queryParams['kategori'] ?? '');
        $preselectedKontestan = null;
        $kontestanNotice = null;

        if (isset($queryParams['kontestan_id']) && trim((string) $queryParams['kontestan_id']) !== '') {
            if (!isPositiveInt($queryParams['kontestan_id'])) {
                $kontestanNotice = 'ID kontestan yang dipilih tidak valid.';
            } else {
                $rawKontestan = $this->kontestanModel->findById((int) $queryParams['kontestan_id']);
                if ($rawKontestan === null) {
                    $kontestanNotice = 'Kontestan yang dipilih tidak ditemukan.';
                } elseif (($rawKontestan['status'] ?? '') !== 'Aktif') {
                    $kontestanNotice = 'Kontestan "' . (string) $rawKontestan['nama'] . '" berstatus Nonaktif dan tidak boleh menerima voting.';
                } elseif (
                    in_array($requestedKategori, ['Kakang', 'Senduk'], true)
                    && (string) ($rawKontestan['kategori'] ?? '') !== $requestedKategori
                ) {
                    $kontestanNotice = 'Kategori tidak sesuai: kontestan "' . (string) $rawKontestan['nama'] . '" terdaftar pada kategori ' . (string) $rawKontestan['kategori'] . '.';
                } else {
                    $preselectedKontestan = $rawKontestan;
                }
            }
        }

        if ($preselectedKontestan !== null) {
            $selectedKategori = (string) $preselectedKontestan['kategori'];
        } elseif (in_array($requestedKategori, ['Kakang', 'Senduk'], true)) {
            $selectedKategori = $requestedKategori;
        } elseif ($statusVoting['sudah_kakang'] && !$statusVoting['sudah_senduk']) {
            $selectedKategori = 'Senduk';
        } else {
            $selectedKategori = 'Kakang';
        }

        $kakangList = $this->kontestanModel->getActiveByKategori('Kakang');
        $sendukList = $this->kontestanModel->getActiveByKategori('Senduk');
        $activeList = $selectedKategori === 'Senduk' ? $sendukList : $kakangList;

        $alreadyVotedSelected = $selectedKategori === 'Senduk'
            ? !empty($statusVoting['sudah_senduk'])
            : !empty($statusVoting['sudah_kakang']);

        $selectedKontestanDetail = $selectedKategori === 'Senduk'
            ? ($statusVoting['detail_senduk'] ?? null)
            : ($statusVoting['detail_kakang'] ?? null);

        $preselectedId = ($preselectedKontestan !== null && !$alreadyVotedSelected)
            ? (int) $preselectedKontestan['id']
            : 0;

        return [
            'periode_aktif'             => $activePeriod,
            'periode_tampil'            => $displayPeriod,
            'bisa_voting'               => $bisaVoting,
            'is_pemilih'                => $isPemilihRole,
            'status_voting'             => $statusVoting,
            'selected_kategori'         => $selectedKategori,
            'already_voted_selected'    => $alreadyVotedSelected,
            'selected_kontestan_detail' => $selectedKontestanDetail,
            'preselected_id'            => $preselectedId,
            'kontestan_notice'          => $kontestanNotice,
            'list_kakang'               => $kakangList,
            'list_senduk'               => $sendukList,
            'list_aktif'                => $activeList,
        ];
    }

    /**
     * Memvalidasi & menyimpan suara pemilih.
     * user_id, periode_id, dan kategori divalidasi sepenuhnya di server dari session & database.
     *
     * @return array{success: bool, code: string, message: string, redirect_path: string}
     */
    public function submitVote(array $sessionUser, array $postData): array
    {
        $redirectKategori = in_array(sanitizeText($postData['kategori'] ?? ''), ['Kakang', 'Senduk'], true)
            ? sanitizeText($postData['kategori'])
            : 'Kakang';
        $defaultRedirect = 'pages/voting.php?kategori=' . urlencode($redirectKategori);

        if (!verifyCsrfToken(is_string($postData[CSRF_TOKEN_KEY] ?? null) ? $postData[CSRF_TOKEN_KEY] : null)) {
            return [
                'success'       => false,
                'code'          => 'INVALID_CSRF',
                'message'       => 'Token keamanan tidak valid atau sesi kedaluwarsa. Silakan muat ulang halaman dan coba lagi.',
                'redirect_path' => $defaultRedirect,
            ];
        }

        if (($sessionUser['role'] ?? '') !== 'pemilih') {
            return [
                'success'       => false,
                'code'          => 'ROLE_FORBIDDEN',
                'message'       => 'Hanya akun pemilih terdaftar yang dapat mengirimkan suara.',
                'redirect_path' => $defaultRedirect,
            ];
        }

        $kategoriInput = sanitizeText($postData['kategori'] ?? '');
        if (!in_array($kategoriInput, ['Kakang', 'Senduk'], true)) {
            return [
                'success'       => false,
                'code'          => 'INVALID_CATEGORY',
                'message'       => 'Kategori pemilihan tidak valid.',
                'redirect_path' => 'pages/voting.php',
            ];
        }

        if (!isPositiveInt($postData['kontestan_id'] ?? null)) {
            return [
                'success'       => false,
                'code'          => 'INVALID_CONTESTANT',
                'message'       => 'Silakan pilih salah satu kontestan yang valid sebelum mengirimkan suara.',
                'redirect_path' => $defaultRedirect,
            ];
        }

        $userId = (int) ($sessionUser['id'] ?? 0);
        $kontestanId = (int) $postData['kontestan_id'];

        $result = $this->votingModel->castVote($userId, $kontestanId, $kategoriInput);

        if (!$result['success']) {
            $failRedirect = !empty($result['kategori'])
                ? 'pages/voting.php?kategori=' . urlencode((string) $result['kategori'])
                : $defaultRedirect;

            return [
                'success'       => false,
                'code'          => (string) ($result['code'] ?? 'VOTE_REJECTED'),
                'message'       => (string) ($result['message'] ?? 'Voting tidak dapat diproses.'),
                'redirect_path' => $failRedirect,
            ];
        }

        // Jika masih ada kategori lain yang belum dipilih pada periode ini, arahkan ke tab tersebut
        $nextRedirect = $defaultRedirect;
        $recordedKat = (string) ($result['kategori'] ?? $kategoriInput);
        $recordedPeriodeId = (int) ($result['periode_id'] ?? 0);
        if ($recordedPeriodeId > 0) {
            $status = $this->votingModel->getUserVotingStatusInPeriode($userId, $recordedPeriodeId);
            if ($recordedKat === 'Kakang' && empty($status['sudah_senduk'])) {
                $nextRedirect = 'pages/voting.php?kategori=Senduk';
            } elseif ($recordedKat === 'Senduk' && empty($status['sudah_kakang'])) {
                $nextRedirect = 'pages/voting.php?kategori=Kakang';
            }
        }

        return [
            'success'       => true,
            'code'          => 'VOTE_RECORDED',
            'message'       => (string) $result['message'],
            'redirect_path' => $nextRedirect,
        ];
    }
}

