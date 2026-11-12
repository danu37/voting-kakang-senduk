-- ============================================================================
-- Seed Akun Administrator Awal
-- Karena registrasi publik dikunci sebagai role 'pemilih', akun admin awal
-- disediakan melalui skrip seed ini.
-- Kredensial default (segera ubah setelah login pertama di produksi):
--   Email   : admin@kakangsenduk.ponorogo.id
--   Password: AdminPonorogo2026!
-- ============================================================================

USE `db_voting_kakang_senduk`;

INSERT IGNORE INTO `users` (`nama`, `email`, `no_hp`, `password`, `role`)
VALUES (
    'Administrator Kakang Senduk',
    'admin@kakangsenduk.ponorogo.id',
    '081234567890',
    '$2y$10$oOveDYblLqHYCFI0x1SfEuIeZpd.bER00sGpeyS25lpHN45nNc15C',
    'admin'
);

