-- =============================================================
--  Migration 002 — Passenger Profile Management
--  For databases created from an OLDER schema.sql (before the
--  passenger portal). Fresh installs do NOT need this.
--  Run 001_driver_profiles.sql first if you haven't already.
--
--  Run once:  C:\xampp\mysql\bin\mysql -u root < database\migrations\002_passenger_profiles.sql
-- =============================================================

USE crab_db;

ALTER TABLE passengers
  ADD COLUMN profile_photo     VARCHAR(255) NULL AFTER password_hash,
  ADD COLUMN date_of_birth     DATE         NULL AFTER profile_photo,
  ADD COLUMN gender            ENUM('male','female') NULL AFTER date_of_birth,
  ADD COLUMN emergency_name    VARCHAR(100) NULL AFTER gender,
  ADD COLUMN emergency_phone   VARCHAR(20)  NULL AFTER emergency_name,
  ADD COLUMN preferred_payment ENUM('cash','card','wallet') NOT NULL DEFAULT 'cash' AFTER emergency_phone,
  ADD COLUMN status_note       VARCHAR(255) NULL AFTER status,
  ADD COLUMN last_login_at     DATETIME     NULL AFTER status_note,
  ADD COLUMN updated_at        DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE TABLE IF NOT EXISTS passenger_saved_places (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  passenger_id  INT UNSIGNED  NOT NULL,
  label         VARCHAR(40)   NOT NULL,
  address       VARCHAR(255)  NOT NULL,
  note          VARCHAR(120)  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_places_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id) ON DELETE CASCADE,
  UNIQUE KEY uq_place_label (passenger_id, label)
) ENGINE=InnoDB;
