-- Run this ONCE against an existing database that was created before the
-- department/level columns existed. Safe to run even if some columns
-- already exist — each ALTER checks first.
--
-- Usage:
--   mysql -u root -p ot_system < migration_add_level_department.sql

USE ot_system;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'department'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN department VARCHAR(100) NULL AFTER name',
  'SELECT "department already exists, skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'level'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN level ENUM(\'operator\',\'leader\',\'engineer\') NULL AFTER approval_stage',
  'SELECT "level already exists, skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'level_token'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN level_token VARCHAR(64) NULL UNIQUE AFTER level',
  'SELECT "level_token already exists, skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'level_set_at'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN level_set_at TIMESTAMP NULL AFTER level_token',
  'SELECT "level_set_at already exists, skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- NOTE: if your existing `users.email` column is still nullable, staff
-- rows created before that was tightened may have NULL emails. Fill those
-- in manually before making the column NOT NULL, e.g.:
--   SELECT id, staff_no, name FROM users WHERE email IS NULL;
-- then:
--   ALTER TABLE users MODIFY email VARCHAR(150) NOT NULL UNIQUE;
