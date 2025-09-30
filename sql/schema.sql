-- DB
CREATE DATABASE IF NOT EXISTS zombie_outbreak
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zombie_outbreak;

-- Users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Roles (Viewer is implicit/default, not stored)
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_key  VARCHAR(50)  NOT NULL UNIQUE,
  role_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Role requests (user asks for a role; SA approves/rejects)
CREATE TABLE IF NOT EXISTS role_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT NULL,
  reviewed_at TIMESTAMP NULL,
  reviewer_comment VARCHAR(255) NULL,
  CONSTRAINT fk_rr_user     FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rr_role     FOREIGN KEY (role_id)     REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rr_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Assigned roles (after approval)
CREATE TABLE IF NOT EXISTS user_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  assigned_by INT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_role (user_id, role_id),
  CONSTRAINT fk_ur_user     FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role     FOREIGN KEY (role_id)     REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed controlled roles (Viewer is always allowed implicitly)
INSERT IGNORE INTO roles (role_key, role_name) VALUES
('super_admin','Super Admin'),
('ops_admin','Ops Admin'),
('mission_commander','Mission Commander'),
('inventory_manager','Inventory Manager'),
('epidemiologist','Epidemiologist'),
('watch_officer','Watch Officer'),
('data_clerk','Data Clerk');
