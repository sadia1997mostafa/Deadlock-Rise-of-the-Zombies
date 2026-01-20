-- Join examples for virus_outbreak
-- Inner join: safe people with their zone name
SELECT s.name AS person_name, s.outbreak_status, z.name AS zone_name
FROM `safe` s JOIN zones z ON s.zone_id = z.id;

-- Left join: all zones with potential recent infection events
SELECT z.id, z.name, ie.cases, ie.created_at
FROM zones z LEFT JOIN infection_events ie ON ie.zone_id = z.id AND ie.created_at >= NOW() - INTERVAL 7 DAY
ORDER BY z.name;

-- Join campaigns with registration counts
SELECT c.id, c.title, COALESCE(cr.reg_count,0) AS registrations
FROM medical_campaign c LEFT JOIN (
  SELECT campaign_id, COUNT(*) AS reg_count FROM medical_campaign_registrations GROUP BY campaign_id
) cr ON cr.campaign_id = c.id;

-- Self-join example: find pairs of people in the same zone
SELECT a.id AS person_a, a.name AS name_a, b.id AS person_b, b.name AS name_b
FROM `safe` a JOIN `safe` b ON a.zone_id = b.zone_id AND a.id < b.id;
