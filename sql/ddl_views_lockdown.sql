-- sql/ddl_views_lockdown.sql
-- View that computes 7-day case counts and flags zones for lockdown when
-- cases per 1000 population in last 7 days exceed a threshold.

-- You can change the numeric threshold below (currently 5 cases per 1000 people).
CREATE OR REPLACE VIEW vw_zone_lockdown AS
SELECT
  z.id AS zone_id,
  z.name AS zone_name,
  z.population,
  COALESCE(SUM(ie.cases), 0) AS cases_7d,
  -- cases per 1000 people over the last 7 days
  (COALESCE(SUM(ie.cases), 0) / NULLIF(z.population, 0)) * 1000 AS cases_7d_per_1000,
  -- lockdown flag: 'yes' when threshold crossed, otherwise 'no'
  CASE
    WHEN (COALESCE(SUM(ie.cases), 0) / NULLIF(z.population, 0)) * 1000 >= 5 THEN 'yes'
    ELSE 'no'
  END AS lockdown
FROM zones z
LEFT JOIN infection_events ie
  ON ie.zone_id = z.id
  AND ie.created_at >= NOW() - INTERVAL 7 DAY
GROUP BY z.id, z.name, z.population;

-- Example usage:
-- SELECT * FROM vw_zone_lockdown WHERE lockdown = 'yes';
-- SELECT zone_name, cases_7d_per_1000 FROM vw_zone_lockdown ORDER BY cases_7d_per_1000 DESC LIMIT 20;
