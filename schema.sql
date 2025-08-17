-- Database schema for the Kartonowy Napełniacz application
-- This schema normalizes the data previously stored in localStorage
-- and expands it to support new features.

-- Users table to store login information and basic user data
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uid` VARCHAR(20) NOT NULL UNIQUE COMMENT 'User login identifier, e.g., SoG1917',
  `name` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Hashed password for security',
  `avatar_url` VARCHAR(2048) NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'Użytkownik',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User-specific settings for UI personalization
CREATE TABLE IF NOT EXISTS `user_settings` (
  `user_id` INT NOT NULL PRIMARY KEY,
  `theme` VARCHAR(20) DEFAULT 'dark',
  `language` VARCHAR(5) DEFAULT 'pl',
  `machine_order` JSON COMMENT 'Stores an array of machine codes defining the display order',
  `panel_order` JSON COMMENT 'Stores an array of panel IDs for the main content area',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Machine-specific configuration and state
CREATE TABLE IF NOT EXISTS `machines` (
  `code` VARCHAR(20) NOT NULL PRIMARY KEY COMMENT 'Unique machine identifier, e.g., MA820061',
  `default_duration_min` INT UNSIGNED,
  `color` VARCHAR(10) DEFAULT '#94a3b8' COMMENT 'Color of the status dot in hex format',
  `completion_timestamp` BIGINT UNSIGNED COMMENT 'Unix timestamp (milliseconds) for the current order completion',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order information linked to machines
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `machine_code` VARCHAR(20) NOT NULL,
  `auftrag_number` VARCHAR(50) NOT NULL,
  `model` VARCHAR(100),
  `period` VARCHAR(255) COMMENT 'Storing period as a string as in the original app',
  `next_auftrag` VARCHAR(255),
  `is_current` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_machine_code_current` (`machine_code`, `is_current`),
  FOREIGN KEY (`machine_code`) REFERENCES `machines`(`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat messages with support for threading
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `parent_id` INT NULL COMMENT 'For threaded replies',
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "Future Plans" section items
CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `text` TEXT NOT NULL,
  `status` VARCHAR(20) DEFAULT 'new',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
