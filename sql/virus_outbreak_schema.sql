-- Minimal schema for Virus Outbreak project
-- Essential tables and views only.

CREATE DATABASE IF NOT EXISTS virus_outbreak CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE virus_outbreak;

-- Users
CREATE TABLE IF NOT EXISTS users (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Regions and zones
CREATE TABLE IF NOT EXISTS regions (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(200) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS zones (
	id INT AUTO_INCREMENT PRIMARY KEY,
	region_id INT NULL,
	name VARCHAR(200) NOT NULL,
	population INT DEFAULT 0,
	death_count INT NOT NULL DEFAULT 0,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_zone_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- People (safe/infected)
CREATE TABLE IF NOT EXISTS `safe` (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NULL,
	name VARCHAR(120) NOT NULL,
	age INT NULL,
	gender ENUM('male','female','other') DEFAULT NULL,
	zone_id INT NULL,
	outbreak_status ENUM('safe','infected','critical','recovered','deceased') DEFAULT 'safe',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_safe_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL,
	CONSTRAINT fk_safe_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_safe_zone_status ON `safe`(zone_id, outbreak_status);

-- Infection events
CREATE TABLE IF NOT EXISTS infection_events (
	id INT AUTO_INCREMENT PRIMARY KEY,
	zone_id INT NOT NULL,
	reporter_id INT NULL,
	event_type ENUM('report','cluster','outbreak') NOT NULL,
	cases INT NOT NULL DEFAULT 1,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_ie_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Alerts
CREATE TABLE IF NOT EXISTS alerts (
	id INT AUTO_INCREMENT PRIMARY KEY,
	zone_id INT NULL,
	title VARCHAR(255) NOT NULL,
	status ENUM('open','acknowledged','closed') DEFAULT 'open',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_alert_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Campaigns and registrations
CREATE TABLE IF NOT EXISTS medical_campaign (
	id INT AUTO_INCREMENT PRIMARY KEY,
	title VARCHAR(200) NOT NULL,
	description TEXT NULL,
	creator_id INT NULL,
	zone_id INT NULL,
	state ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
	capacity INT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_campaign_creator FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL,
	CONSTRAINT fk_campaign_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medical_campaign_registrations (
	id INT AUTO_INCREMENT PRIMARY KEY,
	campaign_id INT NOT NULL,
	user_id INT NOT NULL,
	registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	status ENUM('registered','attended','cancelled') NOT NULL DEFAULT 'registered',
	CONSTRAINT fk_cr_campaign FOREIGN KEY (campaign_id) REFERENCES medical_campaign(id) ON DELETE CASCADE,
	CONSTRAINT fk_cr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	UNIQUE KEY uq_campaign_user (campaign_id, user_id)
) ENGINE=InnoDB;

-- Ambulance & ICU requests
CREATE TABLE IF NOT EXISTS ambulance_requests (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	zone_id INT NULL,
	details VARCHAR(255) NULL,
	status ENUM('requested','assigned','completed','cancelled') NOT NULL DEFAULT 'requested',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_ar_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_ar_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS icu_requests (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	zone_id INT NULL,
	details VARCHAR(255) NULL,
	status ENUM('requested','confirmed','admitted','rejected') NOT NULL DEFAULT 'requested',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_ir_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_ir_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Medical equipments
CREATE TABLE IF NOT EXISTS medical_equipments (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(200) NOT NULL,
	description TEXT NULL,
	stock INT NOT NULL DEFAULT 0,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_campaign_zone_state ON medical_campaign(zone_id, state);

-- Views used by examples
CREATE OR REPLACE VIEW vw_zone_infections AS
SELECT z.id AS zone_id, z.name AS zone_name, z.region_id,
			 COALESCE(SUM(ie.cases),0) AS total_cases,
			 COALESCE(SUM(CASE WHEN ie.created_at >= NOW() - INTERVAL 1 DAY THEN ie.cases ELSE 0 END),0) AS cases_24h,
			 z.population, z.death_count
FROM zones z
LEFT JOIN infection_events ie ON ie.zone_id = z.id
GROUP BY z.id;

CREATE OR REPLACE VIEW vw_active_campaigns AS
SELECT id, title, zone_id, state, capacity FROM medical_campaign WHERE state <> 'done';

SELECT 'virus_outbreak_schema.sql (minimal) ready' AS msg;
-- No backup tables created by this migration.

CREATE DATABASE IF NOT EXISTS virus_outbreak CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE virus_outbreak;

-- Users
CREATE TABLE IF NOT EXISTS users (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	is_active TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Roles (Viewer is implicit/default, not stored)
-- Roles were simplified: the project now uses a single admin email check instead of RBAC tables.
-- The previous roles DDL is preserved in sql/backup_roles.sql if needed.

-- World / geography
CREATE TABLE IF NOT EXISTS regions (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(200) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS zones (
	id INT AUTO_INCREMENT PRIMARY KEY,
	region_id INT NULL,
	name VARCHAR(200) NOT NULL,
	population INT DEFAULT 0,
	death_count INT NOT NULL DEFAULT 0,
	risk_level ENUM('low','medium','high') GENERATED ALWAYS AS (
        CASE
            WHEN death_count > 0 OR population = 0 THEN 'high'
            WHEN population > 0 AND death_count / NULLIF(population,0) > 0.01 THEN 'high'
            WHEN population > 0 AND death_count / NULLIF(population,0) > 0.001 THEN 'medium'
            ELSE 'low'
        END
    ) VIRTUAL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_zone_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Infection events and alerts
CREATE TABLE IF NOT EXISTS infection_events (
	id INT AUTO_INCREMENT PRIMARY KEY,
	zone_id INT NOT NULL,
	reporter_id INT NULL,
	event_type ENUM('report','cluster','outbreak') NOT NULL,
	cases INT NOT NULL DEFAULT 1,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_ie_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alerts (
	id INT AUTO_INCREMENT PRIMARY KEY,
	zone_id INT NULL,
	title VARCHAR(255) NOT NULL,
	status ENUM('open','acknowledged','closed') DEFAULT 'open',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_alert_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Safe people (formerly 'survivors')
CREATE TABLE IF NOT EXISTS `safe` (
	id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
	name VARCHAR(120) NOT NULL,
	age INT NULL,
	gender ENUM('male','female','other') DEFAULT NULL,
	profession VARCHAR(120) NULL,
	skill VARCHAR(120) NULL,
	zone_id INT NULL,
	outbreak_status ENUM('safe','infected','critical','recovered','deceased') DEFAULT 'safe',
	morale TINYINT NOT NULL DEFAULT 5 CHECK (morale BETWEEN 0 AND 10),
	stamina TINYINT NOT NULL DEFAULT 5 CHECK (stamina BETWEEN 0 AND 10),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_safe_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL,
	CONSTRAINT fk_safe_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ensure user_id is unique per user (NULL allowed)
CREATE UNIQUE INDEX IF NOT EXISTS uq_safe_user ON `safe` (user_id);

-- Campaigns (vaccination/awareness) created by doctors
CREATE TABLE IF NOT EXISTS medical_campaign (
	id INT AUTO_INCREMENT PRIMARY KEY,
	title VARCHAR(200) NOT NULL,
	description TEXT NULL,
	creator_id INT NULL,
	zone_id INT NULL,
	state ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
	capacity INT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_campaign_creator FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL,
	CONSTRAINT fk_campaign_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medical_campaign_registrations (
	id INT AUTO_INCREMENT PRIMARY KEY,
	campaign_id INT NOT NULL,
	user_id INT NOT NULL,
	registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	status ENUM('registered','attended','cancelled') NOT NULL DEFAULT 'registered',
	CONSTRAINT fk_cr_campaign FOREIGN KEY (campaign_id) REFERENCES medical_campaign(id) ON DELETE CASCADE,
	CONSTRAINT fk_cr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	UNIQUE KEY uq_campaign_user (campaign_id, user_id)
) ENGINE=InnoDB;

-- Doctor & volunteer request tables were removed during cleanup

-- Ambulance and ICU requests
CREATE TABLE IF NOT EXISTS ambulance_requests (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	zone_id INT NULL,
	details VARCHAR(255) NULL,
	status ENUM('requested','assigned','completed','cancelled') NOT NULL DEFAULT 'requested',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_ar_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_ar_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS icu_requests (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	zone_id INT NULL,
	details VARCHAR(255) NULL,
	status ENUM('requested','confirmed','admitted','rejected') NOT NULL DEFAULT 'requested',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_ir_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	CONSTRAINT fk_ir_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Vaccines (optional catalog)
CREATE TABLE IF NOT EXISTS medical_equipments (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(200) NOT NULL,
	description TEXT NULL,
	stock INT NOT NULL DEFAULT 0 CHECK (stock >= 0),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Useful indexes
CREATE INDEX IF NOT EXISTS idx_safe_zone_status ON `safe`(zone_id, outbreak_status);
CREATE INDEX IF NOT EXISTS idx_campaign_zone_state ON medical_campaign(zone_id, state);

-- Helpful convenience views used by the application and for reporting / lab queries
CREATE OR REPLACE VIEW vw_zone_infections AS
SELECT
	z.id AS zone_id,
	z.name AS zone_name,
	z.region_id,
	COALESCE(SUM(ie.cases),0) AS total_cases,
	COALESCE(SUM(CASE WHEN ie.created_at >= NOW() - INTERVAL 1 DAY THEN ie.cases ELSE 0 END),0) AS cases_24h,
	z.population,
	z.death_count
FROM zones z
LEFT JOIN infection_events ie ON ie.zone_id = z.id
GROUP BY z.id;

CREATE OR REPLACE VIEW vw_safe_by_zone AS
SELECT zone_id, outbreak_status, COUNT(*) AS cnt
FROM `safe`
GROUP BY zone_id, outbreak_status;

-- Example: updatable view showing active campaigns in a zone
CREATE OR REPLACE VIEW vw_active_campaigns AS
SELECT id, title, zone_id, state, capacity
FROM medical_campaign
WHERE state <> 'done';

SELECT 'virus_outbreak_schema.sql applied (enhanced)' AS msg;

SELECT 'virus_outbreak_schema.sql applied' AS msg;
