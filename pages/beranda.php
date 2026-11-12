<?php
declare(strict_types=1);

/**
 * Halaman Landing Page / Beranda Publik
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/app/helpers/ResponseHelper.php';
require_once dirname(__DIR__) . '/app/helpers/AuthHelper.php';
require_once dirname(__DIR__) . '/app/helpers/ValidationHelper.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/KontestanController.php';

startSecureSession();
if (!isLoggedIn()) {
    (new AuthController($pdo))->attemptRememberLogin();
}

$kontestanController = new KontestanController($pdo);
$beranda = $kontestanController->getBerandaData();

$periodeTampil = $beranda['periode_tampil'];
$featuredKontestan = $beranda['featured_kontestan'];
$statsKontestan = $beranda['statistik_kontestan'];
$statsSuara = $beranda['statistik_suara'];

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

$pageTitle = 'Beranda';
$activeMenu = 'beranda';
$bodyClass = 'd-flex flex-column min-vh-100';
$extraCss = ['css/landing.css'];
$showPublicFooter = true;
$errors = [];

require_once dirname(__DIR__) . '/views/layouts/header.php';
require_once dirname(__DIR__) . '/views/layouts/navbar.php';
?>

<main class="flex-grow-1">
    <?php
        $flashAlert = getFlash();
        if ($flashAlert !== null):
            $flashJson = json_encode($flashAlert, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    ?>
        <div class="container pt-3">
            <div class="alert alert-<?= e($flashAlert['type'] === 'error' ? 'danger' : ($flashAlert['type'] ?? 'info')) ?> border-0 shadow-sm rounded-3 mb-0" role="alert">
                <strong class="me-1"><?= e($flashAlert['title'] ?? 'Informasi') ?>:</strong>
                <span><?= e($flashAlert['message'] ?? '') ?></span>
            </div>
        </div>
        <script id="ks-flash-data" type="application/json"><?= $flashJson ?></script>
    <?php endif; ?>

    <!-- 1. Hero Section Editorial + Informasi Periode -->
    <section class="ks-hero py-5 py-lg-5">
        <div class="ks-hero-ornament" aria-hidden="true"></div>
        <div class="container position-relative py-3 py-lg-4">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-7">
                    <span class="ks-hero-eyebrow mb-3">
                        <i class="bi bi-stars"></i> Pemilihan Duta Wisata Kabupaten Ponorogo
                    </span>
                    <h1 class="display-5 ks-hero-title mb-3">
                        Pemilihan Kakang Senduk Ponorogo
                    </h1>
                    <p class="lead fs-6 ks-hero-subtitle mb-4">
                        Salurkan apresiasi Anda bagi putra-putri terbaik Bumi Reog yang siap mengemban amanah pelestarian budaya lokal, promosi pariwisata daerah, dan representasi generasi muda Kabupaten Ponorogo.
                    </p>

                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <a href="<?= e(baseUrl('pages/voting.php')) ?>" class="btn btn-gold btn-lg px-4">
                            <i class="bi bi-check2-circle me-2"></i>Voting Sekarang
                        </a>
                        <a href="<?= e(baseUrl('pages/kontestan.php')) ?>" class="btn btn-outline-light btn-lg px-4">
                            Lihat Kontestan <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>

                <!-- Panel Informasi Periode -->
                <div class="col-lg-5">
                    <div class="ks-period-panel p-4 p-md-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                            <div class="min-w-0">
                                <span class="text-uppercase small fw-semibold text-gold d-block mb-1" style="letter-spacing: 0.08em;">
                                    Informasi Periode Voting
                                </span>
                                <h2 class="h5 text-white fw-bold mb-0">
                                    <?= $periodeTampil !== null ? e($periodeTampil['nama_periode']) : 'Periode Belum Tersedia' ?>
                                </h2>
                            </div>
                            <span class="badge rounded-pill <?= e($statusBadgeClass) ?> px-3 py-2">
                                <?= e($statusLabel) ?>
                            </span>
                        </div>

                        <hr class="ks-period-divider my-3">

                        <?php if ($periodeTampil !== null): ?>
                            <dl class="row g-2 small mb-3">
                                <dt class="col-5 text-white-50 fw-normal">Tanggal Mulai</dt>
                                <dd class="col-7 text-white fw-medium mb-1 text-end">
                                    <?= e(formatTanggalIndonesia($periodeTampil['tanggal_mulai'], true)) ?>
                                </dd>
                                <dt class="col-5 text-white-50 fw-normal">Tanggal Selesai</dt>
                                <dd class="col-7 text-white fw-medium mb-0 text-end">
                                    <?= e(formatTanggalIndonesia($periodeTampil['tanggal_selesai'], true)) ?>
                                </dd>
                            </dl>
                        <?php else: ?>
                            <p class="small text-white-50 mb-3">
                                Jadwal periode pemilihan belum ditetapkan oleh panitia. Anda tetap dapat menelusuri profil peserta aktif.
                            </p>
                        <?php endif; ?>

                        <hr class="ks-period-divider my-3">

                        <div class="row text-center g-2">
                            <div class="col-4">
                                <div class="font-heading fw-bold fs-4 text-white"><?= (int) $statsKontestan['total_kakang'] ?></div>
                                <div class="small text-white-50">Kakang</div>
                            </div>
                            <div class="col-4 border-start border-end border-white border-opacity-10">
                                <div class="font-heading fw-bold fs-4 text-white"><?= (int) $statsKontestan['total_senduk'] ?></div>
                                <div class="small text-white-50">Senduk</div>
                            </div>
                            <div class="col-4">
                                <div class="font-heading fw-bold fs-4 text-gold"><?= number_format((int) $statsSuara['total']) ?></div>
                                <div class="small text-white-50">Total Suara</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. Tentang Pemilihan (Layout Editorial Minimalis) -->
    <section id="tentang" class="ks-section-py bg-white border-bottom">
        <div class="container">
            <div class="row g-4 g-lg-5 align-items-start">
                <div class="col-lg-5">
                    <span class="ks-section-kicker">Tentang Ajang</span>
                    <h2 class="h3 fw-bold mb-3">Merawat Warisan Reog &amp; Pesona Wisata Ponorogo</h2>
                    <div class="ks-editorial-rule mb-3"></div>
                    <p class="text-muted mb-0">
                        Pemilihan Kakang Senduk Ponorogo merupakan wadah regenerasi duta wisata dan budaya daerah yang mengedepankan kecerdasan, kepribadian, serta kecintaan terhadap kearifan lokal Kabupaten Ponorogo.
                    </p>
                </div>

                <div class="col-lg-7">
                    <div class="ks-editorial-list-item">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-compass text-emerald fs-4 mt-1"></i>
                            <div>
                                <h3 class="h6 fw-bold mb-1">Promosi Pariwisata &amp; Ekonomi Kreatif</h3>
                                <p class="small text-muted mb-0">
                                    Memperkenalkan destinasi unggulan Ponorogo mulai dari Telaga Ngebel, kawasan budaya Kota Lama, hingga karya UMKM lokal ke kancah nasional.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="ks-editorial-list-item">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-shield-check text-emerald fs-4 mt-1"></i>
                            <div>
                                <h3 class="h6 fw-bold mb-1">Pemungutan Suara Jujur &amp; Terverifikasi</h3>
                                <p class="small text-muted mb-0">
                                    Setiap pemilih terverifikasi memiliki satu hak suara untuk kategori Kakang dan satu hak suara untuk kategori Senduk dalam setiap periode aktif.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="ks-editorial-list-item">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-bar-chart-line text-emerald fs-4 mt-1"></i>
                            <div>
                                <h3 class="h6 fw-bold mb-1">Transparansi Klasemen Sementara</h3>
                                <p class="small text-muted mb-0">
                                    Masyarakat dapat memantau perolehan suara serta persentase dukungan kontestan secara langsung melalui halaman klasemen resmi.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Featured Contestants (Data Database) -->
    <section class="ks-section-py">
        <div class="container">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
                <div>
                    <span class="ks-section-kicker">Profil Peserta</span>
                    <h2 class="h3 fw-bold mb-1">Finalis Kakang &amp; Senduk Ponorogo</h2>
                    <p class="text-muted small mb-0">Kenali visi, misi, dan rekam jejak kontestan sebelum menyalurkan suara Anda.</p>
                </div>
                <a href="<?= e(baseUrl('pages/kontestan.php')) ?>" class="btn btn-outline-emerald align-self-start align-self-md-auto">
                    Semua Kontestan <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <?php if (!empty($featuredKontestan)): ?>
                <div class="row g-4">
                    <?php foreach ($featuredKontestan as $kontestan): ?>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <?php
                                $showVoteButton = false;
                                require dirname(__DIR__) . '/views/components/kontestan-card.php';
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ks-empty-state">
                    <i class="bi bi-people text-muted fs-1 d-block mb-2"></i>
                    <h3 class="h6 fw-bold mb-1">Belum Ada Kontestan Aktif</h3>
                    <p class="small text-muted mb-3">
                        Data finalis Kakang dan Senduk belum dipublikasikan oleh administrator. Silakan kunjungi kembali dalam waktu dekat.
                    </p>
                    <a href="<?= e(baseUrl('pages/klasemen.php')) ?>" class="btn btn-outline-emerald btn-sm">
                        Lihat Halaman Klasemen
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 4. Cara Melakukan Voting (Timeline Editorial Tanpa Banyak Card) -->
    <section class="ks-section-py bg-white border-top">
        <div class="container">
            <div class="text-center mb-5">
                <span class="ks-section-kicker">Panduan Singkat</span>
                <h2 class="h3 fw-bold mb-2">Cara Melakukan Voting</h2>
                <p class="text-muted small mb-0 mx-auto" style="max-width: 52ch;">
                    Ikuti empat langkah mudah berikut untuk berpartisipasi memilih Kakang dan Senduk favorit Anda.
                </p>
            </div>

            <div class="row g-4 text-center text-md-start">
                <div class="col-12 col-sm-6 col-lg-3">
                    <span class="ks-step-number">01</span>
                    <h3 class="h6 fw-bold mb-2">Registrasi Akun</h3>
                    <p class="small text-muted mb-0">
                        Buat akun pemilih menggunakan email aktif dan nomor HP Anda melalui halaman pendaftaran resmi.
                    </p>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <span class="ks-step-number">02</span>
                    <h3 class="h6 fw-bold mb-2">Login ke Sistem</h3>
                    <p class="small text-muted mb-0">
                        Masuk menggunakan akun pemilih terdaftar untuk membuka akses ke bilik suara digital.
                    </p>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <span class="ks-step-number">03</span>
                    <h3 class="h6 fw-bold mb-2">Pilih Kontestan</h3>
                    <p class="small text-muted mb-0">
                        Tentukan satu pilihan kontestan kategori Kakang dan satu kontestan kategori Senduk favorit Anda.
                    </p>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <span class="ks-step-number">04</span>
                    <h3 class="h6 fw-bold mb-2">Konfirmasi Suara</h3>
                    <p class="small text-muted mb-0">
                        Periksa kembali pilihan Anda lalu konfirmasi. Suara yang masuk tersimpan permanen dan terhitung otomatis.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. CTA Klasemen -->
    <section class="ks-cta-klasemen py-5">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="text-uppercase small fw-semibold text-gold d-block mb-1" style="letter-spacing: 0.08em;">
                        Pemantauan Real-Time
                    </span>
                    <h2 class="h3 text-white fw-bold mb-2">Pantau Klasemen Sementara Pemilihan</h2>
                    <p class="text-white-50 mb-0">
                        Lihat peringkat teratas kategori Kakang dan Senduk lengkap dengan jumlah perolehan suara serta persentase dukungan.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?= e(baseUrl('pages/klasemen.php')) ?>" class="btn btn-gold btn-lg px-4">
                        <i class="bi bi-trophy me-2"></i>Lihat Klasemen
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once dirname(__DIR__) . '/views/layouts/footer.php'; ?>
