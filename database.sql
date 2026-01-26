/*
SQLyog Community v13.3.1 (64 bit)
MySQL - 11.8.3-MariaDB-log : Database - u514031374_leir
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`u514031374_leir` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `u514031374_leir`;

/*Table structure for table `activity_logs` */

DROP TABLE IF EXISTS `activity_logs`;

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `affected_id` int(11) DEFAULT NULL,
  `affected_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_action` (`user_id`,`action`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `activity_logs` */

insert  into `activity_logs`(`id`,`user_id`,`action`,`description`,`ip_address`,`user_agent`,`affected_id`,`affected_type`,`created_at`) values 
(1,1012,'profile_update','Updated profile information','61.245.120.48',NULL,NULL,NULL,'2026-01-22 17:27:13'),
(2,1012,'profile_update','Updated profile information','61.245.120.48',NULL,NULL,NULL,'2026-01-22 17:27:53'),
(3,1019,'Created user #1021 (andy doza) with role tanod','','61.245.120.54','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',NULL,NULL,'2026-01-22 19:35:48'),
(4,1019,'Updated user #1021 (Role: tanod, Status: 0)','','103.186.138.48','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',NULL,NULL,'2026-01-22 19:39:22'),
(5,1019,'Updated user #1021 (Role: citizen, Status: 0)','','103.186.138.48','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',NULL,NULL,'2026-01-22 19:39:33'),
(6,1019,'Updated user #1021 (Role: tanod, Status: 1)','','103.186.138.48','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',NULL,NULL,'2026-01-22 19:39:41'),
(7,1019,'Updated user #1003 (Role: citizen, Status: 1)','','103.167.116.86','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',NULL,NULL,'2026-01-22 19:42:53');

/*Table structure for table `ai_classification_logs` */

DROP TABLE IF EXISTS `ai_classification_logs`;

CREATE TABLE `ai_classification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) DEFAULT NULL,
  `input_text` text NOT NULL,
  `predicted_jurisdiction` enum('barangay','police','uncertain') NOT NULL,
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `keywords_found` text DEFAULT NULL,
  `reasoning` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_report` (`report_id`),
  CONSTRAINT `ai_classification_logs_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `ai_classification_logs` */

/*Table structure for table `announcements` */

DROP TABLE IF EXISTS `announcements`;

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'low',
  `target_role` enum('citizen','tanod','secretary','captain','admin','all') DEFAULT 'citizen',
  `barangay` varchar(100) NOT NULL,
  `is_emergency` tinyint(1) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `attachment` varchar(255) DEFAULT NULL,
  `posted_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_announcements_active` (`is_active`,`barangay`,`target_role`),
  KEY `idx_announcements_barangay_active` (`barangay`,`is_active`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `announcements` */

/*Table structure for table `api_integrations` */

DROP TABLE IF EXISTS `api_integrations`;

CREATE TABLE `api_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_name` varchar(100) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `endpoint` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','testing') DEFAULT 'testing',
  `last_sync` timestamp NULL DEFAULT NULL,
  `sync_status` enum('success','failed','pending') DEFAULT 'pending',
  `sync_message` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_api_name` (`api_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `api_integrations` */

/*Table structure for table `backup_logs` */

DROP TABLE IF EXISTS `backup_logs`;

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_time` datetime NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `backup_logs` */

/*Table structure for table `barangay_personnel_master_codes` */

DROP TABLE IF EXISTS `barangay_personnel_master_codes`;

CREATE TABLE `barangay_personnel_master_codes` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `master_code` varchar(12) NOT NULL,
  `generated_for_email` varchar(100) DEFAULT NULL,
  `generated_for_name` varchar(100) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `purpose` enum('registration','password_reset','master_code_reset') DEFAULT 'registration',
  `is_used` tinyint(1) DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  KEY `barangay_personnel_master_codes_ibfk_1` (`admin_id`),
  KEY `barangay_personnel_master_codes_ibfk_2` (`assigned_to`),
  CONSTRAINT `barangay_personnel_master_codes_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `barangay_personnel_master_codes_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `barangay_personnel_master_codes` */

/*Table structure for table `barangay_personnel_registrations` */

DROP TABLE IF EXISTS `barangay_personnel_registrations`;

CREATE TABLE `barangay_personnel_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) NOT NULL,
  `master_code` varchar(255) NOT NULL,
  `master_code_used` tinyint(1) DEFAULT 0,
  `master_code_used_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `admin_id` (`admin_id`),
  KEY `idx_master_code` (`master_code`),
  CONSTRAINT `barangay_personnel_registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `barangay_personnel_registrations_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `barangay_personnel_registrations` */

insert  into `barangay_personnel_registrations`(`id`,`user_id`,`admin_id`,`master_code`,`master_code_used`,`master_code_used_at`,`is_active`,`created_at`,`updated_at`) values 
(1,1007,1001,'5981',0,NULL,1,'2026-01-03 17:32:35','2026-01-03 17:32:35'),
(2,1008,1001,'4939',1,'2026-01-06 18:37:39',1,'2026-01-03 17:51:52','2026-01-06 18:37:39'),
(3,1009,1001,'6152',1,'2026-01-07 15:24:17',1,'2026-01-07 15:23:41','2026-01-07 15:24:17'),
(4,1010,1001,'5556',1,'2026-01-08 20:31:46',1,'2026-01-08 20:31:07','2026-01-08 20:31:46'),
(5,1011,1001,'6762',1,'2026-01-08 20:45:25',1,'2026-01-08 20:44:19','2026-01-08 20:45:25'),
(6,1012,1001,'4151',1,'2026-01-09 20:11:04',1,'2026-01-09 20:10:34','2026-01-09 20:11:04'),
(7,1013,1001,'2660',1,'2026-01-09 20:44:55',1,'2026-01-09 20:44:21','2026-01-09 20:44:55'),
(8,1016,1001,'0380',1,'2026-01-14 13:16:08',1,'2026-01-14 07:47:51','2026-01-14 13:16:08'),
(9,1019,1001,'5705',1,'2026-01-14 15:16:08',1,'2026-01-14 15:15:20','2026-01-14 15:16:08'),
(10,1020,1001,'8675',1,'2026-01-17 22:55:24',1,'2026-01-17 22:55:06','2026-01-17 22:55:24');

/*Table structure for table `barangay_positions` */

DROP TABLE IF EXISTS `barangay_positions`;

CREATE TABLE `barangay_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `position_name` varchar(50) NOT NULL,
  `role_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `barangay_positions_ibfk_1` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `barangay_positions` */

insert  into `barangay_positions`(`id`,`position_name`,`role_id`,`description`,`permissions`,`created_at`) values 
(1,'Barangay Administrator',1,'System administrator','all','2025-12-07 08:00:00'),
(2,'Barangay Captain',2,'Head of the barangay','manage_reports,view_reports,manage_users,manage_announcements,manage_personnel','2025-12-07 15:14:50'),
(3,'Barangay Secretary',3,'Barangay records keeper','manage_reports,view_reports,manage_announcements','2025-12-07 15:14:50'),
(4,'Barangay Tanod',5,'Barangay security and peacekeeper','view_reports,update_reports_status','2025-12-07 15:14:50'),
(5,'Lupon Member',4,'Member of the Lupong Tagapamayapa','view_reports,manage_reports','2025-12-07 15:14:50'),
(6,'SK Chairman',3,'Sangguniang Kabataan Chairman',NULL,'2025-12-07 15:14:50'),
(7,'Treasurer',3,'Barangay Treasurer',NULL,'2025-12-07 15:14:50');

/*Table structure for table `captain_hearings` */

DROP TABLE IF EXISTS `captain_hearings`;

CREATE TABLE `captain_hearings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `hearing_date` date NOT NULL,
  `hearing_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `participants` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `scheduled_by` int(11) NOT NULL,
  `status` enum('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled',
  `reminders_sent` tinyint(1) DEFAULT 0,
  `last_reminder_sent` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `scheduled_by` (`scheduled_by`),
  KEY `idx_captain_hearings_date` (`hearing_date`,`status`),
  CONSTRAINT `captain_hearings_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `captain_hearings_ibfk_2` FOREIGN KEY (`scheduled_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `captain_hearings` */

/*Table structure for table `case_approvals` */

DROP TABLE IF EXISTS `case_approvals`;

CREATE TABLE `case_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `approved_by` int(11) NOT NULL,
  `resolution_type` enum('mediated_settlement','arbitration_award','dismissed','referred_out','other') NOT NULL,
  `resolution_notes` text DEFAULT NULL,
  `digital_signature` text DEFAULT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_case_approvals_report` (`report_id`,`approved_at`),
  CONSTRAINT `case_approvals_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_approvals_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `case_approvals` */

/*Table structure for table `classification_logs` */

DROP TABLE IF EXISTS `classification_logs`;

CREATE TABLE `classification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `original_classification` varchar(50) DEFAULT NULL,
  `new_classification` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `classification_logs_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`),
  CONSTRAINT `classification_logs_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `classification_logs` */

/*Table structure for table `data_transfer_logs` */

DROP TABLE IF EXISTS `data_transfer_logs`;

CREATE TABLE `data_transfer_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_name` varchar(100) DEFAULT NULL,
  `operation` varchar(50) DEFAULT NULL,
  `status` enum('success','failed') DEFAULT NULL,
  `records_count` int(11) DEFAULT 0,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `data_transfer_logs` */

/*Table structure for table `evidence_handovers` */

DROP TABLE IF EXISTS `evidence_handovers`;

CREATE TABLE `evidence_handovers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanod_id` int(11) NOT NULL,
  `item_description` text NOT NULL,
  `item_type` varchar(100) DEFAULT NULL,
  `handover_to` int(11) NOT NULL,
  `handover_date` datetime DEFAULT NULL,
  `recipient_acknowledged` tinyint(1) DEFAULT 0,
  `chain_of_custody` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tanod_id` (`tanod_id`),
  KEY `handover_to` (`handover_to`),
  CONSTRAINT `evidence_handovers_ibfk_1` FOREIGN KEY (`tanod_id`) REFERENCES `users` (`id`),
  CONSTRAINT `evidence_handovers_ibfk_2` FOREIGN KEY (`handover_to`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `evidence_handovers` */

/*Table structure for table `file_encryption_logs` */

DROP TABLE IF EXISTS `file_encryption_logs`;

CREATE TABLE `file_encryption_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `encryption_key` varchar(255) NOT NULL,
  `encrypted_path` varchar(255) NOT NULL,
  `decryption_count` int(11) DEFAULT 0,
  `last_decrypted` timestamp NULL DEFAULT NULL,
  `last_decrypted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `last_decrypted_by` (`last_decrypted_by`),
  KEY `idx_report` (`report_id`),
  KEY `idx_key` (`encryption_key`),
  KEY `idx_file_encryption_report` (`report_id`,`encryption_key`),
  CONSTRAINT `file_encryption_logs_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `file_encryption_logs_ibfk_2` FOREIGN KEY (`last_decrypted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `file_encryption_logs` */

/*Table structure for table `login_history` */

DROP TABLE IF EXISTS `login_history`;

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `login_history` */

/*Table structure for table `lost_found_items` */

DROP TABLE IF EXISTS `lost_found_items`;

CREATE TABLE `lost_found_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `item_type` enum('lost','found') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `barangay` varchar(100) NOT NULL,
  `status` enum('active','claimed','resolved') DEFAULT 'active',
  `image_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `lost_found_items_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `lost_found_items` */

/*Table structure for table `mediation_logs` */

DROP TABLE IF EXISTS `mediation_logs`;

CREATE TABLE `mediation_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `lupon_id` int(11) NOT NULL,
  `mediation_date` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled',
  `outcome` varchar(100) DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `idx_lupon` (`lupon_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`mediation_date`),
  CONSTRAINT `mediation_logs_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mediation_logs_ibfk_2` FOREIGN KEY (`lupon_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `mediation_logs` */

/*Table structure for table `messages` */

DROP TABLE IF EXISTS `messages`;

CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `messages` */

/*Table structure for table `notifications` */

DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','danger','success','classification') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  KEY `idx_notifications_created` (`created_at`),
  KEY `idx_notifications_related` (`related_type`,`related_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `notifications` */

/*Table structure for table `password_resets` */

DROP TABLE IF EXISTS `password_resets`;

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `password_resets` */

/*Table structure for table `patrol_routes` */

DROP TABLE IF EXISTS `patrol_routes`;

CREATE TABLE `patrol_routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `route_name` varchar(100) NOT NULL,
  `zone_assigned` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `waypoints` text DEFAULT NULL,
  `estimated_time` decimal(5,2) DEFAULT NULL,
  `priority_level` enum('low','medium','high') DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active_route` (`is_active`,`priority_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `patrol_routes` */

/*Table structure for table `report_attachments` */

DROP TABLE IF EXISTS `report_attachments`;

CREATE TABLE `report_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  CONSTRAINT `report_attachments_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `report_attachments` */

/*Table structure for table `report_status_history` */

DROP TABLE IF EXISTS `report_status_history`;

CREATE TABLE `report_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `status` enum('pending','assigned','investigating','resolved','referred','closed') NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `officer_notes` text DEFAULT NULL,
  `next_action` varchar(255) DEFAULT NULL,
  `estimated_completion` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_report_history_report_status` (`report_id`,`status`,`created_at`),
  CONSTRAINT `report_status_history_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_status_history_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `report_status_history` */

insert  into `report_status_history`(`id`,`report_id`,`status`,`updated_by`,`notes`,`created_at`,`officer_notes`,`next_action`,`estimated_completion`) values 
(1,10001,'pending',NULL,'Report submitted by citizen','2026-01-04 16:19:59',NULL,NULL,NULL),
(2,10002,'pending',NULL,'Report submitted by citizen','2026-01-04 16:30:05',NULL,NULL,NULL),
(4,10004,'pending',NULL,'Report submitted by citizen','2026-01-04 17:02:23',NULL,NULL,NULL),
(5,10005,'pending',NULL,'Report submitted by citizen','2026-01-05 16:00:28',NULL,NULL,NULL),
(6,10006,'pending',NULL,'Report submitted by citizen','2026-01-05 18:57:08',NULL,NULL,NULL),
(7,10007,'pending',NULL,'Report submitted by citizen','2026-01-06 09:35:32',NULL,NULL,NULL),
(8,10008,'pending',NULL,'Report submitted by citizen','2026-01-06 09:38:02',NULL,NULL,NULL),
(9,10009,'pending',NULL,'Report submitted by citizen','2026-01-06 20:18:30',NULL,NULL,NULL),
(10,10010,'pending',NULL,'Report submitted by citizen','2026-01-07 12:44:56',NULL,NULL,NULL),
(11,10011,'pending',NULL,'Report submitted by citizen','2026-01-12 12:05:47',NULL,NULL,NULL),
(12,10012,'pending',NULL,'Report submitted by citizen','2026-01-13 19:36:32',NULL,NULL,NULL),
(13,10013,'pending',NULL,'Report submitted by citizen','2026-01-15 20:37:53',NULL,NULL,NULL),
(14,10014,'pending',NULL,'Report submitted by citizen','2026-01-17 22:43:00',NULL,NULL,NULL),
(15,10015,'pending',NULL,'Report submitted by citizen','2026-01-24 05:14:22',NULL,NULL,NULL);

/*Table structure for table `report_timeline` */

DROP TABLE IF EXISTS `report_timeline`;

CREATE TABLE `report_timeline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `report_timeline_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_timeline_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `report_timeline` */

/*Table structure for table `report_types` */

DROP TABLE IF EXISTS `report_types`;

CREATE TABLE `report_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL,
  `category` enum('incident','complaint','blotter') NOT NULL,
  `description` text DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `jurisdiction` enum('barangay','police') NOT NULL,
  `severity_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_report_type` (`type_name`,`category`,`jurisdiction`),
  KEY `idx_category` (`category`),
  KEY `idx_jurisdiction` (`jurisdiction`),
  KEY `idx_severity` (`severity_level`)
) ENGINE=InnoDB AUTO_INCREMENT=179 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `report_types` */

insert  into `report_types`(`id`,`type_name`,`category`,`description`,`keywords`,`jurisdiction`,`severity_level`,`created_at`) values 
(1,'Public Disturbance / Gulo sa Publiko','incident','Noise complaints, public altercations','gulo,disturbance,noise,argument,alitan,away','barangay','low','2025-12-06 05:28:32'),
(2,'Property Damage / Pinsala sa Ari-arian','incident','Vandalism, property destruction','vandalism,property damage,pinsala,basag,basag na bintana','barangay','medium','2025-12-06 05:28:32'),
(3,'Illegal Gambling / Iligal na Sugal','incident','Illegal gambling activities','sugal,gambling,baraha,jueteng,card game','police','medium','2025-12-06 05:28:32'),
(4,'Drug-related Activity / Aktibidad na May Kinalaman sa Droga','incident','Suspected drug use or sale','drugs,shabu,marijuana,drug,stoned','police','high','2025-12-06 05:28:32'),
(5,'Traffic Accident / Aksidente sa Trapiko','incident','Vehicular accidents','accident,bangga,salpok,vehicle,collision','police','medium','2025-12-06 05:28:32'),
(6,'Fire Incident / Sunog','incident','Fire emergencies','fire,sunog,smoke,flames,emergency','police','high','2025-12-06 05:28:32'),
(7,'Theft / Pagnanakaw','incident','Robbery, burglary, theft','theft,nakaw,robbery,steal,magnanakaw','police','high','2025-12-06 05:28:32'),
(8,'Assault / Pag-assalto','incident','Physical assault, battery','assault,bugbog,attack,physical,force','police','high','2025-12-06 05:28:32'),
(9,'Missing Person / Nawawalang Tao','incident','Missing individuals','missing,nawawala,person,lost,tao','police','critical','2025-12-06 05:28:32'),
(10,'Domestic Violence / Karahasan sa Tahanan','incident','Violence within households','domestic,violence,karahasan,tahanan,family','police','high','2025-12-06 05:28:32'),
(11,'Noise Complaint / Reklamo sa Ingay','complaint','Excessive noise complaints',NULL,'barangay','low','2025-12-06 05:28:32'),
(12,'Sanitation Issue / Isyu sa Kalinisan','complaint','Garbage, unsanitary conditions',NULL,'barangay','low','2025-12-06 05:28:32'),
(13,'Animal Nuisance / Abala mula sa Hayop','complaint','Stray animals, animal disturbances',NULL,'barangay','low','2025-12-06 05:28:32'),
(14,'Boundary Dispute / Hidwaan sa Hangganan','complaint','Property boundary conflicts',NULL,'barangay','medium','2025-12-06 05:28:32'),
(15,'Neighbor Dispute / Alitan ng Kapitbahay','complaint','Disputes between neighbors',NULL,'barangay','medium','2025-12-06 05:28:32'),
(16,'Illegal Construction / Iligal na Konstruksyon','complaint','Unauthorized construction',NULL,'barangay','medium','2025-12-06 05:28:32'),
(17,'Business Violation / Paglabag sa Negosyo','complaint','Unlicensed business operations',NULL,'barangay','medium','2025-12-06 05:28:32'),
(18,'Water Issue / Problema sa Tubig','complaint','Water supply problems',NULL,'barangay','low','2025-12-06 05:28:32'),
(19,'Electricity Issue / Problema sa Kuryente','complaint','Electrical supply problems',NULL,'barangay','low','2025-12-06 05:28:32'),
(20,'Road Issue / Problema sa Kalsada','complaint','Road damage, obstructions',NULL,'barangay','low','2025-12-06 05:28:32'),
(21,'Verbal Altercation / Alitan sa Salita','blotter','Arguments without physical contact',NULL,'barangay','low','2025-12-06 05:28:32'),
(22,'Physical Altercation / Alitan na Pisikal','blotter','Physical fights',NULL,'barangay','medium','2025-12-06 05:28:32'),
(23,'Harassment / Pangha-harass','blotter','Harassment cases',NULL,'barangay','medium','2025-12-06 05:28:32'),
(24,'Trespassing / Pagpasok nang Walang Pahintulot','blotter','Unauthorized entry',NULL,'barangay','medium','2025-12-06 05:28:32'),
(25,'Debt-related Issue / Isyu tungkol sa Utang','blotter','Disputes over debts',NULL,'barangay','low','2025-12-06 05:28:32'),
(26,'Child-related Issue / Isyu tungkol sa Bata','blotter','Issues involving minors',NULL,'barangay','high','2025-12-06 05:28:32'),
(27,'Elderly Abuse / Pang-aabuso sa Matanda','blotter','Abuse of elderly persons',NULL,'police','high','2025-12-06 05:28:32'),
(28,'Slander / Paninirang Puri','blotter','Defamation, libel, slander',NULL,'barangay','low','2025-12-06 05:28:32'),
(29,'Breach of Contract / Paglabag sa Kontrata','blotter','Contract violations',NULL,'barangay','medium','2025-12-06 05:28:32'),
(30,'Property Dispute / Hidwaan sa Ari-arian','blotter','Disputes over property',NULL,'barangay','medium','2025-12-06 05:28:32'),
(31,'Theft/Robbery','incident','Stolen property, robbery, burglary','theft,robbery,burglary,nakaw,magnanakaw','police','high','2025-12-08 15:52:07'),
(32,'Assault','incident','Physical attack, battery, fight','assault,attack,physical,force,bugbog','police','critical','2025-12-08 15:52:07'),
(33,'Drug-related Activity','incident','Illegal drug use, possession, or distribution','drug,shabu,marijuana,illegal,substance','police','critical','2025-12-08 15:52:07'),
(34,'Traffic Accident','incident','Vehicle collision, road accident','accident,vehicle,crash,bangga,trapiko','police','medium','2025-12-08 15:52:07'),
(35,'Missing Person','incident','Missing individual, especially minors','missing,person,nawawala,lost,tao','police','high','2025-12-08 15:52:07'),
(36,'Fire Incident','incident','Fire outbreak, arson','fire,sunog,emergency,flames,smoke','police','critical','2025-12-08 15:52:07'),
(37,'Public Disturbance','incident','Disturbance of public peace',NULL,'barangay','medium','2025-12-08 15:52:07'),
(38,'Noise Complaint','complaint','Excessive noise from neighbors or establishments',NULL,'barangay','low','2025-12-08 15:52:07'),
(39,'Garbage/Sanitation','complaint','Improper waste disposal, sanitation issues',NULL,'barangay','medium','2025-12-08 15:52:07'),
(40,'Animal Nuisance','complaint','Stray animals, animal-related issues',NULL,'barangay','low','2025-12-08 15:52:07'),
(41,'Illegal Construction','complaint','Unauthorized construction work',NULL,'barangay','medium','2025-12-08 15:52:07'),
(42,'Business Violation','complaint','Unauthorized business operations',NULL,'barangay','medium','2025-12-08 15:52:07'),
(43,'Water/Electricity Issue','complaint','Utility-related complaints',NULL,'barangay','medium','2025-12-08 15:52:07'),
(44,'Neighbor Dispute','blotter','Arguments or conflicts between neighbors',NULL,'barangay','low','2025-12-08 15:52:07'),
(45,'Family Conflict','blotter','Family disputes requiring mediation',NULL,'barangay','medium','2025-12-08 15:52:07'),
(46,'Property Boundary','blotter','Land or property boundary disputes',NULL,'barangay','medium','2025-12-08 15:52:07'),
(47,'Debt/Financial Dispute','blotter','Money-related conflicts',NULL,'barangay','medium','2025-12-08 15:52:07'),
(48,'Harassment','blotter','Verbal or psychological harassment',NULL,'barangay','high','2025-12-08 15:52:07'),
(49,'Documentation Request','blotter','Request for barangay clearance or certification',NULL,'barangay','low','2025-12-08 15:52:07'),
(50,'Theft/Pickpocketing','incident','Stolen property, pickpocketing','nanakawan,nadukutan,wallet stolen,cellphone stolen,pitaka,pickpocket','police','medium','2025-12-28 09:50:01'),
(51,'Robbery/Hold-up','incident','Armed robbery, hold-up','holdap,armed robbery,binunot ng baril,kinuha pera,gunpoint','police','high','2025-12-28 09:50:01'),
(52,'Burglary/Akyat-bahay','incident','Break-in, home burglary','akwat-bahay,pumasok sa bahay,break in,forced entry,ninakawan habang wala','police','high','2025-12-28 09:50:01'),
(53,'Carnapping/Vehicle Theft','incident','Stolen vehicles','carnapping,vehicle theft,ninakaw ang kotse,ninakaw ang motor,stolen car','police','high','2025-12-28 09:50:01'),
(54,'Snatching/Dukot','incident','Snatching of items','snatched,dinukot,hinablot,bigla kinain,cellphone snatched','police','medium','2025-12-28 09:50:01'),
(55,'Arson/Malicious Burning','incident','Intentional fire setting','arson,sinadyang sunog,intentional fire,nagpaputok,malicious burning','police','critical','2025-12-28 09:50:01'),
(56,'Homicide/Murder','incident','Killing incidents','pinatay,patay,murder,homicide,namatay,killed,dead body','police','critical','2025-12-28 09:50:01'),
(57,'Sexual Assault/Rape','incident','Sexual assault cases','ginahasa,rape,sexual assault,panggagahasa,rape victim','police','critical','2025-12-28 09:50:01'),
(58,'Sexual Harassment','incident','Unwanted sexual advances','hinipuan,catcalling,bastos,manyak,sexual harassment','police','high','2025-12-28 09:50:01'),
(59,'Abduction/Kidnapping','incident','Kidnapping cases','dinukot,kidnapping,ransom,hostage,abduction','police','critical','2025-12-28 09:50:01'),
(60,'Stalking','incident','Stalking behavior','stalking,sinusundan,stalker,sumusunod,pinagmamasdan','police','medium','2025-12-28 09:50:01'),
(61,'Found Person/Amnesia','incident','Found disoriented person','natagpuang lito,found wandering,amnesia,hindi maalala,disoriented','barangay','medium','2025-12-28 09:50:01'),
(62,'Hit and Run','incident','Fleeing after accident','hit and run,tinakbuhan,tumakbo pagkatama,fled scene','police','high','2025-12-28 09:50:01'),
(63,'Workplace/Industrial Accident','incident','Work-related accidents','naaksidente sa trabaho,construction accident,nakuryente,nahulog sa building','police','high','2025-12-28 09:50:01'),
(64,'Gas Leak/Chemical Spill','incident','Chemical hazards','gas leak,chemical spill,amoy gas,tumatagas na kemikal,LPG leak','police','high','2025-12-28 09:50:01'),
(65,'Electric Shock/Power Hazard','incident','Electrical hazards','nakuryente,exposed wires,kuryente sa poste,electrical hazard','police','high','2025-12-28 09:50:01'),
(66,'Suicide Attempt','incident','Suicidal behavior','magpapakamatay,suicide attempt,jumping,overdose,self-harm','police','critical','2025-12-28 09:50:01'),
(67,'Mental Health Crisis','incident','Mental health emergencies','nagwawala,mental health crisis,violent mentally ill,naghahamon ng away','barangay','medium','2025-12-28 09:50:01'),
(68,'Infectious Disease Outbreak','incident','Disease outbreaks','maraming may lagnat,food poisoning,dengue cluster,outbreak','barangay','high','2025-12-28 09:50:01'),
(69,'Public Disturbance (Violent)','incident','Violent public disturbances','riot,gulo sa fiesta,mass fight,rumble,gang war','police','high','2025-12-28 09:50:01'),
(70,'Drug Lab/Manufacturing','incident','Drug manufacturing','shabu lab,drug manufacturing,chemical smells,suspicious lab','police','critical','2025-12-28 09:50:01'),
(71,'Illegal Discharge of Firearm','incident','Gunfire incidents','bumaril,gunshots,nagpapaputok ng baril,putok ng baril','police','high','2025-12-28 09:50:01'),
(72,'Stabbing/Cutting Incident','incident','Knife-related attacks','sinaksak,knife attack,stab wound,saksak,patrolya','police','high','2025-12-28 09:50:01'),
(73,'Online Scam','incident','Internet scams','online scam,na-scam,phishing,fake seller,bogus buyer','police','medium','2025-12-28 09:50:01'),
(74,'Identity Theft','incident','Identity fraud','identity theft,ginamit ang pangalan,fake accounts,stolen identity','police','high','2025-12-28 09:50:01'),
(75,'Cyberbullying/Online Threats','incident','Online harassment','cyberbullying,online threats,malicious posts,online blackmail','police','medium','2025-12-28 09:50:01'),
(76,'Construction Noise','complaint','Noise from construction','construction noise,nagkakanyon,drilling,hammering,construction maingay','barangay','low','2025-12-28 09:50:01'),
(77,'Business/Commercial Noise','complaint','Noise from businesses','factory noise,loud speakers,store noise,videoke bar,commercial noise','barangay','low','2025-12-28 09:50:01'),
(78,'Vehicle Noise','complaint','Noisy vehicles','modified muffler,nagre-revving,truck noise,motorcycle noise,maingay na sasakyan','barangay','low','2025-12-28 09:50:01'),
(79,'Nuisance Neighbors','complaint','Disruptive neighbors','nuisance neighbor,disruptive,abala,nag-aaway,laging may gulo','barangay','low','2025-12-28 09:50:01'),
(80,'Trespassing (Complaint)','complaint','Unauthorized entry complaints','trespassing,pumapasok sa bakuran,unauthorized entry,sumusunod sa lote','barangay','medium','2025-12-28 09:50:01'),
(81,'Animal Cruelty','complaint','Animal abuse cases','animal cruelty,pinapalo ang aso,starving animals,neglected pets,abused animals','barangay','medium','2025-12-28 09:50:01'),
(82,'Obstruction','complaint','Road obstructions','obstruction,nakaharang,blocking road,vendor blocking,construction materials sa daan','barangay','low','2025-12-28 09:50:01'),
(83,'Traffic Violations','complaint','Traffic rule violations','traffic violation,beating red light,no helmet,reckless driving,walang lisensya','barangay','low','2025-12-28 09:50:01'),
(84,'Unregistered Vehicles','complaint','Unregistered vehicles','colorum,no plate number,illegal PUV,unregistered vehicle,walang rehistro','barangay','low','2025-12-28 09:50:01'),
(85,'Illegal Vendor','complaint','Unauthorized vendors','illegal vendor,vendor sa bawal lugar,obstructing pedestrian,walang permit magtinda','barangay','low','2025-12-28 09:50:01'),
(86,'Overpricing/Shortchanging','complaint','Price manipulation','overpricing,shortchanging,sobrang mahal,nagshoshortchange,panloloko sa timbang','barangay','low','2025-12-28 09:50:01'),
(87,'Consumer Complaint','complaint','Product/service complaints','consumer complaint,fake products,defective item,no receipt,pangit na serbisyo','barangay','low','2025-12-28 09:50:01'),
(88,'Public Drinking','complaint','Public alcohol consumption','public drinking,nag-iinom sa kalsada,drunk disturbance,liquor ban violation','barangay','low','2025-12-28 09:50:01'),
(89,'Loitering/Tambay','complaint','Loitering issues','loitering,tambay,suspicious loitering,catcalling sa daan,naka-block sa daan','barangay','low','2025-12-28 09:50:01'),
(90,'Public Urination/Defecation','complaint','Public sanitation issues','public urination,dumudumi sa public,umihi sa pader,lack of public toilet','barangay','low','2025-12-28 09:50:01'),
(91,'Curfew Violation','complaint','Curfew breaches','curfew violation,minors out past curfew,children at night,curfew ordinance','barangay','low','2025-12-28 09:50:01'),
(92,'Pollution','complaint','Environmental pollution','pollution,air pollution,chemical dumping,usok maitim,contamination','barangay','medium','2025-12-28 09:50:01'),
(93,'Tree/Vegetation Hazard','complaint','Dangerous vegetation','dangerous tree,mababagsak na puno,overgrown plants,blocking branches','barangay','low','2025-12-28 09:50:01'),
(94,'Food Safety','complaint','Food handling issues','food safety,dirty food handling,unsanitary food stall,langaw sa pagkain','barangay','medium','2025-12-28 09:50:01'),
(95,'Smoking Violation','complaint','Smoking in prohibited areas','smoking violation,smoking in public,no smoking zone,secondhand smoke','barangay','low','2025-12-28 09:50:01'),
(96,'Abandoned Vehicle','complaint','Abandoned vehicles','abandoned vehicle,nakaparada ng matagal,junk vehicle,abandoned car','barangay','low','2025-12-28 09:50:01'),
(97,'Illegal Terminal','complaint','Unauthorized terminals','illegal terminal,colorum terminal,unauthorized PUV stop,illegal parking for hire','barangay','medium','2025-12-28 09:50:01'),
(98,'Domestic Dispute (Non-violent)','blotter','Family conflicts without violence','domestic dispute,away mag-asawa,marital problems,non-violent family issue','barangay','low','2025-12-28 09:50:01'),
(99,'Child/Parent Conflict','blotter','Parent-child disputes','child parent conflict,rebellious teenager,hindi sumusunod,generation gap','barangay','low','2025-12-28 09:50:01'),
(100,'Financial Family Dispute','blotter','Family money disputes','financial family dispute,away sa pera,inheritance problem,utang sa kapamilya','barangay','medium','2025-12-28 09:50:01'),
(101,'VAWC Cases (Documentation)','blotter','Violence against women documentation','VAWC,psychological violence,economic abuse,emotional abuse,violence against women','barangay','high','2025-12-28 09:50:01'),
(102,'Property Damage Dispute','blotter','Disputes over property damage','property damage dispute,nasira ang pader,ayaw magbayad,accidental damage','barangay','medium','2025-12-28 09:50:01'),
(103,'Shared Facility Dispute','blotter','Common area conflicts','shared facility dispute,away sa tubig,shared wall issue,common area use','barangay','low','2025-12-28 09:50:01'),
(104,'Business Disagreement','blotter','Business partnership disputes','business disagreement,hindi natupad ang kontrata,business partner dispute,payment disagreement','barangay','medium','2025-12-28 09:50:01'),
(105,'Landlord-Tenant Dispute','blotter','Rental conflicts','landlord tenant dispute,hindi nagbabayad ng rent,deposit hindi naibalik,eviction disagreement','barangay','medium','2025-12-28 09:50:01'),
(106,'Property Rental Issues','blotter','Rental property problems','property rental issues,sira ang hiniram,overstaying renter,rules violation','barangay','medium','2025-12-28 09:50:01'),
(107,'Workplace Harassment','blotter','Work harassment cases','workplace harassment,harassment sa trabaho,bullying sa office,discrimination','barangay','medium','2025-12-28 09:50:01'),
(108,'Incident Documentation','blotter','Official documentation of events','incident documentation,for insurance,record ng nangyari,proof for employer','barangay','low','2025-12-28 09:50:01'),
(109,'Lost Document Affidavit','blotter','Lost document reporting','lost document affidavit,nawala ang ID,lost important papers,affidavit of loss','barangay','low','2025-12-28 09:50:01'),
(110,'Verification/Certification','blotter','Document verification','verification certification,certify residency,verify incident,authentication','barangay','low','2025-12-28 09:50:01'),
(111,'Relationship Dispute','blotter','Romantic relationship conflicts','relationship dispute,away magsyota,love triangle,breakup problems','barangay','low','2025-12-28 09:50:01'),
(112,'Friendship Conflict','blotter','Friend disputes','friendship conflict,away magkaibigan,siraan sa barkada,group conflict','barangay','low','2025-12-28 09:50:01'),
(113,'Rumor/Chismis Issues','blotter','Rumor-related conflicts','rumor issues,paninira ng pangalan,false rumors,slander without evidence','barangay','low','2025-12-28 09:50:01'),
(114,'Purok/Sitio Issues','blotter','Community organization problems','purok issues,community disagreement,HOA problems,neighborhood association','barangay','low','2025-12-28 09:50:01'),
(115,'Public Facility Complaint','blotter','Public amenity issues','public facility complaint,sirang street light,damaged waiting shed,playground safety','barangay','low','2025-12-28 09:50:01'),
(116,'Water/Electricity Dispute','blotter','Utility billing disputes','utility dispute,away sa bayarin,shared meter problem,utility conflict','barangay','low','2025-12-28 09:50:01'),
(117,'Suspicious Activity','blotter','Suspicious behavior reporting','suspicious activity,kahina-hinalang tao,suspicious gatherings,possible illegal activity','barangay','medium','2025-12-28 09:50:01'),
(118,'Pre-emptive Complaint','blotter','Preventive reporting','pre-emptive complaint,baka mangyari ang gulo,preventing possible fight,early warning','barangay','low','2025-12-28 09:50:01'),
(119,'General Advice/Consultation','blotter','General inquiries','general advice,paano ang proseso,ask for advice,consultation only','barangay','low','2025-12-28 09:50:01'),
(120,'Bullying in School','blotter','School bullying cases','bullying in school,binubully ang anak,school harassment,cyberbullying minor','barangay','high','2025-12-28 09:50:01'),
(121,'Truancy/School Issues','blotter','School attendance problems','truancy,hindi pumapasok sa school,cutting classes,school problem','barangay','medium','2025-12-28 09:50:01'),
(122,'Youth Gang Concern','blotter','Youth gang issues','youth gang concern,gang sa barangay,teenagers causing trouble,fraternity recruitment','barangay','high','2025-12-28 09:50:01'),
(123,'PWD Accessibility Issues','blotter','Accessibility problems','PWD accessibility,walang ramp,no accessibility,discrimination PWD','barangay','medium','2025-12-28 09:50:01'),
(124,'Social Media Conflict','blotter','Online social conflicts','social media conflict,away sa Facebook,online argument,post causing trouble','barangay','low','2025-12-28 09:50:01'),
(125,'Online Transaction Problem','blotter','Online transaction issues','online transaction problem,e-commerce issue,digital payment problem,online purchase issue','barangay','low','2025-12-28 09:50:01'),
(126,'Government Employee Complaint','blotter','Government service complaints','government employee complaint,mabagal na serbisyo,corrupt employee,poor public service','barangay','medium','2025-12-28 09:50:01'),
(127,'Document Processing Issue','blotter','Document processing delays','document processing issue,tagal ng permit,delayed papers,bureaucracy problem','barangay','low','2025-12-28 09:50:01'),
(128,'Religious/Cultural Issues','blotter','Religious conflicts','religious disturbance,maingay na simbahan,religious discrimination,cultural practice conflict','barangay','medium','2025-12-28 09:50:01'),
(129,'Squatting/Illegal Settlers','blotter','Illegal settlement issues','squatting,illegal settlers,land invasion,squatting on property','barangay','high','2025-12-28 09:50:01'),
(130,'Informal Settler Issues','blotter','Informal settlement problems','informal settler issues,ISF community problems,relocation concerns,informal housing','barangay','medium','2025-12-28 09:50:01'),
(142,'Gender-based Incident','incident','Violence against women',NULL,'police','high','2026-01-03 15:40:01'),
(143,'Missing Pet / Nawawalang Alaga','incident','Lost pets or animals',NULL,'barangay','low','2026-01-03 15:40:01'),
(144,'Natural Disaster / Kalamidad','incident','Natural calamities',NULL,'barangay','high','2026-01-03 15:40:01'),
(145,'Rescue Operations / Pagsagip','incident','Rescue situations',NULL,'police','high','2026-01-03 15:40:01'),
(146,'Online Scam / Panloloko Online','incident','Internet scams',NULL,'police','medium','2026-01-03 15:40:01'),
(147,'Cyberbullying','incident','Online harassment',NULL,'police','medium','2026-01-03 15:40:01'),
(155,'Construction Noise / Ingay sa Konstruksyon','complaint','Noise from construction',NULL,'barangay','low','2026-01-03 15:40:01'),
(156,'Public Health Concern / Alalahanin sa Kalusugan','complaint','Health-related concerns',NULL,'barangay','medium','2026-01-03 15:40:01'),
(157,'Rabies Concern / Alalahanin sa Rabies','complaint','Rabies threats',NULL,'barangay','medium','2026-01-03 15:40:01'),
(158,'Public Nuisance / Istorbong Pampubliko','complaint','Public disturbances',NULL,'barangay','low','2026-01-03 15:40:01'),
(159,'Public Drinking / Pag-inom sa Pampubliko','complaint','Public alcohol consumption',NULL,'barangay','low','2026-01-03 15:40:01'),
(160,'Ordinance Violation / Paglabag sa Ordinansa','complaint','Violation of local ordinances',NULL,'barangay','low','2026-01-03 15:40:01'),
(161,'Smoking Violation / Paglabag sa Paninigarilyo','complaint','Smoking violations',NULL,'barangay','low','2026-01-03 15:40:01'),
(162,'Consumer Complaint / Reklamong Pangkonsyumer','complaint','Consumer-related issues',NULL,'barangay','low','2026-01-03 15:40:01'),
(163,'Illegal Vendor / Iligal na Tindera','complaint','Unauthorized vendors',NULL,'barangay','low','2026-01-03 15:40:01'),
(164,'Air Pollution / Polusyon sa Hangin','complaint','Air pollution issues',NULL,'barangay','medium','2026-01-03 15:40:01'),
(165,'Water Pollution / Polusyon sa Tubig','complaint','Water contamination',NULL,'barangay','medium','2026-01-03 15:40:01'),
(166,'Domestic Dispute / Alitan sa Tahanan','blotter','Family conflicts',NULL,'barangay','medium','2026-01-03 15:40:01'),
(167,'Neighbor Dispute / Alitan ng Kapitbahay','blotter','Neighbor conflicts',NULL,'barangay','low','2026-01-03 15:40:01'),
(168,'Family Conflict / Hidwaan sa Pamilya','blotter','Family disagreements',NULL,'barangay','medium','2026-01-03 15:40:01'),
(169,'Property Conflict / Hidwaan sa Ari-arian','blotter','Property disputes',NULL,'barangay','medium','2026-01-03 15:40:01'),
(170,'Land Dispute / Hidwaan sa Lupa','blotter','Land boundary issues',NULL,'barangay','medium','2026-01-03 15:40:01'),
(171,'Financial Dispute / Hidwaan sa Pera','blotter','Money-related disputes',NULL,'barangay','low','2026-01-03 15:40:01'),
(172,'Threats/Harassment / Pananakot','blotter','Threats and harassment',NULL,'barangay','medium','2026-01-03 15:40:01'),
(173,'Contract Dispute / Hidwaan sa Kontrata','blotter','Contract disagreements',NULL,'barangay','medium','2026-01-03 15:40:01'),
(174,'Record/Blotter Request / Kahilingan ng Blotter','blotter','Documentation requests',NULL,'barangay','low','2026-01-03 15:40:01'),
(175,'Barangay Clearance Request','blotter','Clearance requests',NULL,'barangay','low','2026-01-03 15:40:01'),
(176,'Certificate of Indigency','blotter','Indigency certification',NULL,'barangay','low','2026-01-03 15:40:01'),
(177,'Disability Concern / Alalahanin sa May Kapansanan','blotter','PWD-related issues',NULL,'barangay','medium','2026-01-03 15:40:01'),
(178,'Tenant-Landlord Dispute / Hidwaan ng Upahan','blotter','Rental conflicts',NULL,'barangay','medium','2026-01-03 15:40:01');

/*Table structure for table `report_vetting` */

DROP TABLE IF EXISTS `report_vetting`;

CREATE TABLE `report_vetting` (
  `vetting_id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `tanod_id` int(11) NOT NULL,
  `location_verified` enum('Yes','Partial','No') NOT NULL DEFAULT 'No',
  `facts_verified` enum('Confirmed','Partially Confirmed','Unconfirmed') NOT NULL DEFAULT 'Unconfirmed',
  `verification_notes` text NOT NULL,
  `recommendation` enum('Approved','Needs More Info','Rejected') NOT NULL DEFAULT 'Needs More Info',
  `status` enum('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  `verification_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`vetting_id`),
  UNIQUE KEY `updated_at` (`updated_at`),
  UNIQUE KEY `tanod_id` (`tanod_id`),
  UNIQUE KEY `report_id` (`report_id`),
  CONSTRAINT `report_vetting_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `report_vetting_ibfk_2` FOREIGN KEY (`tanod_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `report_vetting` */

/*Table structure for table `reports` */

DROP TABLE IF EXISTS `reports`;

CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `report_type_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` text NOT NULL,
  `incident_date` datetime NOT NULL,
  `report_date` datetime NOT NULL DEFAULT current_timestamp(),
  `involved_persons` text DEFAULT NULL,
  `witnesses` text DEFAULT NULL,
  `category` enum('incident','complaint','blotter') DEFAULT 'incident',
  `evidence_files` text DEFAULT NULL,
  `status` enum('pending','pending_field_verification','assigned','investigating','resolved','referred','closed') DEFAULT 'pending',
  `assigned_lupon` int(11) DEFAULT NULL,
  `assigned_lupon_chairman` int(11) DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `assigned_tanod` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `resolution_date` datetime DEFAULT NULL,
  `referred_to` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `submitted_by` int(11) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `allow_public` tinyint(1) DEFAULT 0,
  `escalation_level` int(11) DEFAULT 0,
  `evidence_path` varchar(255) DEFAULT NULL,
  `needs_verification` tinyint(1) DEFAULT 0,
  `last_status_change` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ai_classification` varchar(50) DEFAULT NULL,
  `ai_confidence` int(11) DEFAULT 0,
  `ai_keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ai_keywords`)),
  `classification_override` varchar(50) DEFAULT NULL,
  `override_notes` text DEFAULT NULL,
  `overridden_by` int(11) DEFAULT NULL,
  `overridden_at` timestamp NULL DEFAULT NULL,
  `blotter_number` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_number` (`report_number`),
  UNIQUE KEY `blotter_number` (`blotter_number`),
  KEY `user_id` (`user_id`),
  KEY `report_type_id` (`report_type_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  KEY `idx_reports_user_status` (`user_id`,`status`),
  KEY `idx_reports_created` (`created_at`),
  KEY `idx_reports_type` (`report_type_id`),
  KEY `submitted_by` (`submitted_by`),
  KEY `idx_reports_user_status_date` (`user_id`,`status`,`created_at`),
  KEY `fk_reports_verified_by` (`verified_by`),
  KEY `idx_reports_status` (`status`),
  KEY `idx_reports_needs_verification` (`needs_verification`),
  KEY `idx_reports_assigned_tanod` (`assigned_tanod`),
  KEY `idx_reports_verification_date` (`verification_date`),
  KEY `assigned_lupon` (`assigned_lupon`),
  KEY `fk_reports_assigned_lupon_chairman` (`assigned_lupon_chairman`),
  KEY `idx_reports_classification` (`ai_classification`,`classification_override`),
  KEY `idx_reports_status_date` (`status`,`created_at`),
  CONSTRAINT `fk_reports_assigned_lupon_chairman` FOREIGN KEY (`assigned_lupon_chairman`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_assigned_tanod` FOREIGN KEY (`assigned_tanod`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`report_type_id`) REFERENCES `report_types` (`id`),
  CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_ibfk_4` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_ibfk_5` FOREIGN KEY (`assigned_lupon`) REFERENCES `users` (`id`),
  CONSTRAINT `reports_ibfk_6` FOREIGN KEY (`assigned_lupon_chairman`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10016 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `reports` */

insert  into `reports`(`id`,`report_number`,`user_id`,`report_type_id`,`title`,`description`,`location`,`incident_date`,`report_date`,`involved_persons`,`witnesses`,`category`,`evidence_files`,`status`,`assigned_lupon`,`assigned_lupon_chairman`,`verification_notes`,`verification_date`,`verified_by`,`assigned_tanod`,`priority`,`assigned_to`,`resolution`,`resolution_date`,`referred_to`,`created_at`,`updated_at`,`submitted_by`,`barangay`,`latitude`,`longitude`,`is_anonymous`,`allow_public`,`escalation_level`,`evidence_path`,`needs_verification`,`last_status_change`,`ai_classification`,`ai_confidence`,`ai_keywords`,`classification_override`,`override_notes`,`overridden_by`,`overridden_at`,`blotter_number`) values 
(10001,'RPT-20260104-C43F37',1003,6,'fire wiwiwwiwi','may sunog na nangyari kaninang alas sais ng umaga.','dyan','2026-01-04 17:19:00','2026-01-05 00:19:59','','','incident',NULL,'pending_field_verification',NULL,NULL,NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'2026-01-04 16:19:59','2026-01-08 12:03:48',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,1,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10002,'RPT-20260104-50A765',1003,164,'ysa','mga basura nag kalat.','ghgc','2026-01-04 17:29:00','2026-01-05 00:30:05','','','complaint',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,NULL,'2026-01-04 16:30:05','2026-01-04 16:30:05',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,1,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10004,'RPT-20260104-4DC6B2',1003,164,'Basura','basura nag kakalat','ggd','2026-01-04 18:01:00','2026-01-05 01:02:23','','','complaint',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,NULL,'2026-01-04 17:02:23','2026-01-04 17:02:23',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,0,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10005,'RPT-20260105-9B0B25',1003,11,'Ingay','Itong kapit bahay namin na si Jeff Paray, 11:52 pm na nagkakantahan o videoke pa rin sila, napaka ingay. Sinaway na ni Hanah pero pinakyuhan lang siya ni Jeff.','Silang','2026-01-05 23:59:00','2026-01-06 00:00:28','','','complaint',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,'low',NULL,NULL,NULL,NULL,'2026-01-05 16:00:28','2026-01-05 16:00:28',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,0,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10006,'RPT-20260105-A63CBA',1003,72,'yay','may saksakan na nangyari','jjkjhk','2026-01-05 19:56:00','2026-01-06 02:57:08','','','incident','[{\"original_name\":\"Blue and White Simple Corporate Letterhead.png\",\"encrypted_name\":\"encrypted_1767639428_695c098414075_Blue and White Simple Corporate Letterhead.png\",\"file_type\":\"png\",\"file_size\":102358,\"encryption_key_hash\":\"dd5aaf0ba30faee45db2b88214916c4e5b5717d5c5872e6df695c63fffa7ca16\",\"original_hash\":\"e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\",\"iv\":\"f23c1baeacf99860d3d27a176ccb9acc\"}]','pending_field_verification',NULL,NULL,NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'2026-01-05 18:57:08','2026-01-08 12:03:48',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,0,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10007,'RPT-20260106-F95802',1003,7,'Theft Report','may nag nakaw ng mga alahas','aS','2026-01-06 10:35:00','2026-01-06 17:35:32','','','incident',NULL,'pending_field_verification',NULL,NULL,NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'2026-01-06 09:35:32','2026-01-08 12:03:48',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,1,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10008,'RPT-20260106-21CF9A',1003,72,'Stabbing Report','may nag saksakan','aa','2026-01-06 10:37:00','2026-01-06 17:38:02','','','incident',NULL,'pending_field_verification',NULL,NULL,NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'2026-01-06 09:38:02','2026-01-08 12:03:48',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,1,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10009,'RPT-20260106-BF3CEA',1003,50,'Theft Report','nadukutan ng wallet at cellphone','asdads','2026-01-06 21:17:00','2026-01-07 04:18:30','','','incident','[{\"original_name\":\"im.jpeg\",\"encrypted_name\":\"1767730710_695d6e1685d57_im.jpeg\",\"file_type\":\"jpeg\",\"file_size\":254002,\"encryption_key_hash\":null,\"original_hash\":\"e600dcfcb3ea9fd5300121b822e895710472ca2ec278178ab82ca0b5ad6f8055\",\"iv\":null}]','pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,NULL,'2026-01-06 20:18:30','2026-01-06 20:18:30',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,0,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10010,'RPT-20260107-2F9535',1003,170,'Land Dispute Report','agawan sa lupa','bahaya','2026-01-07 13:44:00','2026-01-07 20:44:56','','','blotter','[{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1767789896_695e5548178bf_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1767789896_695e5548178bf_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false}]','pending',NULL,NULL,NULL,NULL,NULL,NULL,'medium',NULL,NULL,NULL,NULL,'2026-01-07 12:44:56','2026-01-07 12:44:56',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,0,0,0,NULL,0,'2026-01-09 20:18:34',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10011,'RPT-20260112-91868A',1003,7,'Theft Report','May nag nakaw ng bag.','Bahay','2026-01-12 12:04:00','2026-01-12 12:05:47','','','incident','[{\"original_name\":\"IMG_20260112_180442_321.jpg\",\"stored_name\":\"1768219547_6964e39b194e9_IMG_20260112_180442_321.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768219547_6964e39b194e9_IMG_20260112_180442_321.jpg\",\"file_type\":\"jpg\",\"file_size\":3035763,\"encrypted\":false}]','pending',NULL,NULL,NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'2026-01-12 12:05:47','2026-01-12 12:05:47',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,0,0,0,NULL,0,'2026-01-12 12:05:47',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10012,'RPT-20260113-D280AE',1015,167,'Neighbor Dispute Report','Yung kapitbahay ko nang bato ng bote sa tapat ng bahay ko','Dito lang','2026-01-13 19:34:00','2026-01-13 19:36:32','','','blotter','[{\"original_name\":\"IMG_8367.jpeg\",\"stored_name\":\"1768332992_69669ec0117ec_IMG_8367.jpeg\",\"path\":\"uploads\\/reports\\/user_1015\\/1768332992_69669ec0117ec_IMG_8367.jpeg\",\"file_type\":\"jpeg\",\"file_size\":1551997,\"encrypted\":false}]','pending',NULL,NULL,NULL,NULL,NULL,NULL,'low',NULL,NULL,NULL,NULL,'2026-01-13 19:36:32','2026-01-13 19:36:32',NULL,'Dito sa silang',NULL,NULL,1,0,0,NULL,0,'2026-01-13 19:36:32',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10013,'RPT-20260115-4BF940',1003,57,'Sexual Assault Report','may na rape dito sa amin','silang','2026-01-15 20:35:00','2026-01-15 20:37:53','','','incident','[{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1768509473_696950212267c_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768509473_696950212267c_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1768509473_6969502122a5c_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768509473_6969502122a5c_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1768509473_6969502122d76_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768509473_6969502122d76_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1768509473_6969502123089_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768509473_6969502123089_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1768509473_6969502123398_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768509473_6969502123398_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1768509473_69695021236af_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768509473_69695021236af_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1768509473_69695021239c1_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768509473_69695021239c1_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1768509473_6969502123d5a_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768509473_6969502123d5a_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false}]','pending',NULL,NULL,NULL,NULL,NULL,NULL,'critical',NULL,NULL,NULL,NULL,'2026-01-15 20:37:53','2026-01-15 20:37:53',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,1,0,0,NULL,0,'2026-01-15 20:37:53',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10014,'RPT-20260117-19E56A',1003,6,'Fire Incident Report','May sunog Dito SA block 8 lot 2','Block 8','2026-01-17 22:40:00','2026-01-17 22:43:00','','','incident','[{\"original_name\":\"IMG_20251029_090851_629.jpg\",\"stored_name\":\"1768689780_696c10749c614_IMG_20251029_090851_629.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768689780_696c10749c614_IMG_20251029_090851_629.jpg\",\"file_type\":\"jpg\",\"file_size\":3127586,\"encrypted\":false},{\"original_name\":\"IMG_20251029_090851_629.jpg\",\"stored_name\":\"1768689780_696c10749d514_IMG_20251029_090851_629.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768689780_696c10749d514_IMG_20251029_090851_629.jpg\",\"file_type\":\"jpg\",\"file_size\":3127586,\"encrypted\":false},{\"original_name\":\"IMG_20251029_090851_629.jpg\",\"stored_name\":\"1768689780_696c10749e277_IMG_20251029_090851_629.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768689780_696c10749e277_IMG_20251029_090851_629.jpg\",\"file_type\":\"jpg\",\"file_size\":3127586,\"encrypted\":false},{\"original_name\":\"IMG_20251029_090851_629.jpg\",\"stored_name\":\"1768689780_696c10749efcd_IMG_20251029_090851_629.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768689780_696c10749efcd_IMG_20251029_090851_629.jpg\",\"file_type\":\"jpg\",\"file_size\":3127586,\"encrypted\":false},{\"original_name\":\"IMG_20251029_090851_629.jpg\",\"stored_name\":\"1768689780_696c10749fd4e_IMG_20251029_090851_629.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768689780_696c10749fd4e_IMG_20251029_090851_629.jpg\",\"file_type\":\"jpg\",\"file_size\":3127586,\"encrypted\":false},{\"original_name\":\"IMG_20251029_090851_629.jpg\",\"stored_name\":\"1768689780_696c1074a0a5d_IMG_20251029_090851_629.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768689780_696c1074a0a5d_IMG_20251029_090851_629.jpg\",\"file_type\":\"jpg\",\"file_size\":3127586,\"encrypted\":false},{\"original_name\":\"IMG_20251029_090851_629.jpg\",\"stored_name\":\"1768689780_696c1074a191b_IMG_20251029_090851_629.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768689780_696c1074a191b_IMG_20251029_090851_629.jpg\",\"file_type\":\"jpg\",\"file_size\":3127586,\"encrypted\":false},{\"original_name\":\"IMG_20251029_090851_629.jpg\",\"stored_name\":\"1768689780_696c1074a2ade_IMG_20251029_090851_629.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1768689780_696c1074a2ade_IMG_20251029_090851_629.jpg\",\"file_type\":\"jpg\",\"file_size\":3127586,\"encrypted\":false}]','pending',NULL,NULL,NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'2026-01-17 22:43:00','2026-01-17 22:43:00',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,0,0,0,NULL,0,'2026-01-17 22:43:00',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL),
(10015,'RPT-20260124-0B4778',1003,7,'Fire Incident Report','may pag nanakaw na nangyari dito sa bahay namin, nawala yung wallet','bahay lang','2026-01-24 05:09:00','2026-01-24 05:14:22','','','incident','[{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1769231662_6974552e0b3c2_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1769231662_6974552e0b3c2_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1769231662_6974552e0b891_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1769231662_6974552e0b891_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1769231662_6974552e0bcba_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1769231662_6974552e0bcba_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1769231662_6974552e0c0d9_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1769231662_6974552e0c0d9_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1769231662_6974552e0c4f9_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1769231662_6974552e0c4f9_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1769231662_6974552e0c90f_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1769231662_6974552e0c90f_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1769231662_6974552e0cd26_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1769231662_6974552e0cd26_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false},{\"original_name\":\"504896849_9887062978015746_9181476553568709558_n.jpg\",\"stored_name\":\"1769231662_6974552e0d137_504896849_9887062978015746_9181476553568709558_n.jpg\",\"path\":\"uploads\\/reports\\/user_1003\\/1769231662_6974552e0d137_504896849_9887062978015746_9181476553568709558_n.jpg\",\"file_type\":\"jpg\",\"file_size\":626135,\"encrypted\":false}]','pending',NULL,NULL,NULL,NULL,NULL,NULL,'high',NULL,NULL,NULL,NULL,'2026-01-24 05:14:22','2026-01-24 05:14:22',NULL,'Block 8 lot 2 6D Towerville',NULL,NULL,0,0,0,NULL,0,'2026-01-24 05:14:22',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL);

/*Table structure for table `roles` */

DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `roles` */

insert  into `roles`(`id`,`role_name`,`description`,`permissions`,`created_at`) values 
(1,'admin','System Administrator','all','2025-12-06 05:28:32'),
(2,'captain','Barangay Captain','manage_reports,view_reports,manage_users,manage_announcements','2025-12-06 05:28:32'),
(3,'secretary','Barangay Secretary','manage_reports,view_reports,manage_announcements','2025-12-06 05:28:32'),
(4,'lupon','Lupon Member','view_reports,manage_reports','2025-12-06 05:28:32'),
(5,'tanod','Barangay Tanod','view_reports,update_reports_status','2025-12-06 05:28:32'),
(7,'super_admin','System Super Administrator with unrestricted access to all modules and system-wide oversight','all','2026-01-14 13:31:54');

/*Table structure for table `settlement_documents` */

DROP TABLE IF EXISTS `settlement_documents`;

CREATE TABLE `settlement_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `document_type` enum('amicable_settlement','mediation_report','closure_certificate','other') NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `generated_at` timestamp NULL DEFAULT current_timestamp(),
  `signature_status` enum('pending','signed') DEFAULT 'pending',
  `signed_by` int(11) DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `generated_by` (`generated_by`),
  KEY `signed_by` (`signed_by`),
  CONSTRAINT `settlement_documents_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `settlement_documents_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `settlement_documents_ibfk_3` FOREIGN KEY (`signed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `settlement_documents` */

/*Table structure for table `system_config` */

DROP TABLE IF EXISTS `system_config`;

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `system_config` */

insert  into `system_config`(`id`,`config_key`,`config_value`,`config_type`,`description`,`updated_by`,`updated_at`,`created_at`) values 
(1,'classification_threshold','0.7','string','AI model confidence threshold for police classification',NULL,'2026-01-09 21:18:50','2026-01-09 21:18:50'),
(2,'system_timezone','Asia/Manila','string','System timezone',NULL,'2026-01-09 21:18:50','2026-01-09 21:18:50'),
(3,'data_retention_days','90','string','Days to keep audit logs',NULL,'2026-01-09 21:18:50','2026-01-09 21:18:50');

/*Table structure for table `tanod_duty_logs` */

DROP TABLE IF EXISTS `tanod_duty_logs`;

CREATE TABLE `tanod_duty_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL,
  `location_lat` decimal(10,8) DEFAULT NULL,
  `location_lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `tanod_duty_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `tanod_duty_logs` */

insert  into `tanod_duty_logs`(`id`,`user_id`,`clock_in`,`clock_out`,`location_lat`,`location_lng`,`created_at`) values 
(1,1010,'2026-01-09 04:35:14','2026-01-09 04:35:15',NULL,NULL,'2026-01-08 20:35:14'),
(2,1009,'2026-01-10 05:08:08','2026-01-10 06:38:15',NULL,NULL,'2026-01-09 21:08:08'),
(3,1009,'2026-01-10 06:38:15','2026-01-10 06:38:26',NULL,NULL,'2026-01-09 22:38:15'),
(4,1009,'2026-01-10 07:43:14','2026-01-10 07:43:14',NULL,NULL,'2026-01-09 23:43:14'),
(5,1009,'2026-01-10 07:43:21','2026-01-10 07:43:52',NULL,NULL,'2026-01-09 23:43:21'),
(6,1009,'2026-01-10 07:43:52','2026-01-10 07:44:12',NULL,NULL,'2026-01-09 23:43:52'),
(7,1009,'2026-01-10 07:44:12',NULL,NULL,NULL,'2026-01-09 23:44:12');

/*Table structure for table `tanod_incidents` */

DROP TABLE IF EXISTS `tanod_incidents`;

CREATE TABLE `tanod_incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `location` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `incident_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `witnesses` text DEFAULT NULL,
  `action_taken` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `reported_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `reported_at` (`reported_at`),
  CONSTRAINT `tanod_incidents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `tanod_incidents` */

/*Table structure for table `tanod_schedules` */

DROP TABLE IF EXISTS `tanod_schedules`;

CREATE TABLE `tanod_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `shift_start` time NOT NULL,
  `shift_end` time NOT NULL,
  `shift_type` varchar(50) DEFAULT NULL,
  `patrol_route` text DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `tanod_schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `tanod_schedules` */

insert  into `tanod_schedules`(`id`,`user_id`,`schedule_date`,`shift_start`,`shift_end`,`shift_type`,`patrol_route`,`assigned_by`,`created_at`) values 
(2,1009,'2026-01-23','03:00:00','15:30:00',NULL,'',1013,'2026-01-22 19:29:18');

/*Table structure for table `tanod_status` */

DROP TABLE IF EXISTS `tanod_status`;

CREATE TABLE `tanod_status` (
  `user_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'Off-Duty',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `tanod_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `tanod_status` */

insert  into `tanod_status`(`user_id`,`status`,`last_updated`) values 
(1009,'On-Duty','2026-01-09 23:44:12'),
(1010,'Off-Duty','2026-01-08 20:35:15');

/*Table structure for table `user_citizen_details` */

DROP TABLE IF EXISTS `user_citizen_details`;

CREATE TABLE `user_citizen_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `id_type` enum('barangay_id','school_id','government_id','other') DEFAULT NULL,
  `id_upload_path` varchar(255) DEFAULT NULL,
  `guardian_id_upload_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_citizen_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `user_citizen_details` */

insert  into `user_citizen_details`(`id`,`user_id`,`guardian_name`,`guardian_contact`,`id_type`,`id_upload_path`,`guardian_id_upload_path`,`created_at`,`updated_at`) values 
(1,1003,'','','barangay_id','uploads/ids/6935f6f75f045_1765144311.jpg',NULL,'2025-12-07 21:51:51','2025-12-07 21:51:51'),
(2,1004,'Clarisa Galez','09876234568','school_id','uploads/ids/6935f95ce533d_1765144924.jpeg','uploads/guardian_ids/guardian_6935f95ce6406_1765144924.jpg','2025-12-07 22:02:04','2025-12-07 22:02:04'),
(3,1014,'Siya Viray','09643464646','school_id','uploads/ids/69654578141e1_1768244600.jpg','uploads/guardian_ids/guardian_6965457814693_1768244600.jpg','2026-01-12 19:03:20','2026-01-12 19:03:20'),
(4,1015,'','','barangay_id','uploads/ids/69669df669663_1768332790.jpeg',NULL,'2026-01-13 19:33:10','2026-01-13 19:33:10'),
(5,1022,'','','barangay_id','uploads/ids/69737e873308b_1769176711.jpg',NULL,'2026-01-23 13:58:31','2026-01-23 13:58:31');

/*Table structure for table `user_notifications` */

DROP TABLE IF EXISTS `user_notifications`;

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error','emergency') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_user_notifications_read` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `user_notifications` */

/*Table structure for table `user_roles` */

DROP TABLE IF EXISTS `user_roles`;

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `user_roles` */

insert  into `user_roles`(`id`,`user_id`,`role_id`,`assigned_by`,`assigned_at`) values 
(1,1001,1,NULL,'2025-12-06 05:32:10'),
(2,1001,7,NULL,'2026-01-14 13:31:54');

/*Table structure for table `user_sessions` */

DROP TABLE IF EXISTS `user_sessions`;

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_token` (`session_token`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `user_sessions` */

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `sex` enum('Male','Female','Other') NOT NULL,
  `birthday` date NOT NULL,
  `age` int(11) DEFAULT NULL,
  `permanent_address` text NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_number` varchar(20) DEFAULT NULL,
  `id_verification_path` varchar(255) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('citizen','barangay_member') DEFAULT 'citizen',
  `role` enum('admin','captain','secretary','lupon','tanod','citizen','super_admin','lupon_chairman') DEFAULT 'citizen',
  `position_id` int(11) DEFAULT NULL,
  `date_appointed` date DEFAULT NULL,
  `term_end` date DEFAULT NULL,
  `office_hours` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','pending','rejected') DEFAULT 'pending',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `pin_code` varchar(10) DEFAULT NULL,
  `master_code` varchar(10) DEFAULT NULL,
  `is_master_code_used` tinyint(1) DEFAULT 0,
  `master_code_used_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` timestamp NULL DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 1,
  `profile_picture` varchar(255) DEFAULT NULL,
  `notification_token` varchar(255) DEFAULT NULL,
  `wants_notifications` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `verification_expiry` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `is_chairman` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_status` (`status`),
  KEY `idx_users_status` (`is_active`,`status`),
  KEY `idx_users_barangay` (`user_type`,`barangay`),
  KEY `idx_position` (`position_id`),
  KEY `idx_users_barangay_status` (`barangay`,`status`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=1023 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`first_name`,`middle_name`,`last_name`,`suffix`,`sex`,`birthday`,`age`,`permanent_address`,`contact_number`,`emergency_contact`,`emergency_number`,`id_verification_path`,`email`,`username`,`password`,`user_type`,`role`,`position_id`,`date_appointed`,`term_end`,`office_hours`,`barangay`,`status`,`is_active`,`created_at`,`updated_at`,`last_login`,`pin_code`,`master_code`,`is_master_code_used`,`master_code_used_at`,`reset_token`,`reset_token_expiry`,`is_online`,`profile_picture`,`notification_token`,`wants_notifications`,`email_verified`,`verification_token`,`verification_expiry`,`verified_at`,`is_chairman`) values 
(1001,'System','','Administrator','','Male','1990-01-01',34,'Barangay Hall, City Hall Compound','9123456789',NULL,NULL,NULL,'admin@leir.com','admin','hello11.','barangay_member','super_admin',NULL,NULL,NULL,NULL,'LGU-4','active',1,'2025-12-06 05:32:10','2026-01-14 13:31:54',NULL,'1234',NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1002,'Juan','Santos','Dela Cruz','Jr','Male','1985-05-15',39,'123 Main Street, Barangay 1, City','9123456780',NULL,NULL,NULL,'citizen@example.com','citizen','hello11.','citizen','citizen',NULL,NULL,NULL,NULL,'Barangay 1','active',1,'2025-12-06 05:32:10','2025-12-07 14:13:06',NULL,NULL,NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1003,'cla','','galvez','','Female','2002-12-01',23,'Block 8  Towerville','09978407627','clarisa galvez','09978407625','uploads/ids/6935f6f75f045_1765144311.jpg','ysa@gmail.com','ysa','$2y$10$ErRqKYCyQD2sBpZv.w2NiudfPvT89YSsiTvHn2i6Ylc6D1ztKOKym','citizen','citizen',NULL,NULL,NULL,NULL,'Block 8 lot 2 6D Towerville','active',1,'2025-12-07 21:51:51','2026-01-25 21:27:30','2026-01-25 21:27:30',NULL,NULL,0,NULL,NULL,NULL,1,'1768993413_6970b285e9da1_504896849_9887062978015746_9181476553568709558_n.jpg',NULL,1,0,NULL,NULL,NULL,0),
(1004,'angelo','galvez','pabericio','','Male','2009-10-22',16,'Block 8 lot 2 6D Towerville','09978407622','clarisa galvez','09978407628','uploads/ids/6935f95ce533d_1765144924.jpeg','ysagalv5@gmail.com','cris','$2y$10$mT0NIt.Ag.ohJtojwKgdnOB/w9JMSpROhQShWXGaQvgHkJeChVnBO','citizen','citizen',NULL,NULL,NULL,NULL,'Block 8 lot 2 6D Towerville','active',0,'2025-12-07 22:02:04','2025-12-08 08:19:25','2025-12-08 08:06:36',NULL,NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1005,'Clarisa','Galvez','Manangan','','Female','2002-03-11',23,'Block 110','09978407689',NULL,NULL,NULL,'ysagalvez@gmail.com','clarisg','$2y$10$Z5aPzVJE.ponvcbFJJ4oS.6IALC5jnaTCIFbNihF8YjCsIrwKMdnG','barangay_member','secretary',NULL,NULL,NULL,NULL,'Block 110','active',1,'2026-01-03 16:34:21','2026-01-06 18:14:29',NULL,NULL,'1019',0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1006,'Ysabel','','Gal','','Female','2001-09-23',24,'6D','08976545678',NULL,NULL,NULL,'ysagalv@gmail.com','ysag','$2y$10$VqqQIuPWdyi.Lz86F/QGselYVD5UGtU98VJ5M8AtbAxsJ2kTGKq46','barangay_member','admin',NULL,NULL,NULL,NULL,'6D','active',1,'2026-01-03 16:45:12','2026-01-03 16:45:12',NULL,NULL,NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1007,'JR','','RACELIS','','Male','2001-11-13',24,'BLOCK 7','09865434567',NULL,NULL,NULL,'Jr@gmail.com','jr','$2y$10$bUtOO.9WDZNnYcwLjoVNN.GJ74c8V7bazXPB.v1rzlFmVdoHV7/Ma','barangay_member','captain',NULL,NULL,NULL,NULL,'BLOCK 7','pending',1,'2026-01-03 17:32:35','2026-01-03 17:32:35',NULL,NULL,'5981',0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1008,'hanah','','magnaye','','Female','2000-09-07',25,'Gloria 5','09875678923',NULL,NULL,NULL,'hanah@gmail.com','hanah','$2y$10$ztzCxhIh82YzPdtHM4.syOWDRe04MxxWrdCNC7RdutzrIjstoVWCi','barangay_member','secretary',NULL,NULL,NULL,NULL,'talipapa','pending',1,'2026-01-03 17:51:52','2026-01-25 21:10:12','2026-01-25 21:10:12',NULL,'4939',1,'2026-01-06 18:37:39',NULL,NULL,1,'1767728803_695d66a356987_504896849_9887062978015746_9181476553568709558_n.jpg',NULL,1,0,NULL,NULL,NULL,0),
(1009,'Jeff','','Paray','','Male','1998-12-02',27,'Phase 9 Wakwak Street','09823456785',NULL,NULL,NULL,'jeff@gmail.com','jeff','$2y$10$6vaUPPfOrZfokIxW3zYA7evdkMw7OX01r4THlroZDngQ0sB4jHaCq','barangay_member','tanod',NULL,NULL,NULL,NULL,'Phase 9 Wakwak Street','active',1,'2026-01-07 15:23:41','2026-01-24 04:50:09','2026-01-24 04:50:09',NULL,'6152',1,'2026-01-07 15:24:17',NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1010,'Abo','','Magnaye','Jr','Male','2003-12-25',22,'Block 9 Lot 78 QC','09876543278',NULL,NULL,NULL,'magnaye@gmail.com','mj','$2y$10$jw4FHv5c6mKG0baOspWX3OKyYGqCbIszO.WJ781bNJJSO9KZXEeXG','barangay_member','tanod',NULL,NULL,NULL,NULL,'Block 9 Lot 78 QC','active',1,'2026-01-08 20:31:07','2026-01-09 11:50:32','2026-01-09 11:50:32',NULL,'5556',1,'2026-01-08 20:31:46',NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1011,'Isagani','','Viray','II','Male','2000-06-05',25,'Block 98 Lot 972','09876543234',NULL,NULL,NULL,'isagani@gmail.com','isagani','$2y$10$/JOwLGfhiOONLC3QyZ.dgOWy8kC4pYNT01Rvi.Poz58QkhJrrvlOq','barangay_member','tanod',NULL,NULL,NULL,NULL,'Block 98 Lot 972','active',1,'2026-01-08 20:44:19','2026-01-08 20:45:25','2026-01-08 20:45:25',NULL,'6762',1,'2026-01-08 20:45:25',NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1012,'John Rey','Racelis','Manangan','','Male','2001-11-13',24,'Block 7 Lot 4 Loma de Gato','09543456786',NULL,NULL,NULL,'rey@gmail.com','JayR','$2y$10$t7R3wxMVWOn2sopCVrbSPO.lPPsdLYyPOg8kqh5paP9wucPg5VUdO','barangay_member','captain',NULL,NULL,NULL,NULL,'Block 7 Lot 4 Loma de Gato','active',1,'2026-01-09 20:10:34','2026-01-24 05:34:46','2026-01-24 05:34:46',NULL,'4151',1,'2026-01-09 20:11:04',NULL,NULL,1,'profile_1012_1769102833.jpg',NULL,1,0,NULL,NULL,NULL,0),
(1013,'Clarisa','Galvez','Manangan','','Female','1982-03-11',43,'Block 98 Lot 75 Bulacan','09987567895',NULL,NULL,NULL,'ysagalvez5@gmail.com','clang','$2y$10$rTn7Qi3l2zvb9DH.3FnRIO7SAhcg6lX3RF1vZbTX/XG81Z90OgLMW','barangay_member','admin',NULL,NULL,NULL,NULL,'Block 98 Lot 75 Bulacan','active',1,'2026-01-09 20:44:21','2026-01-24 19:47:50','2026-01-24 19:47:50',NULL,'2660',1,'2026-01-09 20:44:55',NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1014,'Jossell','Docil','Viray','','Female','2009-01-12',17,'Tandang sora','09643434346','Soy Viray','09346434646','uploads/ids/69654578141e1_1768244600.jpg','jo@gmail.com','Jo','$2y$10$m6sVTY67LbyVTDG/GZEFaue3vwgrVJR.SUCHuecJf/laS4a8BPm8.','citizen','citizen',NULL,NULL,NULL,NULL,'Tandang sora','active',1,'2026-01-12 19:03:20','2026-01-14 16:09:26','2026-01-14 16:09:26',NULL,NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1015,'Jep','Batac','Paray','Sr','Male','1998-12-14',27,'Dito sa silang','0966455484','Jo Viray','09668021169','uploads/ids/69669df669663_1768332790.jpeg','jep@gmail.com','Jep.tomboy','$2y$10$ohRnhsVExsnJXinPVzJZuu54ZHGVHUd88I3ROuzyuJFwopUIqfLPm','citizen','citizen',NULL,NULL,NULL,NULL,'Dito sa silang','active',1,'2026-01-13 19:33:10','2026-01-13 19:33:37','2026-01-13 19:33:37',NULL,NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1016,'Joana','Baba','Sabulao','','Female','1998-09-06',27,'RP, Quezon City','09456787654',NULL,NULL,NULL,'joana@gmail.com','joana','$2y$10$eNlAWQ8AIRlUQONnflB7hOydrGAZGuX/ZNfefslGE1zKu65Rkb/Wu','barangay_member','lupon',NULL,NULL,NULL,NULL,'RP, Quezon City','active',1,'2026-01-14 07:47:51','2026-01-22 13:01:56','2026-01-14 16:06:03',NULL,'0380',1,'2026-01-14 13:16:08',NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,1),
(1017,'Super',NULL,'Administrator',NULL,'Male','1990-01-01',NULL,'System Administration Office','9123456789',NULL,NULL,NULL,'superadmin@leir.com','superadmin','$2y$10$your_hashed_password_here','barangay_member','super_admin',NULL,NULL,NULL,NULL,'System-Wide','active',1,'2026-01-14 13:31:54','2026-01-14 13:31:54',NULL,NULL,NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1019,'Charlie','Villarin','Pabericio','Sr','Male','1971-01-10',55,'Tandang Sora, Quezon City','09876534567',NULL,NULL,NULL,'charlie@gmail.com','charlie','$2y$10$NJKldRhyAAjHz.yBsEMOkuIQHw.SVnAUJdZxxPIYFMWmeYllIY4Gm','barangay_member','super_admin',NULL,NULL,NULL,NULL,'Tandang Sora, Quezon City','active',1,'2026-01-14 15:15:20','2026-01-23 05:30:44','2026-01-23 05:30:44',NULL,'5705',1,'2026-01-14 15:16:08',NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1020,'carmelo','','galvez','','Female','2000-10-03',25,'fghjk','09876544567',NULL,NULL,NULL,'carmelo@gmail.com','carmelo`','$2y$10$BCpPFSPR6s2Y6fn9ypxA2.foB2dyoGdlgCSEvrxvXbaK6S54vTlh.','barangay_member','tanod',NULL,NULL,NULL,NULL,'fghjk','active',1,'2026-01-17 22:55:06','2026-01-17 22:56:20','2026-01-17 22:56:20',NULL,'8675',1,'2026-01-17 22:55:24',NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1021,'andy',NULL,'doza',NULL,'Male','0000-00-00',NULL,'','',NULL,NULL,NULL,'andy@gmail.com','','$2y$10$ZgVOLQmOG3db6MQSYVHt3.oRFqc.oHngTzh/36CMEn2NnH0SuV1re','citizen','tanod',NULL,NULL,NULL,NULL,'qc','pending',1,'2026-01-22 19:35:48','2026-01-22 19:39:41',NULL,NULL,NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0),
(1022,'christine','remolacio','moratalla','','Female','2004-06-22',21,'03 Ivory Street Payatas, A. Quezon City','09536841503','carlito moratalla','09663258456','uploads/ids/69737e873308b_1769176711.jpg','joychristine358@gmail.com','tineee','$2y$10$nyiXfJCw.ywCuyIr/7Y9dugGip865NLONeUlkDKwMxMVfriziODq2','citizen','citizen',NULL,NULL,NULL,NULL,'03 Ivory Street Payatas, A. Quezon City','active',1,'2026-01-23 13:58:31','2026-01-23 13:58:38','2026-01-23 13:58:38',NULL,NULL,0,NULL,NULL,NULL,1,NULL,NULL,1,0,NULL,NULL,NULL,0);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
