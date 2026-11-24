-- ========================================================
-- Database: g2_it_inventory
-- Project: G2 Global Inc. - Simple IT Inventory System
-- ========================================================

CREATE DATABASE IF NOT EXISTS `g2_it_inventory`
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `g2_it_inventory`;

-- --------------------------------------------------------
-- 1. Table: users (IT Staff accounts)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `role` ENUM('Admin', 'Staff') NOT NULL DEFAULT 'Staff',
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 2. Table: categories (Equipment classifications)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `code_prefix` VARCHAR(10) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 3. Table: employees (Company personnel receiving equipment)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(30) NOT NULL UNIQUE,
    `first_name` VARCHAR(60) NOT NULL,
    `last_name` VARCHAR(60) NOT NULL,
    `department` VARCHAR(80) NOT NULL,
    `position` VARCHAR(80) DEFAULT NULL,
    `employment_status` ENUM('Active', 'Resigned', 'Terminated') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_emp_name` (`last_name`, `first_name`),
    INDEX `idx_emp_dept` (`department`)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 4. Table: devices (Hardware assets)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `devices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `asset_tag` VARCHAR(30) NOT NULL UNIQUE,
    `category_id` INT UNSIGNED NOT NULL,
    `brand` VARCHAR(60) NOT NULL,
    `model` VARCHAR(80) NOT NULL,
    `serial_number` VARCHAR(100) NOT NULL UNIQUE,
    `location` VARCHAR(100) NOT NULL DEFAULT 'IT Storage',
    `purchase_date` DATE DEFAULT NULL,
    `device_condition` ENUM('New', 'Good', 'Fair', 'Damaged') NOT NULL DEFAULT 'Good',
    `status` ENUM('Available', 'Assigned', 'Under Repair', 'Retired') NOT NULL DEFAULT 'Available',
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_device_status` (`status`),
    INDEX `idx_device_category` (`category_id`),
    CONSTRAINT `fk_devices_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 5. Table: assignments (Device issuance and return tracking)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_id` INT UNSIGNED NOT NULL,
    `employee_id` INT UNSIGNED NOT NULL,
    `assigned_by` INT UNSIGNED NOT NULL,
    `assigned_date` DATE NOT NULL,
    `condition_on_assign` ENUM('New', 'Good', 'Fair') NOT NULL DEFAULT 'Good',
    `return_date` DATE DEFAULT NULL,
    `returned_to` INT UNSIGNED DEFAULT NULL,
    `condition_on_return` ENUM('Good', 'Fair', 'Damaged') DEFAULT NULL,
    `status` ENUM('Active', 'Returned', 'Transferred') NOT NULL DEFAULT 'Active',
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_assignment_status` (`status`),
    INDEX `idx_assign_device` (`device_id`),
    INDEX `idx_assign_employee` (`employee_id`),
    CONSTRAINT `fk_assign_device`
        FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_assign_employee`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_assign_user`
        FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_return_user`
        FOREIGN KEY (`returned_to`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 6. Table: documents (Liability forms, receipts, warranties)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT UNSIGNED DEFAULT NULL,
    `device_id` INT UNSIGNED DEFAULT NULL,
    `employee_id` INT UNSIGNED DEFAULT NULL,
    `document_type` ENUM('Liability Form', 'Purchase Invoice', 'Warranty', 'Maintenance Report', 'Other') NOT NULL DEFAULT 'Liability Form',
    `original_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(50) NOT NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `remarks` VARCHAR(255) DEFAULT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_doc_assignment` (`assignment_id`),
    INDEX `idx_doc_device` (`device_id`),
    INDEX `idx_doc_employee` (`employee_id`),
    CONSTRAINT `fk_doc_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_doc_device`
        FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_doc_employee`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_doc_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- 7. Table: activity_logs (Audit trail for system actions)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_user` (`user_id`),
    INDEX `idx_log_action` (`action`),
    CONSTRAINT `fk_log_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ========================================================
-- SEED DATA
-- ========================================================

-- Standard IT Equipment Categories
INSERT INTO `categories` (`name`, `code_prefix`) VALUES
('Laptop', 'LAP'),
('Desktop', 'DSK'),
('Monitor', 'MON'),
('Printer', 'PRN'),
('Router', 'RTR'),
('Switch', 'SWH'),
('UPS', 'UPS'),
('Keyboard', 'KBD'),
('Mouse', 'MOU'),
('Other IT Equipment', 'OTH');

-- Default Administrator Account
-- Username: admin
-- Password: AdminPassword123!
-- (Generated using password_hash('AdminPassword123!', PASSWORD_BCRYPT))
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `status`) VALUES
(
    'admin',
    '$2y$10$wT0Xh1d6ZzGzFknO29Q02.y5nFwO1JbH7kO0kFomr6HwD88H7oU5e',
    'System Administrator',
    'it.admin@g2global.com',
    'Admin',
    'Active'
);