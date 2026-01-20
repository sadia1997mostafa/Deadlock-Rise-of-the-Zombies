-- Range searches and set membership on outbreaks and dates
-- Find infection events in the last 7 days with more than 5 cases
SELECT * FROM infection_events WHERE created_at BETWEEN NOW() - INTERVAL 7 DAY AND NOW() AND cases > 5;

-- Find zones with population in a certain range
SELECT * FROM zones WHERE population BETWEEN 1000 AND 100000;

-- Set membership: find safe people in specific zones
SELECT * FROM `safe` WHERE zone_id IN (1,2,3);
SELECT * FROM `safe` WHERE zone_id NOT IN (1,2,3);
