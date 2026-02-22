-- SQL to create the user_activity_logs table
-- Run this in your database to create the user activity logging table

CREATE TABLE IF NOT EXISTS `user_activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(9) NOT NULL,
  `activity` VARCHAR(500) NOT NULL,
  `ip_address` VARCHAR(15) NULL COMMENT 'IPv4 address only',
  `device` VARCHAR(50) NULL,
  `os` VARCHAR(50) NULL,
  `time_in` TIMESTAMP NULL COMMENT 'Login/Session start time',
  `time_out` TIMESTAMP NULL COMMENT 'Logout/Session end time',
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `registered_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: This table logs all user/artist activities including:
-- - Page views
-- - Uploads
-- - Collaborations
-- - Marketplace activities
