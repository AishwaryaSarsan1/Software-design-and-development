-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 30, 2025 at 11:30 PM
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
-- Database: `smart_nutrition`
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics_data`
--

CREATE TABLE `analytics_data` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `metric_type` enum('food_waste','calorie_intake','nutrition_goal','inventory_efficiency') NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `metric_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `food_items`
--

CREATE TABLE `food_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `storage_type` enum('pantry','refrigerator','freezer') DEFAULT 'pantry',
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit` varchar(20) DEFAULT 'pcs',
  `calories_per_unit` int(11) DEFAULT 0,
  `purchase_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `protein_per_unit` decimal(10,2) DEFAULT 0.00,
  `carbs_per_unit` decimal(10,2) DEFAULT 0.00,
  `fat_per_unit` decimal(10,2) DEFAULT 0.00,
  `fiber_per_unit` decimal(10,2) DEFAULT 0.00,
  `sugar_per_unit` decimal(10,2) DEFAULT 0.00,
  `sodium_per_unit` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_items`
--

INSERT INTO `food_items` (`id`, `user_id`, `name`, `category`, `storage_type`, `quantity`, `unit`, `calories_per_unit`, `purchase_date`, `expiry_date`, `created_at`, `protein_per_unit`, `carbs_per_unit`, `fat_per_unit`, `fiber_per_unit`, `sugar_per_unit`, `sodium_per_unit`) VALUES
(1, 2, 'Milk', 'Dairy', 'pantry', 1.00, 'pcs', 100, '2025-09-09', '2025-10-03', '2025-09-30 18:45:28', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(2, 2, 'chia', NULL, 'pantry', 1.00, 'pcs', 0, '2025-09-10', '2025-10-08', '2025-09-30 19:13:47', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(3, 2, 'apples', 'fruit', 'refrigerator', 1.00, 'pcs', 0, '2025-09-10', NULL, '2025-09-30 19:14:15', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(4, 3, 'mango', 'fruit', 'refrigerator', 2.00, 'pcs', 100, '2025-10-16', '2025-10-21', '2025-10-20 18:16:09', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(5, 3, 'kiwi', 'fruit', 'refrigerator', 1.00, 'pcs', 0, '2025-10-21', '2025-10-23', '2025-10-22 19:36:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(6, 3, 'Egg', 'Poutry', 'pantry', 1.00, 'pcs', 0, '2025-10-21', '2025-10-24', '2025-10-22 19:41:21', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(7, 3, 'watermelon', 'fruit', 'pantry', 1.00, 'pcs', 0, '2025-10-21', '2025-10-31', '2025-10-23 00:00:21', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(8, 3, 'milk', 'Dairy', 'refrigerator', 1.00, 'g', 0, '2025-10-26', '2025-10-28', '2025-10-27 18:32:56', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(9, 3, 'yogurt', 'Dairy', 'refrigerator', 1.00, 'g', 0, '2025-10-27', '2025-10-29', '2025-10-28 18:52:05', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(10, 3, 'yogurt', 'Dairy', 'refrigerator', 1.00, 'g', 0, '2025-10-27', '2025-10-29', '2025-10-28 18:54:35', 0.00, 0.10, 0.00, 0.00, 0.00, 0.00),
(11, 3, 'Egg', 'Poutry', 'pantry', 12.00, 'pcs', 0, '2025-10-27', '2025-10-29', '2025-10-28 18:55:29', 0.00, 0.36, 4.83, 0.00, 0.00, 0.00),
(12, 3, 'Egg', 'Poutry', 'pantry', 24.00, 'pcs', 45, '2025-10-28', '2025-10-30', '2025-10-29 13:58:02', 0.00, 0.36, 4.84, 0.00, 0.00, 0.00),
(13, 3, 'egg', 'Poutry', 'pantry', 1.00, 'pcs', 45, '2025-10-28', '2025-10-30', '2025-10-29 14:30:21', 0.00, 0.40, 4.80, 0.00, 0.20, 69.00),
(14, 3, 'Egg', 'Dairy', 'pantry', 1.00, 'pcs', 45, '2025-10-28', '2025-10-30', '2025-10-29 14:36:50', 0.00, 0.40, 4.80, 0.00, 0.20, 69.00),
(15, 3, 'yogurt', 'Dairy', 'refrigerator', 1.00, 'g', 0, '2025-10-28', '2025-10-30', '2025-10-29 14:37:52', 0.00, 0.10, 0.00, 0.00, 0.10, 0.00),
(16, 3, 'Sweet Potato', 'Vegetable', 'pantry', 1.00, 'pcs', 107, '2025-10-23', '2025-10-30', '2025-10-29 14:39:44', 0.00, 26.40, 0.20, 3.80, 8.70, 40.00),
(17, 3, 'Bannana', 'Fruit', 'refrigerator', 1.00, 'pcs', 0, '2025-11-01', '2025-11-03', '2025-11-03 00:49:53', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(18, 3, 'Bread', '', 'pantry', 1.00, 'packet', 67, '2025-11-10', '2025-11-11', '2025-11-10 16:44:39', 0.00, 14.60, 1.00, 0.80, 1.70, 143.00),
(19, 3, 'egg', 'Poutry', 'refrigerator', 12.00, 'pcs', 45, '2025-11-09', '2025-11-12', '2025-11-10 16:45:06', 0.00, 0.36, 4.83, 0.00, 0.19, 69.92),
(20, 3, 'egg', 'Poutry', 'pantry', 1.00, 'pcs', 45, '2025-11-17', '2025-11-18', '2025-11-17 19:39:48', 0.00, 0.40, 4.80, 0.00, 0.20, 69.00),
(21, 5, 'egg', 'Poutry', 'refrigerator', 11.00, 'pcs', 45, '2025-11-25', '2025-11-30', '2025-11-26 21:30:22', 0.00, 0.35, 4.84, 0.00, 0.19, 69.91),
(22, 4, 'Egg', '', 'pantry', 1.00, 'pcs', 45, NULL, NULL, '2025-11-26 23:37:46', 0.00, 0.40, 4.80, 0.00, 0.20, 69.00),
(23, 4, 'bread', 'grocery', 'pantry', 1.00, 'pcs', 67, '2025-11-25', '2025-11-27', '2025-11-26 23:41:48', 0.00, 14.60, 1.00, 0.80, 1.70, 143.00),
(24, 5, 'apple', 'fruit', 'refrigerator', 1.00, 'pcs', 105, '2025-11-25', '2025-11-27', '2025-11-26 23:59:25', 0.00, 25.60, 0.30, 4.30, 18.80, 1.00),
(25, 4, 'pasta', 'grocery', 'pantry', 1.00, 'pcs', 166, '2025-11-25', '2025-11-27', '2025-11-27 00:16:12', 0.00, 38.90, 1.10, 2.20, 0.70, 1.00),
(26, 5, 'mango', '', 'pantry', 1.00, 'pcs', 103, '2025-11-24', '2025-11-25', '2025-11-27 00:47:40', 0.00, 24.40, 0.60, 2.70, 22.40, 1.00),
(27, 5, 'carrots', 'Vegetable', 'pantry', 2.00, 'lb', 154, '2025-11-25', '2025-11-27', '2025-11-27 01:13:53', 0.00, 36.75, 0.80, 13.55, 15.60, 258.00),
(28, 4, 'Milk', 'Dairy', 'refrigerator', 1.00, 'g', 0, '2025-11-28', '2025-11-30', '2025-11-29 16:40:21', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(29, 4, 'egg', 'Poutry', 'refrigerator', 12.00, 'pcs', 45, '2025-11-28', '2025-11-30', '2025-11-29 16:51:05', 0.00, 0.36, 4.83, 0.00, 0.19, 69.92),
(30, 7, 'Eggs', 'Poutry', 'refrigerator', 12.00, 'pcs', 44, '2025-11-27', '2025-12-01', '2025-11-30 18:56:06', 0.00, 0.36, 4.68, 0.00, 0.19, 71.83);

-- --------------------------------------------------------

--
-- Table structure for table `meal_logs`
--

CREATE TABLE `meal_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `food_item_id` int(11) DEFAULT NULL,
  `recipe_id` int(11) DEFAULT NULL,
  `recipe_title` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit` varchar(20) DEFAULT 'pcs',
  `log_date` date NOT NULL,
  `meal_name` varchar(80) NOT NULL,
  `calories` int(11) NOT NULL,
  `protein` decimal(10,2) DEFAULT 0.00,
  `carbs` decimal(10,2) DEFAULT 0.00,
  `fat` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `meal_type` enum('breakfast','lunch','dinner','snack') DEFAULT 'breakfast',
  `consumed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meal_logs`
--

INSERT INTO `meal_logs` (`id`, `user_id`, `food_item_id`, `recipe_id`, `recipe_title`, `quantity`, `unit`, `log_date`, `meal_name`, `calories`, `protein`, `carbs`, `fat`, `created_at`, `meal_type`, `consumed_at`) VALUES
(1, 2, 1, NULL, NULL, 1.00, 'pcs', '0000-00-00', '', 100, 0.00, 0.00, 0.00, '2025-10-02 19:58:57', 'breakfast', '2025-10-02 19:58:57'),
(2, 2, 3, NULL, NULL, 1.00, 'pcs', '0000-00-00', '', 0, 0.00, 0.00, 0.00, '2025-10-02 19:59:31', 'breakfast', '2025-10-02 19:59:31'),
(3, 3, 4, NULL, NULL, 1.00, 'pcs', '0000-00-00', '', 100, 0.00, 0.00, 0.00, '2025-10-20 18:16:59', 'breakfast', '2025-10-20 18:16:59'),
(4, 3, 13, NULL, NULL, 1.00, 'pcs', '0000-00-00', '', 45, 0.00, 0.40, 4.80, '2025-10-29 14:58:43', 'breakfast', '2025-10-29 14:58:43'),
(5, 3, 14, NULL, NULL, 2.00, 'pcs', '0000-00-00', '', 90, 0.00, 0.80, 9.60, '2025-10-29 18:54:12', 'lunch', '2025-10-29 18:54:12'),
(6, 3, NULL, 1018582, 'Delicious Mango Pineapple Smoothie', 1.00, 'serving', '0000-00-00', '', 0, 0.00, 0.00, 0.00, '2025-10-29 21:05:16', 'dinner', '2025-10-29 21:05:16'),
(7, 3, NULL, 1018582, 'Delicious Mango Pineapple Smoothie', 1.00, 'serving', '0000-00-00', '', 91, 4.00, 9.50, 4.00, '2025-10-29 21:08:02', 'dinner', '2025-10-29 21:08:02'),
(8, 3, NULL, 655634, 'Pepita Crusted Chicken Salad With Sweet Adobo Vinaigrette', 1.00, 'serving', '0000-00-00', '', 182, 10.50, 19.25, 7.00, '2025-11-03 00:48:18', 'lunch', '2025-11-03 00:48:18'),
(9, 3, NULL, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 0, 0.00, 0.00, 0.00, '2025-11-03 00:48:56', 'dinner', '2025-11-03 00:48:56'),
(10, 3, NULL, 716426, 'Cauliflower, Brown Rice, and Vegetable Fried Rice', 1.00, 'serving', '0000-00-00', '', 31, 0.88, 3.50, 1.50, '2025-11-03 00:51:04', 'breakfast', '2025-11-03 00:51:04'),
(11, 3, 16, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 107, 0.00, 26.40, 0.20, '2025-11-10 15:25:20', 'dinner', '2025-11-10 15:25:20'),
(12, 3, 11, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 0, 0.00, 0.36, 4.83, '2025-11-10 16:08:36', 'dinner', '2025-11-10 16:08:36'),
(13, 3, 11, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 0, 0.00, 0.36, 4.83, '2025-11-10 16:09:14', 'dinner', '2025-11-10 16:09:14'),
(14, 5, NULL, 646515, 'Healthy Southwestern Oatmeal', 1.00, 'serving', '0000-00-00', '', 440, 26.00, 31.00, 23.00, '2025-11-26 21:33:29', 'dinner', '2025-11-26 21:33:29'),
(15, 5, 21, NULL, NULL, 4.00, 'serving', '0000-00-00', '', 180, 0.00, 1.40, 19.36, '2025-11-26 21:34:03', 'lunch', '2025-11-26 21:34:03'),
(16, 5, 21, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 45, 0.00, 0.35, 4.84, '2025-11-26 23:27:16', 'snack', '2025-11-26 23:27:16'),
(17, 4, 22, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 45, 0.00, 0.40, 4.80, '2025-11-26 23:38:04', 'lunch', '2025-11-26 23:38:04'),
(18, 4, NULL, 642701, 'Feta Walnut Spread with Baguette', 1.00, 'serving', '0000-00-00', '', 55, 2.17, 5.50, 2.67, '2025-11-26 23:42:36', 'dinner', '2025-11-26 23:42:36'),
(19, 5, 21, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 45, 0.00, 0.35, 4.84, '2025-11-26 23:58:44', 'snack', '2025-11-26 23:58:44'),
(20, 5, 24, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 105, 0.00, 25.60, 0.30, '2025-11-26 23:59:41', 'breakfast', '2025-11-26 23:59:41'),
(21, 4, NULL, NULL, NULL, 1.00, 'serving', '0000-00-00', '', 500, 0.00, 0.00, 0.00, '2025-11-27 00:04:01', 'dinner', '2025-11-27 00:04:01'),
(22, 4, NULL, 637440, 'Chapchae (Korean Stir-Fried Noodles)', 1.00, 'serving', '0000-00-00', '', 99, 1.25, 15.75, 3.50, '2025-11-27 00:16:40', 'dinner', '2025-11-27 00:16:40'),
(23, 5, NULL, 716431, 'Crockpot Applesauce', 1.00, 'serving', '0000-00-00', '', 137, 0.67, 35.33, 0.33, '2025-11-27 01:12:38', 'dinner', '2025-11-27 01:12:38'),
(24, 4, 22, NULL, NULL, 1.00, 'serving', '2025-11-29', 'Egg', 45, 0.00, 0.40, 4.80, '2025-11-29 16:53:10', 'breakfast', '2025-11-29 16:53:10'),
(25, 4, NULL, 622598, 'Pittata - Pizza Frittata', 1.00, 'serving', '0000-00-00', '', 299, 20.50, 4.00, 21.50, '2025-11-29 16:57:37', 'lunch', '2025-11-29 16:57:37'),
(26, 7, NULL, 157259, 'Cocoa Protein Pancakes', 1.00, 'serving', '0000-00-00', '', 205, 12.50, 23.00, 6.50, '2025-11-30 19:00:44', 'dinner', '2025-11-30 19:00:44'),
(27, 7, 30, NULL, NULL, 1.00, 'serving', '2025-11-30', 'Eggs', 44, 0.00, 0.36, 4.68, '2025-11-30 19:01:13', 'breakfast', '2025-11-30 19:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `otp_code`, `expires_at`, `used`, `created_at`) VALUES
(22, 5, 'rajbharathdere@gmail.com', '584697', '2025-11-27 00:17:26', 1, '2025-11-26 23:07:26'),
(29, 4, 'saishureddy@gmail.com', '372017', '2025-11-29 19:31:21', 0, '2025-11-29 18:21:21'),
(31, 7, 'testuser1@gmail.com', '713980', '2025-11-30 20:32:17', 0, '2025-11-30 19:22:17');

-- --------------------------------------------------------

--
-- Table structure for table `recipes`
--

CREATE TABLE `recipes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `ingredients` text NOT NULL,
  `instructions` text DEFAULT NULL,
  `calories` int(11) DEFAULT 0,
  `source` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `age` tinyint(3) UNSIGNED DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_otp` varchar(10) DEFAULT NULL,
  `reset_otp_expires` datetime DEFAULT NULL,
  `reset_otp_expires_at` datetime DEFAULT NULL,
  `cal_goal` int(11) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `activity_level` enum('sedentary','light','moderate','active','very_active') DEFAULT NULL,
  `auto_cal_goal` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `age`, `height_cm`, `weight_kg`, `email`, `password_hash`, `created_at`, `reset_otp`, `reset_otp_expires`, `reset_otp_expires_at`, `cal_goal`, `gender`, `activity_level`, `auto_cal_goal`) VALUES
(1, 'hghg', NULL, NULL, NULL, 'redy@gmail.com', '$2y$10$DlwE1SVsOhnA9JTdEvJR9.0dOY9uwQspLc0bRxBRRc5qkHd4SGaOy', '2025-09-30 17:52:25', NULL, NULL, NULL, NULL, NULL, NULL, 1),
(2, 'bb', 21, 180.00, 90.00, 'gdf@gmail.com', '$2y$10$W1CG0c/ORySR7fSP1G9WpedZN6PwmywA7W5QrnvmVw2LKd1BL5yxq', '2025-09-30 17:58:52', NULL, NULL, NULL, NULL, NULL, NULL, 1),
(3, 'apple', 21, 170.00, 65.00, 'apple@gmail.com', '$2y$10$D/YAsZa0P.JCF0MVmDHHSOnk95DvFvntbG58QIqdeYSwYln5Lq/Ci', '2025-10-20 18:13:56', NULL, NULL, NULL, NULL, NULL, NULL, 1),
(4, 'aishwarya', 29, 160.00, 50.00, 'saishureddy@gmail.com', '$2y$10$RKXDiku.CPAg7Z4cfmveNOY4UztustBzSGsytbqdabJsCBm79.73S', '2025-11-21 23:55:58', '391531', '2025-11-22 01:27:37', '2025-11-24 20:46:01', 1900, 'female', 'light', 0),
(5, 'Bharath', 32, 170.00, 66.00, 'rajbharathdere@gmail.com', '$2y$10$CethWJ48NOe7HFrrAINuIuTseuNhqIGvkBGtzv9vB1sAAlNHHPvy6', '2025-11-26 21:25:14', NULL, NULL, NULL, NULL, NULL, NULL, 1),
(6, 'Test user', 25, 160.00, 50.00, 'testuser@gmail.com', '$2y$10$ss8yEJpELRcKYhMJX8RoUeq.Bu9dTBTiCAd2sBNkqA.tLjdYjHoeS', '2025-11-30 18:52:42', NULL, NULL, NULL, NULL, NULL, NULL, 1),
(7, 'Test user1', 25, 160.00, 50.00, 'testuser1@gmail.com', '$2y$10$WEoU2hcCRpFCy6LNkxe87OmtMKs6R/bIA6u9j06.j.c.YVLmIGRqO', '2025-11-30 18:54:27', NULL, NULL, NULL, 1669, 'female', 'light', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_goals`
--

CREATE TABLE `user_goals` (
  `user_id` int(11) NOT NULL,
  `calorie_goal` int(11) DEFAULT 2000,
  `protein_goal` decimal(10,2) DEFAULT 75.00,
  `carbs_goal` decimal(10,2) DEFAULT 250.00,
  `fat_goal` decimal(10,2) DEFAULT 70.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics_data`
--
ALTER TABLE `analytics_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `food_items`
--
ALTER TABLE `food_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expiry_date` (`expiry_date`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `meal_logs`
--
ALTER TABLE `meal_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_meal_logs_food` (`food_item_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `email` (`email`),
  ADD KEY `otp_code` (`otp_code`);

--
-- Indexes for table `recipes`
--
ALTER TABLE `recipes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_goals`
--
ALTER TABLE `user_goals`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics_data`
--
ALTER TABLE `analytics_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `meal_logs`
--
ALTER TABLE `meal_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `recipes`
--
ALTER TABLE `recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `analytics_data`
--
ALTER TABLE `analytics_data`
  ADD CONSTRAINT `analytics_data_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `food_items`
--
ALTER TABLE `food_items`
  ADD CONSTRAINT `food_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meal_logs`
--
ALTER TABLE `meal_logs`
  ADD CONSTRAINT `fk_meal_logs_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `meal_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recipes`
--
ALTER TABLE `recipes`
  ADD CONSTRAINT `recipes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_goals`
--
ALTER TABLE `user_goals`
  ADD CONSTRAINT `user_goals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
