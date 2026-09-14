-- ============================================================
-- JCY Overtime Management System - Complete Database Setup
-- ============================================================
--
-- This is the ONLY SQL file needed for the current OT system.
--
-- It supports BOTH:
--   1. A fresh installation of ot_system
--   2. An existing older ot_system database
--
-- IMPORTANT:
--   - This file does NOT DROP existing tables.
--   - Existing OT requests/users are preserved.
--   - Existing old level values are mapped to the new categories.
--
-- New staff categories:
--   below_ae = Below Assistant Engineer
--   ae_above = Assistant Engineer and Above
--
-- Sunday OT always uses the Sunday MEMO regardless of category.
--
-- Approval stages:
--   1 = En Salim
--   2 = CK Teh
--
-- Staff can register themselves, but they cannot log in until HR
-- confirms their category.
--
-- Run this file in phpMyAdmin or MySQL/MariaDB.
-- ============================================================

CREATE DATABASE IF NOT EXISTS ot_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ot_system;

-- ============================================================
-- 1. USERS TABLE
-- ============================================================
-- Create the current table if this is a fresh installation.

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_no        VARCHAR(20)  NOT NULL UNIQUE,
  name            VARCHAR(100) NOT NULL,
  department      VARCHAR(100) NULL,
  email           VARCHAR(150) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('staff','approver') NOT NULL DEFAULT 'staff',
  approval_stage  TINYINT UNSIGNED NULL,
  category        ENUM('below_ae','ae_above') NULL,
  level           ENUM('operator','leader','engineer') NULL,
  level_token     VARCHAR(64) NULL UNIQUE,
  level_set_at    TIMESTAMP NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT chk_approval_stage
    CHECK (
      (role = 'staff' AND approval_stage IS NULL)
      OR
      (role = 'approver' AND approval_stage IN (1,2))
    ),

  INDEX idx_role_stage (role, approval_stage),
  INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ============================================================
-- 2. UPDATE OLD USERS TABLES IF THEY ALREADY EXIST
-- ============================================================
-- These checks make the same SQL file usable with an older database.

SET @db := DATABASE();

-- department
SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'department'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN department VARCHAR(100) NULL AFTER name',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- old level column (kept temporarily for compatibility/migration)
SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'level'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN level ENUM(\'operator\',\'leader\',\'engineer\') NULL AFTER approval_stage',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- category
SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'category'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN category ENUM(\'below_ae\',\'ae_above\') NULL AFTER level',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- level_token
SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'level_token'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN level_token VARCHAR(64) NULL UNIQUE AFTER category',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- level_set_at
SET @exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'level_set_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN level_set_at TIMESTAMP NULL AFTER level_token',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3. CONVERT OLD LEVEL VALUES TO NEW STAFF CATEGORIES
-- ============================================================
-- Existing data:
--   operator -> Below Assistant Engineer
--   leader   -> Below Assistant Engineer
--   engineer -> Assistant Engineer and Above
--
-- New registrations normally have category=NULL until HR confirms it.

UPDATE users
SET category = CASE
  WHEN level = 'engineer' THEN 'ae_above'
  WHEN level IN ('operator','leader') THEN 'below_ae'
  ELSE category
END
WHERE category IS NULL;

-- ============================================================
-- 4. OT REQUESTS TABLE
-- ============================================================

CREATE TABLE IF NOT EXISTS ot_requests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id      INT UNSIGNED NOT NULL,
  ot_date       DATE NOT NULL,
  start_time    TIME NOT NULL,
  end_time      TIME NOT NULL,
  total_hours   DECIMAL(5,2) NOT NULL,
  reason        TEXT NOT NULL,
  status        ENUM(
                  'pending_stage1',
                  'pending_stage2',
                  'approved',
                  'rejected',
                  'cancelled'
                ) NOT NULL DEFAULT 'pending_stage1',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_ot_requests_staff
    FOREIGN KEY (staff_id)
    REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  INDEX idx_requests_status (status),
  INDEX idx_requests_staff (staff_id),
  INDEX idx_requests_created (created_at),
  INDEX idx_requests_date (ot_date)
) ENGINE=InnoDB;

-- ============================================================
-- 5. OT APPROVALS TABLE
-- ============================================================

CREATE TABLE IF NOT EXISTS ot_approvals (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id   INT UNSIGNED NOT NULL,
  approver_id  INT UNSIGNED NOT NULL,
  stage        TINYINT UNSIGNED NOT NULL,
  decision     ENUM('approved','rejected') NOT NULL,
  comment      TEXT NULL,
  acted_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_ot_approvals_request
    FOREIGN KEY (request_id)
    REFERENCES ot_requests(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_ot_approvals_approver
    FOREIGN KEY (approver_id)
    REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  CONSTRAINT chk_approval_stage_value
    CHECK (stage IN (1,2)),

  INDEX idx_approvals_request (request_id),
  INDEX idx_approvals_approver (approver_id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. OPTIONAL: CHECK THE RESULT
-- ============================================================
-- These SELECTs simply show the final structure/data after setup.

SELECT
  id,
  staff_no,
  name,
  role,
  approval_stage,
  category,
  level,
  level_token,
  level_set_at,
  is_active
FROM users
ORDER BY id;

SELECT
  TABLE_NAME,
  TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users','ot_requests','ot_approvals');

-- ============================================================
-- APPROVER ACCOUNTS
-- ============================================================
-- Do NOT create approver accounts through public registration.
--
-- En Salim should have:
--   role = approver
--   approval_stage = 1
--   is_active = 1
--
-- CK Teh should have:
--   role = approver
--   approval_stage = 2
--   is_active = 1
--
-- Create their password_hash using the application's make_hash.php
-- or another secure password_hash(PASSWORD_BCRYPT) process.
-- Never put a plain-text password into password_hash.
-- ============================================================
