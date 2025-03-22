-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Erstellungszeit: 22. Mrz 2025 um 09:30
-- Server-Version: 10.11.11-MariaDB-0+deb12u1
-- PHP-Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `erp_crm_system`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `contact_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `planned_duration` int(11) NOT NULL COMMENT 'in Minuten',
  `actual_duration` int(11) DEFAULT NULL COMMENT 'in Minuten',
  `rounded_duration` int(11) DEFAULT NULL COMMENT 'in 15-Minuten-Schritten gerundet',
  `status` enum('geplant','durchgeführt','abgesagt','verschoben','abgerechnet') DEFAULT 'geplant',
  `billing_status` enum('offen','abgerechnet') DEFAULT 'offen',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `billing_periods`
--

CREATE TABLE `billing_periods` (
  `period_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('offen','abgerechnet') DEFAULT 'offen',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `billing_summary`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `billing_summary` (
`customer_id` bigint(20) unsigned
,`customer_name` varchar(101)
,`appointment_id` bigint(20) unsigned
,`title` varchar(100)
,`start_time` datetime
,`end_time` datetime
,`actual_duration` int(11)
,`rounded_duration` int(11)
,`status` enum('geplant','durchgeführt','abgesagt','verschoben','abgerechnet')
,`billing_status` enum('offen','abgerechnet')
,`services_provided` mediumtext
);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `contacts`
--

CREATE TABLE `contacts` (
  `contact_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `position` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `customers`
--

CREATE TABLE `customers` (
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `customer_type` enum('private','business') NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `street` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'Deutschland',
  `company_name` varchar(100) DEFAULT NULL,
  `tax_id` varchar(30) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `industry` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `services`
--

CREATE TABLE `services` (
  `service_id` bigint(20) UNSIGNED NOT NULL,
  `appointment_id` bigint(20) UNSIGNED NOT NULL,
  `description` text NOT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'in Minuten',
  `billing_status` enum('offen','abgerechnet') DEFAULT 'offen',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `service_catalog`
--

CREATE TABLE `service_catalog` (
  `catalog_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `default_duration` int(11) DEFAULT NULL COMMENT 'in Minuten',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `contact_id` (`contact_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_appointments_customer` (`customer_id`),
  ADD KEY `idx_appointments_status` (`status`),
  ADD KEY `idx_appointments_billing` (`billing_status`),
  ADD KEY `idx_appointments_date` (`start_time`);

--
-- Indizes für die Tabelle `billing_periods`
--
ALTER TABLE `billing_periods`
  ADD PRIMARY KEY (`period_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indizes für die Tabelle `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`contact_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indizes für die Tabelle `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_customer_type` (`customer_type`);

--
-- Indizes für die Tabelle `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indizes für die Tabelle `service_catalog`
--
ALTER TABLE `service_catalog`
  ADD PRIMARY KEY (`catalog_id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `billing_periods`
--
ALTER TABLE `billing_periods`
  MODIFY `period_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `contacts`
--
ALTER TABLE `contacts`
  MODIFY `contact_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `services`
--
ALTER TABLE `services`
  MODIFY `service_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `service_catalog`
--
ALTER TABLE `service_catalog`
  MODIFY `catalog_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Struktur des Views `billing_summary`
--
DROP TABLE IF EXISTS `billing_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`_keyhelp_auto_login_67d2ca021b1d3`@`localhost` SQL SECURITY DEFINER VIEW `billing_summary`  AS SELECT `c`.`customer_id` AS `customer_id`, CASE WHEN `c`.`customer_type` = 'business' THEN `c`.`company_name` ELSE concat(`c`.`first_name`,' ',`c`.`last_name`) END AS `customer_name`, `a`.`appointment_id` AS `appointment_id`, `a`.`title` AS `title`, `a`.`start_time` AS `start_time`, `a`.`end_time` AS `end_time`, `a`.`actual_duration` AS `actual_duration`, `a`.`rounded_duration` AS `rounded_duration`, `a`.`status` AS `status`, `a`.`billing_status` AS `billing_status`, group_concat(`s`.`description` separator '; ') AS `services_provided` FROM ((`customers` `c` join `appointments` `a` on(`c`.`customer_id` = `a`.`customer_id`)) left join `services` `s` on(`a`.`appointment_id` = `s`.`appointment_id`)) WHERE `a`.`status` = 'durchgeführt' AND `a`.`billing_status` = 'offen' GROUP BY `c`.`customer_id`, `a`.`appointment_id` ORDER BY `c`.`customer_id` ASC, `a`.`start_time` ASC ;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints der Tabelle `billing_periods`
--
ALTER TABLE `billing_periods`
  ADD CONSTRAINT `billing_periods_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`);

--
-- Constraints der Tabelle `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints der Tabelle `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
