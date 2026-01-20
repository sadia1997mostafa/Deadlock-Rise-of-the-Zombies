-- sql/ddl_vw_campaign_summary.sql
-- View: campaign summary with registration counts and availability
CREATE OR REPLACE VIEW vw_campaign_summary AS
SELECT
  mc.id AS campaign_id,
  mc.title,
  mc.description,
  mc.zone_id,
  z.name AS zone_name,
  mc.created_at,
  mc.capacity,
  COALESCE(reg.registered_count, 0) AS registrations,
  GREATEST(COALESCE(mc.capacity,0) - COALESCE(reg.registered_count,0), 0) AS slots_left,
  CASE
    WHEN mc.capacity IS NULL THEN 'unknown'
    WHEN COALESCE(reg.registered_count,0) >= mc.capacity THEN 'full'
    ELSE 'open'
  END AS availability,
  -- flag if any equipment in global catalog has stock > 0
  (EXISTS(SELECT 1 FROM medical_equipments me WHERE me.stock > 0)) AS any_equipment_available
FROM medical_campaign mc
LEFT JOIN zones z ON z.id = mc.zone_id
LEFT JOIN (
  SELECT campaign_id, COUNT(*) AS registered_count
  FROM medical_campaign_registrations
  GROUP BY campaign_id
) reg ON reg.campaign_id = mc.id;

-- Example usage:
-- SELECT * FROM vw_campaign_summary WHERE availability = 'open' ORDER BY slots_left DESC;
