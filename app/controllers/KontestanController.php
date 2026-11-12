<?php
declare(strict_types=1);

/**
 * Controller Kontestan (Halaman Publik: Beranda, Daftar Kontestan, Detail Kontestan)
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

require_once dirname(__DIR__) . '/models/Kontestan.php';
require_once dirname(__DIR__) . '/models/PeriodeVoting.php';
require_once dirname(__DIR__) . '/models/Voting.php';

class KontestanController
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
     * Mengambil data untuk halaman Beranda / Landing Page.
     */
    public function getBerandaData(): array
    {
        $activePeriod = $this->periodeModel->getActivePeriod();
        if ($activePeriod === null) {
            $allPeriods = $this->periodeModel->getAllPeriods();
            $displayPeriod = $allPeriods[0] ?? null;
        } else {
            $displayPeriod = $activePeriod;
        }

        $periodeId = isset($displayPeriod['id']) ? (int) $displayPeriod['id'] : null;

        return [
            'periode_aktif'       => $activePeriod,
            'periode_tampil'      => $displayPeriod,
            'featured_kontestan'  => $this->kontestanModel->getFeatured(6),
            'statistik_kontestan' => $this->kontestanModel->getActiveStats(),
            'statistik_suara'     => $this->votingModel->getTotalSuaraByPeriode($periodeId),
        ];
    }

    /**
     * Mengambil data daftar kontestan publik dengan filter kategori, pencarian, dan pagination.
     */
    public function getDaftarKontestan(array $queryParams): array
    {
        $kategoriRaw = sanitizeText($queryParams['kategori'] ?? '');
        $kategori = in_array($kategoriRaw, ['Kakang', 'Senduk'], true) ? $kategoriRaw : null;

        $cari = sanitizeText($queryParams['cari'] ?? '');
        if (mb_strlen($cari, 'UTF-8') > 100) {
            $cari = mb_substr($cari, 0, 100, 'UTF-8');
        }

        $halamanRaw = $queryParams['halaman'] ?? '1';
        $halaman = isPositiveInt($halamanRaw) ? (int) $halamanRaw : 1;
        $perPage = defined('PAGINATION_PER_PAGE') ? (int) PAGINATION_PER_PAGE : 9;

        $paginated = $this->kontestanModel->getPaginatedPublic($kategori, $cari, $halaman, $perPage);

        return [
            'items'       => $paginated['items'],
            'total'       => $paginated['total'],
            'page'        => $paginated['page'],
            'per_page'    => $paginated['per_page'],
            'total_pages' => $paginated['total_pages'],
            'kategori'    => $kategori ?? '',
            'cari'        => $cari,
            'periode'     => $this->periodeModel->getActivePeriod(),
        ];
    }

    /**
     * Mengambil detail lengkap kontestan aktif berdasarkan query ID.
     */
    public function getDetailKontestan(mixed $idParam): array
    {
        if (!isPositiveInt($idParam)) {
            return [
                'found'     => false,
                'kontestan' => null,
                'periode'   => $this->periodeModel->getActivePeriod(),
            ];
        }

        $kontestan = $this->kontestanModel->findActiveById((int) $idParam);

        return [
            'found'     => $kontestan !== null,
            'kontestan' => $kontestan,
            'periode'   => $this->periodeModel->getActivePeriod(),
        ];
    }
}

