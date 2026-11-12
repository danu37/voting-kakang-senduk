# Dokumentasi Fitur Aplikasi

Dokumen ini merangkum seluruh hak akses dan alur fitur bagi **Pengunjung**, **Pemilih**, dan **Administrator**.

---

## 1. Peran & Matriks Hak Akses

| Fitur / Akses | Pengunjung (Guest) | Pemilih (`pemilih`) | Administrator (`admin`) |
| :--- | :---: | :---: | :---: |
| Beranda, Daftar & Detail Kontestan | Ya | Ya | Ya |
| Melihat Klasemen & Filter Periode | Ya | Ya | Ya |
| Registrasi Akun Pemilih | Ya | - | - |
| Memberikan Suara Kakang & Senduk | Tidak | Ya (1x/kategori/periode) | Tidak (dibatasi netralitas) |
| Mengubah atau Menghapus Suara Masuk | Tidak | Tidak | Tidak |
| Dashboard & Statistik Admin | Tidak (`302`) | Tidak (`403`) | Ya |
| Kelola Kontestan, Pemilih, Periode, Ekspor | Tidak (`302`) | Tidak (`403`) | Ya |

---

## 2. Alur Penggunaan Pemilih

1. **Pendaftaran Akun (`auth/register.php`):**
   - Isi Nama Lengkap, Email unik, Nomor HP, dan Password (minimal 8 karakter).
   - Sistem selalu mengunci role akun sebagai `pemilih`.
2. **Masuk ke Sistem (`auth/login.php`):**
   - Masukkan email dan password. Tersedia opsi **Ingat Saya** (token kriptografis 30 hari dengan rotasi otomatis).
   - Pembatasan percobaan gagal diterapkan di sisi server (maks. 5 percobaan per email+IP sebelum terkunci sementara selama 5 menit).
3. **Menjelajahi Kontestan (`pages/kontestan.php` & `pages/detail-kontestan.php`):**
   - Pengunjung dan pemilih dapat menyaring kategori `Kakang` / `Senduk`, mencari nama/nomor peserta, dan mempelajari visi, misi, prestasi, serta deskripsi kontestan.
4. **Memberikan Suara (`pages/voting.php` & `proses/simpan-voting.php`):**
   - Pemilih memilih tab kategori **Kakang** atau **Senduk**, memilih satu kandidat aktif, lalu meninjau modal konfirmasi SweetAlert2.
   - Server memverifikasi autentikasi, token CSRF, periode berstatus `Aktif` dan sesuai waktu server (`tanggal_mulai <= now <= tanggal_selesai`), kontestan `Aktif`, kesesuaian kategori, dan belum adanya suara ganda dalam transaksi PDO.
   - Setelah tersimpan, kartu status menampilkan pilihan permanen dan pemilih masih dapat menggunakan hak suara untuk kategori satunya.
5. **Memantau Klasemen (`pages/klasemen.php`):**
   - Klasemen memisahkan Kakang dan Senduk lengkap dengan podium Juara 1–3, total suara, persentase, serta *polling* pembaruan otomatis setiap 15 detik.

---

## 3. Alur & Fitur Administrator

1. **Dashboard (`admin/dashboard.php`):**
   - Menampilkan total kontestan (total, Kakang, Senduk), total pemilih, total suara Kakang/Senduk/keseluruhan, status periode berjalan, grafik Chart.js perolehan suara, pimpinan klasemen, dan aktivitas terbaru.
2. **Manajemen Kontestan (`admin/kontestan/`):**
   - Tambah, ubah, cari, filter kategori/status, aktifkan/arsipkan, unggah foto (JPEG/PNG/WebP maks. 2 MB), dan hapus kontestan.
   - Foto lama otomatis dihapus setelah pembaruan foto berhasil.
   - Kontestan yang sudah menerima suara **tidak dapat dihapus** (hanya boleh diarsipkan menjadi `Nonaktif`) dan **kategorinya dikunci** agar integritas suara di klasemen tetap terjaga.
3. **Manajemen Pemilih (`admin/pemilih/`):**
   - Memantau data registrasi pemilih, status hak suara Kakang & Senduk per periode, pencarian & filter partisipasi.
   - Hash password tidak pernah dimuat atau dikirim ke tampilan. Akun yang sudah memiliki riwayat voting ditolak jika dihapus.
4. **Manajemen Periode Voting (`admin/periode/`):**
   - Membuat dan mengubah periode, mengaktifkan/menonaktifkan sakelar voting, dan melihat total suara per periode (`detail.php`).
   - Sistem menolak pembuatan/aktivasi periode yang jadwalnya bertentangan dengan periode aktif lain serta melarang perubahan/penghapusan periode yang sudah memiliki suara.
5. **Hasil Voting & Ekspor (`admin/hasil-voting.php` & `admin/export-hasil.php`):**
   - Menampilkan klasemen lengkap per periode, grafik Chart.js, serta ekspor resmi ke format **Excel (.xlsx)** dan **Laporan Cetak / PDF**.

