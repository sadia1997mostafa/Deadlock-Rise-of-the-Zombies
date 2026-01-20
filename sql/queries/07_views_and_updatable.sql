-- Views examples
-- Views for virus_outbreak
CREATE OR REPLACE VIEW vw_zone_summary AS
SELECT z.id AS zone_id, z.name AS zone_name, z.population, z.death_count,
	COALESCE(SUM(ie.cases),0) AS total_cases,
	COALESCE(SUM(CASE WHEN ie.created_at >= NOW() - INTERVAL 1 DAY THEN ie.cases ELSE 0 END),0) AS cases_24h
FROM zones z LEFT JOIN infection_events ie ON ie.zone_id = z.id
GROUP BY z.id;

SELECT * FROM vw_zone_summary;

-- Updatable view example for active campaigns
CREATE OR REPLACE VIEW vw_active_campaigns_demo AS
SELECT id, title, zone_id, state, capacity FROM medical_campaign WHERE state <> 'done';

SELECT * FROM vw_active_campaigns_demo;
