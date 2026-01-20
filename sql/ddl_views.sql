-- Clean DDL file to create required views for the application
-- This file contains only CREATE OR REPLACE VIEW statements and is safe to execute
CREATE OR REPLACE VIEW vw_zone_summary AS
SELECT z.id AS zone_id,
       z.name AS zone_name,
       z.population,
       z.death_count,
       COALESCE(SUM(ie.cases),0) AS total_cases,
       COALESCE(SUM(CASE WHEN ie.created_at >= NOW() - INTERVAL 1 DAY THEN ie.cases ELSE 0 END),0) AS cases_24h
FROM zones z
LEFT JOIN infection_events ie ON ie.zone_id = z.id
GROUP BY z.id;

CREATE OR REPLACE VIEW vw_active_campaigns_demo AS
SELECT id, title, zone_id, state, capacity FROM medical_campaign WHERE state <> 'done';

-- Unified user requests view (used by the user panel's "My Requests")
CREATE OR REPLACE VIEW vw_user_requests AS
SELECT
    'ambulance' AS type,
    ar.id AS request_id,
    ar.user_id,
    ar.zone_id,
    COALESCE(z.name, '') AS zone_name,
    ar.status,
    ar.details,
    ar.created_at
FROM ambulance_requests ar
LEFT JOIN zones z ON z.id = ar.zone_id
UNION ALL
SELECT
    'icu' AS type,
    ir.id AS request_id,
    ir.user_id,
    ir.zone_id,
    COALESCE(z2.name, '') AS zone_name,
    ir.status,
    ir.details,
    ir.created_at
FROM icu_requests ir
LEFT JOIN zones z2 ON z2.id = ir.zone_id;

-- User registrations view (used by the user panel's "My Campaign Registrations")
CREATE OR REPLACE VIEW vw_user_registrations AS
SELECT
    r.id AS registration_id,
    r.user_id,
    r.campaign_id,
    COALESCE(c.title, '') AS campaign_name,
    c.created_at AS campaign_created,
    COALESCE(c.description, '') AS description,
    r.registered_at
FROM medical_campaign_registrations r
LEFT JOIN medical_campaign c ON c.id = r.campaign_id;
