-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 17, 2026 at 10:47 AM
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
-- Database: `roadmap`
--

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `roadmap_id` int(11) NOT NULL,
  `issue_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `certificate_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `roadmap_id` int(11) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `roadmap_id`, `enrollment_date`) VALUES
(1, 9, 13, '2026-01-03 05:34:21'),
(2, 9, 7, '2026-01-04 15:23:29'),
(3, 9, 5, '2026-01-04 18:09:50'),
(4, 9, 14, '2026-01-05 11:57:04'),
(5, 9, 3, '2026-01-18 11:34:33');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `roadmap_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `roadmap_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_id` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `roadmap_id`, `amount`, `payment_date`, `transaction_id`) VALUES
(1, 9, 13, 85.00, '2026-01-03 05:34:21', 'MOCK_1767418461_4542'),
(2, 9, 7, 79.00, '2026-01-04 15:23:29', 'MOCK_1767540209_4318'),
(3, 9, 5, 885.00, '2026-01-04 18:09:50', 'MOCK_1767550190_8507'),
(4, 9, 14, 79.00, '2026-01-05 11:57:04', 'MOCK_1767614224_9233'),
(5, 9, 3, 784.00, '2026-01-18 11:34:33', 'MOCK_1768736073_6352');

-- --------------------------------------------------------

--
-- Table structure for table `progress`
--

CREATE TABLE `progress` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `progress`
--

INSERT INTO `progress` (`id`, `student_id`, `video_id`, `completed`, `completed_at`) VALUES
(1, 9, 6, 1, '2026-01-04 18:21:36'),
(2, 9, 7, 1, '2026-01-04 18:31:12'),
(3, 9, 10, 1, '2026-01-05 06:47:46'),
(5, 9, 11, 1, '2026-01-05 05:23:43'),
(7, 9, 12, 1, '2026-01-05 05:23:47'),
(9, 9, 13, 1, '2026-01-05 05:23:54'),
(12, 9, 28, 1, '2026-01-05 17:41:32'),
(13, 9, 29, 1, '2026-01-05 11:32:49'),
(14, 9, 30, 1, '2026-01-05 11:57:48'),
(16, 9, 8, 1, '2026-01-18 11:35:20'),
(18, 9, 9, 1, '2026-01-18 11:35:32');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `roadmap_id` int(11) DEFAULT NULL,
  `phase_id` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `passed` tinyint(1) NOT NULL,
  `certificate_id` int(11) DEFAULT NULL,
  `attempt_number` int(11) DEFAULT 1,
  `attempt_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roadmaps`
--

CREATE TABLE `roadmaps` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(100) DEFAULT '8 Weeks',
  `instructor_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected','changed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roadmaps`
--

INSERT INTO `roadmaps` (`id`, `title`, `description`, `duration`, `instructor_id`, `price`, `status`, `created_at`) VALUES
(3, 'wef', 'efhb', '8 Weeks', 4, 784.00, 'approved', '2025-12-28 12:24:30'),
(4, 'Test Roadmap', 'Test description', '8 Weeks', 4, 50.00, 'approved', '2025-12-28 12:32:24'),
(5, 'rf', 'fb', '8 Weeks', 4, 885.00, 'rejected', '2025-12-28 12:35:07'),
(6, 'simpl', 'fn', '8week', 4, 85.00, 'changed', '2025-12-29 09:01:36'),
(7, 'Test Roadmap121', 'Tesdclmst Description', '8 weeks', 4, 79.00, 'rejected', '2025-12-29 09:16:15'),
(10, 'lastt', 'sfgvm', '8week', 4, 25.00, 'pending', '2025-12-29 09:50:51'),
(12, 'ergf m', 'rfes', '8week', 4, 7894.00, 'pending', '2025-12-29 09:55:16'),
(13, 'sf', 'sf', '8week', 4, 85.00, 'rejected', '2025-12-30 16:19:09'),
(14, 'data analytics', 'sdfewf', '8 week', 1, 79.00, 'approved', '2026-01-05 11:30:29');

-- --------------------------------------------------------

--
-- Table structure for table `roadmap_phases`
--

CREATE TABLE `roadmap_phases` (
  `id` int(11) NOT NULL,
  `roadmap_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `phase_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roadmap_phases`
--

INSERT INTO `roadmap_phases` (`id`, `roadmap_id`, `title`, `phase_order`, `created_at`) VALUES
(6, 3, 'e', 1, '2025-12-28 12:24:30'),
(7, 3, 'gkmk', 2, '2025-12-28 12:24:30'),
(8, 3, 'wefm', 3, '2025-12-28 12:24:30'),
(9, 3, '32lejiu', 4, '2025-12-28 12:24:30'),
(10, 4, 'Introduction', 1, '2025-12-28 12:32:24'),
(11, 4, 'Getting Started', 2, '2025-12-28 12:32:24'),
(12, 5, 'skrjfn', 1, '2025-12-28 12:35:07'),
(13, 5, 'g', 2, '2025-12-28 12:35:07'),
(14, 5, 'bc', 3, '2025-12-28 12:35:07'),
(15, 5, 'bgvh', 4, '2025-12-28 12:35:07'),
(16, 6, 'efnj', 1, '2025-12-29 09:01:36'),
(17, 6, 'kjrfn', 2, '2025-12-29 09:01:36'),
(23, 10, 'rfkm', 1, '2025-12-29 09:50:51'),
(24, 10, 'remfn', 2, '2025-12-29 09:50:51'),
(25, 10, 'tgm,', 3, '2025-12-29 09:50:51'),
(28, 12, 'rfkm', 1, '2025-12-29 09:55:16'),
(29, 12, 'remfn', 2, '2025-12-29 09:55:16'),
(32, 13, 'rfkm', 1, '2025-12-30 16:19:09'),
(33, 13, 'remfn', 2, '2025-12-30 16:19:09'),
(34, 14, 'rfkm', 1, '2026-01-05 11:30:29'),
(35, 14, 'remfn', 2, '2026-01-05 11:30:29'),
(36, 14, 'drfn', 3, '2026-01-05 11:30:29');

-- --------------------------------------------------------

--
-- Table structure for table `roadmap_videos`
--

CREATE TABLE `roadmap_videos` (
  `id` int(11) NOT NULL,
  `phase_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `video_url` varchar(255) NOT NULL,
  `video_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roadmap_videos`
--

INSERT INTO `roadmap_videos` (`id`, `phase_id`, `title`, `video_url`, `video_order`, `created_at`) VALUES
(6, 6, 'selfkm', 'uploads/videos/video_6951217ed3d964.97191121_1766924670.mp4', 1, '2025-12-28 12:24:30'),
(7, 7, 'ltkgm', 'uploads/videos/video_6951217ed589e6.42702254_1766924670.mp4', 1, '2025-12-28 12:24:30'),
(8, 8, 'wefj', 'uploads/videos/video_6951217edcaef7.74996738_1766924670.mp4', 1, '2025-12-28 12:24:30'),
(9, 9, 'wkeu', 'uploads/videos/video_6951217eddcc31.48738377_1766924670.mp4', 1, '2025-12-28 12:24:30'),
(10, 12, 'skfn', 'uploads/videos/video_695123fb76a3d8.19896403_1766925307.mp4', 1, '2025-12-28 12:35:07'),
(11, 13, 'hgv', 'uploads/videos/video_695123fb78fc64.94258385_1766925307.mp4', 1, '2025-12-28 12:35:07'),
(12, 14, 'kjhy', 'uploads/videos/video_695123fb79b246.69362173_1766925307.mp4', 1, '2025-12-28 12:35:07'),
(13, 15, 'gfcg', 'uploads/videos/video_695123fb7ccfb4.08530815_1766925307.mp4', 1, '2025-12-28 12:35:07'),
(17, 23, 'dgkm,', 'uploads/videos/video_69524efb0c60c5.18699486_1767001851.mp4', 1, '2025-12-29 09:50:51'),
(18, 24, 'gkm,dtg', 'uploads/videos/video_69524efb0d5428.28711422_1767001851.mp4', 1, '2025-12-29 09:50:51'),
(19, 25, 'etglkmrt', 'uploads/videos/video_69524efb0e8314.32029154_1767001851.mp4', 1, '2025-12-29 09:50:51'),
(22, 28, 'rfknm', 'uploads/videos/video_6952500444de93.21602710_1767002116.mp4', 1, '2025-12-29 09:55:16'),
(23, 29, 'rfnm', 'uploads/videos/video_6952500445edd7.33100016_1767002116.mp4', 1, '2025-12-29 09:55:16'),
(26, 32, 'rfknm', 'uploads/videos/video_6953fb7d073e87.69617211_1767111549.mp4', 1, '2025-12-30 16:19:09'),
(27, 33, 'new', 'uploads/videos/video_6953fb7d0c22b0.10352214_1767111549.mp4', 1, '2025-12-30 16:19:09'),
(28, 34, 'rfknm', 'uploads/videos/video_695ba0d5217a40.38701381_1767612629.mp4', 1, '2026-01-05 11:30:29'),
(29, 35, 'new', 'uploads/videos/video_695ba0d5273406.88341683_1767612629.mp4', 1, '2026-01-05 11:30:29'),
(30, 36, 'fn', 'uploads/videos/video_695ba0d52a4197.86268633_1767612629.mp4', 1, '2026-01-05 11:30:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','instructor','student') NOT NULL DEFAULT 'student',
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `first_login` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `first_login`, `created_at`, `profile_picture`) VALUES
(1, 'Feny Patel', 'fenyp8421@gmail.com', '$2y$10$kvolbgQeJCBwGzt46f3RVe6P9rUKgpr/sz9xIdvz/cizBR9kx0NfS', 'student', 'active', 0, '2025-12-26 16:13:18', NULL),
(2, 'ee', 'ee@gmail.com', '$2y$10$pGEjLQIIr.Zvt/0LFn94..BookNJkZgBKRB0BDQObKVIhQrWhc4Pm', 'student', 'active', 0, '2025-12-27 05:19:16', NULL),
(3, 'ee1', 'ee1@gmail.com', '$2y$10$WHHkGgHNHmjT6U8xsxsn6ugsCF.m..MMYrbSlfWYroUoQPKGqFSZi', 'student', 'active', 0, '2025-12-27 05:41:02', NULL),
(4, 'Happy', 'happy@gmail.com', '$2y$10$.4gZ5idjo6ulNk522txQguoG58bP3L4BR7yPtfOQCiDWBy9dZf1MG', 'instructor', 'active', 0, '2025-12-27 15:28:34', NULL),
(5, 'jash', 'jash@gmail.com', '$2y$10$K4vHSmEOAu75mLZQlBqaZOQ4bZ9PCURnEDyGA.YKljRJRUMHqLioC', 'instructor', 'active', 0, '2025-12-29 15:48:05', NULL),
(6, 'foram', 'foram@gmail.com', '$2y$10$HVmyLd2pCvD6tJtwts04gOEXycmRY6AXtFdzxJ/o7uRD87ftIIdX2', 'instructor', 'active', 0, '2025-12-29 15:51:31', NULL),
(7, 'Feny Patel', 'feny@gmail.com', '$2y$10$rt9CyljuXjO9qYeAacg5CO9pxC3PXpCR9.2p.oipbX0Q3pFgdu8aC', 'instructor', 'active', 0, '2025-12-29 15:53:18', NULL),
(8, 'jash', 'jash1@gmail.com', '$2y$10$BtXi549T7GfvwefjIYzoU.73w8rHaW4hAZUwvKGDzk4tKB2ffJxtq', 'student', 'active', 0, '2025-12-30 16:41:45', NULL),
(9, 'Riya', 'riya@gmail.com', '$2y$10$w.sc/II6CCO50pUIP98Sl.L08O7TcyfSCbk3ZPuQ8ogeJh9PSx4Ee', 'student', 'active', 0, '2026-01-01 14:53:30', '/fianlroadmap/uploads/profile_pictures/user_9_1767438271.jpg'),
(10, 'virat', 'virat18@gmail.com', '$2y$10$QzKFnLq9XdQEDpdFl2Tvg.Kd1DTIL9gu7Adw3VKFznka3sIhv7dWW', 'student', 'active', 0, '2026-01-03 04:47:13', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `roadmap_id` (`roadmap_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_roadmap_unique` (`student_id`,`roadmap_id`),
  ADD KEY `roadmap_id` (`roadmap_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `roadmap_id` (`roadmap_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `roadmap_id` (`roadmap_id`);

--
-- Indexes for table `progress`
--
ALTER TABLE `progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_video_unique` (`student_id`,`video_id`),
  ADD KEY `video_id` (`video_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `phase_id` (`phase_id`),
  ADD KEY `roadmap_id` (`roadmap_id`),
  ADD KEY `certificate_id` (`certificate_id`);

--
-- Indexes for table `roadmaps`
--
ALTER TABLE `roadmaps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `status_idx` (`status`);

--
-- Indexes for table `roadmap_phases`
--
ALTER TABLE `roadmap_phases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `roadmap_id` (`roadmap_id`);

--
-- Indexes for table `roadmap_videos`
--
ALTER TABLE `roadmap_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `phase_id` (`phase_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `email_idx` (`email`),
  ADD KEY `role_idx` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `progress`
--
ALTER TABLE `progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roadmaps`
--
ALTER TABLE `roadmaps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `roadmap_phases`
--
ALTER TABLE `roadmap_phases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `roadmap_videos`
--
ALTER TABLE `roadmap_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `progress`
--
ALTER TABLE `progress`
  ADD CONSTRAINT `progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `progress_ibfk_2` FOREIGN KEY (`video_id`) REFERENCES `roadmap_videos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`phase_id`) REFERENCES `roadmap_phases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_attempts_ibfk_3` FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_attempts_ibfk_4` FOREIGN KEY (`certificate_id`) REFERENCES `certificates` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `roadmaps`
--
ALTER TABLE `roadmaps`
  ADD CONSTRAINT `roadmaps_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `roadmap_phases`
--
ALTER TABLE `roadmap_phases`
  ADD CONSTRAINT `roadmap_phases_ibfk_1` FOREIGN KEY (`roadmap_id`) REFERENCES `roadmaps` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `roadmap_videos`
--
ALTER TABLE `roadmap_videos`
  ADD CONSTRAINT `roadmap_videos_ibfk_1` FOREIGN KEY (`phase_id`) REFERENCES `roadmap_phases` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
