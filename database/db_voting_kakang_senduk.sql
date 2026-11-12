-- ============================================================================
-- Database: db_voting_kakang_senduk
-- Aplikasi: Voting Pemilihan Kakang Senduk Ponorogo
-- Engine  : InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

CREATE DATABASE IF NOT EXISTS `db_voting_kakang_senduk`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `db_voting_kakang_senduk`;

-- ----------------------------------------------------------------------------
-- 1. Tabel users
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `no_hp` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'pemilih') NOT NULL DEFAULT 'pemilih',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_role_created_at` (`role`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Tabel kontestan
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kontestan` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nomor_peserta` VARCHAR(10) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `kategori` ENUM('Kakang', 'Senduk') NOT NULL,
    `foto` VARCHAR(255) DEFAULT NULL,
    `asal_instansi` VARCHAR(150) NOT NULL,
    `tempat_lahir` VARCHAR(100) DEFAULT NULL,
    `tanggal_lahir` DATE DEFAULT NULL,
    `visi` TEXT NOT NULL,
    `misi` TEXT NOT NULL,
    `prestasi` TEXT DEFAULT NULL,
    `deskripsi` TEXT NOT NULL,
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_kontestan_nomor_peserta` (`nomor_peserta`),
    KEY `idx_kontestan_kategori_status` (`kategori`, `status`),
    KEY `idx_kontestan_nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Tabel periode_voting
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `periode_voting` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_periode` VARCHAR(100) NOT NULL,
    `tanggal_mulai` DATETIME NOT NULL,
    `tanggal_selesai` DATETIME NOT NULL,
    `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Nonaktif',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_periode_status_waktu` (`status`, `tanggal_mulai`, `tanggal_selesai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Tabel voting
--    Mendukung 1 suara kategori Kakang dan 1 suara kategori Senduk per pemilih
--    pada setiap periode melalui UNIQUE(user_id, periode_id, kategori).
--    Foreign key memakai ON DELETE RESTRICT agar data suara tidak terhapus
--    secara tidak sengaja.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `voting` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `kontestan_id` INT UNSIGNED NOT NULL,
    `periode_id` INT UNSIGNED NOT NULL,
    `kategori` ENUM('Kakang', 'Senduk') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_voting_user_periode_kategori` (`user_id`, `periode_id`, `kategori`),
    KEY `idx_voting_user` (`user_id`),
    KEY `idx_voting_kontestan` (`kontestan_id`),
    KEY `idx_voting_periode` (`periode_id`),
    KEY `idx_voting_periode_kategori` (`periode_id`, `kategori`),
    KEY `idx_voting_periode_kategori_kontestan` (`periode_id`, `kategori`, `kontestan_id`),
    KEY `idx_voting_created_at` (`created_at`),
    CONSTRAINT `fk_voting_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_voting_kontestan`
        FOREIGN KEY (`kontestan_id`) REFERENCES `kontestan` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_voting_periode`
        FOREIGN KEY (`periode_id`) REFERENCES `periode_voting` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Tabel remember_tokens (Opsional untuk fitur "Ingat Saya" yang aman)
--    Menyimpan hash SHA-256 dari validator token beserta selector unik.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `selector` CHAR(32) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_remember_selector` (`selector`),
    KEY `idx_remember_user_expires` (`user_id`, `expires_at`),
    CONSTRAINT `fk_remember_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

