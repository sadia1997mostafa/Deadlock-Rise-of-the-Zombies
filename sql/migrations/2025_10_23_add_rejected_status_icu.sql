-- Migration: add 'rejected' status to icu_requests
ALTER TABLE icu_requests
  MODIFY COLUMN status ENUM('requested','confirmed','admitted','rejected','rejected') NOT NULL DEFAULT 'requested';

-- Admin queries
-- SELECT * FROM icu_requests WHERE status = 'requested' ORDER BY created_at DESC;
-- SELECT COUNT(*) AS pending_icu FROM icu_requests WHERE status = 'requested';
