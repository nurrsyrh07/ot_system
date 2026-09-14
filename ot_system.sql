-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 14, 2026 at 09:04 AM
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
-- Database: `ot_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `ot_approvals`
--

CREATE TABLE `ot_approvals` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `approver_id` int(10) UNSIGNED NOT NULL,
  `stage` tinyint(3) UNSIGNED NOT NULL,
  `decision` enum('approved','rejected') NOT NULL,
  `comment` text DEFAULT NULL,
  `acted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `ot_approvals`
--

INSERT INTO `ot_approvals` (`id`, `request_id`, `approver_id`, `stage`, `decision`, `comment`, `acted_at`) VALUES
(1, 1, 4, 1, 'approved', 'ok', '2026-09-14 01:05:30'),
(2, 1, 9, 2, 'approved', NULL, '2026-09-14 01:08:13'),
(3, 2, 4, 1, 'approved', NULL, '2026-09-14 01:54:58'),
(4, 2, 9, 2, 'approved', NULL, '2026-09-14 01:55:07'),
(5, 3, 4, 1, 'approved', NULL, '2026-09-14 03:34:28'),
(6, 3, 9, 2, 'approved', NULL, '2026-09-14 03:34:42');

-- --------------------------------------------------------

--
-- Table structure for table `ot_requests`
--

CREATE TABLE `ot_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `ot_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `total_hours` decimal(5,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending_stage1','pending_stage2','approved','rejected','cancelled') NOT NULL DEFAULT 'pending_stage1',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ot_requests`
--

INSERT INTO `ot_requests` (`id`, `staff_id`, `ot_date`, `start_time`, `end_time`, `total_hours`, `reason`, `status`, `created_at`, `updated_at`) VALUES
(1, 6, '2026-09-14', '09:04:00', '10:04:00', 1.00, 'OT', 'approved', '2026-09-14 01:04:54', '2026-09-14 01:08:13'),
(2, 11, '2026-09-14', '09:54:00', '10:54:00', 1.00, 'ot', 'approved', '2026-09-14 01:54:15', '2026-09-14 01:55:07'),
(3, 6, '2026-09-15', '11:33:00', '13:33:00', 2.00, 'working on overtime system', 'approved', '2026-09-14 03:34:05', '2026-09-14 03:34:42');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(4, 6, 'f38cb98f192af27e2667d235670328f078ca71266ea7f7adf71e895417ccc3d3', '2026-09-14 11:29:26', '2026-09-14 11:00:36', '2026-09-14 10:59:26'),
(5, 6, 'c6afbdc660245926e83a06306e02c908029dfc24d40b4de67912f0ddd6d952a7', '2026-09-14 11:45:11', '2026-09-14 11:15:37', '2026-09-14 11:15:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_no` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('staff','approver','admin') NOT NULL,
  `approval_stage` tinyint(3) UNSIGNED DEFAULT NULL,
  `category` enum('below_ae','ae_above') DEFAULT NULL,
  `level` enum('operator','leader','engineer') DEFAULT NULL,
  `level_token` varchar(64) DEFAULT NULL,
  `level_set_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `staff_no`, `name`, `department`, `email`, `password_hash`, `role`, `approval_stage`, `category`, `level`, `level_token`, `level_set_at`, `is_active`, `created_at`) VALUES
(4, 'A001', 'Salim', 'MIS', 'salim@jcy.com', '$2y$10$Nggen7CAX5RDJD73HX1DMusDD./gU7Iqq02v3HyAMP50.mVsu/Xsu', 'approver', 1, 'ae_above', NULL, '', NULL, 1, '2026-09-14 00:51:07'),
(6, 'S002', 'Syahirah', 'MIS', 'nur.syahirah@jcyinternational.com', '$2y$10$mtYSwNkVkwlAo6165XLCh.x.MRofwotjevD8PUF4h7DoaZVCM0OJW', 'staff', NULL, 'ae_above', NULL, NULL, '2026-09-14 01:03:51', 1, '2026-09-14 01:00:52'),
(9, 'A002', 'CK Teh', 'MIS', 'ck@jcy.com', '$2y$10$crisvcxc4mYQckvq92lLnu95.SNaCxO0f6jAm8r.TZcqn5b3uBEBK', 'approver', 2, 'ae_above', NULL, NULL, NULL, 1, '2026-09-14 00:51:07'),
(11, 'S001', 'halim', 'MIS', 'halim@jcy.com', '$2y$10$i86su/hD67ypumKWZf.GWuEEafy4IfDqocWU0U8OKFaLGU.r0xzkO', 'staff', NULL, 'below_ae', NULL, NULL, '2026-09-14 01:53:37', 1, '2026-09-14 01:52:54'),
(13, 'A003', 'Ms Ena', 'HR', 'admin@jcy.com', '$2y$10$iOCp50GwS8/j5liVrfGr/.FXEnKn749w5f3yy8JNpf9jq55IIkc/W', 'admin', NULL, NULL, NULL, NULL, NULL, 1, '2026-09-14 06:18:01'),
(14, 'S003', 'Azrina', 'MIS', 'nur.azrina@jcyinternational.com', '$2y$10$0ltB.nkoo9cOvibN64mPROZsMH2DrvlSqOOGdB1iLS4rbcscBBeUi', 'staff', NULL, 'ae_above', NULL, NULL, '2026-09-14 06:38:49', 1, '2026-09-14 06:38:12'),
(15, 'S004', 'Aaron', 'MIS', 'aa@jcy.com', '$2y$10$ALepsys9IoZ6MZgQYlR8.OUB7cxQw6/rTdVhQujcNX4dKoB4IfkCm', 'staff', NULL, NULL, NULL, '1ea0417faf4a65fa3e05d7e0c4ce3478c93a66a2ddc157cc03a7ad7f47a5a255', NULL, 1, '2026-09-14 06:42:32'),
(16, 'S005', 'TEST', 'MIS', 'test@jcy.com', '$2y$10$LzsF8Th2IfBDo6562tlxVO8F2BjlwZNo1i9ASE1EBXTT5Sx7OwYc2', 'staff', NULL, NULL, NULL, 'a1d0ac8bd5b1b867cc95b51a8b997cf3a6826f8aa67b6c38e2cd649c03de13f7', NULL, 1, '2026-09-14 06:48:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ot_approvals`
--
ALTER TABLE `ot_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_approvals_request` (`request_id`),
  ADD KEY `idx_approvals_approver` (`approver_id`);

--
-- Indexes for table `ot_requests`
--
ALTER TABLE `ot_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_staff` (`staff_id`),
  ADD KEY `idx_requests_created` (`created_at`),
  ADD KEY `idx_requests_date` (`ot_date`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_password_resets_user_id` (`user_id`),
  ADD KEY `idx_password_resets_expires_at` (`expires_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_no` (`staff_no`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `level_token` (`level_token`),
  ADD KEY `idx_role_stage` (`role`,`approval_stage`),
  ADD KEY `idx_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ot_approvals`
--
ALTER TABLE `ot_approvals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ot_requests`
--
ALTER TABLE `ot_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ot_approvals`
--
ALTER TABLE `ot_approvals`
  ADD CONSTRAINT `fk_ot_approvals_approver` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ot_approvals_request` FOREIGN KEY (`request_id`) REFERENCES `ot_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ot_requests`
--
ALTER TABLE `ot_requests`
  ADD CONSTRAINT `fk_ot_requests_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
