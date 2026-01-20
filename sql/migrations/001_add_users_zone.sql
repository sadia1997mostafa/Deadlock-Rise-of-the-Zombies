-- Migration: add zone_id to users table (safe fallback for deployments)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `zone_id` INT NULL DEFAULT NULL;

-- Optionally add foreign key (will fail if zones table missing or names differ)
ALTER TABLE `users`
  ADD CONSTRAINT IF NOT EXISTS fk_users_zone FOREIGN KEY (`zone_id`) REFERENCES `zones`(id) ON DELETE SET NULL;

-- Note: Run this migration on the target database (mysql CLI or via php script).
SELECT 'migrations/001_add_users_zone.sql prepared' AS msg;
