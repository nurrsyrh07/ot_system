-- JCY Overtime Management System
-- Staff can self-register.
-- Approver accounts are NOT self-registered; they must be created/configured
-- by the system administrator/IT directly in the database.
--
-- Approval stages:
--   1 = En Salim
--   2 = CK Teh
--
-- This version has NO admin role in the application.

CREATE DATABASE IF NOT EXISTS ot_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ot_system;

DROP TABLE IF EXISTS ot_approvals;
DROP TABLE IF EXISTS ot_requests;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_no        VARCHAR(20)  NOT NULL UNIQUE,
  name            VARCHAR(100) NOT NULL,
  email           VARCHAR(150) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('staff','approver') NOT NULL DEFAULT 'staff',
  approval_stage  TINYINT UNSIGNED NULL,
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

CREATE TABLE ot_requests (
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
  INDEX idx_requests_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE ot_approvals (
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

-- IMPORTANT:
-- Do not let public registration create approver accounts.
-- Create the two approver accounts using a secure internal/admin process.
--
-- Example only:
-- INSERT INTO users
--   (staff_no, name, email, password_hash, role, approval_stage, is_active)
-- VALUES
--   ('APP001', 'En Salim', 'REAL_EMAIL_HERE', 'PASSWORD_HASH_HERE', 'approver', 1, 1),
--   ('APP002', 'CK Teh', 'REAL_EMAIL_HERE', 'PASSWORD_HASH_HERE', 'approver', 2, 1);
--
-- Replace REAL_EMAIL_HERE and PASSWORD_HASH_HERE with the actual values.
-- Never store a plain-text password in password_hash.