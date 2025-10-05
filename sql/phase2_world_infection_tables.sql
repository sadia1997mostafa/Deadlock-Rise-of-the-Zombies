USE zom;

-- Regions
CREATE TABLE IF NOT EXISTS regions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  risk_level TINYINT NOT NULL DEFAULT 1,  -- 1..5
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Zones
CREATE TABLE IF NOT EXISTS zones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  region_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  danger_score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_region_zone (region_id, name),
  CONSTRAINT fk_zone_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Infection Events
CREATE TABLE IF NOT EXISTS infection_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT NOT NULL,
  event_type ENUM('report','cluster','outbreak') NOT NULL DEFAULT 'report',
  severity TINYINT NOT NULL DEFAULT 1,  -- 1..5
  notes VARCHAR(255) NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ie_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
  CONSTRAINT fk_ie_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Alerts (opened by PHP when danger crosses threshold)
CREATE TABLE IF NOT EXISTS alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  status ENUM('open','acknowledged','closed') NOT NULL DEFAULT 'open',
  threshold DECIMAL(6,2) NOT NULL,
  danger_at_creation DECIMAL(6,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  acknowledged_by INT NULL,
  acknowledged_at TIMESTAMP NULL,
  closed_by INT NULL,
  closed_at TIMESTAMP NULL,
  CONSTRAINT fk_alert_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
  CONSTRAINT fk_alert_ack FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_alert_close FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
