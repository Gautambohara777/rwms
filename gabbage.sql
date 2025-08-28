-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 28, 2025 at 07:14 AM
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
-- Database: `gabbage`
--

-- --------------------------------------------------------

--
-- Table structure for table `pickup_requests`
--

CREATE TABLE `pickup_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `waste_type` varchar(255) NOT NULL,
  `rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `weight` decimal(10,2) NOT NULL,
  `pickup_date` date NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,6) NOT NULL,
  `longitude` decimal(10,6) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_collector_id` int(11) DEFAULT NULL,
  `final_weight` decimal(10,2) DEFAULT NULL,
  `collected_items` varchar(255) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `2Rstatus` enum('Reuse','Recycle') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pickup_requests`
--

INSERT INTO `pickup_requests` (`id`, `user_id`, `name`, `waste_type`, `rate`, `weight`, `pickup_date`, `address`, `latitude`, `longitude`, `status`, `created_at`, `assigned_collector_id`, `final_weight`, `collected_items`, `total_cost`, `2Rstatus`) VALUES
(11, 3, 'shyam', 'plastic', 9.00, 21.00, '2025-08-27', 'kalnaki', 27.695916, 85.281200, 'Refused', '2025-08-26 14:38:07', 2, NULL, NULL, NULL, NULL),
(12, 3, 'shyam', 'glass', 17.00, 23.00, '2025-08-28', 'balkhu', 27.682692, 85.297809, 'Collected', '2025-08-26 14:38:58', 2, 23.00, 'glass', 391.00, NULL),
(13, 3, 'shyam', 'bike', 150.00, 110.00, '2025-08-27', 'dungeadda', 27.688725, 85.272059, 'Collected', '2025-08-26 14:40:39', 2, 120.00, 'bike', 18000.00, NULL),
(14, 3, 'hh', 'car', 55.00, 1500.00, '2025-08-28', 'thankot', 27.692040, 85.217772, 'Collected', '2025-08-26 14:41:23', 2, 1500.00, 'car', 82500.00, NULL),
(15, 3, 'dd', 'copper', 15.00, 23.00, '2025-08-27', 'munal', 27.685162, 85.268669, 'Collected', '2025-08-26 14:42:14', 2, 23.00, 'copper', 345.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reusable_waste_listings`
--

CREATE TABLE `reusable_waste_listings` (
  `listing_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL COMMENT 'Image uploaded by the admin',
  `quantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reusable_waste_listings`
--

INSERT INTO `reusable_waste_listings` (`listing_id`, `title`, `description`, `status`, `price`, `user_id`, `image`, `quantity`) VALUES
(2, 'asds', 'sdasd', 'sold', 43.00, 1, 'uploads/1755788531_black-myth-wukong-5120x2880-19191.png', 2),
(3, 'sda', 'sdasd', 'sold', 232.00, 1, 'uploads/reusable/1755789903_goku-black-and-5120x2880-20871.png', 12),
(5, 'tgfads', 'fgdsfg', 'removed', 564.00, 1, 'uploads/reusable/1755790424_itadori-yuuji-5120x2880-9272.jpg', 343),
(7, 'adss', 'asdasd', 'removed', 232.00, 1, 'uploads/reusable/1755790659_spider-man-miles-5120x2880-11246.png', 2),
(8, 'edsfasd', 'asfas', 'available', 32.00, 1, 'uploads/reusable/1755826140_sasuke-uchiha-5120x2880-17605.png', 23),
(9, 'ff', 'ddd', 'available', 23.00, 1, 'uploads/reusable/1755827923_logo.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user','collector') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `address`, `password`, `role`, `created_at`) VALUES
(1, 'Gautam', 'dntbohara@gmail.com', '9860459990', 'tinthana', '$2y$10$jgTEPTsjI4fYrdnt3xQbPuB1Psp4QlhT80vYFU5CIdxNJiuzPvFp.', 'admin', '2025-08-21 06:04:36'),
(2, 'hari', 'hari111@gmail.com', '987654321', 'kalanki', '$2y$10$gh2Kn0HAwU3b/2LQQeBrUO/zUIjcjnP6G1cd7AkMWuK.sJvyNAjGm', 'collector', '2025-08-22 02:22:14'),
(3, 'shyam', 'user111@gmail.com', '778899445566', 'teku', '$2y$10$bAQPa4vmXOG7LB00XkovouCRrJ2p9aNSU/oOoN3ZysYKn0BZnHlBS', 'user', '2025-08-26 14:37:20');

-- --------------------------------------------------------

--
-- Table structure for table `waste_rates`
--

CREATE TABLE `waste_rates` (
  `id` int(11) NOT NULL,
  `waste_type` varchar(100) NOT NULL,
  `rate_per_kg` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `waste_rates`
--

INSERT INTO `waste_rates` (`id`, `waste_type`, `rate_per_kg`, `created_at`, `updated_at`) VALUES
(8, 'iron', 15.00, '2025-07-16 13:04:57', '2025-08-21 13:50:31'),
(13, 'paper', 9.00, '2025-07-16 13:40:17', '2025-08-21 13:50:31'),
(14, 'copper', 15.00, '2025-07-16 13:40:48', '2025-08-21 13:50:31'),
(16, 'plastic', 9.00, '2025-07-16 13:47:54', '2025-08-21 13:50:31'),
(17, 'glass', 17.00, '2025-08-21 13:50:43', '2025-08-21 13:50:43');

--
-- Triggers `waste_rates`
--
DELIMITER $$
CREATE TRIGGER `log_new_rate` AFTER INSERT ON `waste_rates` FOR EACH ROW BEGIN
    INSERT INTO waste_rate_history (waste_rate_id, waste_type, rate_per_kg, updated_at)
    VALUES (NEW.id, NEW.waste_type, NEW.rate_per_kg, NEW.updated_at);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_rate_change` AFTER UPDATE ON `waste_rates` FOR EACH ROW BEGIN
    IF OLD.rate_per_kg <> NEW.rate_per_kg OR OLD.waste_type <> NEW.waste_type THEN
        INSERT INTO waste_rate_history (waste_rate_id, waste_type, rate_per_kg, updated_at)
        VALUES (OLD.id, OLD.waste_type, OLD.rate_per_kg, OLD.updated_at);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `waste_rate_history`
--

CREATE TABLE `waste_rate_history` (
  `id` int(11) NOT NULL,
  `waste_rate_id` int(11) NOT NULL,
  `waste_type` varchar(255) NOT NULL,
  `rate_per_kg` decimal(10,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pickup_requests`
--
ALTER TABLE `pickup_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reusable_waste_listings`
--
ALTER TABLE `reusable_waste_listings`
  ADD PRIMARY KEY (`listing_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `waste_rates`
--
ALTER TABLE `waste_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `waste_rate_history`
--
ALTER TABLE `waste_rate_history`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pickup_requests`
--
ALTER TABLE `pickup_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `reusable_waste_listings`
--
ALTER TABLE `reusable_waste_listings`
  MODIFY `listing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `waste_rates`
--
ALTER TABLE `waste_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `waste_rate_history`
--
ALTER TABLE `waste_rate_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reusable_waste_listings`
--
ALTER TABLE `reusable_waste_listings`
  ADD CONSTRAINT `reusable_waste_listings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
