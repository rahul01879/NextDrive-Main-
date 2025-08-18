-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 12, 2025 at 01:15 PM
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
-- Database: `jack`
--

-- --------------------------------------------------------

--
-- Table structure for table `complaints_complaint`
--

CREATE TABLE `complaints_complaint` (
  `id` char(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `reporting_date` date NOT NULL,
  `city` varchar(100) NOT NULL,
  `description` longtext NOT NULL,
  `status` varchar(20) NOT NULL,
  `last_updated` datetime(6) NOT NULL,
  `resolution_details` longtext DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL,
  `priority` smallint(5) UNSIGNED NOT NULL CHECK (`priority` >= 0),
  `created_by_id` int(11) DEFAULT NULL,
  `report_type_id` bigint(20) NOT NULL,
  `supervisor_id` bigint(20) DEFAULT NULL,
  `ward_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `complaints_complaint`
--
ALTER TABLE `complaints_complaint`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaints_complaint_report_type_id_03d909a6_fk_complaint` (`report_type_id`),
  ADD KEY `complaints_complaint_supervisor_id_eeb5a7cd_fk_complaint` (`supervisor_id`),
  ADD KEY `complaints_complaint_ward_id_a09da571_fk_complaints_ward_id` (`ward_id`),
  ADD KEY `complaints__status_7c8de0_idx` (`status`),
  ADD KEY `complaints__reporti_aff2c2_idx` (`reporting_date`),
  ADD KEY `complaints_complaint_created_by_id_974948e4_fk_auth_user_id` (`created_by_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `complaints_complaint`
--
ALTER TABLE `complaints_complaint`
  ADD CONSTRAINT `complaints_complaint_created_by_id_974948e4_fk_auth_user_id` FOREIGN KEY (`created_by_id`) REFERENCES `auth_user` (`id`),
  ADD CONSTRAINT `complaints_complaint_report_type_id_03d909a6_fk_complaint` FOREIGN KEY (`report_type_id`) REFERENCES `complaints_complainttype` (`id`),
  ADD CONSTRAINT `complaints_complaint_supervisor_id_eeb5a7cd_fk_complaint` FOREIGN KEY (`supervisor_id`) REFERENCES `complaints_supervisor` (`id`),
  ADD CONSTRAINT `complaints_complaint_ward_id_a09da571_fk_complaints_ward_id` FOREIGN KEY (`ward_id`) REFERENCES `complaints_ward` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
