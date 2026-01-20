-- Constraint examples for virus_outbreak
-- Example: ensure vaccine names are unique
ALTER TABLE medical_equipments ADD UNIQUE (name);

-- Example: change primary key on zones (will fail if child FKs reference it)
-- Attempting to change parent PK while children exist raises an error. Correct approach:
-- 1) Drop or ALTER child foreign keys
-- 2) Change parent primary key
-- 3) Recreate child foreign keys pointing to new parent PK

-- Example sequence (names of FK constraints must match your DB):
-- ALTER TABLE infection_events DROP FOREIGN KEY fk_ie_zone;
-- ALTER TABLE zones DROP PRIMARY KEY;
-- ALTER TABLE zones ADD PRIMARY KEY (name);
-- ALTER TABLE infection_events ADD CONSTRAINT fk_ie_zone FOREIGN KEY (zone_id) REFERENCES zones(name);

-- WARNING: Changing PK to a non-integer type is disruptive and not recommended on production data.
