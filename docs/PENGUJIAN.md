# Panduan Pengujian Aplikasi

Panduan ini mencakup skenario pengujian fungsional, integritas transaksi, keamanan aplikasi, serta responsivitas UI sebelum dan sesudah rilis.

---

## 1. Pengujian Sintaks & Lingkungan

```bash
# Verifikasi sintaks seluruh file PHP (wajib 0 error)
php -l index.php
# Windows PowerShell untuk seluruh file PHP:
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }

# Verifikasi koneksi, constraint unik, foreign key & direktori (mode development)
php cek-koneksi.php
```

---

## 2. Daftar Skenario Pengujian Fungsional Wajib

| No | Area | Skenario Uji | Hasil yang Diharapkan |
| :--- | :--- | :--- | :--- |
| 1 | **Autentikasi** | Registrasi pemilih baru dengan data valid | Akun tersimpan dengan `role='pemilih'` & hash `password_hash()` |
| 2 | **Autentikasi** | Registrasi ulang dengan email sama | Ditolak dengan pesan error jelas; tidak menimpa akun lama |
| 3 | **Autentikasi** | Registrasi menyisipkan `role=admin` via request | Server mengabaikan input dan tetap mengunci `role='pemilih'` |
| 4 | **Autentikasi** | Login benar & salah + 5x gagal berturut-turut | Login benar regenerasi session ID; 5x gagal memicu kunci sementara server-side |
| 5 | **Kontestan** | Upload `.php`, `shell.php.jpg`, file palsu, atau > 2 MB | Seluruh file tidak valid ditolak dengan pesan error aman |
| 6 | **Kontestan** | Upload JPG/PNG/WebP valid & update foto baru | Nama acak tersimpan di `uploads/kontestan/`; foto lama terhapus saat diganti |
| 7 | **Kontestan** | Hapus atau ubah kategori kontestan yang sudah dipilih | Ditolak server; kategori terkunci di form edit; kontestan bisa dinonaktifkan (arsip) |
| 8 | **Periode** | Aktivasi dua periode bersamaan atau jadwal bertumpukan | Ditolak oleh transaksi `checkActiveConflict()` |
| 9 | **Voting** | Vote pada periode `Nonaktif`, `Belum Dimulai`, atau `Sudah Berakhir` | Ditolak (`NO_ACTIVE_PERIOD` / `PERIOD_NOT_STARTED` / `PERIOD_ENDED`) |
| 10 | **Voting** | Vote kontestan `Nonaktif` atau manipulasi kategori | Ditolak (`CONTESTANT_INACTIVE` / `CATEGORY_MISMATCH`) |
| 11 | **Voting** | Vote 1 Kakang lalu 1 Senduk oleh pemilih yang sama | Keduanya berhasil tercatat pada masing-masing kategori |
| 12 | **Voting** | Kirim 8+ request voting bersamaan (*concurrent requests*) | Maksimal **1 suara** tersimpan akibat `FOR UPDATE` + `UNIQUE(user_id, periode_id, kategori)` |
| 13 | **Voting** | Kirim suara melalui HTTP `GET` ke `proses/simpan-voting.php` | Ditolak (`302` redirect / `405` JSON); 0 baris masuk ke tabel `voting` |
| 14 | **Klasemen** | Kontestan aktif dengan `0` suara saat total suara kategori 0 atau > 0 | Tetap tampil via `LEFT JOIN`, persentase `0%` saat total 0, terurut tie-breaker konsisten |
| 15 | **Admin & Ekspor** | Statistik dashboard, filter periode hasil voting, ekspor XLSX & PDF | Angka sinkron dengan query DB; file `.xlsx` & PDF terbuka valid tanpa error |

---

## 3. Pengujian Audit Keamanan

1. **Akses Direktori & File Sensitif:** Pastikan request HTTP langsung ke `/config/app.php`, `/config/koneksi.php`, `/app/`, `/database/`, `/views/`, `/storage/`, `/docs/`, `/.env`, `/AGENTS.md`, dan `/cek-koneksi.php` (pada mode `production`) mengembalikan **`HTTP 403`**.
2. **Eksekusi Upload:** Letakkan file uji sementara `.php` di `uploads/kontestan/`, akses lewat browser — wajib mengembalikan **`HTTP 403`** dan tidak pernah mengeksekusi PHP.
3. **RBAC & IDOR:** Login sebagai `pemilih` lalu akses semua URL `/admin/*` termasuk `export-hasil.php` dan edit/detail dengan ID valid — seluruhnya wajib **`HTTP 403`**.
4. **CSRF:** Kirim `POST` ke endpoint login, register, logout, voting, dan seluruh form admin tanpa atau dengan `_csrf_token` palsu — wajib ditolak dan tidak mengubah database.
5. **SQLi & XSS:** Uji input pencarian `"' OR 1=1 --"` dan payload `<script>window.__xss=1</script>` pada nama kontestan/pemilih — output ter-escape (`e()`) tanpa eksekusi script maupun kebocoran error SQL.
6. **UI/UX Responsif:** Verifikasi di viewport Desktop (`1280px`), Tablet (`768px`), dan Mobile (`390px`) dengan `overflow-x` (scroll horizontal) `<= 0`, hamburger navbar berfungsi, offcanvas sidebar admin berfungsi, serta modal SweetAlert2 & empty state tampil rapi.

