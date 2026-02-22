-- SQL to create the login_logs table
-- This table tracks all login sessions across the platform

CREATE TABLE IF NOT EXISTS `login_logs` (
  `login_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(9) NOT NULL,
  `device` VARCHAR(50) NULL,
  `os` VARCHAR(50) NULL,
  `ip_address` VARCHAR(15) NULL COMMENT 'IPv4 address only',
  `username` VARCHAR(255) NOT NULL,
  `login_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `logout_time` TIMESTAMP NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_login_time` (`login_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
