-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 15, 2025 at 01:53 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
  `rating` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`ID`, `name`, `comment`, `rating`) VALUES
(1, 'Eryza Galang', '', ''),
(2, 'Av', 'fasfasfsadf', '4'),
(3, 'VA', 'asfadfasfasdf', '2');

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
(4, 'Chicken', 'meat', 20.000, 'kg', '2025-11-29', '2028-11-30', 2);

-- --------------------------------------------------------

--
-- Table structure for table `loyalty`
--

CREATE TABLE `loyalty` (
  `ID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `points` int(11) NOT NULL,
  `note` varchar(255) NOT NULL,
  `expdate` date NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loyalty`
--

INSERT INTO `loyalty` (`ID`, `name`, `email`, `points`, `note`, `expdate`, `timestamp`) VALUES
(1, 'James Tanglao', 'tangalao66@gmail.com', 0, 'sarap', '2025-11-01', '2025-11-14 09:27:20'),
(2, 'eryza', 'eryzagalang73@gmail.com', 0, 'Redeemed 10% voucher', '2025-11-14', '2025-11-14 09:27:20'),
(3, 'Ronald', '0912345678910', 4, 'Purchase: ₱200 = 4 pts', '2025-11-14', '2025-11-14 09:27:20');

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
(4, 'ery', 1, 'ery@gmail.com', '$2y$10$.CX5TzuxiFd2uo6n6BQcPOWWEcAiBy9UWxfSPX67KpBfn3qZ.Sc.u', 1, '2025-11-14 16:04:59'),
(5, 'tangalao', 1, 'tangalao@gmail.com', '$2y$10$Cqi8FM9K3968GHZdb4QhXu/QUsVQTWJB7ykjAOSG0Vm09IxwQGD4m', 1, '2025-11-14 16:28:01'),
(6, 'tester1', 0, 'tester1@gmail.com', '$2y$10$0Sw4h2uLDnVSP2hwRk6HP.AZlOvDB/aNinxU6NfAsdQceNe.SydOe', 1, '2025-11-14 16:52:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`ID`);

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
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `loyalty`
--
ALTER TABLE `loyalty`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
