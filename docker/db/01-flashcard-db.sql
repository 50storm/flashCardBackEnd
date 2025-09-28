CREATE DATABASE IF NOT EXISTS flashcard_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;


CREATE USER IF NOT EXISTS 'admin_user'@'%' IDENTIFIED BY 'admin_pass';
GRANT ALL PRIVILEGES ON flashcard_db.* TO 'admin_user'@'%';
FLUSH PRIVILEGES;

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初期ユーザー1件
INSERT INTO `users` (`name`, `email`, `password`)
VALUES (
  'テストユーザー',
  'test@example.com',
  -- password_hash('password', PASSWORD_BCRYPT) の例
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);



-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: db
-- 生成日時: 2025 年 9 月 16 日 15:27
-- サーバのバージョン： 8.0.34
-- PHP のバージョン: 8.2.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `flashcard_db`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `flash_cards`
--

CREATE TABLE `flash_cards` (
  `id` bigint UNSIGNED NOT NULL COMMENT 'PK',
  `user_id` bigint UNSIGNED DEFAULT NULL COMMENT '作成ユーザーID（users.id）',
  `front` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '表面テキスト',
  `back` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '裏面テキスト',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- テーブルのデータのダンプ `flash_cards`
--

INSERT INTO `flash_cards` (`id`, `user_id`, `front`, `back`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'eat out', '外食する', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(2, 1, 'I\'m open!', '（サッカーで）フリーだよ！', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(3, 1, 'How\'s it going?', '調子どう？', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(4, 1, 'Could you say that again?', 'もう一度言っていただけますか？', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(5, 1, 'What do you do?', 'お仕事は何をされていますか？', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(6, 1, 'Where are you from?', '出身はどちらですか？', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(7, 1, 'I\'m just browsing, thanks.', '見てるだけです、ありがとう。', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(8, 1, 'Do you take credit cards?', 'クレジットカードは使えますか？', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(9, 1, 'Can I get this to go?', '持ち帰りできますか？', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL),
(10, 1, 'Any café recommendations around here?', 'この辺でおすすめのカフェはありますか？', '2025-09-15 08:58:09', '2025-09-15 08:58:09', NULL);

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `flash_cards`
--
ALTER TABLE `flash_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_flash_cards_user_id` (`user_id`),
  ADD KEY `idx_flash_cards_user_deleted` (`user_id`,`deleted_at`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `flash_cards`
--
ALTER TABLE `flash_cards`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK', AUTO_INCREMENT=12;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `flash_cards`
--
ALTER TABLE `flash_cards`
  ADD CONSTRAINT `fk_flash_cards_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
