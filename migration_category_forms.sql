-- Run ONCE against an existing OT system database.
USE ot_system;

SET @has_category := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'category'
);
SET @sql := IF(@has_category = 0,
  'ALTER TABLE users ADD COLUMN category ENUM(\'below_ae\',\'ae_above\') NULL AFTER approval_stage',
  'SELECT "category already exists, skipping"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Existing mappings are only a starting point. HR can reassign each staff member
-- using the database until an admin screen is added. Engineer -> AE and above;
-- Operator/Leader -> Below AE.
UPDATE users SET category = CASE
  WHEN level = 'engineer' THEN 'ae_above'
  WHEN level IN ('operator','leader') THEN 'below_ae'
  ELSE category END
WHERE category IS NULL;

-- New registrations already create category=NULL and a one-time level_token.
-- Existing staff with category=NULL must be assigned by HR before login.
