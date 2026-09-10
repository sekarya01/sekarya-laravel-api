-- =============================================================================
--  Sekarya API — berkas pemasangan basis data
-- =============================================================================
--
--  BASIS DATANYA HARUS ADA LEBIH DULU
--
--  Berkas ini TIDAK membuat dan TIDAK memilih basis data. Kalau diimpor
--  sebelum basis datanya ada — atau dari halaman utama phpMyAdmin, bukan dari
--  halaman basis datanya — MySQL menjawab:
--
--      #1046 - No database selected
--
--  dan yang gagal SELALU pernyataan pertama, apa pun isinya. Itu bukan
--  masalah pada berkas ini.
--
--  LANGKAHNYA DI cPanel, berurutan:
--
--    1. cPanel > MySQL Databases > "Create New Database".
--       Namanya otomatis diberi awalan akun, mis. `akunanda_sekarya`.
--       CATAT NAMA LENGKAPNYA — itu yang masuk ke DB_DATABASE di .env.
--    2. Di halaman yang sama, "Add New User". Catat nama dan sandinya.
--    3. "Add User To Database" > pilih keduanya > centang ALL PRIVILEGES.
--       Tanpa langkah ini aplikasinya tidak bisa masuk, walau tabelnya ada.
--    4. cPanel > phpMyAdmin. KLIK NAMA BASIS DATANYA DI PANEL KIRI,
--       sampai judul halaman berbunyi "Database: akunanda_sekarya".
--    5. Baru tab Import > Choose File > Go.
--
--  Kalau punya SSH atau server sendiri dan ingin berkas ini membuat basis
--  datanya sekalian:
--
--      php artisan sekarya:build-install-sql --with-database=nama_basis_data
--
--  LEWAT SSH, kalau tersedia:
--
--    mysql -u PENGGUNA -p NAMA_DATABASE < sekarya-install.sql
--
--  ISINYA
--    - Seluruh tabel beserta indeks, foreign key, dan indeks FULLTEXT
--    - Kategori dan keahlian (data acuan; aplikasi tidak berjalan tanpanya)
--    - Riwayat migrasi, supaya `php artisan migrate` tahu seluruh migrasi
--      sudah dijalankan dan tidak mengulanginya di atas tabel yang sudah ada
--
--    TIDAK berisi pengguna, task, penawaran, pembayaran, atau data pribadi
--    apa pun. Berkas ini aman dilacak git, termasuk kalau repositorinya
--    suatu saat dibuka untuk umum.
--
--  SIFAT BERKAS INI
--    - Tabel diurutkan menurut KETERGANTUNGAN, bukan abjad: setiap foreign
--      key menunjuk tabel yang sudah dibuat di atasnya.
--    - Tidak menghapus apa pun. CREATE TABLE IF NOT EXISTS dan INSERT IGNORE,
--      jadi aman dijalankan ulang dan tidak menuntut hak DROP.
--    - Tidak membuat dan tidak memilih basis data.
--    - Butuh MySQL 8.0+ dengan InnoDB.
--
--  SESUDAH IMPOR
--    Perubahan skema berikutnya memakai `php artisan migrate`, bukan berkas
--    ini. Jangan pernah menjalankan `migrate:fresh` di produksi.
--
--  JANGAN SUNTING TANGAN. Dibuat oleh:
--    php artisan sekarya:build-install-sql
-- =============================================================================

-- BERKAS INI TIDAK MENYEBUT BASIS DATA TUJUAN.
--
-- Kalau diimpor tanpa basis data terpilih, MySQL menjawab
-- #1046 - No database selected, dan yang gagal SELALU pernyataan
-- pertama — apa pun isinya. Itu bukan masalah pada berkas ini.
--
-- Cara termudah menghilangkan kemungkinan itu: buat ulang berkasnya
-- dengan tujuan tertulis di dalamnya.
--
--   php artisan sekarya:build-install-sql --use-database=namaakun_sekarya
--
-- `USE` saja, bukan CREATE DATABASE: basis datanya dibuat lewat cPanel
-- (harus, supaya penggunanya bisa diberi hak), dan CREATE DATABASE di
-- sini justru gagal #1044 pada akun tanpa hak CREATE — walaupun basis
-- datanya sudah ada, karena hak akses diperiksa lebih dulu.
--
-- Punya SSH atau server sendiri, dan basis datanya belum ada?
--
--   php artisan sekarya:build-install-sql --with-database=nama_basis_data

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET TIME_ZONE = '+00:00';

-- ---------- STRUKTUR TABEL ----------
-- Urut menurut ketergantungan: setiap foreign key menunjuk tabel
-- yang sudah dibuat di atasnya.

CREATE TABLE IF NOT EXISTS `admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'suspended',
  `super_admin_lock` char(1) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS ((case when (`role` = _utf8mb4'super_admin') then _utf8mb4's' end)) STORED,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_ulid_unique` (`ulid`),
  UNIQUE KEY `admins_email_unique` (`email`),
  UNIQUE KEY `admins_super_admin_lock_unique` (`super_admin_lock`),
  KEY `admins_status_role_index` (`status`,`role`),
  KEY `admins_created_at_index` (`created_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_price_min` bigint unsigned DEFAULT NULL,
  `ref_price_max` bigint unsigned DEFAULT NULL,
  `ref_price_median` bigint unsigned DEFAULT NULL,
  `ref_sample_size` int unsigned NOT NULL DEFAULT '0',
  `ref_computed_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_is_active_sort_order_index` (`is_active`,`sort_order`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `active_mode` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hiring',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_verification',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `address_line` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poster_rating_avg` decimal(3,2) NOT NULL DEFAULT '0.00',
  `poster_rating_count` int unsigned NOT NULL DEFAULT '0',
  `tasks_posted` int unsigned NOT NULL DEFAULT '0',
  `cancellations` int unsigned NOT NULL DEFAULT '0',
  `theme` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `last_active_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_ulid_unique` (`ulid`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  KEY `users_created_at_index` (`created_at`),
  KEY `users_status_active_mode_index` (`status`,`active_mode`),
  KEY `users_city_province_index` (`city`,`province`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned NOT NULL,
  `action` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_audit_logs_admin_id_created_at_index` (`admin_id`,`created_at`),
  KEY `admin_audit_logs_subject_type_subject_id_created_at_index` (`subject_type`,`subject_id`,`created_at`),
  KEY `admin_audit_logs_action_created_at_index` (`action`,`created_at`),
  KEY `admin_audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `admin_audit_logs_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_verification_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NOT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `last_sent_at` timestamp NULL DEFAULT NULL,
  `request_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_verification_codes_user_id_consumed_at_expires_at_index` (`user_id`,`consumed_at`,`expires_at`),
  KEY `email_verification_codes_expires_at_index` (`expires_at`),
  CONSTRAINT `email_verification_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `skills` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `skills_slug_unique` (`slug`),
  KEY `skills_is_active_sort_order_index` (`is_active`,`sort_order`),
  KEY `skills_category_id_is_active_index` (`category_id`,`is_active`),
  CONSTRAINT `skills_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `poster_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` json DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `budget_min` bigint unsigned NOT NULL,
  `budget_max` bigint unsigned DEFAULT NULL,
  `ref_price_median` bigint unsigned DEFAULT NULL,
  `location_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_remote` tinyint(1) NOT NULL DEFAULT '0',
  `needed_at` timestamp NULL DEFAULT NULL,
  `bidding_closes_at` timestamp NULL DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `workers_needed` int unsigned NOT NULL DEFAULT '1',
  `workers_hired` int unsigned NOT NULL DEFAULT '0',
  `bids_count` int unsigned NOT NULL DEFAULT '0',
  `agreed_amount` bigint unsigned DEFAULT NULL,
  `dealt_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks_ulid_unique` (`ulid`),
  UNIQUE KEY `tasks_task_number_unique` (`task_number`),
  KEY `tasks_status_created_at_index` (`status`,`created_at`),
  KEY `tasks_category_id_status_created_at_index` (`category_id`,`status`,`created_at`),
  KEY `tasks_poster_id_status_created_at_index` (`poster_id`,`status`,`created_at`),
  KEY `tasks_city_status_created_at_index` (`city`,`status`,`created_at`),
  KEY `tasks_latitude_longitude_index` (`latitude`,`longitude`),
  KEY `tasks_status_bidding_closes_at_index` (`status`,`bidding_closes_at`),
  KEY `tasks_created_at_index` (`created_at`),
  KEY `tasks_status_workers_needed_index` (`status`,`workers_needed`),
  CONSTRAINT `tasks_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `tasks_poster_id_foreign` FOREIGN KEY (`poster_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `id_card_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selfie_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `face_match_score` decimal(5,2) DEFAULT NULL,
  `document_number_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_number_enc` blob,
  `name_on_document` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date_on_document` date DEFAULT NULL,
  `bank_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number_enc` blob,
  `account_holder_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NOT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_verifications_user_id_type_status_index` (`user_id`,`type`,`status`),
  KEY `user_verifications_document_number_hash_index` (`document_number_hash`),
  KEY `user_verifications_status_submitted_at_index` (`status`,`submitted_at`),
  KEY `user_verifications_created_at_index` (`created_at`),
  KEY `user_verifications_reviewed_by_reviewed_at_index` (`reviewed_by`,`reviewed_at`),
  CONSTRAINT `user_verifications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_verifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_workers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `radius_km` smallint unsigned DEFAULT NULL,
  `worker_rating_avg` decimal(3,2) NOT NULL DEFAULT '0.00',
  `worker_rating_count` int unsigned NOT NULL DEFAULT '0',
  `tasks_completed` int unsigned NOT NULL DEFAULT '0',
  `bids_won` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_workers_user_id_unique` (`user_id`),
  KEY `user_workers_worker_rating_avg_tasks_completed_index` (`worker_rating_avg`,`tasks_completed`),
  KEY `user_workers_latitude_longitude_index` (`latitude`,`longitude`),
  KEY `user_workers_city_province_index` (`city`,`province`),
  CONSTRAINT `user_workers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bids` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_id` bigint unsigned NOT NULL,
  `bidder_id` bigint unsigned NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `message` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_responses` json DEFAULT NULL,
  `estimated_hours` decimal(5,2) DEFAULT NULL,
  `can_start_at` timestamp NULL DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bids_task_id_bidder_id_unique` (`task_id`,`bidder_id`),
  UNIQUE KEY `bids_ulid_unique` (`ulid`),
  KEY `bids_task_id_status_amount_index` (`task_id`,`status`,`amount`),
  KEY `bids_task_id_status_created_at_index` (`task_id`,`status`,`created_at`),
  KEY `bids_bidder_id_status_created_at_index` (`bidder_id`,`status`,`created_at`),
  KEY `bids_created_at_index` (`created_at`),
  CONSTRAINT `bids_bidder_id_foreign` FOREIGN KEY (`bidder_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bids_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_id` bigint unsigned NOT NULL,
  `payer_id` bigint unsigned NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `amount` bigint unsigned NOT NULL,
  `reported_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `held_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_ulid_unique` (`ulid`),
  UNIQUE KEY `payments_task_id_unique` (`task_id`),
  KEY `payments_payer_id_status_created_at_index` (`payer_id`,`status`,`created_at`),
  KEY `payments_status_created_at_index` (`status`,`created_at`),
  KEY `payments_created_at_index` (`created_at`),
  KEY `payments_status_reported_at_index` (`status`,`reported_at`),
  CONSTRAINT `payments_payer_id_foreign` FOREIGN KEY (`payer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `reviewer_id` bigint unsigned NOT NULL,
  `reviewee_id` bigint unsigned NOT NULL,
  `reviewer_role` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_task_id_reviewer_id_reviewee_id_unique` (`task_id`,`reviewer_id`,`reviewee_id`),
  KEY `reviews_reviewer_id_foreign` (`reviewer_id`),
  KEY `reviews_reviewee_id_reviewer_role_is_visible_created_at_index` (`reviewee_id`,`reviewer_role`,`is_visible`,`created_at`),
  KEY `reviews_created_at_index` (`created_at`),
  CONSTRAINT `reviews_reviewee_id_foreign` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `skill_task` (
  `task_id` bigint unsigned NOT NULL,
  `skill_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`task_id`,`skill_id`),
  KEY `skill_task_skill_id_task_id_index` (`skill_id`,`task_id`),
  CONSTRAINT `skill_task_skill_id_foreign` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `skill_task_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `skill_user` (
  `user_id` bigint unsigned NOT NULL,
  `skill_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`skill_id`),
  KEY `skill_user_skill_id_user_id_index` (`skill_id`,`user_id`),
  CONSTRAINT `skill_user_skill_id_foreign` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `skill_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_search` (
  `task_id` bigint unsigned NOT NULL,
  `terms` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`task_id`),
  FULLTEXT KEY `task_search_terms_fulltext` (`terms`),
  CONSTRAINT `task_search_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `from_status` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_type` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_status_logs_task_id_created_at_index` (`task_id`,`created_at`),
  KEY `task_status_logs_to_status_created_at_index` (`to_status`,`created_at`),
  CONSTRAINT `task_status_logs_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_id` bigint unsigned NOT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `agreed_amount` bigint unsigned NOT NULL,
  `opened_at` timestamp NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `worker_note` text COLLATE utf8mb4_unicode_ci,
  `proof_photos` json DEFAULT NULL,
  `poster_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `activities_ulid_unique` (`ulid`),
  UNIQUE KEY `activities_task_id_worker_id_unique` (`task_id`,`worker_id`),
  KEY `activities_worker_id_status_created_at_index` (`worker_id`,`status`,`created_at`),
  KEY `activities_status_submitted_at_index` (`status`,`submitted_at`),
  KEY `activities_created_at_index` (`created_at`),
  KEY `activities_payment_id_index` (`payment_id`),
  CONSTRAINT `activities_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activities_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activities_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- DATA ACUAN ----------
-- Kategori dan keahlian: aplikasi tidak berjalan tanpanya.
-- Riwayat migrasi: supaya `php artisan migrate` tahu semuanya sudah jalan.

-- categories: 9 baris
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (856, 'mencuci', 'Mencuci', 'Cuci pakaian, setrika, cuci kering', 'washing-machine', 50000, 150000, 80000, 0, NULL, 1, 0, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (857, 'bersih-rumah', 'Membersihkan Rumah', 'Bersih-bersih rumah, kamar, dapur, kamar mandi', 'broom', 75000, 300000, 150000, 0, NULL, 1, 1, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (858, 'jaga-hewan', 'Menjaga Hewan', 'Titip hewan, jalan-jalan, beri makan', 'paw', 50000, 200000, 100000, 0, NULL, 1, 2, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (859, 'antar-barang', 'Mengantarkan Barang', 'Antar dokumen, paket, barang dalam kota', 'package', 15000, 100000, 35000, 0, NULL, 1, 3, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (860, 'tukang', 'Tukang & Perbaikan', 'Perbaikan kecil, pasang, servis rumah', 'wrench', 100000, 1000000, 250000, 0, NULL, 1, 4, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (861, 'jaga-anak', 'Menjaga Anak', 'Menemani dan menjaga anak', 'baby', 75000, 300000, 150000, 0, NULL, 1, 5, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (862, 'berkebun', 'Berkebun', 'Rawat tanaman, potong rumput, bersihkan halaman', 'sprout', 75000, 250000, 125000, 0, NULL, 1, 6, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (863, 'pindahan', 'Bantu Pindahan', 'Angkat, kemas, bantu pindah barang', 'truck', 150000, 1000000, 350000, 0, NULL, 1, 7, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (864, 'lainnya', 'Lainnya', 'Pekerjaan yang belum masuk kategori di atas', 'ellipsis', NULL, NULL, NULL, 0, NULL, 1, 8, '2026-09-10 11:55:05', '2026-09-10 11:55:05');

-- skills: 42 baris
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3487, 'setrika', 'Setrika', 856, 1, 0, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3488, 'cuci-tangan', 'Cuci tangan', 856, 1, 1, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3489, 'cuci-mesin', 'Cuci mesin', 856, 1, 2, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3490, 'lipat-pakaian', 'Lipat pakaian', 856, 1, 3, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3491, 'cuci-sepatu', 'Cuci sepatu', 856, 1, 4, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3492, 'bersih-umum', 'Bersih umum', 857, 1, 5, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3493, 'cuci-ac', 'Cuci ac', 857, 1, 6, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3494, 'bersih-kamar-mandi', 'Bersih kamar mandi', 857, 1, 7, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3495, 'poles-lantai', 'Poles lantai', 857, 1, 8, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3496, 'bersih-dapur', 'Bersih dapur', 857, 1, 9, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3497, 'cuci-jendela', 'Cuci jendela', 857, 1, 10, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3498, 'jaga-kucing', 'Jaga kucing', 858, 1, 11, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3499, 'jaga-anjing', 'Jaga anjing', 858, 1, 12, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3500, 'grooming', 'Grooming', 858, 1, 13, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3501, 'jalan-anjing', 'Jalan anjing', 858, 1, 14, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3502, 'beri-makan-hewan', 'Beri makan hewan', 858, 1, 15, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3503, 'antar-dokumen', 'Antar dokumen', 859, 1, 16, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3504, 'antar-paket', 'Antar paket', 859, 1, 17, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3505, 'antar-makanan', 'Antar makanan', 859, 1, 18, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3506, 'kurir-motor', 'Kurir motor', 859, 1, 19, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3507, 'kurir-mobil', 'Kurir mobil', 859, 1, 20, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3508, 'listrik', 'Listrik', 860, 1, 21, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3509, 'pipa-air', 'Pipa air', 860, 1, 22, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3510, 'kayu', 'Kayu', 860, 1, 23, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3511, 'cat-dinding', 'Cat dinding', 860, 1, 24, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3512, 'pasang-keramik', 'Pasang keramik', 860, 1, 25, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3513, 'servis-atap', 'Servis atap', 860, 1, 26, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3514, 'jaga-bayi', 'Jaga bayi', 861, 1, 27, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3515, 'jaga-anak-balita', 'Jaga anak balita', 861, 1, 28, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3516, 'antar-jemput-sekolah', 'Antar jemput sekolah', 861, 1, 29, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3517, 'temani-belajar', 'Temani belajar', 861, 1, 30, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3518, 'potong-rumput', 'Potong rumput', 862, 1, 31, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3519, 'rawat-tanaman', 'Rawat tanaman', 862, 1, 32, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3520, 'tebang-dahan', 'Tebang dahan', 862, 1, 33, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3521, 'bersih-halaman', 'Bersih halaman', 862, 1, 34, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3522, 'angkat-barang', 'Angkat barang', 863, 1, 35, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3523, 'kemas-barang', 'Kemas barang', 863, 1, 36, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3524, 'bongkar-pasang-mebel', 'Bongkar pasang mebel', 863, 1, 37, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3525, 'antre', 'Antre', 864, 1, 38, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3526, 'belanja-titipan', 'Belanja titipan', 864, 1, 39, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3527, 'input-data', 'Input data', 864, 1, 40, '2026-09-10 11:55:05', '2026-09-10 11:55:05');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3528, 'fotografi', 'Fotografi', 864, 1, 41, '2026-09-10 11:55:05', '2026-09-10 11:55:05');

-- migrations: 26 baris
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (1, '0001_01_01_000000_create_users_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (2, '0001_01_01_000001_create_cache_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (3, '0001_01_01_000002_create_jobs_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (4, '2026_09_08_015359_create_personal_access_tokens_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (5, '2026_09_08_134626_add_sekarya_fields_to_users_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (6, '2026_09_08_134627_create_user_verifications_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (7, '2026_09_08_134628_create_categories_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (8, '2026_09_08_134709_create_tasks_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (9, '2026_09_08_134710_create_bids_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (10, '2026_09_08_134711_create_payments_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (11, '2026_09_08_134712_create_activities_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (12, '2026_09_08_134713_create_reviews_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (13, '2026_09_08_134714_create_task_status_logs_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (14, '2026_09_08_150001_create_skills_tables', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (15, '2026_09_08_150002_add_fulltext_index_to_tasks_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (16, '2026_09_08_160001_create_email_verification_codes_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (17, '2026_09_08_160002_change_users_status_default_to_pending', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (18, '2026_09_09_100000_add_multi_worker_hiring_to_tasks', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (19, '2026_09_09_120000_create_task_search_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (20, '2026_09_10_090000_create_admins_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (21, '2026_09_10_090001_create_admin_audit_logs_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (22, '2026_09_10_090002_link_verification_reviewer_to_admins', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (23, '2026_09_10_090003_add_transfer_confirmation_to_payments', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (24, '2026_09_10_100000_add_gender_and_birth_date_to_users_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (25, '2026_09_10_100001_create_user_workers_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (26, '2026_09_10_100002_move_worker_aggregates_to_user_workers', 1);

SET FOREIGN_KEY_CHECKS = 1;
