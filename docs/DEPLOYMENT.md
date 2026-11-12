# Panduan Deployment Produksi

Dokumen ini merupakan panduan resmi untuk mempublikasikan aplikasi **Voting Pemilihan Kakang Senduk Ponorogo** ke server produksi (Shared Hosting cPanel maupun VPS Apache/Nginx).

> **Catatan Penting Sebelum Deploy:** Jangan menghapus atau menjalankan migrasi destruktif pada database produksi tanpa mengambil backup penuh terlebih dahulu (`php database/backup.php` atau `mysqldump`). Lakukan deployment hanya setelah seluruh pengujian lolos dan mendapat persetujuan penanggung jawab.

---

## 1. Pemisahan Konfigurasi Production vs Development

Aplikasi membaca konfigurasi dari prioritas:
`config/config.local.php` -> *Environment variable* server -> `.env` -> nilai fallback pada `config/app.php`.

1. **Jangan pernah mengunggah file `.env`, `config/config.local.php`, atau password database ke repository Git.** (Sudah dicegah melalui `.gitignore`.)
2. Di server produksi, salin salah satu template:
   - **Metode `.env`:** Salin `.env.example` menjadi `.env`, lalu isi kredensial database hosting Anda:
     ```ini
     APP_ENV=production
     APP_BASE_URL=
     FORCE_HTTPS=true
     APP_TIMEZONE=Asia/Jakarta
     APP_TIMEZONE_OFFSET=+07:00
     DB_HOST=localhost
     DB_PORT=3306
     DB_NAME=nama_db_hosting
     DB_USER=user_db_hosting
     DB_PASS=password_db_hosting_yang_kuat
     DB_CHARSET=utf8mb4
     ```
   - **Atau Metode PHP Config:** Salin `config/config.local.example.php` menjadi `config/config.local.php` dan isi array konfigurasinya.
3. Jika dihosting di **subfolder** (mis. `https://domain.go.id/voting`), isi `APP_BASE_URL=/voting`. Jika di **root domain** (`https://voting.ponorogo.go.id`), biarkan `APP_BASE_URL=` kosong.

---

## 2. Konfigurasi HTTPS / SSL

1. Pasang sertifikat SSL/TLS valid (Let's Encrypt / SSL penyedia domain).
2. Pastikan `FORCE_HTTPS=true` dan `APP_ENV=production` aktif agar:
   - Pengunjung HTTP otomatis dialihkan (`301`) ke `https://`.
   - Cookie session (`ks_ponorogo_sid`) dan *Remember Me* (`ks_ponorogo_rem`) mengirim flag `Secure`.
   - Header **HSTS** (`Strict-Transport-Security: max-age=31536000; includeSubDomains`) dikirim otomatis.
3. Jika memakai **Apache** dan ingin redirect di tingkat web server sebelum PHP berjalan, hapus tanda `#` pada blok `HTTPS Redirect Produksi` di dalam [.htaccess](../.htaccess).

---

## 3. Konfigurasi Server Web

### A. Apache / cPanel Shared Hosting
- Pastikan `AllowOverride All` aktif pada VirtualHost agar `.htaccess` di root, `storage/.htaccess`, dan `uploads/.htaccess` diterapkan penuh.
- Pastikan hak izin file aman: folder `750`/`755`, file PHP `644` (`.env` / `config.local.php` cukup `640` atau `600`).

### B. Nginx + PHP-FPM (VPS)
Karena Nginx **tidak membaca file `.htaccess`**, wajib tambahkan blok perlindungan ini pada `server { ... }` Nginx:

```nginx
# Blokir akses publik ke direktori internal & file sensitif
location ~* ^/(config|app|database|views|storage|docs)(/|$) {
    return 403;
}

location ~* /(\.env.*|\.htaccess|\.gitignore|AGENTS\.md|README\.md|.*\.sql|.*\.log)$ {
    return 403;
}

# Matikan total eksekusi PHP atau script lain di dalam folder uploads
location ~* ^/uploads/.*\.(php[0-9]?|phtml|phar|html?|svg|js|sh|pl|py|cgi)$ {
    return 403;
}

# Izinkan file PHP lain ke PHP-FPM
location ~ \.php$ {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
}
```

---

## 4. Logging Error & Monitoring

- Pada `APP_ENV=production`:
  - `display_errors` dimatikan (`0`) dan global exception handler mengembalikan halaman/JSON 500 generik tanpa membocorkan path file atau query SQL.
  - Seluruh error PHP & PDO dicatat ke `storage/logs/php_error.log`.
- Lakukan pengecekan berkala terhadap `storage/logs/php_error.log` selama hari pertama pembukaan voting dan bersihkan/arsipkan log setelah pemeriksaan.

---

## 5. Checklist Kesiapan Produksi

- [ ] `APP_ENV=production` aktif dan `display_errors` bernilai `0`.
- [ ] `FORCE_HTTPS=true` aktif dan SSL/TLS terverifikasi hijau di browser.
- [ ] Kredensial DB produksi dimuat dari `.env` / `config/config.local.php` (bukan akun `root` tanpa password).
- [ ] Skema `database/db_voting_kakang_senduk.sql` (termasuk seluruh index terbaru) sudah terimpor.
- [ ] Akun admin resmi sudah dibuat lewat `php database/create-admin.php`, dan akun demo bawaan sudah diganti/dihapus.
- [ ] Akses HTTP langsung ke `/config/`, `/app/`, `/database/`, `/views/`, `/storage/`, `/docs/`, `/.env`, `/cek-koneksi.php`, dan file `.php` di `/uploads/` semuanya mengembalikan `403`.
- [ ] Direktori `uploads/kontestan/` dan `storage/logs/` dapat ditulisi oleh proses PHP.
- [ ] Pengujian backup database (`php database/backup.php`) dan satu siklus uji voting sebelum rilis publik berhasil.

