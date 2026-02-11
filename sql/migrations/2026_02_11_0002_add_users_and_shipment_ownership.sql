-- MySQL 8.0+
-- Adds authentication tables and shipment ownership.

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  avatar_url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE shipments
  ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER id;

-- Ensure index exists for ownership lookups.
SET @idx_user_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'shipments'
    AND index_name = 'idx_shipments_user_id'
);
SET @sql_idx_user := IF(@idx_user_exists = 0, 'ALTER TABLE shipments ADD INDEX idx_shipments_user_id (user_id)', 'SELECT 1');
PREPARE stmt_idx_user FROM @sql_idx_user;
EXECUTE stmt_idx_user;
DEALLOCATE PREPARE stmt_idx_user;

-- Replace global tracking uniqueness with per-user uniqueness.
SET @idx_old_unique_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'shipments'
    AND index_name = 'uniq_shipments_tracking_number'
);
SET @sql_drop_old_unique := IF(@idx_old_unique_exists > 0, 'ALTER TABLE shipments DROP INDEX uniq_shipments_tracking_number', 'SELECT 1');
PREPARE stmt_drop_old_unique FROM @sql_drop_old_unique;
EXECUTE stmt_drop_old_unique;
DEALLOCATE PREPARE stmt_drop_old_unique;

SET @idx_new_unique_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'shipments'
    AND index_name = 'uniq_shipments_user_tracking'
);
SET @sql_add_new_unique := IF(@idx_new_unique_exists = 0, 'ALTER TABLE shipments ADD UNIQUE KEY uniq_shipments_user_tracking (user_id, tracking_number)', 'SELECT 1');
PREPARE stmt_add_new_unique FROM @sql_add_new_unique;
EXECUTE stmt_add_new_unique;
DEALLOCATE PREPARE stmt_add_new_unique;

-- Optional but recommended (add manually if not present):
-- ALTER TABLE shipments
--   ADD CONSTRAINT fk_shipments_user_id
--   FOREIGN KEY (user_id) REFERENCES users(id)
--   ON DELETE CASCADE;
