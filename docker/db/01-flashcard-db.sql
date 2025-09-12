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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 初期ユーザー1件
INSERT INTO `users` (`name`, `email`, `password`)
VALUES (
  'テストユーザー',
  'test@example.com',
  -- password_hash('password', PASSWORD_BCRYPT) の例
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);