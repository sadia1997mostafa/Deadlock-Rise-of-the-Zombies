-- Window function examples (MySQL 8+)
-- Rolling sum of cases per zone (partitioned by zone, ordered by time)
SELECT id, zone_id, cases, created_at,
    SUM(cases) OVER (PARTITION BY zone_id ORDER BY created_at ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS cumulative_cases
FROM infection_events
ORDER BY zone_id, created_at
LIMIT 200;

-- Row number per zone for latest events
SELECT id, zone_id, cases, created_at,
    ROW_NUMBER() OVER (PARTITION BY zone_id ORDER BY created_at DESC) AS rn
FROM infection_events
LIMIT 200;
