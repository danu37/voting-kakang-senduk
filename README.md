# Voting Pemilihan Kakang Senduk Ponorogo

Aplikasi web resmi untuk **Pemilihan Duta Wisata Kakang Senduk Kabupaten Ponorogo** berbasis **PHP Native 8.2+** dan **MySQL / MariaDB** dengan arsitektur MVC sederhana. Aplikasi ini memungkinkan masyarakat melihat profil finalis Kakang dan Senduk, memberikan suara secara aman (satu suara Kakang dan satu suara Senduk per periode), serta memantau klasemen sementara secara *real-time*, sementara Administrator mengelola seluruh data pemilihan.

---

## Daftar Dokumentasi Lengkap

- [Panduan Instalasi Lokal & Server](docs/INSTALASI.md)
- [Dokumentasi Struktur Database & Backup](docs/DATABASE.md)
- [Dokumentasi Fitur Pemilih & Administrator](docs/FITUR.md)
- [Panduan Pengujian Sistem & Keamanan](docs/PENGUJIAN.md)
- [Panduan Deployment Produksi](docs/DEPLOYMENT.md)

---

## Teknologi

| Lapisan | Teknologi |
| :--- | :--- |
| **Backend** | PHP Native `8.2+` (strict types, arsitektur MVC terstruktur) |
| **Database & Driver** | MySQL 8 / MariaDB (`InnoDB`, `utf8mb4`), **PDO** (`ATTR_EMULATE_PREPARES = false`) |
| **Frontend & UI** | HTML5, CSS3, Bootstrap 5.3, Bootstrap Icons 1.11 |
| **Interaktivitas** | JavaScript ES6+ (`fetch` AJAX polling), Chart.js, SweetAlert2 |
| **Tipografi** | Google Fonts (`Poppins` untuk heading, `Inter` untuk isi teks) |
| **Identitas Visual** | Emerald Green (`#047857`), Forest Green (`#064E3B`), Deep Green (`#022C22`), Gold (`#D4AF37`), Warm White (`#F8FAF7`) |

---

## Fitur Utama

### 1. Halaman Publik (Pengunjung)
- **Landing Page Editorial:** Hero budaya Ponorogo, status & jadwal periode aktif, kontestan unggulan, alur cara voting, dan CTA klasemen.
- **Daftar & Detail Kontestan:** Filter kategori (`Kakang`/`Senduk`), pencarian nama/nomor peserta, *pagination*, profil lengkap (visi, misi, prestasi, deskripsi), serta HTTP 404 untuk ID tidak valid/nonaktif.
- **Klasemen Real-Time:** Pemisahan klasemen `Kakang` & `Senduk`, podium Juara 1–3, tabel lengkap berbasis `LEFT JOIN` (kontestan `0` suara tetap tampil), persentase akurat, indikator seri (*tie-breaker*), filter periode, dan *polling* AJAX setiap 15 detik.

### 2. Sistem Pemilih Terautentikasi
- **Registrasi & Login Aman:** Registrasi publik terkunci otomatis sebagai role `pemilih`, validasi server-side, `password_hash()`, `password_verify()`, regenerasi session ID, fitur opsional *Remember Me* (selector + hash SHA-256 validator dengan rotasi token), serta pembatasan *brute-force* berbasis penyimpanan server.
- **Bilik Suara & Validasi Ketat:** Pemilihan kategori `Kakang` dan `Senduk` masing-masing **1 suara per periode**, modal konfirmasi SweetAlert2, pemrosesan POST + CSRF + transaksi database (`SELECT ... FOR UPDATE`), serta perlindungan kunci unik database terhadap *concurrent requests*.

### 3. Panel Administrator
- **Dashboard Analitik:** Ringkasan total kontestan, pemilih, perolehan suara Kakang/Senduk, status jadwal periode berjalan, grafik Chart.js, dan aktivitas terbaru.
- **Manajemen Kontestan (CRUD + Upload Aman):** Unggah foto JPEG/PNG/WebP maks. 2 MB dengan validasi ekstensi, `finfo` MIME, `getimagesize()`, optimasi ukuran (jika ekstensi `gd` aktif), penguncian perubahan kategori bila sudah memiliki suara, dan mekanisme arsip aman.
- **Manajemen Pemilih:** Daftar pemilih, pencarian, filter status voting, detail riwayat partisipasi (tanpa pernah menampilkan hash password), serta pencegahan penghapusan pemilih yang sudah memberikan suara.
- **Manajemen Periode Voting:** Pembuatan/edit jadwal, pencegahan dua periode aktif atau jadwal bentrok, serta evaluasi otomatis 4 status (`Sedang Berlangsung`, `Belum Dimulai`, `Sudah Berakhir`, `Nonaktif`).
- **Hasil Voting & Ekspor:** Filter periode, grafik perolehan suara, ekspor **Excel (.xlsx)** native dan laporan cetak **PDF**.

---

## Persyaratan Sistem

- **Web Server:** Apache 2.4+ (dengan `mod_rewrite`, `mod_headers`, `AllowOverride All`) atau Nginx + PHP-FPM.
- **PHP:** `>= 8.2.0`
- **Ekstensi PHP Wajib:** `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `openssl`, `session`
- **Ekstensi PHP Direkomendasikan (Produksi):** `gd` (optimasi & kompresi otomatis foto kontestan), `zip` (ekspor XLSX via `ZipArchive`; tersedia fallback terkompresi bawaan bila `ZipArchive` tidak aktif).
- **Database Server:** MySQL `8.0+` atau MariaDB `10.4+` (dukungan transaksi InnoDB & `utf8mb4_unicode_ci`).

---

## Instalasi Lokal (XAMPP / Server Lokal)

1. **Salin kode sumber** ke direktori webroot, misalnya:
   ```text
   C:\xampp\htdocs\voting-kakang-senduk
   ```
2. **Aktifkan modul & ekstensi** pada `php.ini` dan Apache (`httpd.conf`):
   - Pastikan `extension=pdo_mysql`, `extension=fileinfo`, `extension=mbstring`, dan `extension=gd` aktif.
   - Pastikan `mod_rewrite` aktif dan direktif `AllowOverride All` berlaku pada folder root proyek agar seluruh aturan proteksi di `.htaccess` berjalan.
3. **Buat database & impor skema** `database/db_voting_kakang_senduk.sql` (detail pada bagian Konfigurasi Database di bawah).
4. **Siapkan konfigurasi lokal** dengan menyalin template `.env.example` menjadi `.env` (atau menyalin `config/config.local.example.php` menjadi `config/config.local.php`):
   ```ini
   APP_ENV=development
   APP_BASE_URL=/voting-kakang-senduk
   FORCE_HTTPS=false
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=db_voting_kakang_senduk
   DB_USER=root
   DB_PASS=
   ```
   > **Catatan:** Jangan pernah menaruh password produksi atau file `.env` / `config/config.local.php` ke dalam repository Git.

---

## Konfigurasi Database

1. Jalankan MySQL/MariaDB dan impor skema resmi:
   ```bash
   mysql -u root -p < database/db_voting_kakang_senduk.sql
   ```
   Atau impor file `database/db_voting_kakang_senduk.sql` melalui phpMyAdmin / MySQL Client.
2. Pastikan user database produksi memiliki hak akses minimal yang diperlukan pada database `db_voting_kakang_senduk` (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `INDEX`, `ALTER`), bukan hak akses global yang berlebihan.
3. Seluruh pengaturan koneksi dimuat dari `.env`, `config/config.local.php`, atau *environment variable* server oleh [config/app.php](config/app.php) dan dipakai oleh [config/koneksi.php](config/koneksi.php).

---

## Cara Membuat Admin Pertama Secara Aman

Registrasi melalui halaman publik ([auth/register.php](auth/register.php)) **selalu dikunci sebagai `pemilih`**. Buat akun administrator pertama menggunakan **CLI server** sehingga kredensial tidak pernah tersimpan dalam file skrip/repository:

```bash
php database/create-admin.php "Panitia Administrator" "admin@domain-resmi.go.id" "081234567890" "GantiDenganPasswordKuat12!"
```

Alternatif melalui SQL dengan hash `bcrypt` yang dihasilkan di terminal:
1. Buat hash di terminal:
   ```bash
   php -r "echo password_hash('PasswordAdminKuatAnda!', PASSWORD_DEFAULT) . PHP_EOL;"
   ```
2. Sisipkan secara manual ke tabel `users` dengan `role = 'admin'` menggunakan hash hasil langkah 1.

---

## Cara Menjalankan Aplikasi

1. Pastikan layanan **Apache** dan **MySQL/MariaDB** aktif.
2. Pada mode `development` lokal, verifikasi kesiapan database dan izin folder melalui:
   ```text
   http://localhost/voting-kakang-senduk/cek-koneksi.php
   ```
   *(File diagnostik ini otomatis menolak akses HTTP dengan status `403 Forbidden` saat `APP_ENV=production`.)*
3. Buka aplikasi publik:
   ```text
   http://localhost/voting-kakang-senduk/
   ```
4. Buka halaman login pemilih & admin:
   ```text
   http://localhost/voting-kakang-senduk/auth/login.php
   ```
5. Kembalikan `APP_ENV=production` di `.env` atau `config/config.local.php` sebelum mempublikasikan ke server live.

---

## Struktur Folder

```text
voting-kakang-senduk/
├── AGENTS.md                        # Spesifikasi & aturan pengembangan proyek
├── README.md                        # Dokumentasi utama proyek
├── .env.example                     # Template variabel environment (tanpa kredensial asli)
├── .gitignore                       # Daftar pengecualian file rahasia, log, backup, upload
├── .htaccess                        # Proteksi direktori sensitif, kompresi & aturan cache
├── index.php                        # Landing page publik
├── cek-koneksi.php                  # Diagnostik koneksi (khusus development lokal)
│
├── config/
│   ├── app.php                      # Konfigurasi environment, HTTPS, header keamanan & logging
│   ├── config.local.example.php     # Template alternatif konfigurasi lokal/hosting (PHP array)
│   └── koneksi.php                  # Singleton PDO & helper transaksi database
│
├── app/
│   ├── controllers/                 # Logika kontroler (Auth, Kontestan, Voting, Klasemen, Admin)
│   ├── helpers/                     # Helper auth, response/URL/escaping, validasi, export XLSX/PDF
│   ├── middleware/                  # AuthMiddleware & AdminMiddleware
│   └── models/                      # Model database (User, Kontestan, PeriodeVoting, Voting)
│
├── admin/                           # Modul panel administrator (dashboard, CRUD, pemilih, periode, hasil)
├── auth/                            # Login, registrasi publik (pemilih), konfirmasi logout
├── pages/                           # Halaman publik & bilik suara (kontestan, detail, voting, klasemen)
├── proses/                          # Pemrosesan voting (POST) & modul upload/optimasi foto aman
├── views/                           # Layout global (header, footer, navbar, sidebar) & komponen UI
├── assets/                          # Asset CSS kustom, JavaScript ES6+, dan ilustrasi SVG ringan
├── uploads/                         # Penyimpanan foto terupload (eksekusi script dinonaktifkan)
├── storage/logs/                    # Log error PHP terproteksi dari akses HTTP
├── database/
│   ├── db_voting_kakang_senduk.sql  # Skema resmi database + index teroptimasi
│   ├── seed_admin.sql               # Seed admin dev lokal (ganti/hapus sebelum produksi)
│   ├── create-admin.php             # Utilitas CLI pembuatan admin pertama secara aman
│   ├── backup.php                   # Utilitas CLI backup database otomatis (.sql)
│   └── backups/                     # Penyimpanan file backup SQL (diblokir dari akses publik)
└── docs/                            # Dokumentasi instalasi, database, fitur, pengujian & deployment
```

---

## Akun Demo (Khusus Pengujian Lokal)

Untuk uji coba cepat di lingkungan **pengembangan lokal**, tersedia skrip opsional [database/seed_admin.sql](database/seed_admin.sql) yang berisi satu akun contoh administrator:
- **Email:** `admin@kakangsenduk.ponorogo.id`
- **Password & hash contoh:** tercatat di dalam `database/seed_admin.sql` khusus pengujian lokal.

> **PERINGATAN KEAMANAN PRODUKSI:** Jangan gunakan akun demo di lingkungan produksi. Sebelum sistem dibuka ke publik, buat akun administrator baru melalui `php database/create-admin.php`, hapus atau nonaktifkan akun demo, dan ubah seluruh password bawaan.

---

## Informasi Keamanan

- **SQL Injection:** Seluruh query memakai **PDO Prepared Statements** (`ATTR_EMULATE_PREPARES = false`) dan *whitelist* urutan/parameter.
- **XSS:** Seluruh output HTML di-escape menggunakan helper `e()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`) dan rendering DOM aman pada JavaScript (`textContent`).
- **CSRF:** Seluruh endpoint perubahan data (`POST`) di auth, voting, dan admin memverifikasi token CSRF (`hash_equals`).
- **Autentikasi & Session:** Penyimpanan password dengan `password_hash()`, `session_regenerate_id(true)` saat login, cookie session & *Remember Me* `HttpOnly` + `SameSite=Lax` + `Secure` (saat HTTPS), serta *rate limit* login tersimpan di sisi server.
- **Anti Double-Voting & Race Condition:** Transaksi PDO dengan penguncian baris (`SELECT ... FOR UPDATE`), validasi jadwal server-side, dan constraint database `UNIQUE(user_id, periode_id, kategori)`.
- **Proteksi Berkas & Upload:** Direktori `config/`, `app/`, `database/`, `views/`, `storage/`, dan `docs/` diblokir HTTP `403`; folder `uploads/` menonaktifkan PHP/CGI via `.htaccess` dan memvalidasi ekstensi + `finfo` MIME + `getimagesize()` + nama file acak.
- **Kerentanan Error & Transport:** `display_errors=0` di mode web produksi, seluruh error dicatat ke `storage/logs/php_error.log`, *header* keamanan HTTP aktif (`X-Frame-Options`, `X-Content-Type-Options`, `CSP frame-ancestors`), serta dukungan pemaksaan **HTTPS** dan **HSTS** di produksi.

