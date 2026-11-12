# AGENTS.md
# VOTING KAKANG SENDUK PONOROGO

## 1. IDENTITAS PROYEK

Nama aplikasi: Voting Pemilihan Kakang Senduk Ponorogo

Jenis aplikasi: Web Application

Tema: Sistem Informasi Pemilihan Duta Wisata Daerah Berbasis Web.

Tujuan:
Membangun aplikasi pemilihan Kakang Senduk Ponorogo yang memungkinkan masyarakat melihat profil kontestan, memberikan suara, dan melihat klasemen sementara. Administrator dapat mengelola seluruh data dan aktivitas pemilihan.

## 2. TEKNOLOGI

Gunakan teknologi berikut:

- PHP Native 8.2+
- MySQL 8
- HTML5
- CSS3
- Bootstrap 5
- JavaScript ES6+
- PDO untuk koneksi database
- Bootstrap Icons
- Chart.js untuk grafik
- SweetAlert2 untuk notifikasi

Ketentuan:
- Jangan menggunakan Laravel.
- Jangan menggunakan framework backend tambahan.
- Jangan menggunakan data dummy untuk menggantikan database pada fitur yang sudah selesai.
- Semua proses utama harus terhubung ke MySQL.
- Gunakan PHP Native dengan struktur MVC sederhana.

## 3. IDENTITAS VISUAL DAN DESAIN

Buat desain yang modern, elegan, profesional, dan merepresentasikan budaya serta pariwisata Ponorogo.

### Palet Warna

- Primary: Emerald Green (#047857)
- Secondary: Forest Green (#064E3B)
- Dark: Deep Green (#022C22)
- Accent: Gold (#D4AF37)
- Background: Warm White (#F8FAF7)
- Surface: White (#FFFFFF)
- Text: Slate Dark (#1E293B)
- Muted: Slate Gray (#64748B)
- Border: #E5E7EB

### Tipografi

- Heading: Poppins
- Body: Inter
- Gunakan ukuran teks yang konsisten.
- Pastikan keterbacaan pada perangkat mobile.

### Prinsip UI/UX

1. Desain harus responsif.
2. Gunakan whitespace yang cukup.
3. Hindari terlalu banyak card.
4. Gunakan border tipis dan shadow yang lembut.
5. Hindari gradient berlebihan.
6. Gunakan animasi sederhana dan halus.
7. Gunakan navbar yang konsisten.
8. Gunakan sidebar untuk dashboard administrator.
9. Berikan feedback visual untuk setiap tindakan pengguna.
10. Pastikan tombol memiliki fungsi yang benar.
11. Gunakan modal konfirmasi untuk tindakan penting.
12. Gunakan empty state ketika data belum tersedia.
13. Gunakan loading state ketika memproses data.

### Identitas Ponorogo

Gunakan elemen visual yang terinspirasi dari:
- Reog Ponorogo.
- Budaya lokal Ponorogo.
- Pariwisata daerah.
- Motif tradisional sebagai elemen dekoratif.

Jangan membuat desain terlalu ramai. Kontestan harus tetap menjadi fokus utama.

## 4. STRUKTUR FOLDER

Gunakan struktur berikut:

voting-kakang-senduk/
│
├── AGENTS.md
├── index.php
├── .htaccess
│
├── config/
│   ├── koneksi.php
│   └── app.php
│
├── app/
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── KontestanController.php
│   │   ├── VotingController.php
│   │   ├── KlasemenController.php
│   │   └── AdminController.php
│   │
│   ├── models/
│   │   ├── User.php
│   │   ├── Kontestan.php
│   │   ├── Voting.php
│   │   └── PeriodeVoting.php
│   │
│   ├── middleware/
│   │   ├── AuthMiddleware.php
│   │   └── AdminMiddleware.php
│   │
│   └── helpers/
│       ├── ResponseHelper.php
│       ├── AuthHelper.php
│       └── ValidationHelper.php
│
├── admin/
│   ├── dashboard.php
│   ├── kontestan/
│   │   ├── index.php
│   │   ├── tambah.php
│   │   ├── edit.php
│   │   └── hapus.php
│   ├── pemilih/
│   │   └── index.php
│   ├── periode/
│   │   └── index.php
│   └── hasil-voting.php
│
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── pages/
│   ├── beranda.php
│   ├── kontestan.php
│   ├── detail-kontestan.php
│   ├── voting.php
│   └── klasemen.php
│
├── proses/
│   ├── simpan-voting.php
│   └── upload-foto.php
│
├── views/
│   ├── layouts/
│   │   ├── header.php
│   │   ├── footer.php
│   │   ├── navbar.php
│   │   └── admin-sidebar.php
│   │
│   └── components/
│       ├── alert.php
│       ├── pagination.php
│       └── kontestan-card.php
│
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   ├── landing.css
│   │   └── admin.css
│   ├── js/
│   │   ├── app.js
│   │   ├── voting.js
│   │   └── admin.js
│   └── img/
│
├── uploads/
│   └── kontestan/
│
└── database/
    └── db_voting_kakang_senduk.sql

Catatan:
- Jangan membuat seluruh logika aplikasi dalam satu file PHP.
- Pisahkan proses database, tampilan, dan autentikasi.
- Gunakan require_once untuk memanggil file yang diperlukan.
- Lindungi direktori config, app, dan database dari akses publik.
- Jangan menyimpan file upload di direktori yang dapat mengeksekusi PHP.

## 5. DATABASE

Nama database:

db_voting_kakang_senduk

Buat tabel utama:

1. users
2. kontestan
3. voting
4. periode_voting

### Tabel users

- id: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- nama: VARCHAR(100)
- email: VARCHAR(100) UNIQUE
- no_hp: VARCHAR(20)
- password: VARCHAR(255)
- role: ENUM('admin','pemilih')
- created_at: TIMESTAMP
- updated_at: TIMESTAMP

### Tabel kontestan

- id: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- nomor_peserta: VARCHAR(10) UNIQUE
- nama: VARCHAR(100)
- kategori: ENUM('Kakang','Senduk')
- foto: VARCHAR(255)
- asal_instansi: VARCHAR(150)
- tempat_lahir: VARCHAR(100), nullable
- tanggal_lahir: DATE, nullable
- visi: TEXT
- misi: TEXT
- prestasi: TEXT, nullable
- deskripsi: TEXT
- status: ENUM('Aktif','Nonaktif')
- created_at: TIMESTAMP
- updated_at: TIMESTAMP

### Tabel periode_voting

- id: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- nama_periode: VARCHAR(100)
- tanggal_mulai: DATETIME
- tanggal_selesai: DATETIME
- status: ENUM('Aktif','Nonaktif')
- created_at: TIMESTAMP
- updated_at: TIMESTAMP

### Tabel voting

- id: INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- user_id: INT UNSIGNED
- kontestan_id: INT UNSIGNED
- periode_id: INT UNSIGNED
- kategori: ENUM('Kakang','Senduk')
- created_at: DATETIME

Relasi:
- voting.user_id -> users.id
- voting.kontestan_id -> kontestan.id
- voting.periode_id -> periode_voting.id

Aturan database:

- Email pengguna harus unik.
- Nomor peserta harus unik.
- Terapkan UNIQUE(user_id, periode_id, kategori).
- Gunakan foreign key.
- Gunakan InnoDB.
- Gunakan utf8mb4.
- Jangan menghapus data voting secara sembarangan.
- Jangan mengubah suara yang sudah masuk.

## 6. ROLE DAN HAK AKSES

### Pengunjung

Dapat:
- Melihat halaman beranda.
- Melihat daftar kontestan.
- Melihat detail kontestan.
- Melihat klasemen.

Tidak dapat:
- Melakukan voting tanpa login.
- Mengakses dashboard admin.

### Pemilih

Dapat:
- Login dan logout.
- Melihat profil kontestan.
- Melakukan voting Kakang satu kali per periode.
- Melakukan voting Senduk satu kali per periode.
- Melihat status voting.
- Melihat klasemen.

Tidak dapat:
- Mengubah suara yang sudah diberikan.
- Menghapus suara.
- Mengelola kontestan.
- Mengakses administrator.

### Administrator

Dapat:
- Melihat dashboard.
- Menambah kontestan.
- Mengubah kontestan.
- Menghapus kontestan dengan validasi relasi.
- Mengelola akun pemilih.
- Mengelola periode voting.
- Mengaktifkan dan menonaktifkan voting.
- Melihat hasil voting.
- Melihat statistik.
- Melihat seluruh data aplikasi.

## 7. FITUR HALAMAN PUBLIK

### A. Landing Page

Komponen:
- Navbar.
- Hero section.
- Judul Pemilihan Kakang Senduk Ponorogo.
- Deskripsi kegiatan.
- Tombol Lihat Kontestan.
- Tombol Voting Sekarang.
- Tombol Lihat Klasemen.
- Informasi periode voting.
- Featured contestants.
- Penjelasan cara voting.
- Informasi kegiatan.
- Footer.

### B. Daftar Kontestan

Tampilkan:
- Foto.
- Nomor peserta.
- Nama lengkap.
- Kategori.
- Asal instansi.
- Deskripsi singkat.
- Tombol Detail.
- Tombol Vote.

Tambahkan:
- Filter Kakang dan Senduk.
- Pencarian nama.
- Pagination.

### C. Detail Kontestan

Tampilkan:
- Foto utama.
- Nomor peserta.
- Nama.
- Kategori.
- Asal instansi.
- Tempat dan tanggal lahir.
- Visi.
- Misi.
- Prestasi.
- Deskripsi lengkap.
- Tombol Vote.

### D. Registrasi

Input:
- Nama lengkap.
- Email.
- Nomor HP.
- Password.
- Konfirmasi password.

Validasi:
- Semua input wajib diisi.
- Format email valid.
- Email belum digunakan.
- Password minimal 8 karakter.
- Password dan konfirmasi harus sama.
- Password disimpan menggunakan password_hash().

Role registrasi publik harus selalu pemilih. Pengguna tidak boleh menentukan role admin sendiri.

### E. Login

Input:
- Email.
- Password.

Ketentuan:
- Gunakan password_verify().
- Gunakan session PHP.
- Regenerate session ID setelah login.
- Berikan pesan kesalahan yang aman.
- Terapkan pembatasan percobaan login.

### F. Voting

Alur:
1. Pemilih login.
2. Membuka halaman voting.
3. Memilih kategori Kakang atau Senduk.
4. Memilih kontestan.
5. Sistem menampilkan konfirmasi.
6. Pemilih mengonfirmasi.
7. Server memvalidasi seluruh ketentuan.
8. Sistem menyimpan suara.
9. Sistem menampilkan notifikasi berhasil.

Ketentuan:
- Harus login.
- Periode harus aktif.
- Waktu server harus berada dalam rentang periode.
- Kontestan harus aktif.
- Kategori kontestan harus sesuai.
- Satu suara untuk setiap kategori per periode.
- Suara tidak dapat diubah.
- Gunakan POST.
- Gunakan CSRF token.
- Gunakan database transaction.
- Tangani duplicate key secara aman.

### G. Klasemen

Pisahkan klasemen Kakang dan Senduk.

Tampilkan:
- Ranking.
- Foto.
- Nomor peserta.
- Nama.
- Jumlah suara.
- Persentase suara.

Tambahkan:
- Podium juara 1, 2, dan 3.
- Total suara.
- Waktu pembaruan.
- Filter periode.

Ranking harus dihitung berdasarkan jumlah suara terbesar.

Persentase:

(jumlah suara kontestan / total suara kategori) * 100

Gunakan LEFT JOIN agar kontestan dengan nol suara tetap ditampilkan.

## 8. FITUR ADMINISTRATOR

### Dashboard

Tampilkan:
- Total kontestan.
- Total pemilih.
- Total suara Kakang.
- Total suara Senduk.
- Total suara keseluruhan.
- Status periode.
- Grafik perolehan suara.
- Kontestan dengan suara tertinggi.
- Aktivitas terbaru.

### Manajemen Kontestan

CRUD:
- Tambah.
- Tampilkan.
- Edit.
- Hapus.

Validasi:
- Nama wajib diisi.
- Nomor peserta unik.
- Kategori wajib dipilih.
- Asal instansi wajib diisi.
- Foto harus divalidasi.
- Format foto JPEG, PNG, atau WebP.
- Maksimal ukuran foto 2 MB.
- Nama file upload harus dibuat unik.
- Hapus file foto lama setelah pembaruan berhasil.

Jangan menghapus kontestan yang memiliki data voting tanpa prosedur pengarsipan yang aman.

### Manajemen Pemilih

Tampilkan:
- Nama.
- Email.
- Nomor HP.
- Tanggal registrasi.
- Status voting Kakang.
- Status voting Senduk.

### Manajemen Periode

Fitur:
- Tambah periode.
- Edit periode.
- Aktifkan periode.
- Nonaktifkan periode.
- Lihat tanggal mulai.
- Lihat tanggal selesai.

Pastikan tidak terdapat dua periode aktif yang saling bertentangan.

### Hasil Voting

Tampilkan:
- Hasil Kakang.
- Hasil Senduk.
- Total suara.
- Ranking.
- Grafik.
- Filter periode.

## 9. KEAMANAN APLIKASI

Wajib menerapkan:

1. PDO Prepared Statements untuk semua query dengan input pengguna.
2. password_hash() untuk penyimpanan password.
3. password_verify() untuk autentikasi.
4. htmlspecialchars() untuk output HTML.
5. Validasi input di server.
6. CSRF token pada seluruh form yang mengubah data.
7. Session regeneration setelah login.
8. Pemeriksaan role pada setiap halaman admin.
9. Validasi upload file berdasarkan MIME dan ekstensi.
10. Batasi ukuran upload.
11. Jangan tampilkan error database kepada pengguna.
12. Simpan konfigurasi sensitif di luar direktori publik.
13. Gunakan HTTPS ketika deployment.
14. Jangan pernah mengizinkan pemilih mengirim voting melalui GET.
15. Jangan percaya nilai user_id, role, kategori, atau periode dari form tanpa verifikasi server.

## 10. STANDAR CODING

- Gunakan nama variabel dan fungsi yang jelas.
- Konsisten menggunakan bahasa Indonesia untuk istilah bisnis.
- Gunakan fungsi reusable.
- Hindari duplikasi kode.
- Pisahkan query database dari tampilan.
- Gunakan prepared statements.
- Berikan komentar pada logika kompleks.
- Gunakan try-catch untuk menangani exception.
- Gunakan redirect setelah POST untuk mencegah pengiriman ulang form.
- Jangan menggunakan query SQL yang dibangun dari input mentah.
- Gunakan format tanggal Indonesia pada tampilan.

## 11. TAHAPAN PENGEMBANGAN

AI wajib mengembangkan proyek secara bertahap.

### Tahap 1: Setup
- Membuat struktur folder.
- Membuat database.
- Membuat koneksi PDO.
- Membuat konfigurasi.
- Menguji koneksi database.

### Tahap 2: Authentication
- Registrasi.
- Login.
- Logout.
- Session.
- Middleware.
- Role.

### Tahap 3: Public Website
- Landing page.
- Daftar kontestan.
- Detail kontestan.
- Klasemen.

### Tahap 4: Admin
- Dashboard.
- CRUD kontestan.
- Upload foto.
- Manajemen pemilih.
- Manajemen periode.

### Tahap 5: Voting
- Pemilihan Kakang.
- Pemilihan Senduk.
- Konfirmasi.
- Penyimpanan suara.
- Pencegahan voting ganda.

### Tahap 6: Analytics
- Statistik.
- Grafik.
- Ranking.
- Persentase suara.

### Tahap 7: Testing
- Pengujian autentikasi.
- Pengujian CRUD.
- Pengujian voting.
- Pengujian keamanan.
- Pengujian responsivitas.

## 12. STANDAR PENGUJIAN

Setiap fitur wajib diuji sebelum melanjutkan tahap berikutnya.

Skenario minimum:

1. Registrasi berhasil.
2. Registrasi dengan email duplikat ditolak.
3. Login berhasil.
4. Password salah ditolak.
5. Pemilih tidak dapat mengakses admin.
6. Admin dapat mengelola kontestan.
7. Foto berhasil diunggah.
8. Pemilih dapat memilih Kakang.
9. Pemilih dapat memilih Senduk.
10. Voting ganda ditolak.
11. Voting pada periode nonaktif ditolak.
12. Voting di luar jadwal ditolak.
13. Klasemen sesuai database.
14. Kontestan tanpa suara tetap tampil.
15. Dashboard menampilkan statistik akurat.

## 13. ATURAN KERJA AI CODING

WAJIB:

- Baca AGENTS.md sebelum melakukan perubahan.
- Kerjakan satu tahap dalam satu waktu.
- Periksa struktur proyek sebelum membuat file.
- Jangan menghapus file yang sudah ada tanpa alasan.
- Jangan mengganti teknologi yang sudah ditentukan.
- Jangan membuat fitur yang belum diminta tanpa persetujuan.
- Jangan menggunakan data dummy sebagai implementasi akhir.
- Pastikan setiap tombol dan form benar-benar berfungsi.
- Pastikan seluruh query menggunakan database.
- Uji setiap fitur setelah implementasi.
- Jangan melanjutkan jika terdapat error kritis pada tahap sebelumnya.
- Jelaskan file yang dibuat atau diubah.
- Berikan instruksi menjalankan dan menguji fitur.

Setelah menyelesaikan setiap tahap, berikan laporan:

1. Fitur yang selesai.
2. File yang dibuat.
3. File yang diubah.
4. Database atau tabel yang terpengaruh.
5. Hasil pengujian.
6. Error yang masih ditemukan.
7. Tahap berikutnya.

## 14. KRITERIA SELESAI

Aplikasi dinyatakan selesai apabila:

- Seluruh fitur utama berfungsi.
- Login dan registrasi aman.
- CRUD kontestan berjalan.
- Upload foto berjalan.
- Voting tidak dapat dilakukan berulang.
- Pemilihan Kakang dan Senduk terpisah.
- Klasemen otomatis dan akurat.
- Dashboard administrator berfungsi.
- Periode voting dapat dikelola.
- UI responsif.
- Tidak terdapat error kritis.
- Seluruh skenario pengujian berhasil.

Prioritas utama proyek adalah keamanan voting, akurasi data, kemudahan penggunaan, dan konsistensi desain.

JANGAN MEMBUAT SEMUA FITUR SEKALIGUS. IKUTI TAHAPAN PENGEMBANGAN SECARA BERURUTAN.