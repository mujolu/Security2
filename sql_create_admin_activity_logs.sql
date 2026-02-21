-- SQL to create the admin_activity_logs table
-- Run this in your database to create the admin activity logging table

CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(9) NOT NULL,
  `activity` VARCHAR(500) NOT NULL,
  `ip_address` VARCHAR(15) NULL COMMENT 'IPv4 address only',
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `registered_users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: This table logs all admin activities including:
-- - Page views
-- - User deletions
-- - User bans
-- - Moderator additions with credentials
-- - Moderator deletions
-- The table is automatically created in PHP if it doesn't exist.
-- IP addresses are stored as IPv4 only (VARCHAR(15), e.g., 192.168.1.1)
