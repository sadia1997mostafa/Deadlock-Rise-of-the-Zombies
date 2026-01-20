-- Aggregates: infections and campaign stats
-- Total infection events and total cases
SELECT COUNT(*) AS events_count, COALESCE(SUM(cases),0) AS total_cases FROM infection_events;

-- Infections per zone
SELECT zone_id, SUM(cases) AS total_cases FROM infection_events GROUP BY zone_id ORDER BY total_cases DESC LIMIT 20;

-- Zones with more than X cases in last 24h
SELECT zone_id, SUM(cases) AS cases_24h FROM infection_events WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY zone_id HAVING SUM(cases) > 10;

-- Campaign registrations per campaign
SELECT campaign_id, COUNT(*) AS registrations FROM medical_campaign_registrations GROUP BY campaign_id HAVING COUNT(*) > 0;
