SELECT id, name, email FROM users LIMIT 50;

-- DISTINCT: zones that have at least one safe person
SELECT DISTINCT zone_id FROM `safe` WHERE zone_id IS NOT NULL;

-- ALL (explicit) - MySQL treats ALL as default
SELECT ALL zone_id FROM `safe` WHERE outbreak_status = 'infected' LIMIT 50;

-- Column alias and expression
SELECT name, morale, stamina, (morale + stamina) AS total_status FROM `safe` LIMIT 50;

-- Pattern matching: find campaigns and medical equipments by name
SELECT * FROM medical_campaign WHERE title LIKE '%vaccine%' OR description LIKE '%vaccine%';
SELECT * FROM medical_equipments WHERE name LIKE '%covid%' OR description LIKE '%covid%';
