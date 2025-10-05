USE zom;

CREATE TABLE IF NOT EXISTS survivors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  age INT NULL,
  gender ENUM('male','female','other') DEFAULT NULL,
  profession VARCHAR(120) NULL,
  skill VARCHAR(120) NULL,
  zone_id INT NULL,
  health_status ENUM('healthy','infected','critical','turned') DEFAULT 'healthy',
  morale TINYINT NOT NULL DEFAULT 5,   -- 1..10
  stamina TINYINT NOT NULL DEFAULT 5,  -- 1..10
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_survivor_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB;
