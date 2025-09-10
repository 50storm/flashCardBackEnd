CREATE DATABASE IF NOT EXISTS flashcard_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;


CREATE USER IF NOT EXISTS 'admin_user'@'%' IDENTIFIED BY 'admin_pass';
GRANT ALL PRIVILEGES ON flashcard_db.* TO 'admin_user'@'%';
FLUSH PRIVILEGES;
