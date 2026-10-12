-- =======================================================
-- Campus Complaint Management and Tracking System
-- Database Schema and Demonstration Seed Data
-- Target Database: campus_complaint_system
-- Compatibility: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.4+
-- =======================================================

CREATE DATABASE IF NOT EXISTS `campus_complaint_system` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `campus_complaint_system`;

-- Disable foreign key checks during schema creation & data population
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------
-- 1. Table: users
-- -------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(25) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'staff', 'admin') NOT NULL DEFAULT 'student',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_role` (`role`),
  INDEX `idx_user_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 2. Table: categories
-- -------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. Table: departments
-- -------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 4. Table: complaints
-- -------------------------------------------------------
DROP TABLE IF EXISTS `complaints`;
CREATE TABLE `complaints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_code` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(150) NOT NULL,
  `priority` ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
  `image` VARCHAR(255) NULL,
  `resolution_image` VARCHAR(255) NULL,
  `resolution_notes` TEXT NULL,
  `resolved_at` TIMESTAMP NULL,
  `resolved_by` INT NULL,
  `status` ENUM('Submitted', 'Under Review', 'Approved', 'Assigned', 'In Progress', 'Resolved', 'Rejected', 'Closed') NOT NULL DEFAULT 'Submitted',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_complaints_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_complaints_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_complaints_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_complaints_status` (`status`),
  INDEX `idx_complaints_priority` (`priority`),
  INDEX `idx_complaints_code` (`complaint_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 5. Table: assignments
-- -------------------------------------------------------
DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  `staff_id` INT NOT NULL,
  `assigned_by` INT NOT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_assign_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_assign_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_assign_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  INDEX `idx_assign_staff` (`staff_id`),
  INDEX `idx_assign_complaint` (`complaint_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 6. Table: complaint_updates (Timeline)
-- -------------------------------------------------------
DROP TABLE IF EXISTS `complaint_updates`;
CREATE TABLE `complaint_updates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT NOT NULL,
  `user_id` INT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `comment` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_updates_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_updates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_updates_complaint` (`complaint_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 7. Table: feedback
-- -------------------------------------------------------
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_feedback_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 8. Table: notifications
-- -------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_notif_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;


-- =======================================================
-- SEED DATA
-- =======================================================

-- 1. Seed Categories (13 default categories)
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Electrical', 'Power issues, light fixtures, fans, switchboards, and electrical wiring.'),
(2, 'Plumbing', 'Water supply, pipeline leaks, taps, drainage, and water cooler faults.'),
(3, 'Internet/Wi-Fi', 'Campus Wi-Fi connectivity, LAN ports, authentication, and router faults.'),
(4, 'Classroom', 'Blackboards, podiums, audio-visual equipment, and lecture hall amenities.'),
(5, 'Laboratory', 'Lab equipment, chemical storage facilities, instrument calibration, and benches.'),
(6, 'Furniture', 'Desks, benches, executive chairs, seminar hall seating, and cupboards.'),
(7, 'Cleaning', 'Waste disposal, dust accumulation, corridor sweeping, and window cleaning.'),
(8, 'Restroom', 'Hygiene issues, flush mechanisms, hand dryers, and sanitation supplies.'),
(9, 'Library', 'Reading hall lighting, ventilation, book racks, and digital terminal access.'),
(10, 'Canteen', 'Food hygiene, dining table cleanliness, drinking water, and waste bins.'),
(11, 'Transportation', 'Campus shuttle buses, parking facility congestion, and vehicle permits.'),
(12, 'Security', 'CCTV coverage, gate access control, night lighting, and emergency call points.'),
(13, 'Other', 'General campus utilities, landscaping, signage, and unlisted concerns.');

-- 2. Seed Departments
INSERT INTO `departments` (`id`, `name`, `description`) VALUES
(1, 'Electrical Department', 'Handles power distribution, backup generators, lighting, fans, and campus electrical safety.'),
(2, 'IT Services', 'Manages campus networking, Wi-Fi hotspots, computer lab systems, and servers.'),
(3, 'Maintenance & Repair', 'Oversees civil repairs, carpentry, furniture refurbishment, and structural upkeep.'),
(4, 'Plumbing Works', 'Manages water piping, overhead tanks, drainage lines, and water dispensers.'),
(5, 'Housekeeping & Sanitation', 'Maintains campus cleanliness, waste segregation, restroom sanitization, and pest control.'),
(6, 'Campus Security', 'Responsible for surveillance, campus gate protocols, patrol, and emergency response.'),
(7, 'Transport Office', 'Oversees college buses, fleet scheduling, driver operations, and parking allocations.');

-- 3. Seed Users
-- Passwords hashed with bcrypt:
-- admin@demo.com   -> admin123   ($2y$10$Y.GVNBNczcn8uequ0NnGtusNG2oTuQh8sDcXcFcHk7YNRz1DvQLIe)
-- staff@demo.com   -> staff123   ($2y$10$H16BSZwONfCx6jeIIg7N0u0hENI0ZIpnb0g7TPIuM7x8VNfQd/bxa)
-- student@demo.com -> student123 ($2y$10$LxyFY97Yzyi7RbG035JUHOCNf6HHNUF0PTrxjroM1Fhpgt5QzE9mq)
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `status`) VALUES
(1, 'Demo Admin', 'admin@demo.com', '+91 98765 43210', '$2y$10$Y.GVNBNczcn8uequ0NnGtusNG2oTuQh8sDcXcFcHk7YNRz1DvQLIe', 'admin', 'active'),
(2, 'Demo Staff', 'staff@demo.com', '+91 98765 43211', '$2y$10$H16BSZwONfCx6jeIIg7N0u0hENI0ZIpnb0g7TPIuM7x8VNfQd/bxa', 'staff', 'active'),
(3, 'Sarah Jenkins', 'sarah.it@demo.com', '+91 98765 43212', '$2y$10$H16BSZwONfCx6jeIIg7N0u0hENI0ZIpnb0g7TPIuM7x8VNfQd/bxa', 'staff', 'active'),
(4, 'Michael Chang', 'michael.elec@demo.com', '+91 98765 43213', '$2y$10$H16BSZwONfCx6jeIIg7N0u0hENI0ZIpnb0g7TPIuM7x8VNfQd/bxa', 'staff', 'active'),
(5, 'Demo Student', 'student@demo.com', '+91 98765 43220', '$2y$10$LxyFY97Yzyi7RbG035JUHOCNf6HHNUF0PTrxjroM1Fhpgt5QzE9mq', 'student', 'active'),
(6, 'Alex Rivera', 'alex.student@demo.com', '+91 98765 43221', '$2y$10$LxyFY97Yzyi7RbG035JUHOCNf6HHNUF0PTrxjroM1Fhpgt5QzE9mq', 'student', 'active');

-- 4. Seed Complaints across various lifecycle stages
INSERT INTO `complaints` 
(`id`, `complaint_code`, `user_id`, `category_id`, `title`, `description`, `location`, `priority`, `image`, `resolution_image`, `resolution_notes`, `resolved_at`, `resolved_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 'CMP-2026-00001', 5, 1, 'Ceiling fan making vibrating noise and wobbling', 'The middle ceiling fan in Lecture Hall B makes a loud rattling sound and wobbles erratically whenever set to speed 3 or higher. Poses a distraction and safety concern during lectures.', 'Main Academic Block - 1st Floor, Lecture Hall B', 'High', NULL, NULL, 'Replaced the worn-out mounting rubber bush and tightened safety downrod clamps. Tested fan operation at all 5 speeds - running silently and balanced.', '2026-09-17 14:30:00', 4, 'Resolved', '2026-09-16 09:15:00', '2026-09-17 14:30:00'),

(2, 'CMP-2026-00002', 5, 3, 'Campus Wi-Fi drops continuously in Lab 3', 'Students are unable to maintain a stable connection on the Campus_Secure SSID. Authentication times out every 5 minutes, disrupting online programming practical tests.', 'IT Block - 2nd Floor, Computer Lab 3', 'High', NULL, NULL, NULL, NULL, NULL, 'In Progress', '2026-09-18 10:00:00', '2026-09-18 13:45:00'),

(3, 'CMP-2026-00003', 6, 2, 'Water leakage under washbasin in 2nd floor restroom', 'Continuous slow water seepage from the drainage P-trap pipe underneath the washbasin, causing water puddles on the restroom floor and slipping hazard.', 'Mechanical Block - Restroom 204', 'Medium', NULL, NULL, NULL, NULL, NULL, 'Assigned', '2026-09-18 14:20:00', '2026-09-19 09:10:00'),

(4, 'CMP-2026-00004', 5, 6, 'Broken armrest and loose screws on seminar chairs', 'Three cushioned chairs in the front row of Seminar Hall 1 have detached armrests and loose base bolts, preventing safe seating during guest lectures.', 'Convention Center - Seminar Hall 1', 'Medium', NULL, NULL, NULL, NULL, NULL, 'Approved', '2026-09-19 11:30:00', '2026-09-19 16:00:00'),

(5, 'CMP-2026-00005', 6, 4, 'Projector HDMI port damaged and screen flickering', 'The wall mounted projector in Classroom 302 flickers magenta and loses signal intermittently. Requires replacement of HDMI wall faceplate cable.', 'Science Block - Room 302', 'Low', NULL, NULL, NULL, NULL, NULL, 'Under Review', '2026-09-20 08:45:00', '2026-09-20 09:30:00'),

(6, 'CMP-2026-00006', 6, 13, 'Request to install an automated soft drink dispenser in hostel lobby', 'Students often need chilled drinks during late night study sessions. Please consider installing a commercial vending machine.', 'Boys Hostel Block C - Ground Floor', 'Low', NULL, NULL, 'Commercial vending installation proposals are handled through the Campus Canteen Committee annual tender process. This complaint channel is intended for maintenance and facility faults.', NULL, NULL, 'Rejected', '2026-09-15 11:00:00', '2026-09-15 15:30:00'),

(7, 'CMP-2026-00007', 5, 9, 'AC thermostat display showing error code E4 in Reading Hall', 'The central split air conditioner unit in the corner of the Reference Section is blowing warm air and showing error code E4.', 'Central Library - 1st Floor Reference Section', 'Medium', NULL, NULL, NULL, NULL, NULL, 'Submitted', '2026-09-20 10:15:00', '2026-09-20 10:15:00');

-- 5. Seed Assignments
INSERT INTO `assignments` (`id`, `complaint_id`, `department_id`, `staff_id`, `assigned_by`, `assigned_at`) VALUES
(1, 1, 1, 4, 1, '2026-09-16 11:00:00'),
(2, 2, 2, 3, 1, '2026-09-18 11:30:00'),
(3, 3, 4, 2, 1, '2026-09-19 09:10:00');

-- 6. Seed Complaint Updates (Timeline Events)
INSERT INTO `complaint_updates` (`complaint_id`, `user_id`, `old_status`, `new_status`, `comment`, `created_at`) VALUES
-- Timeline for Complaint #1 (Resolved)
(1, 5, NULL, 'Submitted', 'Complaint registered by student Demo Student.', '2026-09-16 09:15:00'),
(1, 1, 'Submitted', 'Approved', 'Complaint inspected and approved for urgent electrical maintenance.', '2026-09-16 10:30:00'),
(1, 1, 'Approved', 'Assigned', 'Assigned to Electrical Department and staff technician Michael Chang.', '2026-09-16 11:00:00'),
(1, 4, 'Assigned', 'In Progress', 'Technician arrived on-site with ladder and replacement rubber bushings.', '2026-09-17 11:15:00'),
(1, 4, 'In Progress', 'Resolved', 'Replaced the worn-out mounting rubber bush and tightened safety downrod clamps. Tested fan operation at all 5 speeds - running silently and balanced.', '2026-09-17 14:30:00'),

-- Timeline for Complaint #2 (In Progress)
(2, 5, NULL, 'Submitted', 'Complaint submitted regarding network disconnection.', '2026-09-18 10:00:00'),
(2, 1, 'Submitted', 'Approved', 'Admin validated high impact on student lab practicals.', '2026-09-18 10:45:00'),
(2, 1, 'Approved', 'Assigned', 'Assigned to IT Services and Network Engineer Sarah Jenkins.', '2026-09-18 11:30:00'),
(2, 3, 'Assigned', 'In Progress', 'Investigating PoE switch in Lab 3 rack. Suspecting firmware freeze on AP-04.', '2026-09-18 13:45:00'),

-- Timeline for Complaint #3 (Assigned)
(3, 6, NULL, 'Submitted', 'Plumbing complaint registered by Alex Rivera.', '2026-09-18 14:20:00'),
(3, 1, 'Submitted', 'Approved', 'Admin approved plumbing repair.', '2026-09-19 08:30:00'),
(3, 1, 'Approved', 'Assigned', 'Assigned to Plumbing Works and technician Demo Staff.', '2026-09-19 09:10:00'),

-- Timeline for Complaint #4 (Approved)
(4, 5, NULL, 'Submitted', 'Complaint submitted for broken furniture.', '2026-09-19 11:30:00'),
(4, 1, 'Submitted', 'Approved', 'Complaint reviewed and approved. Awaiting carpenter schedule.', '2026-09-19 16:00:00'),

-- Timeline for Complaint #5 (Under Review)
(5, 6, NULL, 'Submitted', 'HDMI cable fault submitted.', '2026-09-20 08:45:00'),
(5, 1, 'Submitted', 'Under Review', 'Admin inspecting AV inventory stock before assigning technician.', '2026-09-20 09:30:00'),

-- Timeline for Complaint #6 (Rejected)
(6, 6, NULL, 'Submitted', 'Vending machine request submitted.', '2026-09-15 11:00:00'),
(6, 1, 'Submitted', 'Rejected', 'Commercial vending installation proposals are handled through the Campus Canteen Committee annual tender process. This complaint channel is intended for maintenance and facility faults.', '2026-09-15 15:30:00'),

-- Timeline for Complaint #7 (Submitted)
(7, 5, NULL, 'Submitted', 'AC error submitted by Demo Student.', '2026-09-20 10:15:00');

-- 7. Seed Feedback (For resolved complaint #1)
INSERT INTO `feedback` (`id`, `complaint_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 5, 5, 'The technician arrived very promptly and fixed the fan before our afternoon session. No noise at all now. Outstanding service!', '2026-09-17 16:45:00');

-- 8. Seed Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(5, 'Complaint Resolved', 'Your complaint CMP-2026-00001 (Ceiling fan vibrating violently) has been marked as Resolved. Please provide your feedback.', 'student/complaint-details.php?id=1', 1, '2026-09-17 14:30:00'),
(5, 'Complaint In Progress', 'Work has started on your complaint CMP-2026-00002 (Campus Wi-Fi drops) by Sarah Jenkins.', 'student/complaint-details.php?id=2', 0, '2026-09-18 13:45:00'),
(5, 'Complaint Approved', 'Your complaint CMP-2026-00004 (Broken armrest on chairs) has been approved by admin.', 'student/complaint-details.php?id=4', 0, '2026-09-19 16:00:00'),
(6, 'Complaint Assigned', 'Your complaint CMP-2026-00003 (Water leakage) has been assigned to Plumbing Works.', 'student/complaint-details.php?id=3', 0, '2026-09-19 09:10:00'),
(6, 'Complaint Rejected', 'Your complaint CMP-2026-00006 was reviewed and rejected. Reason: Commercial vending installation...', 'student/complaint-details.php?id=6', 1, '2026-09-15 15:30:00'),
(3, 'New Task Assigned', 'You have been assigned to complaint CMP-2026-00002: Campus Wi-Fi drops continuously in Lab 3.', 'staff/complaint-details.php?id=2', 0, '2026-09-18 11:30:00'),
(2, 'New Task Assigned', 'You have been assigned to complaint CMP-2026-00003: Water leakage under washbasin in 2nd floor restroom.', 'staff/complaint-details.php?id=3', 0, '2026-09-19 09:10:00'),
(1, 'New Feedback Received', 'Student Demo Student left a 5-star review on complaint CMP-2026-00001.', 'admin/feedback.php', 0, '2026-09-17 16:45:00'),
(1, 'New Complaint Submitted', 'New complaint CMP-2026-00007 submitted: AC thermostat display showing error code E4 in Reading Hall.', 'admin/complaint-details.php?id=7', 0, '2026-09-20 10:15:00');
