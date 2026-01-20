
-- CREATE INDEX idx_cr_campaign_id ON medical_campaign_registrations(campaign_id);
-- Optional uniqueness (only if you guarantee one safe row per user):
-- CREATE UNIQUE INDEX uq_safe_user_id ON safe(user_id);

-- Foreign keys (add only if no orphan rows exist):
-- ALTER TABLE users ADD CONSTRAINT fk_users_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL;
-- ALTER TABLE safe ADD CONSTRAINT fk_safe_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL;
-- ALTER TABLE safe ADD CONSTRAINT fk_safe_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

SELECT 'migrations/002_add_indexes_and_fks.sql prepared' AS msg;
