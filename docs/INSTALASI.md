# Panduan Instalasi Lengkap

Dokumen ini menjelaskan langkah instalasi **Voting Pemilihan Kakang Senduk Ponorogo** baik di lingkungan pengembangan lokal (Windows XAMPP/Laragon) maupun di server Linux (LAMP).

---

## 1. Persyaratan Perangkat Lunak

| Komponen | Versi Minimum | Keterangan |
| :--- | :--- | :--- |
| **PHP** | `8.2.0` | Gunakan 8.2+ untuk strict typing & performa |
| **MySQL / MariaDB** | MySQL `8.0+` / MariaDB `10.4+` | Wajib mendukung InnoDB & `utf8mb4_unicode_ci` |
| **Web Server** | Apache `2.4+` atau Nginx | Apache wajib mengaktifkan `mod_rewrite` dan `AllowOverride All` |
| **Ekstensi PHP Wajib** | `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `openssl`, `session` | Diperlukan untuk PDO DB, UTF-8, validasi MIME gambar, token acak |
| **Ekstensi PHP Opsional** | `gd`, `zip` | `gd` untuk optimasi ukuran/kompresi foto kontestan; `zip` untuk `ZipArchive` ekspor Excel |

Periksa ekstensi PHP yang aktif melalui terminal:
```bash
php -m
```

---

## 2. Instalasi Lokal (Windows dengan XAMPP)

1. Unduh dan letakkan folder proyek di:
   ```text
   C:\xampp\htdocs\voting-kakang-senduk
   ```
2. Buka `C:\xampp\php\php.ini` dan pastikan ekstensi berikut aktif (tidak diawali `;`):
   ```ini
   extension=fileinfo
   extension=gd
   extension=mbstring
   extension=pdo_mysql
   extension=zip
   ```
3. Buka `C:\xampp\apache\conf\httpd.conf` dan pastikan:
   - `LoadModule rewrite_module modules/mod_rewrite.so` aktif.
   - `LoadModule headers_module modules/mod_headers.so` aktif.
   - Pada blok `<Directory "C:/xampp/htdocs">`, tertulis `AllowOverride All`.
4. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
5. Buat database `db_voting_kakang_senduk` dan impor `database/db_voting_kakang_senduk.sql`.
6. Salin `.env.example` menjadi `.env` (atau `config/config.local.example.php` menjadi `config/config.local.php`) dan atur untuk lokal:
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
7. Uji koneksi & integritas skema via browser:
   ```text
   http://localhost/voting-kakang-senduk/cek-koneksi.php
   ```
   Pastikan seluruh indikator tabel, *unique constraint*, *foreign key*, dan direktori upload berstatus **OK**, lalu ubah `APP_ENV=production` ketika siap diuji dalam konfigurasi produksi.

---

## 3. Instalasi di Server Linux (Ubuntu / Debian + Apache)

1. Pasang paket yang dibutuhkan:
   ```bash
   sudo apt update
   sudo apt install apache2 php8.2 php8.2-mysql php8.2-mbstring php8.2-gd php8.2-fileinfo php8.2-zip mysql-server
   sudo a2enmod rewrite headers expires deflate ssl
   ```
2. Tempatkan kode aplikasi ke `/var/www/voting-kakang-senduk`.
3. Atur kepemilikan dan izin direktori secara aman:
   ```bash
   sudo chown -R www-data:www-data /var/www/voting-kakang-senduk
   find /var/www/voting-kakang-senduk -type d -exec chmod 750 {} \;
   find /var/www/voting-kakang-senduk -type f -exec chmod 640 {} \;
   chmod 770 /var/www/voting-kakang-senduk/uploads/kontestan
   chmod 770 /var/www/voting-kakang-senduk/storage/logs
   chmod 750 /var/www/voting-kakang-senduk/database/backups
   ```
4. Impor database & buat user khusus aplikasi (bukan `root`):
   ```sql
   CREATE DATABASE db_voting_kakang_senduk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'ks_app_user'@'localhost' IDENTIFIED BY 'GantiPasswordKuatDatabase!';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON db_voting_kakang_senduk.* TO 'ks_app_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
5. Impor skema dan buat admin pertama:
   ```bash
   mysql -u ks_app_user -p db_voting_kakang_senduk < database/db_voting_kakang_senduk.sql
   php database/create-admin.php "Administrator Resmi" "admin@domain-anda.go.id" "081234567890" "PasswordAdminKuat12!"
   ```
6. Salin `.env.example` menjadi `.env` dengan `APP_ENV=production` dan `FORCE_HTTPS=true`.

