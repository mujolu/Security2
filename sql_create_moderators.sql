-- SQL to create the moderators table
-- Run this in your database if the automatic create in PHP is not desired.

CREATE TABLE IF NOT EXISTS `moderators` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `firstname` VARCHAR(100) NOT NULL,
  `middlename` VARCHAR(100) DEFAULT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: passwords should be stored as hashes. The PHP helper uses password_hash().
