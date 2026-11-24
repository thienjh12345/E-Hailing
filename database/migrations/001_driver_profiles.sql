-- =============================================================
--  Migration 001 — Driver Profile Management
--  For databases created from an OLDER schema.sql (before the
--  driver portal). Fresh installs do NOT need this: schema.sql
--  already contains these columns and tables.
--
--  Run once:  C:\xampp\mysql\bin\mysql -u root < database\migrations\001_driver_profiles.sql
-- =============================================================

USE crab_db;

ALTER TABLE drivers
  ADD COLUMN profile_photo   VARCHAR(255) NULL AFTER password_hash,
  ADD COLUMN ic_no           VARCHAR(14)  NULL AFTER profile_photo,
  ADD COLUMN address         VARCHAR(255) NULL AFTER ic_no,
  ADD COLUMN emergency_name  VARCHAR(100) NULL AFTER address,
  ADD COLUMN emergency_phone VARCHAR(20)  NULL AFTER emergency_name,
  ADD COLUMN license_expiry  DATE         NULL AFTER license_no,
  ADD COLUMN vehicle_color   VARCHAR(30)  NULL AFTER vehicle_model,
  ADD COLUMN vehicle_year    SMALLINT UNSIGNED NULL AFTER vehicle_color,
  ADD COLUMN status_note     VARCHAR(255) NULL AFTER status,
  ADD COLUMN updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS driver_change_requests (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_id    INT UNSIGNED  NOT NULL,
  changes      TEXT          NOT NULL,
  status       ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  admin_note   VARCHAR(255)  NULL,
  reviewed_by  INT UNSIGNED  NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at  DATETIME      NULL,
  CONSTRAINT fk_dcr_driver FOREIGN KEY (driver_id)   REFERENCES drivers(id) ON DELETE CASCADE,
  CONSTRAINT fk_dcr_admin  FOREIGN KEY (reviewed_by) REFERENCES admins(id)  ON DELETE SET NULL,
  INDEX idx_dcr_status (status, driver_id)
) ENGINE=InnoDB;
