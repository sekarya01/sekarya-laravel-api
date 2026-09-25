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

CREATE TABLE IF NOT EXISTS `cities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `cities_name_index` (`name`),
  KEY `cities_province_index` (`province`)
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
  `first_name` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `active_mode` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hiring',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_verification',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `poster_tasks_completed` int unsigned NOT NULL DEFAULT '0',
  `tasks_posted` int unsigned NOT NULL DEFAULT '0',
  `cancellations` int unsigned NOT NULL DEFAULT '0',
  `theme` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `last_active_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_ulid_unique` (`ulid`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  UNIQUE KEY `users_username_unique` (`username`),
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

CREATE TABLE IF NOT EXISTS `category_city_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref_price_min` bigint unsigned DEFAULT NULL,
  `ref_price_max` bigint unsigned DEFAULT NULL,
  `ref_price_median` bigint unsigned DEFAULT NULL,
  `ref_sample_size` int unsigned NOT NULL DEFAULT '0',
  `ref_computed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_city_prices_category_city_unique` (`category_id`,`city`),
  CONSTRAINT `category_city_prices_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `device_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_tokens_token_unique` (`token`),
  KEY `device_tokens_user_id_platform_index` (`user_id`,`platform`),
  CONSTRAINT `device_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `checklist` json DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `budget_min` bigint unsigned NOT NULL,
  `budget_max` bigint unsigned DEFAULT NULL,
  `ref_price_median` bigint unsigned DEFAULT NULL,
  `location_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_remote` tinyint(1) NOT NULL DEFAULT '0',
  `needed_at` timestamp NULL DEFAULT NULL,
  `end_at` timestamp NULL DEFAULT NULL,
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
  KEY `tasks_status_needed_at_index` (`status`,`needed_at`),
  CONSTRAINT `tasks_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `tasks_poster_id_foreign` FOREIGN KEY (`poster_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `label` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_addresses_user_id_unique` (`user_id`),
  CONSTRAINT `user_addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_notifications_user_id_created_at_id_index` (`user_id`,`created_at`,`id`),
  KEY `user_notifications_user_id_read_at_created_at_index` (`user_id`,`read_at`,`created_at`),
  CONSTRAINT `user_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_worker_verifications` (
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
  `account_number_last4` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `user_worker_verifications_user_id_type_status_index` (`user_id`,`type`,`status`),
  KEY `user_worker_verifications_document_number_hash_index` (`document_number_hash`),
  KEY `user_worker_verifications_status_submitted_at_index` (`status`,`submitted_at`),
  KEY `user_worker_verifications_created_at_index` (`created_at`),
  KEY `user_worker_verifications_reviewed_by_reviewed_at_index` (`reviewed_by`,`reviewed_at`),
  CONSTRAINT `user_worker_verifications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_worker_verifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_workers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headline` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `radius_km` smallint unsigned DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
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
  KEY `user_workers_available_latest_index` (`is_available`,`created_at`,`id`),
  CONSTRAINT `user_workers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_topups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `unique_code` smallint unsigned NOT NULL DEFAULT '0',
  `transfer_amount` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'awaiting_confirmation',
  `sender_note` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_topups_ulid_unique` (`ulid`),
  KEY `wallet_topups_reviewed_by_foreign` (`reviewed_by`),
  KEY `wallet_topups_status_created_at_id_index` (`status`,`created_at`,`id`),
  KEY `wallet_topups_user_id_created_at_id_index` (`user_id`,`created_at`,`id`),
  CONSTRAINT `wallet_topups_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_topups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `balance` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallets_ulid_unique` (`ulid`),
  UNIQUE KEY `wallets_user_id_unique` (`user_id`),
  KEY `wallets_created_at_index` (`created_at`),
  CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `worker_invite_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_plain` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prefix` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_uses` int unsigned NOT NULL DEFAULT '1',
  `used_count` int unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_invite_codes_code_hash_unique` (`code_hash`),
  KEY `worker_invite_codes_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `worker_invite_codes_is_active_expires_at_index` (`is_active`,`expires_at`),
  KEY `worker_invite_codes_city_province_index` (`city`,`province`),
  CONSTRAINT `worker_invite_codes_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
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
  `tags` json DEFAULT NULL,
  `photos` json DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `task_bookmarks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `task_id` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_bookmarks_user_task_unique` (`user_id`,`task_id`),
  KEY `task_bookmarks_task_id_foreign` (`task_id`),
  KEY `task_bookmarks_user_latest_index` (`user_id`,`created_at`,`id`),
  CONSTRAINT `task_bookmarks_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_bookmarks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_cancel_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_id` bigint unsigned NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_cancel_requests_ulid_unique` (`ulid`),
  KEY `task_cancel_requests_decided_by_foreign` (`decided_by`),
  KEY `task_cancel_requests_task_id_status_id_index` (`task_id`,`status`,`id`),
  KEY `task_cancel_requests_requested_by_created_at_id_index` (`requested_by`,`created_at`,`id`),
  CONSTRAINT `task_cancel_requests_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `task_cancel_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_cancel_requests_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS `wallet_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wallet_id` bigint unsigned NOT NULL,
  `type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `balance_after` bigint unsigned NOT NULL,
  `reference_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `description` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_entries_ulid_unique` (`ulid`),
  UNIQUE KEY `wallet_entries_reference_unique` (`reference_type`,`reference_id`,`type`),
  KEY `wallet_entries_wallet_id_created_at_id_index` (`wallet_id`,`created_at`,`id`),
  KEY `wallet_entries_wallet_id_type_index` (`wallet_id`,`type`),
  CONSTRAINT `wallet_entries_wallet_id_foreign` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_withdrawals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` varchar(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `verification_id` bigint unsigned NOT NULL,
  `rejection_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_by` bigint unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallet_withdrawals_ulid_unique` (`ulid`),
  KEY `wallet_withdrawals_verification_id_foreign` (`verification_id`),
  KEY `wallet_withdrawals_processed_by_foreign` (`processed_by`),
  KEY `wallet_withdrawals_status_created_at_id_index` (`status`,`created_at`,`id`),
  KEY `wallet_withdrawals_user_id_created_at_id_index` (`user_id`,`created_at`,`id`),
  CONSTRAINT `wallet_withdrawals_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wallet_withdrawals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wallet_withdrawals_verification_id_foreign` FOREIGN KEY (`verification_id`) REFERENCES `user_worker_verifications` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `worker_invite_redemptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invite_code_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_invite_redemptions_invite_code_id_user_id_unique` (`invite_code_id`,`user_id`),
  KEY `worker_invite_redemptions_user_id_foreign` (`user_id`),
  CONSTRAINT `worker_invite_redemptions_invite_code_id_foreign` FOREIGN KEY (`invite_code_id`) REFERENCES `worker_invite_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `worker_invite_redemptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `departed_at` timestamp NULL DEFAULT NULL,
  `arrived_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `live_latitude` decimal(10,7) DEFAULT NULL,
  `live_longitude` decimal(10,7) DEFAULT NULL,
  `live_updated_at` timestamp NULL DEFAULT NULL,
  `checklist_state` json DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `review_search` (
  `review_id` bigint unsigned NOT NULL,
  `terms` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`review_id`),
  FULLTEXT KEY `review_search_terms_fulltext` (`terms`),
  CONSTRAINT `review_search_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_cancel_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cancel_request_id` bigint unsigned NOT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_cancel_approvals_cancel_request_id_worker_id_unique` (`cancel_request_id`,`worker_id`),
  KEY `task_cancel_approvals_worker_id_foreign` (`worker_id`),
  KEY `task_cancel_approvals_cancel_request_id_status_index` (`cancel_request_id`,`status`),
  CONSTRAINT `task_cancel_approvals_cancel_request_id_foreign` FOREIGN KEY (`cancel_request_id`) REFERENCES `task_cancel_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_cancel_approvals_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `task_fund_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned NOT NULL,
  `kind` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `reason` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_fund_movements_payment_id_foreign` (`payment_id`),
  KEY `task_fund_movements_task_id_created_at_index` (`task_id`,`created_at`),
  CONSTRAINT `task_fund_movements_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_fund_movements_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_updates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` bigint unsigned NOT NULL,
  `note` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_updates_activity_id_created_at_id_index` (`activity_id`,`created_at`,`id`),
  CONSTRAINT `activity_updates_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- DATA ACUAN ----------
-- Kategori dan keahlian: aplikasi tidak berjalan tanpanya.
-- Riwayat migrasi: supaya `php artisan migrate` tahu semuanya sudah jalan.

-- categories: 9 baris
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1, 'mencuci', 'Mencuci', 'Cuci pakaian, setrika, cuci kering', 'washing-machine', 50000, 150000, 80000, 0, NULL, 1, 0, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (2, 'bersih-rumah', 'Membersihkan Rumah', 'Bersih-bersih rumah, kamar, dapur, kamar mandi', 'broom', 75000, 300000, 150000, 0, NULL, 1, 1, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3, 'jaga-hewan', 'Menjaga Hewan', 'Titip hewan, jalan-jalan, beri makan', 'paw', 50000, 200000, 100000, 0, NULL, 1, 2, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (4, 'antar-barang', 'Mengantarkan Barang', 'Antar dokumen, paket, barang dalam kota', 'package', 15000, 100000, 35000, 0, NULL, 1, 3, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (5, 'tukang', 'Tukang & Perbaikan', 'Perbaikan kecil, pasang, servis rumah', 'wrench', 100000, 1000000, 250000, 0, NULL, 1, 4, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (6, 'jaga-anak', 'Menjaga Anak', 'Menemani dan menjaga anak', 'baby', 75000, 300000, 150000, 0, NULL, 1, 5, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (7, 'berkebun', 'Berkebun', 'Rawat tanaman, potong rumput, bersihkan halaman', 'sprout', 75000, 250000, 125000, 0, NULL, 1, 6, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (8, 'pindahan', 'Bantu Pindahan', 'Angkat, kemas, bantu pindah barang', 'truck', 150000, 1000000, 350000, 0, NULL, 1, 7, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `categories` (`id`, `slug`, `name`, `description`, `icon`, `ref_price_min`, `ref_price_max`, `ref_price_median`, `ref_sample_size`, `ref_computed_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (9, 'lainnya', 'Lainnya', 'Pekerjaan yang belum masuk kategori di atas', 'ellipsis', NULL, NULL, NULL, 0, NULL, 1, 8, '2026-09-25 22:46:51', '2026-09-25 22:46:51');

-- skills: 42 baris
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (1, 'setrika', 'Setrika', 1, 1, 0, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (2, 'cuci-tangan', 'Cuci tangan', 1, 1, 1, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (3, 'cuci-mesin', 'Cuci mesin', 1, 1, 2, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (4, 'lipat-pakaian', 'Lipat pakaian', 1, 1, 3, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (5, 'cuci-sepatu', 'Cuci sepatu', 1, 1, 4, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (6, 'bersih-umum', 'Bersih umum', 2, 1, 5, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (7, 'cuci-ac', 'Cuci ac', 2, 1, 6, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (8, 'bersih-kamar-mandi', 'Bersih kamar mandi', 2, 1, 7, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (9, 'poles-lantai', 'Poles lantai', 2, 1, 8, '2026-09-25 22:46:51', '2026-09-25 22:46:51');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (10, 'bersih-dapur', 'Bersih dapur', 2, 1, 9, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (11, 'cuci-jendela', 'Cuci jendela', 2, 1, 10, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (12, 'jaga-kucing', 'Jaga kucing', 3, 1, 11, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (13, 'jaga-anjing', 'Jaga anjing', 3, 1, 12, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (14, 'grooming', 'Grooming', 3, 1, 13, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (15, 'jalan-anjing', 'Jalan anjing', 3, 1, 14, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (16, 'beri-makan-hewan', 'Beri makan hewan', 3, 1, 15, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (17, 'antar-dokumen', 'Antar dokumen', 4, 1, 16, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (18, 'antar-paket', 'Antar paket', 4, 1, 17, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (19, 'antar-makanan', 'Antar makanan', 4, 1, 18, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (20, 'kurir-motor', 'Kurir motor', 4, 1, 19, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (21, 'kurir-mobil', 'Kurir mobil', 4, 1, 20, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (22, 'listrik', 'Listrik', 5, 1, 21, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (23, 'pipa-air', 'Pipa air', 5, 1, 22, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (24, 'kayu', 'Kayu', 5, 1, 23, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (25, 'cat-dinding', 'Cat dinding', 5, 1, 24, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (26, 'pasang-keramik', 'Pasang keramik', 5, 1, 25, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (27, 'servis-atap', 'Servis atap', 5, 1, 26, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (28, 'jaga-bayi', 'Jaga bayi', 6, 1, 27, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (29, 'jaga-anak-balita', 'Jaga anak balita', 6, 1, 28, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (30, 'antar-jemput-sekolah', 'Antar jemput sekolah', 6, 1, 29, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (31, 'temani-belajar', 'Temani belajar', 6, 1, 30, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (32, 'potong-rumput', 'Potong rumput', 7, 1, 31, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (33, 'rawat-tanaman', 'Rawat tanaman', 7, 1, 32, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (34, 'tebang-dahan', 'Tebang dahan', 7, 1, 33, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (35, 'bersih-halaman', 'Bersih halaman', 7, 1, 34, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (36, 'angkat-barang', 'Angkat barang', 8, 1, 35, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (37, 'kemas-barang', 'Kemas barang', 8, 1, 36, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (38, 'bongkar-pasang-mebel', 'Bongkar pasang mebel', 8, 1, 37, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (39, 'antre', 'Antre', 9, 1, 38, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (40, 'belanja-titipan', 'Belanja titipan', 9, 1, 39, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (41, 'input-data', 'Input data', 9, 1, 40, '2026-09-25 22:46:52', '2026-09-25 22:46:52');
INSERT IGNORE INTO `skills` (`id`, `slug`, `name`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES (42, 'fotografi', 'Fotografi', 9, 1, 41, '2026-09-25 22:46:52', '2026-09-25 22:46:52');

-- cities: 146 baris
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (1, 'Banda Aceh', 'Aceh', 'kota', 0);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (2, 'Lhokseumawe', 'Aceh', 'kota', 1);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (3, 'Langsa', 'Aceh', 'kota', 2);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (4, 'Sabang', 'Aceh', 'kota', 3);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (5, 'Meulaboh', 'Aceh', 'kabupaten', 4);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (6, 'Medan', 'Sumatera Utara', 'kota', 5);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (7, 'Binjai', 'Sumatera Utara', 'kota', 6);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (8, 'Pematangsiantar', 'Sumatera Utara', 'kota', 7);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (9, 'Tebing Tinggi', 'Sumatera Utara', 'kota', 8);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (10, 'Sibolga', 'Sumatera Utara', 'kota', 9);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (11, 'Padangsidimpuan', 'Sumatera Utara', 'kota', 10);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (12, 'Deli Serdang', 'Sumatera Utara', 'kabupaten', 11);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (13, 'Padang', 'Sumatera Barat', 'kota', 12);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (14, 'Bukittinggi', 'Sumatera Barat', 'kota', 13);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (15, 'Payakumbuh', 'Sumatera Barat', 'kota', 14);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (16, 'Solok', 'Sumatera Barat', 'kota', 15);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (17, 'Pariaman', 'Sumatera Barat', 'kota', 16);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (18, 'Padang Panjang', 'Sumatera Barat', 'kota', 17);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (19, 'Pekanbaru', 'Riau', 'kota', 18);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (20, 'Dumai', 'Riau', 'kota', 19);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (21, 'Kampar', 'Riau', 'kabupaten', 20);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (22, 'Batam', 'Kepulauan Riau', 'kota', 21);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (23, 'Tanjungpinang', 'Kepulauan Riau', 'kota', 22);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (24, 'Bintan', 'Kepulauan Riau', 'kabupaten', 23);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (25, 'Jambi', 'Jambi', 'kota', 24);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (26, 'Sungai Penuh', 'Jambi', 'kota', 25);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (27, 'Bengkulu', 'Bengkulu', 'kota', 26);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (28, 'Curup', 'Bengkulu', 'kabupaten', 27);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (29, 'Palembang', 'Sumatera Selatan', 'kota', 28);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (30, 'Prabumulih', 'Sumatera Selatan', 'kota', 29);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (31, 'Pagar Alam', 'Sumatera Selatan', 'kota', 30);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (32, 'Lubuklinggau', 'Sumatera Selatan', 'kota', 31);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (33, 'Pangkalpinang', 'Kepulauan Bangka Belitung', 'kota', 32);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (34, 'Sungailiat', 'Kepulauan Bangka Belitung', 'kabupaten', 33);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (35, 'Bandar Lampung', 'Lampung', 'kota', 34);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (36, 'Metro', 'Lampung', 'kota', 35);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (37, 'Lampung Selatan', 'Lampung', 'kabupaten', 36);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (38, 'Serang', 'Banten', 'kota', 37);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (39, 'Tangerang', 'Banten', 'kota', 38);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (40, 'Tangerang Selatan', 'Banten', 'kota', 39);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (41, 'Cilegon', 'Banten', 'kota', 40);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (42, 'Pandeglang', 'Banten', 'kabupaten', 41);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (43, 'Jakarta Pusat', 'DKI Jakarta', 'kota', 42);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (44, 'Jakarta Selatan', 'DKI Jakarta', 'kota', 43);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (45, 'Jakarta Timur', 'DKI Jakarta', 'kota', 44);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (46, 'Jakarta Barat', 'DKI Jakarta', 'kota', 45);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (47, 'Jakarta Utara', 'DKI Jakarta', 'kota', 46);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (48, 'Bandung', 'Jawa Barat', 'kota', 47);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (49, 'Bekasi', 'Jawa Barat', 'kota', 48);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (50, 'Bogor', 'Jawa Barat', 'kota', 49);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (51, 'Depok', 'Jawa Barat', 'kota', 50);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (52, 'Cimahi', 'Jawa Barat', 'kota', 51);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (53, 'Sukabumi', 'Jawa Barat', 'kota', 52);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (54, 'Cirebon', 'Jawa Barat', 'kota', 53);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (55, 'Tasikmalaya', 'Jawa Barat', 'kota', 54);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (56, 'Banjar', 'Jawa Barat', 'kota', 55);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (57, 'Garut', 'Jawa Barat', 'kabupaten', 56);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (58, 'Karawang', 'Jawa Barat', 'kabupaten', 57);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (59, 'Semarang', 'Jawa Tengah', 'kota', 58);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (60, 'Surakarta', 'Jawa Tengah', 'kota', 59);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (61, 'Magelang', 'Jawa Tengah', 'kota', 60);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (62, 'Pekalongan', 'Jawa Tengah', 'kota', 61);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (63, 'Salatiga', 'Jawa Tengah', 'kota', 62);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (64, 'Tegal', 'Jawa Tengah', 'kota', 63);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (65, 'Banyumas', 'Jawa Tengah', 'kabupaten', 64);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (66, 'Kudus', 'Jawa Tengah', 'kabupaten', 65);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (67, 'Jepara', 'Jawa Tengah', 'kabupaten', 66);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (68, 'Yogyakarta', 'DI Yogyakarta', 'kota', 67);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (69, 'Sleman', 'DI Yogyakarta', 'kabupaten', 68);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (70, 'Bantul', 'DI Yogyakarta', 'kabupaten', 69);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (71, 'Kulon Progo', 'DI Yogyakarta', 'kabupaten', 70);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (72, 'Gunungkidul', 'DI Yogyakarta', 'kabupaten', 71);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (73, 'Surabaya', 'Jawa Timur', 'kota', 72);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (74, 'Malang', 'Jawa Timur', 'kota', 73);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (75, 'Kediri', 'Jawa Timur', 'kota', 74);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (76, 'Blitar', 'Jawa Timur', 'kota', 75);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (77, 'Madiun', 'Jawa Timur', 'kota', 76);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (78, 'Mojokerto', 'Jawa Timur', 'kota', 77);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (79, 'Pasuruan', 'Jawa Timur', 'kota', 78);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (80, 'Probolinggo', 'Jawa Timur', 'kota', 79);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (81, 'Batu', 'Jawa Timur', 'kota', 80);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (82, 'Sidoarjo', 'Jawa Timur', 'kabupaten', 81);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (83, 'Gresik', 'Jawa Timur', 'kabupaten', 82);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (84, 'Jember', 'Jawa Timur', 'kabupaten', 83);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (85, 'Banyuwangi', 'Jawa Timur', 'kabupaten', 84);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (86, 'Denpasar', 'Bali', 'kota', 85);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (87, 'Badung', 'Bali', 'kabupaten', 86);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (88, 'Gianyar', 'Bali', 'kabupaten', 87);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (89, 'Tabanan', 'Bali', 'kabupaten', 88);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (90, 'Buleleng', 'Bali', 'kabupaten', 89);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (91, 'Mataram', 'Nusa Tenggara Barat', 'kota', 90);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (92, 'Bima', 'Nusa Tenggara Barat', 'kota', 91);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (93, 'Lombok Timur', 'Nusa Tenggara Barat', 'kabupaten', 92);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (94, 'Sumbawa', 'Nusa Tenggara Barat', 'kabupaten', 93);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (95, 'Kupang', 'Nusa Tenggara Timur', 'kota', 94);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (96, 'Ende', 'Nusa Tenggara Timur', 'kabupaten', 95);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (97, 'Sikka', 'Nusa Tenggara Timur', 'kabupaten', 96);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (98, 'Manggarai', 'Nusa Tenggara Timur', 'kabupaten', 97);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (99, 'Pontianak', 'Kalimantan Barat', 'kota', 98);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (100, 'Singkawang', 'Kalimantan Barat', 'kota', 99);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (101, 'Mempawah', 'Kalimantan Barat', 'kabupaten', 100);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (102, 'Palangka Raya', 'Kalimantan Tengah', 'kota', 101);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (103, 'Kotawaringin Timur', 'Kalimantan Tengah', 'kabupaten', 102);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (104, 'Kotawaringin Barat', 'Kalimantan Tengah', 'kabupaten', 103);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (105, 'Banjarmasin', 'Kalimantan Selatan', 'kota', 104);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (106, 'Banjarbaru', 'Kalimantan Selatan', 'kota', 105);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (107, 'Banjar', 'Kalimantan Selatan', 'kabupaten', 106);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (108, 'Samarinda', 'Kalimantan Timur', 'kota', 107);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (109, 'Balikpapan', 'Kalimantan Timur', 'kota', 108);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (110, 'Bontang', 'Kalimantan Timur', 'kota', 109);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (111, 'Kutai Kartanegara', 'Kalimantan Timur', 'kabupaten', 110);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (112, 'Tarakan', 'Kalimantan Utara', 'kota', 111);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (113, 'Bulungan', 'Kalimantan Utara', 'kabupaten', 112);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (114, 'Manado', 'Sulawesi Utara', 'kota', 113);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (115, 'Bitung', 'Sulawesi Utara', 'kota', 114);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (116, 'Tomohon', 'Sulawesi Utara', 'kota', 115);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (117, 'Kotamobagu', 'Sulawesi Utara', 'kota', 116);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (118, 'Gorontalo', 'Gorontalo', 'kota', 117);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (119, 'Kabupaten Gorontalo', 'Gorontalo', 'kabupaten', 118);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (120, 'Palu', 'Sulawesi Tengah', 'kota', 119);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (121, 'Banggai', 'Sulawesi Tengah', 'kabupaten', 120);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (122, 'Poso', 'Sulawesi Tengah', 'kabupaten', 121);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (123, 'Mamuju', 'Sulawesi Barat', 'kabupaten', 122);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (124, 'Polewali Mandar', 'Sulawesi Barat', 'kabupaten', 123);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (125, 'Makassar', 'Sulawesi Selatan', 'kota', 124);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (126, 'Parepare', 'Sulawesi Selatan', 'kota', 125);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (127, 'Palopo', 'Sulawesi Selatan', 'kota', 126);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (128, 'Gowa', 'Sulawesi Selatan', 'kabupaten', 127);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (129, 'Bone', 'Sulawesi Selatan', 'kabupaten', 128);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (130, 'Kendari', 'Sulawesi Tenggara', 'kota', 129);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (131, 'Baubau', 'Sulawesi Tenggara', 'kota', 130);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (132, 'Kolaka', 'Sulawesi Tenggara', 'kabupaten', 131);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (133, 'Ambon', 'Maluku', 'kota', 132);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (134, 'Tual', 'Maluku', 'kota', 133);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (135, 'Ternate', 'Maluku Utara', 'kota', 134);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (136, 'Tidore Kepulauan', 'Maluku Utara', 'kota', 135);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (137, 'Jayapura', 'Papua', 'kota', 136);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (138, 'Merauke', 'Papua', 'kabupaten', 137);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (139, 'Mimika', 'Papua', 'kabupaten', 138);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (140, 'Manokwari', 'Papua Barat', 'kabupaten', 139);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (141, 'Fakfak', 'Papua Barat', 'kabupaten', 140);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (142, 'Sorong', 'Papua Barat Daya', 'kota', 141);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (143, 'Raja Ampat', 'Papua Barat Daya', 'kabupaten', 142);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (144, 'Nabire', 'Papua Tengah', 'kabupaten', 143);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (145, 'Jayawijaya', 'Papua Pegunungan', 'kabupaten', 144);
INSERT IGNORE INTO `cities` (`id`, `name`, `province`, `type`, `sort_order`) VALUES (146, 'Boven Digoel', 'Papua Selatan', 'kabupaten', 145);

-- migrations: 59 baris
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
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (27, '2026_09_10_110000_rename_user_verifications_to_user_worker_verifications', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (28, '2026_09_12_000001_add_identity_fields_to_users_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (29, '2026_09_13_000001_add_end_at_to_tasks_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (30, '2026_09_14_000001_widen_gender_column_on_users_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (31, '2026_09_16_000001_create_wallet_tables', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (32, '2026_09_16_000002_create_wallet_request_tables', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (33, '2026_09_19_000001_open_stuck_dealt_tasks', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (34, '2026_09_19_000002_add_travel_steps_to_activities', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (35, '2026_09_20_000001_create_task_fund_movements_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (36, '2026_09_21_000001_create_worker_invite_codes_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (37, '2026_09_21_000002_add_code_plain_to_worker_invite_codes_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (38, '2026_09_21_000003_add_area_to_worker_invite_codes_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (39, '2026_09_22_000001_create_task_cancel_requests_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (40, '2026_09_23_000001_create_task_cancel_approvals_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (41, '2026_09_24_000001_create_device_tokens_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (42, '2026_09_25_000001_add_area_to_tasks_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (43, '2026_09_25_000002_add_account_number_last4_to_user_worker_verifications', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (44, '2026_09_25_000003_add_tags_to_reviews_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (45, '2026_09_25_000004_create_review_search_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (46, '2026_09_26_000001_add_is_available_to_user_workers_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (47, '2026_09_26_000002_create_user_notifications_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (48, '2026_09_26_000003_create_user_addresses_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (49, '2026_09_26_000004_add_headline_to_user_workers_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (50, '2026_09_26_000005_add_poster_tasks_completed_to_users_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (51, '2026_09_26_000006_add_needed_at_index_to_tasks_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (52, '2026_09_26_000007_add_unique_code_to_wallet_topups_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (53, '2026_09_26_000008_add_photos_to_reviews_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (54, '2026_09_26_000009_create_task_bookmarks_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (55, '2026_09_26_000010_add_live_location_and_checklist_to_activities_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (56, '2026_09_26_000011_add_checklist_to_tasks_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (57, '2026_09_26_000012_create_activity_updates_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (58, '2026_09_26_000013_create_cities_table', 1);
INSERT IGNORE INTO `migrations` (`id`, `migration`, `batch`) VALUES (59, '2026_09_26_000014_create_category_city_prices_table', 1);

SET FOREIGN_KEY_CHECKS = 1;
