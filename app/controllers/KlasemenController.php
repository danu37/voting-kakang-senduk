<?php
declare(strict_types=1);

/**
 * Controller Klasemen (Halaman Klasemen Publik Kakang & Senduk + Polling AJAX)
 */

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

require_once dirname(__DIR__) . '/models/PeriodeVoting.php';
require_once dirname(__DIR__) . '/models/Voting.php';

class KlasemenController
{
    private PeriodeVoting $periodeModel;
    private Voting $votingModel;

    public function __construct(PDO $pdo)
    {
        $this->periodeModel = new PeriodeVoting($pdo);
        $this->votingModel = new Voting($pdo);
    }

    /**
     * Mengambil data klasemen terpisah untuk Kakang dan Senduk berdasarkan periode terpilih.
     */
    public function getKlasemenData(array $queryParams): array
    {
        $daftarPeriode = $this->periodeModel->getAllPeriods();
        $periodeAktif = $this->periodeModel->getActivePeriod();

        $selectedPeriode = null;
        $requestedId = $queryParams['periode_id'] ?? null;

        if (isPositiveInt($requestedId)) {
            $selectedPeriode = $this->periodeModel->findById((int) $requestedId);
        }

        if ($selectedPeriode === null) {
            $selectedPeriode = $periodeAktif ?? ($daftarPeriode[0] ?? null);
        }

        $periodeId = isset($selectedPeriode['id']) ? (int) $selectedPeriode['id'] : null;

        $klasemenKakang = $this->votingModel->getKlasemenByKategori('Kakang', $periodeId);
        $klasemenSenduk = $this->votingModel->getKlasemenByKategori('Senduk', $periodeId);

        $totalKakang = (int) ($klasemenKakang['total_suara'] ?? 0);
        $totalSenduk = (int) ($klasemenSenduk['total_suara'] ?? 0);

        $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
        $now = new DateTimeImmutable('now', $tz);
        $nowSql = $now->format('Y-m-d H:i:s');

        return [
            'daftar_periode'        => $daftarPeriode,
            'periode_terpilih'      => $selectedPeriode,
            'klasemen_kakang'       => $klasemenKakang,
            'klasemen_senduk'       => $klasemenSenduk,
            'total_kakang'          => $totalKakang,
            'total_senduk'          => $totalSenduk,
            'total_semua'           => $totalKakang + $totalSenduk,
            'waktu_pembaruan'       => formatTanggalIndonesia($nowSql, true),
            'waktu_pembaruan_detik' => formatTanggalIndonesia($nowSql, false) . ', ' . $now->format('H:i:s') . ' WIB',
        ];
    }

    /**
     * Menghasilkan payload JSON terstruktur untuk polling klasemen real-time tanpa data simulasi.
     */
    public function getKlasemenJsonPayload(array $queryParams): array
    {
        $data = $this->getKlasemenData($queryParams);
        $periode = $data['periode_terpilih'];

        $formatRows = static function (array $rows): array {
            return array_map(static function (array $row): array {
                return [
                    'id'            => (int) ($row['id'] ?? 0),
                    'ranking'       => (int) ($row['ranking'] ?? 0),
                    'is_seri'       => !empty($row['is_seri']),
                    'nomor_peserta' => (string) ($row['nomor_peserta'] ?? ''),
                    'nama'          => (string) ($row['nama'] ?? ''),
                    'kategori'      => (string) ($row['kategori'] ?? ''),
                    'asal_instansi' => (string) ($row['asal_instansi'] ?? ''),
                    'jumlah_suara'  => (int) ($row['jumlah_suara'] ?? 0),
                    'persentase'    => (float) ($row['persentase'] ?? 0.0),
                    'foto_url'      => kontestanFotoUrl($row['foto'] ?? null),
                    'detail_url'    => baseUrl('pages/detail-kontestan.php?id=' . (int) ($row['id'] ?? 0)),
                ];
            }, $rows);
        };

        return [
            'success'               => true,
            'periode_id'            => isset($periode['id']) ? (int) $periode['id'] : null,
            'nama_periode'          => $periode !== null ? (string) ($periode['nama_periode'] ?? '') : 'Belum Ada Periode',
            'jadwal_status'         => $periode !== null ? (string) ($periode['jadwal_status'] ?? 'Nonaktif') : 'Belum Ada Periode',
            'total_kakang'          => (int) $data['total_kakang'],
            'total_senduk'          => (int) $data['total_senduk'],
            'total_semua'           => (int) $data['total_semua'],
            'waktu_pembaruan'       => (string) $data['waktu_pembaruan'],
            'waktu_pembaruan_detik' => (string) $data['waktu_pembaruan_detik'],
            'kakang'                => [
                'total_suara' => (int) ($data['klasemen_kakang']['total_suara'] ?? 0),
                'podium'      => $formatRows($data['klasemen_kakang']['podium'] ?? []),
                'items'       => $formatRows($data['klasemen_kakang']['items'] ?? []),
            ],
            'senduk'                => [
                'total_suara' => (int) ($data['klasemen_senduk']['total_suara'] ?? 0),
                'podium'      => $formatRows($data['klasemen_senduk']['podium'] ?? []),
                'items'       => $formatRows($data['klasemen_senduk']['items'] ?? []),
            ],
        ];
    }
}
