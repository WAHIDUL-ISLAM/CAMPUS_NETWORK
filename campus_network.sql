-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 17, 2025 at 01:47 AM
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
-- Database: `campus_network`
--

-- --------------------------------------------------------

--
-- Table structure for table `deleted_events_log`
--

CREATE TABLE `deleted_events_log` (
  `log_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `location` varchar(160) DEFAULT NULL,
  `poster` varchar(255) DEFAULT NULL,
  `host_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` varchar(160) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deleted_events_log`
--

INSERT INTO `deleted_events_log` (`log_id`, `event_id`, `title`, `description`, `event_date`, `location`, `poster`, `host_user_id`, `created_at`, `deleted_at`, `deleted_by`) VALUES
(2, 10, 'Ekhon ektu dekhi try kore', 'Kaj top kore', '2025-08-20 04:14:00', 'audi 801', NULL, 32, '2025-08-13 04:12:02', '2025-08-13 04:18:11', 'WAHIDUL ISLAM ZIAD'),
(3, 11, 'Testing', 'Test Purpose', '2025-08-26 13:39:00', 'audi 802', NULL, 32, '2025-08-13 10:36:27', '2025-08-13 04:37:11', 'WAHIDUL ISLAM ZIAD'),
(4, 3, 'Emni Hudai Kortesi', 'Baler Project kortesi dhon amr', '2025-08-17 07:47:00', 'VC Room', NULL, 15, '2025-08-13 03:44:36', '2025-08-13 04:43:15', 'Wahidul Islam Ziad'),
(5, 6, 'dafdfdsaf', 'fafdfsdaf', '2025-08-27 03:59:00', 'fdsfdsf', NULL, 15, '2025-08-13 03:58:17', '2025-08-15 12:52:30', 'Wahidul Islam Ziad'),
(6, 5, 'Bal Amar Again', 'Dhon koritesi ekhon rate', '2025-09-24 18:56:00', 'VC Room', NULL, 15, '2025-08-13 03:55:14', '2025-08-15 12:53:54', 'Wahidul Islam Ziad'),
(7, 7, 'dhon kaj kore na kn', 'emni', '2025-08-26 20:02:00', 'audi 801', NULL, 32, '2025-08-13 04:02:14', '2025-08-16 04:59:18', 'WAHIDUL ISLAM ZIAD'),
(8, 18, 'Emni2', NULL, '2025-08-27 11:47:00', 'VC BED ROOM', NULL, 32, '2025-08-16 11:43:25', '2025-08-16 05:44:06', 'WAHIDUL ISLAM ZIAD');

-- --------------------------------------------------------

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `event_id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `location` varchar(160) DEFAULT NULL,
  `poster` varchar(255) DEFAULT NULL,
  `host_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event`
--

INSERT INTO `event` (`event_id`, `title`, `description`, `event_date`, `location`, `poster`, `host_user_id`, `created_at`) VALUES
(2, 'Test Event', 'This is a sample event to test data fetching.', '2026-08-15 00:00:00', 'New York City..', NULL, 32, '2025-08-12 03:35:40'),
(8, 'Again test Korlam', 'emni', '2025-08-18 16:06:00', 'audi 801', NULL, 32, '2025-08-13 04:04:18'),
(9, 'Test purpose', 'Ekhon ki kaj korbe?', '2025-08-07 04:11:00', 'audi 801', NULL, 32, '2025-08-13 04:10:54'),
(12, 'Test event 1', NULL, '2025-08-19 11:03:00', NULL, NULL, 32, '2025-08-16 11:00:12'),
(13, 'Test event 2', NULL, '2025-08-29 04:00:00', NULL, NULL, 32, '2025-08-16 11:00:24'),
(14, 'Event 3', NULL, '2025-08-28 11:04:00', NULL, NULL, 32, '2025-08-16 11:00:37'),
(15, '4', NULL, '2025-08-28 11:06:00', NULL, NULL, 32, '2025-08-16 11:01:05'),
(16, '5', NULL, '2025-09-04 11:06:00', NULL, NULL, 32, '2025-08-16 11:01:14'),
(17, '6', NULL, '2025-08-21 11:05:00', NULL, NULL, 32, '2025-08-16 11:01:21'),
(19, 'For Testing Purpose', 'An object\r\n represents an entity in the real world that can be distinctly \r\nidentified.  For example, student, desk, circle, button, person, course, \r\netc…\r\n For instance, an object might represent a particular employee\r\n company. Each employee object\r\n in a \r\nhandles the processing and data \r\nmanagement related to that employee.\r\n An object has a unique identity\r\n , state\r\n The state\r\n , and behaviors\r\n of an object consists of a set of data\r\n .\r\n fields (instance variables \r\nor properties) with their current values.\r\n The behavior\r\n of an object is defined by a set of methods\r\n class from which the object is created.', '2025-08-27 21:10:00', 'New York City', NULL, 32, '2025-08-16 21:07:49');

--
-- Triggers `event`
--
DELIMITER $$
CREATE TRIGGER `before_event_delete` BEFORE DELETE ON `event` FOR EACH ROW BEGIN
    DECLARE host_name VARCHAR(160);

    -- Get host full name from users table
    SELECT CONCAT(first_name, ' ', last_name)
    INTO host_name
    FROM users
    WHERE id = OLD.host_user_id
    LIMIT 1;

    -- Insert into deleted_events_log, storing host name in deleted_by column
    INSERT INTO deleted_events_log (
        event_id,
        title,
        description,
        event_date,
        location,
        poster,
        host_user_id,
        created_at,
        deleted_by
    )
    VALUES (
        OLD.event_id,
        OLD.title,
        OLD.description,
        OLD.event_date,
        OLD.location,
        OLD.poster,
        OLD.host_user_id,
        OLD.created_at,
        host_name
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `event_participant`
--

CREATE TABLE `event_participant` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `major` varchar(100) DEFAULT NULL,
  `applied_at` datetime DEFAULT NULL,
  `registered_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_participant`
--

INSERT INTO `event_participant` (`id`, `event_id`, `user_id`, `student_id`, `phone`, `department`, `major`, `applied_at`, `registered_at`) VALUES
(8, 2, 32, NULL, NULL, NULL, NULL, NULL, '2025-08-15 00:19:31'),
(10, 8, 15, '2231985642', '01890305778', 'ECE', 'CSE', '2025-08-15 18:14:07', '2025-08-15 18:14:07');

-- --------------------------------------------------------

--
-- Table structure for table `opportunities`
--

CREATE TABLE `opportunities` (
  `op_post_id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `organization` varchar(180) DEFAULT NULL,
  `type` varchar(80) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `posted_date` date NOT NULL,
  `deadline` date DEFAULT NULL,
  `poster_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `success_stories`
--

CREATE TABLE `success_stories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `author` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `success_stories`
--

INSERT INTO `success_stories` (`id`, `user_id`, `author`, `content`, `photo`, `created_at`, `updated_at`) VALUES
(3, 32, 'WAHIDUL ISLAM ZIAD', 'what the fuck is nsu doing?', 'uploads/story_689f434fc3025.webp', '2025-08-15 20:25:19', NULL),
(5, 32, 'WAHIDUL ISLAM ZIAD', 'Very Old Pic', 'uploads/story_689faa703feeb.jpg', '2025-08-16 03:45:20', NULL),
(6, 32, 'WAHIDUL ISLAM ZIAD', 'Mara is on fire....', 'uploads/story_68a01a3059de2.jpg', '2025-08-16 11:42:08', NULL);

--
-- Triggers `success_stories`
--
DELIMITER $$
CREATE TRIGGER `before_success_story_delete` BEFORE DELETE ON `success_stories` FOR EACH ROW BEGIN
    INSERT INTO success_stories_deleted
    (id, user_id, author, content, photo, created_at)
    VALUES
    (OLD.id, OLD.user_id, OLD.author, OLD.content, OLD.photo, OLD.created_at);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `success_stories_deleted`
--

CREATE TABLE `success_stories_deleted` (
  `id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `success_stories_deleted`
--

INSERT INTO `success_stories_deleted` (`id`, `user_id`, `author`, `content`, `photo`, `created_at`, `deleted_at`) VALUES
(2, 32, 'WAHIDUL ISLAM ZIAD', 'Huga mara is on fire and flower', 'uploads/story_689f3f256617c.jpg', '2025-08-15 20:07:33', '2025-08-16 11:04:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('student','alumni','admin') NOT NULL DEFAULT 'student',
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `student_id` varchar(40) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `degree` varchar(120) DEFAULT NULL,
  `batch` varchar(50) DEFAULT NULL,
  `employer` varchar(160) DEFAULT NULL,
  `job_title` varchar(160) DEFAULT NULL,
  `profile_complete` tinyint(1) NOT NULL DEFAULT 0,
  `grad_year` varchar(10) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `first_name`, `last_name`, `profile_photo`, `email`, `password_hash`, `student_id`, `department`, `degree`, `batch`, `employer`, `job_title`, `profile_complete`, `grad_year`, `gender`, `created_at`, `last_login`) VALUES
(15, 'student', 'Wahidul Islam', 'Ziad', NULL, 'wahidul.islam.ziad@gmail.com', '$2y$10$lF4QbPJtVsZOjA.ynRqT9.YnhadE8.nxPnwOfqrlhxeAJnGsKCluq', '2231985642', 'ECE', 'BSc', '223', NULL, NULL, 0, '2026', 'male', '2025-08-11 13:38:09', NULL),
(28, 'student', 'WAHIDUL ISLAM', 'ZIAD', NULL, 'ziadchowdhury24@gmail.com', '$2y$10$MCxycYXCQlbdMzZKVS0RgeMg4pFzsdQjOomBr0zpZ35npD0X0ernu', '2231985642', 'Computer Science & Engineering', 'BSC', '223', '', '', 1, '2026', 'male', '2025-08-11 16:59:50', NULL),
(32, 'alumni', 'WAHIDUL ISLAM', 'ZIAD', '/uploads/avatars/32_1755286298.jpg', 'ziadchowdhury20@gmail.com', '$2y$10$7E16ybbbNcDNX8n87rlDw..hs2NB3tm/OYvFMQkF9eNQ1blaHq/UG', '', 'Computer Science & Engineering', 'BSC', '', 'Private Job', 'Developer', 1, '2026', 'male', '2025-08-11 17:22:02', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `deleted_events_log`
--
ALTER TABLE `deleted_events_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `event`
--
ALTER TABLE `event`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `host_user_id` (`host_user_id`),
  ADD KEY `event_date` (`event_date`);

--
-- Indexes for table `event_participant`
--
ALTER TABLE `event_participant`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_event_user` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `opportunities`
--
ALTER TABLE `opportunities`
  ADD PRIMARY KEY (`op_post_id`),
  ADD KEY `poster_user_id` (`poster_user_id`),
  ADD KEY `posted_date` (`posted_date`),
  ADD KEY `deadline` (`deadline`);

--
-- Indexes for table `success_stories`
--
ALTER TABLE `success_stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_success_stories_user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_created` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `deleted_events_log`
--
ALTER TABLE `deleted_events_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `event`
--
ALTER TABLE `event`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `event_participant`
--
ALTER TABLE `event_participant`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `opportunities`
--
ALTER TABLE `opportunities`
  MODIFY `op_post_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `success_stories`
--
ALTER TABLE `success_stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `event`
--
ALTER TABLE `event`
  ADD CONSTRAINT `event_ibfk_1` FOREIGN KEY (`host_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_participant`
--
ALTER TABLE `event_participant`
  ADD CONSTRAINT `event_participant_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_participant_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `opportunities`
--
ALTER TABLE `opportunities`
  ADD CONSTRAINT `opportunities_ibfk_1` FOREIGN KEY (`poster_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `success_stories`
--
ALTER TABLE `success_stories`
  ADD CONSTRAINT `fk_success_stories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
