-- ORDER BY examples for virus_outbreak
-- Order zones by death_count ascending/descending
SELECT * FROM zones ORDER BY death_count ASC;
SELECT * FROM zones ORDER BY death_count DESC;

-- Order medical campaigns by state and capacity
SELECT * FROM medical_campaign ORDER BY FIELD(state,'in_progress','todo','done'), capacity DESC;

-- Latest infection events
SELECT * FROM infection_events ORDER BY created_at DESC LIMIT 50;
