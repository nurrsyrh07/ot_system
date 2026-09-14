-- ============================================================
-- JCY Overtime Management System - Complete Database Setup
-- ============================================================
-- Single SQL file for the current OT system codebase.
--
-- Includes:
--   * Staff / approver / admin accounts
--   * Staff self-registration
--   * Company / personal / no-email registration
--   * HR category confirmation
--   * Two-stage OT approval
--   * Email password reset
--   * Manual HR/supervisor password reset
--   * Temporary-password expiry / forced change
--   * Password-reset audit
--
-- IMPORTANT:
--   - This file creates the schema only; it does not contain live
--     usernames, password hashes, reset tokens, or test OT requests.
--   - Back up an existing database before applying schema changes.
-- ============================================================

CREATE DATABASE IF NOT EXISTS ot_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ot_system;

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ============================================================
-- 1. USERS
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
  id                              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_no                        VARCHAR(20) NOT NULL,
  name                            VARCHAR(100) NOT NULL,
  department                      VARCHAR(100) DEFAULT NULL,

  email                           VARCHAR(150) DEFAULT NULL,
  email_type                      ENUM('company','personal') DEFAULT NULL,

  password_hash                   VARCHAR(255) NOT NULL,

  role                            ENUM('staff','approver','admin') NOT NULL DEFAULT 'staff',
  approval_stage                  TINYINT UNSIGNED DEFAULT NULL,

  category                        ENUM('below_ae','ae_above') DEFAULT NULL,

  -- Retained for compatibility with older versions of the system.
  level                           ENUM('operator','leader','engineer') DEFAULT NULL,

  -- Token used by declare_level.php for HR category confirmation.
  level_token                     VARCHAR(64) DEFAULT NULL,
  level_set_at                    TIMESTAMP NULL DEFAULT NULL,

  -- Set when HR confirms a declared personal email.
  personal_email_confirmed_at    TIMESTAMP NULL DEFAULT NULL,

  -- Manual temporary-password flow.
  must_change_password            TINYINT(1) NOT NULL DEFAULT 0,
  temporary_password_expires_at   DATETIME DEFAULT NULL,

  is_active                       TINYINT(1) NOT NULL DEFAULT 1,
  created_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_users_staff_no (staff_no),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_level_token (level_token),
  KEY idx_users_role_stage (role, approval_stage),
  KEY idx_users_active (is_active),
  KEY idx_users_email_type (email_type)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. OT REQUESTS
-- ============================================================

CREATE TABLE IF NOT EXISTS ot_requests (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
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

  PRIMARY KEY (id),
  KEY idx_requests_status (status),
  KEY idx_requests_staff (staff_id),
  KEY idx_requests_created (created_at),
  KEY idx_requests_date (ot_date),

  CONSTRAINT fk_ot_requests_staff
    FOREIGN KEY (staff_id)
    REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. OT APPROVALS
-- ============================================================

CREATE TABLE IF NOT EXISTS ot_approvals (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id   INT UNSIGNED NOT NULL,
  approver_id  INT UNSIGNED NOT NULL,
  stage        TINYINT UNSIGNED NOT NULL,
  decision     ENUM('approved','rejected') NOT NULL,
  comment      TEXT DEFAULT NULL,
  acted_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_approvals_request (request_id),
  KEY idx_approvals_approver (approver_id),

  CONSTRAINT fk_ot_approvals_request
    FOREIGN KEY (request_id)
    REFERENCES ot_requests(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_ot_approvals_approver
    FOREIGN KEY (approver_id)
    REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. EMAIL PASSWORD RESETS
-- ============================================================

CREATE TABLE IF NOT EXISTS password_resets (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  expires_at   DATETIME NOT NULL,
  used_at      DATETIME DEFAULT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token_hash (token_hash),
  KEY idx_password_resets_user_id (user_id),
  KEY idx_password_resets_expires_at (expires_at),

  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. PASSWORD RESET AUDIT
-- ============================================================
-- The current PHP manual_reset_password.php inserts only:
--   user_id, approver_id, method, created_at
--
-- Therefore the remaining fields have safe defaults and can be used
-- by future versions without breaking the current code.

CREATE TABLE IF NOT EXISTS password_reset_audit (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NOT NULL,
  approver_id      INT UNSIGNED DEFAULT NULL,
  method           VARCHAR(30) NOT NULL DEFAULT 'manual',
  reset_method     ENUM('manual_badge_verified','email') NOT NULL DEFAULT 'manual_badge_verified',
  badge_verified   TINYINT(1) NOT NULL DEFAULT 0,
  expires_at       DATETIME DEFAULT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_reset_audit_user (user_id),
  KEY idx_reset_audit_approver (approver_id),
  KEY idx_reset_audit_created (created_at),

  CONSTRAINT fk_password_reset_audit_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

  CONSTRAINT fk_password_reset_audit_approver
    FOREIGN KEY (approver_id)
    REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. COMPATIBILITY / MIGRATION NOTES FOR OLDER DATABASES
-- ============================================================
--
-- For an existing database, the following statements should be run
-- only when the corresponding column/table is missing. They are kept
-- here as reference so the single SQL file documents the full schema.
--
-- USERS additions used by the current PHP:
--   email                           nullable
--   email_type                      nullable
--   personal_email_confirmed_at    nullable
--   must_change_password            default 0
--   temporary_password_expires_at   nullable
--
-- PASSWORD RESET additions:
--   password_resets
--   password_reset_audit
--
-- ============================================================

-- Make sure legacy level values map to the current categories when
-- existing data is migrated manually:
--
-- UPDATE users
-- SET category = CASE
--   WHEN level = 'engineer' THEN 'ae_above'
--   WHEN level IN ('operator','leader') THEN 'below_ae'
--   ELSE category
-- END
-- WHERE category IS NULL;

-- ============================================================
-- 7. QUICK STRUCTURE CHECK
-- ============================================================

SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'users',
    'ot_requests',
    'ot_approvals',
    'password_resets',
    'password_reset_audit'
  )
ORDER BY TABLE_NAME;

SELECT
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
ORDER BY ORDINAL_POSITION;

SELECT
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'password_reset_audit'
ORDER BY ORDINAL_POSITION;

-- ============================================================
-- END
-- ============================================================
