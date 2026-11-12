<?php
declare(strict_types=1);

/**
 * Halaman Bilik Suara Pemilihan Kakang Senduk Ponorogo
 * Mendukung pemilihan terpisah 1 suara Kakang dan 1 suara Senduk per periode aktif.
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/VotingController.php';

$user = AuthMiddleware::handle($pdo);

$votingController = new VotingController($pdo);
$votingData = $votingController->getVotingPageData($user, $_GET);

$periodeAktif = $votingData['periode_aktif'];
$periodeTampil = $votingData['periode_tampil'];
$bisaVoting = (bool) $votingData['bisa_voting'];
$isPemilih = (bool) $votingData['is_pemilih'];
$statusVoting = $votingData['status_voting'];
$selectedKategori = (string) $votingData['selected_kategori'];
$alreadyVotedSelected = (bool) $votingData['already_voted_selected'];
$selectedKontestanDetail = $votingData['selected_kontestan_detail'];
$preselectedId = (int) $votingData['preselected_id'];
$kontestanNotice = $votingData['kontestan_notice'];
$listKakang = $votingData['list_kakang'];
$listSenduk = $votingData['list_senduk'];
$listAktif = $votingData['list_aktif'];

$statusBadgeClass = 'text-bg-secondary';
$statusLabel = 'Belum Ada Periode Aktif';
if (is_array($periodeTampil)) {
    $statusLabel = (string) ($periodeTampil['jadwal_status'] ?? 'Nonaktif');
    if (!empty($periodeTampil['bisa_voting'])) {
        $statusBadgeClass = 'text-bg-success';
    } elseif ($statusLabel === 'Belum Dimulai') {
        $statusBadgeClass = 'text-bg-warning';
    } elseif ($statusLabel === 'Sudah Berakhir') {
        $statusBadgeClass = 'text-bg-dark';
    }
}

$tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');
$waktuServerStr = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');

$otherKategori = $selectedKategori === 'Kakang' ? 'Senduk' : 'Kakang';
$sudahOther = $otherKategori === 'Senduk'
    ? !empty($statusVoting['sudah_senduk'])
    : !empty($statusVoting['sudah_kakang']);

$pageTitle = 'Bilik Suara Pemilih';
$activeMenu = 'voting';
$bodyClass = 'd-flex flex-column min-vh-100';
$extraCss = ['css/landing.css'];
$extraJs = ['js/voting.js'];
$showPublicFooter = true;
$errors = [];

require_once dirname(__DIR__) . '/views/layouts/header.php';
require_once dirname(__DIR__) . '/views/layouts/navbar.php';
?>

<main class="flex-grow-1 py-4 py-lg-5">
    <div class="container">
        <!-- Header Bilik Suara -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div class="min-w-0">
                <span class="ks-section-kicker">Pemungutan Suara Resmi</span>
                <h1 class="h2 fw-bold mb-1">Bilik Suara Kakang Senduk Ponorogo</h1>
                <p class="text-muted small mb-0">
                    Pilih satu kontestan kategori <strong>Kakang</strong> dan satu kontestan kategori <strong>Senduk</strong> pada periode aktif. Suara yang dikirim bersifat permanen.
                </p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                <span class="badge rounded-pill <?= $isPemilih ? 'text-bg-success' : 'text-bg-dark' ?> px-3 py-2">
                    <i class="bi bi-person-check me-1"></i><?= e($user['nama'] ?? 'Pengguna') ?> (<?= $isPemilih ? 'Pemilih' : 'Administrator' ?>)
                </span>
            </div>
        </div>

        <?php require_once dirname(__DIR__) . '/views/components/alert.php'; ?>

        <?php if ($kontestanNotice !== null): ?>
            <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($kontestanNotice) ?>
            </div>
        <?php endif; ?>

        <!-- Informasi Periode Voting & Waktu Server -->
        <section class="ks-card p-4 mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-7 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge rounded-pill <?= e($statusBadgeClass) ?> px-3 py-2">
                            <i class="bi bi-broadcast me-1"></i><?= e($statusLabel) ?>
                        </span>
                        <span class="small text-muted">
                            <i class="bi bi-clock me-1"></i>Waktu Server: <?= e(formatTanggalIndonesia($waktuServerStr, true)) ?>
                        </span>
                    </div>
                    <h2 class="h5 fw-bold mb-1">
                        <?= $periodeTampil !== null ? e($periodeTampil['nama_periode']) : 'Belum Ada Periode Voting Terjadwal' ?>
                    </h2>
                    <?php if ($periodeTampil !== null): ?>
                        <p class="small text-muted mb-0">
                            Jadwal: <strong><?= e(formatTanggalIndonesia((string) $periodeTampil['tanggal_mulai'], true)) ?></strong>
                            s.d. <strong><?= e(formatTanggalIndonesia((string) $periodeTampil['tanggal_selesai'], true)) ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="small text-muted mb-0">
                            Administrator belum menambahkan atau mengaktifkan jadwal periode pemilihan.
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-lg-5 text-lg-end">
                    <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                        <a href="<?= e(baseUrl('pages/kontestan.php')) ?>" class="btn btn-outline-emerald btn-sm">
                            <i class="bi bi-people me-1"></i>Profil Kontestan
                        </a>
                        <a href="<?= e(baseUrl('pages/klasemen.php')) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-trophy me-1"></i>Lihat Klasemen
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Panel Status Hak Suara Pemilih (Kakang & Senduk) -->
        <?php if ($isPemilih): ?>
            <section class="mb-4" aria-label="Status voting pemilih">
                <div class="row g-3">
                    <!-- Status Kakang -->
                    <div class="col-md-6">
                        <?php $detailKakang = $statusVoting['detail_kakang']; ?>
                        <div class="ks-vote-status-card <?= !empty($statusVoting['sudah_kakang']) ? 'is-done' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <span class="text-uppercase small fw-semibold text-muted d-block">Kategori Kakang</span>
                                    <h3 class="h6 fw-bold mb-0 mt-1">
                                        <?= !empty($statusVoting['sudah_kakang']) ? 'Sudah voting Kakang' : 'Belum voting Kakang' ?>
                                    </h3>
                                </div>
                                <span class="badge rounded-pill <?= !empty($statusVoting['sudah_kakang']) ? 'text-bg-success' : 'text-bg-warning' ?> px-3 py-2">
                                    <?php if (!empty($statusVoting['sudah_kakang'])): ?>
                                        <i class="bi bi-check-circle-fill me-1"></i>Sudah voting Kakang
                                    <?php else: ?>
                                        <i class="bi bi-hourglass-split me-1"></i>Belum voting Kakang
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if (is_array($detailKakang)): ?>
                                <div class="small text-muted border-top pt-2 mt-2">
                                    Pilihan Anda:
                                    <strong class="text-dark"><?= e($detailKakang['nomor_peserta']) ?> &mdash; <?= e($detailKakang['nama']) ?></strong>
                                    <div class="mt-1">
                                        <i class="bi bi-lock-fill text-emerald me-1"></i>Tercatat pada <?= e(formatTanggalIndonesia((string) $detailKakang['waktu_voting'], true)) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="small text-muted border-top pt-2 mt-2 mb-0">
                                    <?= $bisaVoting ? 'Hak suara Kakang masih tersedia untuk periode berjalan.' : 'Menunggu jadwal voting dibuka untuk menyalurkan suara Kakang.' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Status Senduk -->
                    <div class="col-md-6">
                        <?php $detailSenduk = $statusVoting['detail_senduk']; ?>
                        <div class="ks-vote-status-card <?= !empty($statusVoting['sudah_senduk']) ? 'is-done' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <span class="text-uppercase small fw-semibold text-muted d-block">Kategori Senduk</span>
                                    <h3 class="h6 fw-bold mb-0 mt-1">
                                        <?= !empty($statusVoting['sudah_senduk']) ? 'Sudah voting Senduk' : 'Belum voting Senduk' ?>
                                    </h3>
                                </div>
                                <span class="badge rounded-pill <?= !empty($statusVoting['sudah_senduk']) ? 'text-bg-success' : 'text-bg-warning' ?> px-3 py-2">
                                    <?php if (!empty($statusVoting['sudah_senduk'])): ?>
                                        <i class="bi bi-check-circle-fill me-1"></i>Sudah voting Senduk
                                    <?php else: ?>
                                        <i class="bi bi-hourglass-split me-1"></i>Belum voting Senduk
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if (is_array($detailSenduk)): ?>
                                <div class="small text-muted border-top pt-2 mt-2">
                                    Pilihan Anda:
                                    <strong class="text-dark"><?= e($detailSenduk['nomor_peserta']) ?> &mdash; <?= e($detailSenduk['nama']) ?></strong>
                                    <div class="mt-1">
                                        <i class="bi bi-lock-fill text-emerald me-1"></i>Tercatat pada <?= e(formatTanggalIndonesia((string) $detailSenduk['waktu_voting'], true)) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="small text-muted border-top pt-2 mt-2 mb-0">
                                    <?= $bisaVoting ? 'Hak suara Senduk masih tersedia untuk periode berjalan.' : 'Menunggu jadwal voting dibuka untuk menyalurkan suara Senduk.' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($statusVoting['sudah_kakang']) && !empty($statusVoting['sudah_senduk'])): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-3 mt-3 mb-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                        <div class="small">
                            <i class="bi bi-patch-check-fill me-2"></i>
                            <strong>Terima kasih!</strong> Seluruh hak suara Anda (Kakang &amp; Senduk) untuk periode ini telah lengkap tercatat.
                        </div>
                        <a href="<?= e(baseUrl('pages/klasemen.php')) ?>" class="btn btn-emerald btn-sm flex-shrink-0">
                            <i class="bi bi-trophy me-1"></i>Lihat Klasemen Sementara
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- Tab Pemilihan Kategori -->
        <div class="ks-category-tabs pb-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex flex-wrap gap-2" role="tablist" aria-label="Pilih kategori voting">
                <a
                    href="<?= e(baseUrl('pages/voting.php?kategori=Kakang')) ?>"
                    class="ks-category-tab <?= $selectedKategori === 'Kakang' ? 'active' : '' ?>"
                    role="tab"
                    aria-selected="<?= $selectedKategori === 'Kakang' ? 'true' : 'false' ?>"
                >
                    <i class="bi <?= !empty($statusVoting['sudah_kakang']) ? 'bi-check-circle-fill' : 'bi-person-badge' ?>"></i>
                    <span>Kategori Kakang</span>
                    <span class="badge rounded-pill <?= $selectedKategori === 'Kakang' ? 'bg-white text-dark' : 'text-bg-light border' ?>">
                        <?= count($listKakang) ?>
                    </span>
                </a>

                <a
                    href="<?= e(baseUrl('pages/voting.php?kategori=Senduk')) ?>"
                    class="ks-category-tab <?= $selectedKategori === 'Senduk' ? 'active' : '' ?>"
                    role="tab"
                    aria-selected="<?= $selectedKategori === 'Senduk' ? 'true' : 'false' ?>"
                >
                    <i class="bi <?= !empty($statusVoting['sudah_senduk']) ? 'bi-check-circle-fill' : 'bi-person-badge-fill' ?>"></i>
                    <span>Kategori Senduk</span>
                    <span class="badge rounded-pill <?= $selectedKategori === 'Senduk' ? 'bg-white text-dark' : 'text-bg-light border' ?>">
                        <?= count($listSenduk) ?>
                    </span>
                </a>
            </div>

            <div class="small text-muted">
                Menampilkan kategori: <strong class="text-dark"><?= e($selectedKategori) ?></strong>
            </div>
        </div>

        <!-- Konten Bilik Suara Berdasarkan Kondisi Server -->
        <?php if (!$isPemilih): ?>
            <div class="ks-empty-state py-5">
                <i class="bi bi-shield-lock text-emerald fs-1 d-block mb-2"></i>
                <h2 class="h5 fw-bold mb-2">Akses Khusus Akun Pemilih</h2>
                <p class="small text-muted mb-4 mx-auto" style="max-width: 52ch;">
                    Anda sedang masuk menggunakan akun <strong>Administrator</strong>. Untuk menjaga netralitas pemilihan, hanya akun dengan peran pemilih yang dapat memberikan suara.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="<?= e(baseUrl('admin/dashboard.php')) ?>" class="btn btn-emerald">
                        <i class="bi bi-speedometer2 me-1"></i>Ke Dashboard Admin
                    </a>
                    <a href="<?= e(baseUrl('pages/klasemen.php')) ?>" class="btn btn-outline-emerald">
                        <i class="bi bi-trophy me-1"></i>Lihat Klasemen
                    </a>
                </div>
            </div>

        <?php elseif (!$bisaVoting): ?>
            <div class="ks-empty-state py-5">
                <i class="bi bi-calendar-x text-muted fs-1 d-block mb-2"></i>
                <h2 class="h5 fw-bold mb-2">Pemungutan Suara Sedang Ditutup (<?= e($statusLabel) ?>)</h2>
                <p class="small text-muted mb-4 mx-auto" style="max-width: 56ch;">
                    <?php if (is_array($periodeTampil) && !empty($periodeTampil['alasan_status'])): ?>
                        <?= e($periodeTampil['alasan_status']) ?>
                    <?php else: ?>
                        Saat ini belum terdapat periode pemilihan yang berstatus Aktif dan berada di dalam rentang jadwal server.
                    <?php endif; ?>
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="<?= e(baseUrl('pages/kontestan.php')) ?>" class="btn btn-emerald">
                        <i class="bi bi-people me-1"></i>Jelajahi Profil Kontestan
                    </a>
                    <a href="<?= e(baseUrl('pages/klasemen.php')) ?>" class="btn btn-outline-emerald">
                        <i class="bi bi-trophy me-1"></i>Pantau Klasemen
                    </a>
                </div>
            </div>

        <?php elseif ($alreadyVotedSelected && is_array($selectedKontestanDetail)): ?>
            <?php
                $votedFotoUrl = kontestanFotoUrl($selectedKontestanDetail['foto'] ?? null);
                $votedBadgeClass = $selectedKategori === 'Senduk' ? 'ks-badge-senduk' : 'ks-badge-kakang';
            ?>
            <div class="ks-card p-4 p-lg-5">
                <div class="row g-4 align-items-center">
                    <div class="col-md-4 col-lg-3">
                        <div class="ks-detail-photo-wrap">
                            <img
                                src="<?= e($votedFotoUrl) ?>"
                                alt="Foto <?= e($selectedKontestanDetail['nama']) ?>"
                                class="ks-detail-photo"
                            >
                        </div>
                    </div>
                    <div class="col-md-8 col-lg-9 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge rounded-pill text-bg-success px-3 py-2">
                                <i class="bi bi-lock-fill me-1"></i>Sudah voting <?= e($selectedKategori) ?>
                            </span>
                            <span class="badge rounded-pill <?= e($votedBadgeClass) ?> px-3 py-2">
                                Nomor Peserta: <?= e($selectedKontestanDetail['nomor_peserta']) ?>
                            </span>
                        </div>

                        <h2 class="h4 fw-bold mb-1"><?= e($selectedKontestanDetail['nama']) ?></h2>
                        <p class="small text-muted mb-3">
                            <i class="bi bi-building me-1"></i><?= e($selectedKontestanDetail['asal_instansi']) ?>
                        </p>

                        <div class="alert alert-light border small mb-4">
                            <div class="fw-semibold text-emerald mb-1">
                                <i class="bi bi-shield-check me-1"></i>Suara Anda Tercatat Secara Permanen
                            </div>
                            <p class="mb-0 text-muted">
                                Suara untuk kategori <strong><?= e($selectedKategori) ?></strong> disalurkan pada
                                <strong><?= e(formatTanggalIndonesia((string) $selectedKontestanDetail['waktu_voting'], true)) ?></strong>.
                                Demi keabsahan pemilihan, suara tidak dapat diubah atau dihapus.
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <?php if (!$sudahOther): ?>
                                <a href="<?= e(baseUrl('pages/voting.php?kategori=' . urlencode($otherKategori))) ?>" class="btn btn-emerald">
                                    <i class="bi bi-arrow-right-circle me-1"></i>Lanjutkan Voting <?= e($otherKategori) ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?= e(baseUrl('pages/detail-kontestan.php?id=' . (int) $selectedKontestanDetail['kontestan_id'])) ?>" class="btn btn-outline-emerald">
                                Lihat Detail Kontestan
                            </a>
                            <a href="<?= e(baseUrl('pages/klasemen.php')) ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-trophy me-1"></i>Lihat Klasemen
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif (empty($listAktif)): ?>
            <div class="ks-empty-state py-5">
                <i class="bi bi-people text-muted fs-1 d-block mb-2"></i>
                <h2 class="h5 fw-bold mb-2">Belum Ada Kontestan Aktif Kategori <?= e($selectedKategori) ?></h2>
                <p class="small text-muted mb-4">
                    Panitia belum menayangkan kontestan berstatus Aktif untuk kategori <?= e($selectedKategori) ?> pada saat ini.
                </p>
                <a href="<?= e(baseUrl('pages/voting.php?kategori=' . urlencode($otherKategori))) ?>" class="btn btn-outline-emerald">
                    Periksa Kategori <?= e($otherKategori) ?>
                </a>
            </div>

        <?php else: ?>
            <form
                id="ks-voting-form"
                method="POST"
                action="<?= e(baseUrl('proses/simpan-voting.php')) ?>"
            >
                <?= csrfField() ?>
                <input type="hidden" name="kategori" value="<?= e($selectedKategori) ?>">

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-0">Pilih Satu Kontestan Kategori <?= e($selectedKategori) ?></h2>
                        <small class="text-muted">Klik kartu atau tombol Pilih pada salah satu finalis terbaik pilihan Anda.</small>
                    </div>
                    <span class="badge rounded-pill text-bg-light border text-dark px-3 py-2 align-self-start">
                        <i class="bi bi-info-circle text-emerald me-1"></i>1 Suara / Periode
                    </span>
                </div>

                <div class="row g-4 mb-4">
                    <?php foreach ($listAktif as $kontestan): ?>
                        <?php
                            $kid = (int) $kontestan['id'];
                            $isPreselected = ($preselectedId === $kid);
                            $fotoSrc = kontestanFotoUrl($kontestan['foto'] ?? null);
                            $katBadgeClass = $selectedKategori === 'Senduk' ? 'ks-badge-senduk' : 'ks-badge-kakang';
                            $deskripsiSingkat = mb_strimwidth(trim((string) ($kontestan['deskripsi'] ?? '')), 0, 88, '...', 'UTF-8');
                        ?>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <article class="ks-vote-option <?= $isPreselected ? 'is-selected' : '' ?>">
                                <input
                                    type="radio"
                                    class="ks-vote-radio visually-hidden"
                                    id="kontestan-opt-<?= $kid ?>"
                                    name="kontestan_id"
                                    value="<?= $kid ?>"
                                    data-nomor="<?= e($kontestan['nomor_peserta']) ?>"
                                    data-nama="<?= e($kontestan['nama']) ?>"
                                    data-instansi="<?= e($kontestan['asal_instansi']) ?>"
                                    data-kategori="<?= e($selectedKategori) ?>"
                                    <?= $isPreselected ? 'checked' : '' ?>
                                    required
                                >

                                <label for="kontestan-opt-<?= $kid ?>" class="ks-vote-card-label">
                                    <div class="ks-contestant-photo-wrap">
                                        <img
                                            src="<?= e($fotoSrc) ?>"
                                            alt="Foto <?= e($kontestan['nama']) ?>"
                                            class="ks-contestant-photo"
                                            loading="lazy"
                                        >
                                        <span class="ks-contestant-number"><?= e($kontestan['nomor_peserta']) ?></span>
                                        <span class="ks-vote-check-pill">
                                            <i class="bi <?= $isPreselected ? 'bi-check-circle-fill' : 'bi-circle' ?> ks-vote-check-icon"></i>
                                            <span class="ks-vote-check-text"><?= $isPreselected ? 'Terpilih' : 'Pilih' ?></span>
                                        </span>
                                    </div>

                                    <div class="p-3 p-md-4 d-flex flex-column flex-grow-1">
                                        <div class="mb-2">
                                            <span class="badge rounded-pill <?= e($katBadgeClass) ?> fw-medium px-2 py-1">
                                                <?= e($selectedKategori) ?>
                                            </span>
                                        </div>
                                        <h3 class="h6 fw-bold mb-1"><?= e($kontestan['nama']) ?></h3>
                                        <p class="small text-muted mb-2">
                                            <i class="bi bi-building me-1"></i><?= e($kontestan['asal_instansi']) ?>
                                        </p>
                                        <?php if ($deskripsiSingkat !== ''): ?>
                                            <p class="small text-muted mb-0 flex-grow-1"><?= e($deskripsiSingkat) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </label>

                                <div class="px-3 px-md-4 pb-3 pt-2 border-top d-flex justify-content-between align-items-center gap-2 bg-white">
                                    <a
                                        href="<?= e(baseUrl('pages/detail-kontestan.php?id=' . $kid)) ?>"
                                        class="small ks-link-accent"
                                    >
                                        <i class="bi bi-person-vcard me-1"></i>Detail Profil
                                    </a>
                                    <label
                                        for="kontestan-opt-<?= $kid ?>"
                                        class="btn btn-sm btn-outline-emerald ks-select-btn mb-0 px-3"
                                    >
                                        <?= $isPreselected ? 'Terpilih' : 'Pilih' ?>
                                    </label>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Bar Konfirmasi & Pengiriman Suara -->
                <div class="ks-vote-confirm-bar p-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-8 min-w-0">
                            <span class="text-uppercase small fw-semibold text-muted d-block mb-1" style="letter-spacing: 0.06em;">
                                Ringkasan Pilihan Kategori <?= e($selectedKategori) ?>
                            </span>
                            <div id="ks-selected-summary" class="mb-1 text-muted">
                                Belum ada kontestan yang dipilih.
                            </div>
                            <div class="small text-muted">
                                <i class="bi bi-shield-lock me-1 text-emerald"></i>
                                Suara yang sudah dikirimkan bersifat <strong>permanen</strong> dan tidak dapat diubah kembali.
                            </div>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <button
                                type="submit"
                                id="ks-btn-submit-vote"
                                class="btn btn-emerald btn-lg w-100 w-lg-auto px-4"
                                data-loading-text="Menyimpan suara..."
                                <?= $preselectedId > 0 ? '' : 'disabled' ?>
                            >
                                <i class="bi bi-check2-circle me-2"></i>Kirim Suara <?= e($selectedKategori) ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>
