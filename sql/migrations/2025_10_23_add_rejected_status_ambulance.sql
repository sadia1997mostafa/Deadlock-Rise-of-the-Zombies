-- Migration: add 'rejected' status to ambulance_requests
-- Run this manually using mysql or via a safe runner. Example:
-- mysql -u root -p virus_outbreak < 2025_10_23_add_rejected_status_ambulance.sql

ALTER TABLE ambulance_requests
  MODIFY COLUMN status ENUM('requested','assigned','completed','cancelled','rejected') NOT NULL DEFAULT 'requested';

-- Helpful admin queries
-- Pending ambulance requests for admin
-- SELECT * FROM ambulance_requests WHERE status = 'requested' ORDER BY created_at DESC;
-- Count pending
-- SELECT COUNT(*) AS pending_ambulance FROM ambulance_requests WHERE status = 'requested';
