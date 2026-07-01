-- ConnectDRRM schema bootstrap for database: connecdrrm
-- Creates core tables from the provided screenshots and adds a few extra columns
-- referenced by the current codebase (e.g., users.municipalityID, requests.originalToDRRMO).
--
-- Safe to re-run: uses IF NOT EXISTS / conditional ALTER where possible.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Make sure we are using the requested database.
CREATE DATABASE IF NOT EXISTS `connecdrrm` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `connecdrrm`;

-- -------------------------
-- Table: drrmo
-- -------------------------
CREATE TABLE IF NOT EXISTS `drrmo` (
  `drrmoID` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(255) NULL,
  `location` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) NULL,
  `contactInfo` VARCHAR(255) NULL,
  `contact_number` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `logo_url` VARCHAR(255) NULL,
  `drrmo_head` VARCHAR(255) NULL,
  `drrmo_head_title` VARCHAR(255) NULL,
  `operator_name` VARCHAR(255) NULL,
  `operator_title` VARCHAR(255) NULL,
  `operator_signature` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`drrmoID`),
  UNIQUE KEY `uniq_drrmo_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- Table: users
-- -------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `userID` INT NOT NULL AUTO_INCREMENT,
  `drrmoID` INT NULL,
  -- Some endpoints still reference this legacy name; keep it to prevent runtime errors.
  `municipalityID` INT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `fullName` VARCHAR(255) NULL,
  `position` VARCHAR(255) NULL,
  `contactNumber` VARCHAR(255) NULL,
  `signature` TEXT NULL,
  `profileCompleted` TINYINT(1) NULL DEFAULT 0,
  `role` ENUM('drrmo_staff','approving_authority','emergency_coordinator','admin') NULL,
  `status` VARCHAR(50) NULL DEFAULT 'active',
  `createdAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`userID`),
  UNIQUE KEY `uniq_users_email` (`email`),
  KEY `idx_users_drrmoid` (`drrmoID`),
  KEY `idx_users_municipalityid` (`municipalityID`),
  CONSTRAINT `fk_users_drrmo` FOREIGN KEY (`drrmoID`) REFERENCES `drrmo` (`drrmoID`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- Table: resources
-- -------------------------
CREATE TABLE IF NOT EXISTS `resources` (
  `resourceID` INT NOT NULL AUTO_INCREMENT,
  `drrmoID` INT NULL,
  `resourceName` VARCHAR(255) NOT NULL,
  `category` VARCHAR(255) NULL,
  `subcategory` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `unit` VARCHAR(100) NULL,
  `totalStock` INT NULL,
  `availableStock` INT NULL,
  `minimumStock` INT NULL,
  `storageLocation` VARCHAR(255) NULL,
  `createdAt` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lastUpdated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`resourceID`),
  KEY `idx_resources_drrmoid` (`drrmoID`),
  KEY `idx_resources_category` (`category`),
  CONSTRAINT `fk_resources_drrmo` FOREIGN KEY (`drrmoID`) REFERENCES `drrmo` (`drrmoID`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- Table: hazards
-- -------------------------
CREATE TABLE IF NOT EXISTS `hazards` (
  `hazardID` INT NOT NULL AUTO_INCREMENT,
  `drrmoID` INT NULL,
  `hazardType` VARCHAR(255) NOT NULL,
  `intensity` ENUM('Low','Medium','High','Critical') NULL,
  `location` VARCHAR(255) NOT NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `description` TEXT NULL,
  `affectedPopulation` INT NULL,
  `informationSource` VARCHAR(255) NULL,
  `contactInfo` VARCHAR(255) NULL,
  `reportedBy` INT NULL,
  `reportedAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('active','monitoring','resolved') NULL DEFAULT 'active',
  PRIMARY KEY (`hazardID`),
  KEY `idx_hazards_drrmoid` (`drrmoID`),
  KEY `idx_hazards_reportedat` (`reportedAt`),
  KEY `idx_hazards_reportedby` (`reportedBy`),
  KEY `idx_hazards_status` (`status`),
  CONSTRAINT `fk_hazards_drrmo` FOREIGN KEY (`drrmoID`) REFERENCES `drrmo` (`drrmoID`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_hazards_reportedby` FOREIGN KEY (`reportedBy`) REFERENCES `users` (`userID`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- Table: hazard_images (optional attachments)
-- -------------------------
CREATE TABLE IF NOT EXISTS `hazard_images` (
  `imageID` INT NOT NULL AUTO_INCREMENT,
  `hazardID` INT NOT NULL,
  `filePath` VARCHAR(500) NOT NULL,
  `uploadedAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`imageID`),
  KEY `idx_hazard_images_hazard` (`hazardID`),
  CONSTRAINT `fk_hazard_images_hazard` FOREIGN KEY (`hazardID`) REFERENCES `hazards` (`hazardID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- Table: requests
-- -------------------------
CREATE TABLE IF NOT EXISTS `requests` (
  `requestID` INT NOT NULL AUTO_INCREMENT,
  `requestGroupId` VARCHAR(50) NULL,
  `fromDRRMO` INT NULL,
  `toDRRMO` INT NULL,
  `originalToDRRMO` INT NULL,
  `resourceID` INT NULL,
  `quantity` INT NULL,
  `priority` ENUM('low','medium','high') NULL,
  `urgency` ENUM('low','medium','high') NULL,
  `notes` TEXT NULL,
  `purposeOfRequest` VARCHAR(255) NULL,

  `deliveryDate` DATETIME NULL,
  `deliveryLocation` TEXT NULL,
  `expectedDuration` VARCHAR(255) NULL,
  `returnDate` DATE NULL,
  `transportationMethod` VARCHAR(255) NULL,

  `contactPhone` VARCHAR(255) NULL,
  `contactEmail` VARCHAR(255) NULL,

  `requestorName` VARCHAR(255) NULL,
  `requestorTitle` VARCHAR(255) NULL,
  `requestorSignature` LONGTEXT NULL,

  `approvingAuthority` VARCHAR(255) NULL,
  `approverTitle` VARCHAR(255) NULL,
  `approverSignature` LONGTEXT NULL,

  `requestDate` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `responseDate` TIMESTAMP NULL DEFAULT NULL,
  `returnRequestedAt` DATETIME NULL DEFAULT NULL,
  `returnedAt` DATETIME NULL DEFAULT NULL,
  `returnedQty` INT NULL DEFAULT NULL,

  `status` ENUM(
    'pending',
    'pending_head_approval',
    'group_approved_pending',
    'group_rejected_pending',
    'approved',
    'rejected',
    'fulfilled',
    'return pending',
    'returned'
  ) NULL,

  PRIMARY KEY (`requestID`),
  KEY `idx_requests_group` (`requestGroupId`),
  KEY `idx_requests_from` (`fromDRRMO`),
  KEY `idx_requests_to` (`toDRRMO`),
  KEY `idx_requests_originalto` (`originalToDRRMO`),
  KEY `idx_requests_resource` (`resourceID`),
  KEY `idx_requests_requestdate` (`requestDate`),
  KEY `idx_requests_status` (`status`),
  CONSTRAINT `fk_requests_from` FOREIGN KEY (`fromDRRMO`) REFERENCES `drrmo` (`drrmoID`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_requests_to` FOREIGN KEY (`toDRRMO`) REFERENCES `drrmo` (`drrmoID`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_requests_originalto` FOREIGN KEY (`originalToDRRMO`) REFERENCES `drrmo` (`drrmoID`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_requests_resource` FOREIGN KEY (`resourceID`) REFERENCES `resources` (`resourceID`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- Table: notifications
-- -------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `notifID` INT NOT NULL AUTO_INCREMENT,
  `userID` INT NULL,
  `message` TEXT NULL,
  `href` VARCHAR(255) NULL,
  `priority` ENUM('low','normal','high') NULL DEFAULT 'normal',
  `isRead` TINYINT(1) NULL DEFAULT 0,
  `createdAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notifID`),
  KEY `idx_notifications_user` (`userID`),
  KEY `idx_notifications_createdat` (`createdAt`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- Table: reports
-- -------------------------
CREATE TABLE IF NOT EXISTS `reports` (
  `reportID` INT NOT NULL AUTO_INCREMENT,
  `drrmoID` INT NULL,
  `generatedBy` INT NULL,
  `title` VARCHAR(255) NULL,
  `reportType` ENUM('overview','resources','requests','hazards','analytics','other') NULL,
  `description` TEXT NULL,
  `reportData` LONGTEXT NULL,
  `filePath` VARCHAR(255) NULL,
  `isPublic` TINYINT(1) NULL DEFAULT 0,
  `generatedAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reportID`),
  KEY `idx_reports_drrmoid` (`drrmoID`),
  KEY `idx_reports_generatedby` (`generatedBy`),
  KEY `idx_reports_generatedat` (`generatedAt`),
  CONSTRAINT `fk_reports_drrmo` FOREIGN KEY (`drrmoID`) REFERENCES `drrmo` (`drrmoID`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_reports_generatedby` FOREIGN KEY (`generatedBy`) REFERENCES `users` (`userID`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------
-- Compatibility ALTERs (for existing DBs)
-- -------------------------
-- Ensure requests.originalToDRRMO exists for head-approval routing.
ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `originalToDRRMO` INT NULL AFTER `toDRRMO`;

-- Ensure requests.requestGroupId exists for grouped requests.
ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `requestGroupId` VARCHAR(50) NULL AFTER `requestID`;

-- Ensure requests.purposeOfRequest exists (present in the original table screenshots).
ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `purposeOfRequest` VARCHAR(255) NULL;

-- Ensure users.profileCompleted exists (used by profile flow).
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `profileCompleted` TINYINT(1) NULL DEFAULT 0;

-- Ensure requests.municipalityID exists (legacy column referenced in a few endpoints).
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `municipalityID` INT NULL AFTER `drrmoID`;

-- Ensure requests.head_approval_status exists to track if request was approved by the head or bypassed.
ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `head_approval_status` VARCHAR(50) DEFAULT NULL;

-- Ensure requests.head_approved_by exists to track who approved or bypassed the request.
ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `head_approved_by` VARCHAR(255) DEFAULT NULL;

-- Ensure resources.damagedStock exists to track damaged items.
ALTER TABLE `resources` ADD COLUMN IF NOT EXISTS `damagedStock` INT NULL DEFAULT 0 AFTER `availableStock`;

-- Ensure requests.damagedQty and damageAssessment exist for returns.
ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `damagedQty` INT NULL DEFAULT NULL AFTER `returnedQty`;
ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `damageAssessment` TEXT NULL AFTER `damagedQty`;

-- Ensure resources.plateNumber exists to track serial numbers / plate numbers.
ALTER TABLE `resources` ADD COLUMN IF NOT EXISTS `plateNumber` VARCHAR(255) NULL DEFAULT NULL AFTER `storageLocation`;

-- Table: resource_items
CREATE TABLE IF NOT EXISTS `resource_items` (
    `itemID` INT NOT NULL AUTO_INCREMENT,
    `resourceID` INT NOT NULL,
    `uniqueIdentifier` VARCHAR(255) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Available',
    `storageLocation` VARCHAR(255) NULL,
    `conditionNotes` TEXT NULL,
    `createdAt` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`itemID`),
    CONSTRAINT `fk_resource_items_resource` FOREIGN KEY (`resourceID`) REFERENCES `resources` (`resourceID`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: request_dispatched_items
CREATE TABLE IF NOT EXISTS `request_dispatched_items` (
    `requestID` INT NOT NULL,
    `itemID` INT NOT NULL,
    PRIMARY KEY (`requestID`, `itemID`),
    CONSTRAINT `fk_rdi_request` FOREIGN KEY (`requestID`) REFERENCES `requests` (`requestID`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_rdi_item` FOREIGN KEY (`itemID`) REFERENCES `resource_items` (`itemID`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

