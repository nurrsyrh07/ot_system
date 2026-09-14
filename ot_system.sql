-- ============================================================
-- JCY OVERTIME MANAGEMENT SYSTEM - SINGLE DATABASE FILE
-- ============================================================
--
-- This is the ONLY SQL file required for the current system.
--
-- It supports:
--   1. Fresh installation
--   2. Existing/older ot_system databases
--
-- IMPORTANT:
--   - This file does NOT DROP tables.
--   - Existing users, OT requests, approvals and email reset
--     records are preserved.
--   - It includes the current staff-category workflow.
--   - It includes company-email, declared personal-email and
--     no-email/manual-reset support.
--
-- STAFF EMAIL RULES
--   - Company email is the default: @jcyinternational.com
--   - A staff member may declare that they do not have a company
--     email and provide a personal email as an exception.
--   - HR must verify a declared personal email during registration
--     approval.
--   - A staff member may also have no email at all.
--
-- PASSWORD RECOVERY
--   - Registered email: secure one-time email reset token.
--   - No email: HR/supervisor must verify the staff badge in person
--     before issuing a one-time temporary password.
--   - Temporary passwords must be changed after login.
--   - Manual resets are recorded in password_reset_audit.
--
-- APPROVAL STAGES
--   1 = En Salim
--   2 = CK Teh
--
-- STAFF CATEGORIES
--   below_ae = Below Assistant Engineer
--   ae_above = Assistant Engineer and Above
--
-- SUNDAY OT always uses the Sunday MEMO regardless of category.
-- ============================================================

CREATE DATABASE IF NOT EXISTS ot_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ot_system;

-- ============================================================
-- 1. USERS TABLE
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_no                    VARCHAR(20)  NOT NULL UNIQUE,
  name                        VARCHAR(100) NOT NULL,
  department                  VARCHAR(100) NULL,
  email                       VARCHAR(150) NULL UNIQUE,
  password_hash               VARCHAR(255) NOT NULL,
  role                        ENUM('staff','approver') NOT NULL DEFAULT 'staff',
  approval_stage              TINYINT UNSIGNED NULL,
  category                    ENUM('below_ae','ae_above') NULL,
  level                       ENUM('operator','leader','engineer') NULL,
  level_token                 VARCHAR(64) NULL UNIQUE,
  level_set_at                TIMESTAMP NULL,
  email_type                  ENUM('company','personal','none') NOT NULL DEFAULT 'company',
  personal_email_confirmed_at TIMESTAMP NULL,
  must_change_password        TINYINT(1) NOT NULL DEFAULT 0,
  temporary_password_expires_at DATETIME NULL,
  is_active                   TINYINT(1) NOT NULL DEFAULT 1,
  created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT chk_approval_stage
    CHECK (
      (role = 'staff' AND approval_stage IS NULL)
      OR
      (role = 'approver' AND approval_stage IN (1,2))
    ),

  INDEX idx_role_stage (role, approval_stage),
  INDEX idx_active (is_active),
  INDEX idx_email_type (email_type)
) ENGINE=InnoDB;

-- ============================================================
-- 2. UPGRADE AN EXISTING USERS TABLE
-- ============================================================

SET @db := DATABASE();

-- department
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'department'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN department VARCHAR(100) NULL AFTER name',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- email: make it nullable so staff without email are supported.
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'email'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL UNIQUE AFTER department',
  'ALTER TABLE users MODIFY COLUMN email VARCHAR(150) NULL');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- old level column
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'level'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN level ENUM(\'operator\',\'leader\',\'engineer\') NULL AFTER approval_stage',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- category
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'category'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN category ENUM(\'below_ae\',\'ae_above\') NULL AFTER level',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- level_token
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'level_token'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN level_token VARCHAR(64) NULL UNIQUE AFTER category',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- level_set_at
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'level_set_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN level_set_at TIMESTAMP NULL AFTER level_token',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- email_type
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'email_type'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN email_type ENUM(\'company\',\'personal\',\'none\') NOT NULL DEFAULT \'company\' AFTER level_set_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- personal_email_confirmed_at
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'personal_email_confirmed_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN personal_email_confirmed_at TIMESTAMP NULL AFTER email_type',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- must_change_password
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'must_change_password'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER personal_email_confirmed_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- temporary_password_expires_at
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'temporary_password_expires_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE users ADD COLUMN temporary_password_expires_at DATETIME NULL AFTER must_change_password',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3. NORMALISE EXISTING EMAIL DATA
-- ============================================================
-- Existing users with an email are treated as company-email users
-- unless their address is already clearly a personal exception.
-- Blank strings are converted to NULL.

UPDATE users
SET email = NULL
WHERE TRIM(COALESCE(email, '')) = '';

UPDATE users
SET email_type = CASE
  WHEN email IS NULL THEN 'none'
  WHEN LOWER(email) LIKE '%@jcyinternational.com' THEN 'company'
  ELSE 'personal'
END
WHERE email_type = 'company';

-- Existing non-company emails are NOT automatically marked as verified.
-- HR must verify them through the application.
UPDATE users
SET personal_email_confirmed_at = NULL
WHERE email_type = 'personal';

-- ============================================================
-- 4. CONVERT OLD LEVEL VALUES TO NEW STAFF CATEGORIES
-- ============================================================

UPDATE users
SET category = CASE
  WHEN level = 'engineer' THEN 'ae_above'
  WHEN level IN ('operator','leader') THEN 'below_ae'
  ELSE category
END
WHERE category IS NULL;

-- ============================================================
-- 5. OT REQUESTS TABLE
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
-- 6. OT APPROVALS TABLE
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
-- 7. EMAIL PASSWORD RESET TOKENS
-- ============================================================
-- One-time tokens for staff who have a registered company/personal email.

CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL UNIQUE,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE,

  INDEX idx_password_resets_user_id (user_id),
  INDEX idx_password_resets_expires_at (expires_at)
) ENGINE=InnoDB;

-- ============================================================
-- 8. MANUAL PASSWORD RESET AUDIT
-- ============================================================
-- Records HR/approver-mediated resets for staff with no usable
-- recovery email. The application should insert a row only after
-- the staff member's badge has been verified in person.

CREATE TABLE IF NOT EXISTS password_reset_audit (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  approver_id    INT UNSIGNED NOT NULL,
  reset_method   ENUM('manual_badge_verified','email') NOT NULL,
  badge_verified TINYINT(1) NOT NULL DEFAULT 0,
  expires_at     DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_password_reset_audit_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE RESTRICT,

  CONSTRAINT fk_password_reset_audit_approver
    FOREIGN KEY (approver_id)
    REFERENCES users(id)
    ON DELETE RESTRICT,

  INDEX idx_reset_audit_user (user_id),
  INDEX idx_reset_audit_approver (approver_id),
  INDEX idx_reset_audit_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 9. CLEAN UP EXPIRED EMAIL RESET TOKENS
-- ============================================================
-- Safe cleanup; does not affect active or used history unnecessarily.

DELETE FROM password_resets
WHERE expires_at < NOW()
  AND used_at IS NULL;

-- ============================================================
-- 10. OPTIONAL STRUCTURE CHECKS
-- ============================================================

SELECT
  id,
  staff_no,
  name,
  email,
  email_type,
  personal_email_confirmed_at,
  role,
  approval_stage,
  category,
  level,
  must_change_password,
  temporary_password_expires_at,
  is_active
FROM users
ORDER BY id;

SELECT
  TABLE_NAME,
  TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'users',
    'ot_requests',
    'ot_approvals',
    'password_resets',
    'password_reset_audit'
  );

-- ============================================================
-- APPROVER ACCOUNTS
-- ============================================================
-- Do NOT create approver accounts through public registration.
--
-- En Salim:
--   role = approver
--   approval_stage = 1
--   is_active = 1
--
-- CK Teh:
--   role = approver
--   approval_stage = 2
--   is_active = 1
--
-- Create password_hash using password_hash(..., PASSWORD_DEFAULT)
-- or the application's secure password-hash helper.
-- Never store a plain-text password in password_hash.
-- ============================================================
