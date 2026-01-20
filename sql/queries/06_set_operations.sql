-- Set operations: example combining lists of users involved in campaigns or requests
-- Users who registered for campaigns or made ambulance requests
SELECT user_id FROM medical_campaign_registrations
UNION
SELECT user_id FROM ambulance_requests;

-- UNION ALL (allows duplicates)
SELECT user_id FROM medical_campaign_registrations
UNION ALL
SELECT user_id FROM ambulance_requests;

-- INTERSECT: users who both registered and attended (if supported)
SELECT user_id FROM medical_campaign_registrations WHERE status = 'attended'
INTERSECT
SELECT user_id FROM medical_campaign_registrations WHERE status = 'registered';

-- EXCEPT: users who registered but did not attend
SELECT user_id FROM medical_campaign_registrations WHERE status = 'registered'
EXCEPT
SELECT user_id FROM medical_campaign_registrations WHERE status = 'attended';
