-- Subqueries in virus_outbreak
-- Scalar subquery: total cases
SELECT id, name, (SELECT COALESCE(SUM(cases),0) FROM infection_events ie WHERE ie.zone_id = s.zone_id) AS zone_cases
FROM `safe` s LIMIT 50;

-- Correlated subquery: find safe people in zones with more than 10 cases in last 24h
SELECT * FROM `safe` WHERE zone_id IN (
  SELECT zone_id FROM infection_events WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY zone_id HAVING SUM(cases) > 10
);

-- Subquery in FROM: campaign registrations with campaign info
SELECT c.id, c.title, cr.reg_count
FROM medical_campaign c
JOIN (
  SELECT campaign_id, COUNT(*) AS reg_count FROM medical_campaign_registrations GROUP BY campaign_id
) cr ON cr.campaign_id = c.id;
