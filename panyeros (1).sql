-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 15, 2025 at 01:52 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `panyeros`
--

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `ID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `rating` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`ID`, `name`, `comment`, `rating`, `created_at`, `archived`) VALUES
(1, 'Anonymous', 'Sobrang sarap ng pagkain! Highly recommended!', '5', '2025-11-15 07:16:51', 0),
(2, 'Anonymous', 'Good food but service can be improved.', '3', '2025-11-15 07:16:51', 0),
(3, 'Anonymous', 'Not satisfied with the waiting time.', '2', '2025-11-15 07:16:51', 0),
(5, 'eryza', 'maasim si kyle lasang sinigang', '2', '2025-11-15 09:40:58', 0),
(6, 'anonymous', 'maasim si kyle lasang sinigang', '2', '2025-11-15 09:42:25', 0),
(7, 'anonymous', 'Excellent service and food quality!', '5', '2025-01-15 02:30:00', 0),
(8, 'anonymous', 'Good value for money', '4', '2025-01-20 06:20:00', 0),
(9, 'anonymous', 'Average experience', '3', '2025-01-25 08:45:00', 0),
(10, 'anonymous', 'Outstanding quality!', '5', '2025-02-10 03:00:00', 0),
(11, 'anonymous', 'Very satisfied', '5', '2025-02-14 05:30:00', 0),
(12, 'anonymous', 'Good but can improve', '3', '2025-02-20 07:00:00', 0),
(13, 'anonymous', 'Nice ambiance', '4', '2025-02-25 09:30:00', 0),
(14, 'anonymous', 'Amazing food!', '5', '2025-03-05 04:00:00', 0),
(15, 'anonymous', 'Excellent', '5', '2025-03-10 06:00:00', 0),
(16, 'anonymous', 'Very good', '4', '2025-03-15 08:00:00', 0),
(17, 'anonymous', 'Satisfactory', '4', '2025-03-20 10:00:00', 0),
(18, 'anonymous', 'Good service', '4', '2025-03-25 11:00:00', 0),
(19, 'anonymous', 'Best restaurant!', '5', '2025-04-05 02:30:00', 0),
(20, 'anonymous', 'Highly recommend', '5', '2025-04-10 04:30:00', 0),
(21, 'anonymous', 'Wonderful experience', '5', '2025-05-08 03:00:00', 0),
(22, 'anonymous', 'Great food', '4', '2025-05-15 05:00:00', 0),
(23, 'anonymous', 'Good quality', '4', '2025-05-22 07:00:00', 0),
(24, 'anonymous', 'Excellent!', '5', '2025-06-05 02:00:00', 0),
(25, 'anonymous', 'Very pleased', '4', '2025-06-12 04:00:00', 0),
(26, 'anonymous', 'Good experience', '4', '2025-06-18 06:00:00', 0),
(27, 'anonymous', 'Satisfied', '3', '2025-06-25 08:00:00', 0),
(28, 'anonymous', 'Outstanding', '5', '2025-07-03 03:30:00', 0),
(29, 'anonymous', 'Loved it', '5', '2025-07-10 05:30:00', 0),
(30, 'anonymous', 'Great service', '4', '2025-07-17 07:30:00', 0),
(31, 'anonymous', 'Fantastic', '5', '2025-08-05 02:45:00', 0),
(32, 'anonymous', 'Excellent quality', '5', '2025-08-12 04:45:00', 0),
(33, 'anonymous', 'Very good', '4', '2025-08-19 06:45:00', 0),
(34, 'anonymous', 'Satisfied', '4', '2025-08-26 08:45:00', 0),
(35, 'anonymous', 'Amazing', '5', '2025-09-03 03:15:00', 0),
(36, 'anonymous', 'Perfect', '5', '2025-09-10 05:15:00', 0),
(37, 'anonymous', 'Great', '4', '2025-09-17 07:15:00', 0),
(38, 'anonymous', 'Excellent food', '5', '2025-10-05 02:20:00', 0),
(39, 'anonymous', 'Very satisfied', '5', '2025-10-12 04:20:00', 0),
(40, 'anonymous', 'Wonderful', '5', '2025-11-08 03:40:00', 0),
(41, 'anonymous', 'Great experience', '4', '2025-11-15 05:40:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `feedback_history`
--

CREATE TABLE `feedback_history` (
  `ID` int(11) NOT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `rating` varchar(255) NOT NULL,
  `original_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `action_type` enum('deleted','archived') NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_history`
--

INSERT INTO `feedback_history` (`ID`, `feedback_id`, `name`, `comment`, `rating`, `original_date`, `action_type`, `action_date`, `deleted_by`, `notes`) VALUES
(1, 4, 'kyle', 'sarap ko shet', '5', '2025-11-15 09:40:17', 'deleted', '2025-11-15 10:04:18', 'Admin', 'Individual feedback deleted');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `ID` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `date_purchase` date NOT NULL,
  `expiration_date` date NOT NULL,
  `stocks` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`ID`, `item_name`, `category`, `quantity`, `unit`, `date_purchase`, `expiration_date`, `stocks`) VALUES
(3, 'Chicken', 'meat', 60.000, 'kg', '2025-11-01', '2025-12-27', 2),
(4, 'Chicken', 'meat', 20.000, 'kg', '2025-11-29', '2028-11-30', 2),
(5, 'rice', 'grains', 50.000, 'kg', '2025-11-15', '2028-10-30', 2),
(6, 'hatdog', 'others', 100.000, 'pcs', '2025-10-15', '2025-10-31', 10);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_history`
--

CREATE TABLE `inventory_history` (
  `ID` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `date_purchase` date NOT NULL,
  `expiration_date` date NOT NULL,
  `stocks` int(11) NOT NULL,
  `action_type` enum('added','updated','deleted') NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty`
--

CREATE TABLE `loyalty` (
  `ID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `Contact` varchar(255) NOT NULL,
  `points` int(11) NOT NULL,
  `voucher_status` varchar(255) NOT NULL,
  `expdate` date NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loyalty`
--

INSERT INTO `loyalty` (`ID`, `name`, `Contact`, `points`, `voucher_status`, `expdate`, `timestamp`) VALUES
(1, 'James Tanglao', 'tangalao66@gmail.com', 0, 'sarap', '2025-11-01', '2025-11-14 09:27:20'),
(2, 'eryza', 'eryzagalang73@gmail.com', 0, 'Redeemed 10% voucher', '2025-11-14', '2025-11-14 09:27:20'),
(3, 'Ronald', '0912345678910', 4, 'Purchase: ₱200 = 4 pts', '2025-11-14', '2025-11-14 09:27:20'),
(4, 'kyle', '0326589955', 40, 'Purchase: ₱2000 = 40 pts', '2026-02-15', '2025-11-15 02:11:58'),
(5, 'asim', 'ddgfhds457', 2, 'Purchase: ₱100 = 2 pts', '2026-02-15', '2025-11-15 02:48:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `is_admin`, `email`, `password`, `active`, `created_at`) VALUES
(1, 'User', 1, 'test@example.com', '$2y$10$TFLVEhKVFs6piUwft5R85ug76JhF1CDCgBXzvRwWSQatJyrpvevJe', 1, '2025-11-14 15:21:17'),
(2, 'Avery', 0, 'zzz@gmail.com', '$2y$10$VSf583qnTd8.bxvWCJbhcef79I2wzDuKxWBrDPK/MLWTnX4cUk7dy', 1, '2025-11-14 15:57:18'),
(4, 'ery', 1, 'ery@gmail.com', 'admin', 1, '2025-11-14 16:04:59'),
(5, 'tangalao', 1, 'tangalao@gmail.com', '$2y$10$Cqi8FM9K3968GHZdb4QhXu/QUsVQTWJB7ykjAOSG0Vm09IxwQGD4m', 1, '2025-11-14 16:28:01'),
(6, 'tester1', 0, 'tester1@gmail.com', '$2y$10$0Sw4h2uLDnVSP2hwRk6HP.AZlOvDB/aNinxU6NfAsdQceNe.SydOe', 1, '2025-11-14 16:52:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_archived` (`archived`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `feedback_history`
--
ALTER TABLE `feedback_history`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `feedback_id` (`feedback_id`),
  ADD KEY `action_date` (`action_date`),
  ADD KEY `rating` (`rating`),
  ADD KEY `idx_original_date` (`original_date`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `action_date` (`action_date`),
  ADD KEY `idx_item_name` (`item_name`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_action_type` (`action_type`);

--
-- Indexes for table `loyalty`
--
ALTER TABLE `loyalty`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `feedback_history`
--
ALTER TABLE `feedback_history`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_history`
--
ALTER TABLE `inventory_history`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loyalty`
--
ALTER TABLE `loyalty`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
