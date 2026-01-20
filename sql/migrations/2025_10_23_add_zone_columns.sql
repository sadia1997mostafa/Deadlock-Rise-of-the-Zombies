-- Migration: add danger_score and active columns to zones
ALTER TABLE zones
  ADD COLUMN IF NOT EXISTS danger_score DECIMAL(5,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1;

-- Quick checks:
-- SELECT id, name, danger_score, active FROM zones LIMIT 10;
