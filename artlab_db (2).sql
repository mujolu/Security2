-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 14, 2024 at 06:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `artlab_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `login`
--

CREATE TABLE `login` (
  `login_id` int(11) NOT NULL,
  `user_id` varchar(9) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login`
--

INSERT INTO `login` (`login_id`, `user_id`, `login_time`) VALUES
(78, '7656-6854', '2024-12-06 00:44:56'),
(79, '7656-6854', '2024-12-06 00:45:19'),
(80, '7656-6854', '2024-12-06 00:45:42'),
(81, '7656-6854', '2024-12-06 05:24:34'),
(82, '5555-5555', '2024-12-13 03:37:19');

--
-- Triggers `login`
--
DELIMITER $$
CREATE TRIGGER `validate_foreign_key_user_id` BEFORE INSERT ON `login` FOR EACH ROW BEGIN
    -- Check if the `user_id` exists in the `registered_users` table
    IF NOT EXISTS (
        SELECT 1
        FROM `registered_users`
        WHERE `id` = NEW.user_id
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid foreign key value for `user_id`. The specified user does not exist in `registered_users`.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `registered_users`
--

CREATE TABLE `registered_users` (
  `id` varchar(9) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_initial` char(1) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `extension_name` varchar(12) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `purok` varchar(50) DEFAULT NULL,
  `barangay` varchar(50) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `reg_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `birthdate` date DEFAULT NULL,
  `age` int(3) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registered_users`
--

INSERT INTO `registered_users` (`id`, `first_name`, `middle_initial`, `last_name`, `extension_name`, `username`, `email`, `password`, `sex`, `purok`, `barangay`, `city`, `province`, `country`, `zip_code`, `reg_date`, `birthdate`, `age`) VALUES
('1123-6455', 'Jos', 'K', 'Jos', '', 'user15211', 'asdsaj@email.com', '$2y$10$/oUll4Eri5ZJqFVUSMxZBeWsuq5Ve/f.JEsBT9n53bZfzT6rMc6km', 'female', '2', '2', 'Ags', 'Ags', 'Ags', '12312', '2024-12-06 06:02:02', '2003-02-22', 21),
('1212-1313', 'Jahan', 'B', 'Leibert', '', 'johan123', 'jannell@gmail.com', '$2y$10$Y2PjPnraj/vxo6dR/CaWLemj1h1zZEQESvwB8zNhS/TXjbwz6pfCq', 'male', 'P-1', 'Buhang', 'Cabadbaran', 'Agusan', 'Germany', '31324', '2024-12-06 03:20:51', '1996-05-06', 28),
('1235-1234', 'Kill', 'S', 'Kill', '', 'user12312', 'email@email.com', '$2y$10$ylQBvz61L6m1RM1qjGnI4uY33v9FpBaME5g5vxRHOfyeIb04eRheK', 'male', '2', '2', 'Tubay', 'Agusan', 'Ph', '12355', '2024-12-06 05:42:29', '2003-02-22', 21),
('5555-5555', 'Janel', 'B', 'Magdasal', 'Jr', 'jane_22', 'kenn@gmail.com', '$2y$10$fuP8mou3.TGYtrNBv9ziX.gFvEbqpx1FYxsUYB0GoO0BpaR3wVi1i', 'male', '2', '2', 'Manila', 'Manila', 'Manila', '12312', '2024-12-13 03:36:45', '2000-02-22', 24),
('7656-6854', 'Jannelle', '', 'Magdasal', 'Jr', 'Janel3', 'Jelay@gmail.com', '$2y$10$JD5dar0LFWvc2N/7N4JsauaxqouqMUTSvpYyDDyl9CIGRzf/u./yq', 'male', '7', '4', 'Manila', 'Manila', 'Phils', '45565', '2024-11-23 05:10:21', NULL, NULL),
('8635-7236', 'Jane', 'B', 'Mag', 'Jr', 'usern_2', 'janelmagda@gmail.com', '$2y$10$BlT4RNd5QsBGSsCLrB0EXerZUefVRNpWIy1eo8EiA1mPEICoepwuO', 'male', 'P2', 'Barang', 'Barang', 'Agusan Del Norte', 'Country', '63456', '2024-12-06 06:30:02', '2000-03-02', 24);

--
-- Triggers `registered_users`
--
DELIMITER $$
CREATE TRIGGER `validate_id_format` BEFORE INSERT ON `registered_users` FOR EACH ROW BEGIN
    IF NOT (NEW.id REGEXP '^[0-9]{4}-[0-9]{4}$') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid ID format. Expected format: ####-####';
    END IF;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`login_id`),
  ADD KEY `fk_user_id` (`user_id`);

--
-- Indexes for table `registered_users`
--
ALTER TABLE `registered_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username_2` (`username`),
  ADD UNIQUE KEY `email_2` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `login`
--
ALTER TABLE `login`
  MODIFY `login_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `login`
--
ALTER TABLE `login`
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `registered_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
