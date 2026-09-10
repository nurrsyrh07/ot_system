-- OT Application System — schema
-- Run this once against a fresh MySQL server, e.g.:
--   mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS ot_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ot_system;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
-- approval_stage is only meaningful for role = 'approver':
--   1 = En Salim (first approval)
--   2 = CK Teh   (final approval)
-- NULL for staff / admin accounts.
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_no        VARCHAR(20)  NOT NULL UNIQUE,
  name            VARCHAR(100) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('staff','approver','admin') NOT NULL DEFAULT 'staff',
  approval_stage  TINYINT UNSIGNED NULL,          -- 1, 2, or NULL
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_approval_stage CHECK (approval_stage IS NULL OR approval_stage IN (1,2))
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ot_requests
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ot_requests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id      INT UNSIGNED NOT NULL,
  ot_date       DATE NOT NULL,
  start_time    TIME NOT NULL,
  end_time      TIME NOT NULL,
  total_hours   DECIMAL(4,2) NOT NULL,
  reason        TEXT NOT NULL,
  status        ENUM('pending_stage1','pending_stage2','approved','rejected')
                NOT NULL DEFAULT 'pending_stage1',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ot_requests_staff FOREIGN KEY (staff_id) REFERENCES users(id),
  INDEX idx_status (status),
  INDEX idx_staff (staff_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ot_approvals  (one row per decision, so the full chain is auditable)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ot_approvals (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id   INT UNSIGNED NOT NULL,
  approver_id  INT UNSIGNED NOT NULL,
  stage        TINYINT UNSIGNED NOT NULL,          -- 1 or 2
  decision     ENUM('approved','rejected') NOT NULL,
  comment      TEXT NULL,
  acted_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ot_approvals_request  FOREIGN KEY (request_id)  REFERENCES ot_requests(id),
  CONSTRAINT fk_ot_approvals_approver FOREIGN KEY (approver_id) REFERENCES users(id),
  INDEX idx_request (request_id)
) ENGINE=InnoDB;
