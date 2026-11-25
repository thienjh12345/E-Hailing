-- =============================================================
--  Crab E-Hailing — database schema
--  Import order: schema.sql  ->  seed.sql (optional demo data)
-- =============================================================

CREATE DATABASE IF NOT EXISTS crab_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE crab_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS passenger_saved_places;
DROP TABLE IF EXISTS driver_change_requests;
DROP TABLE IF EXISTS rides;
DROP TABLE IF EXISTS drivers;
DROP TABLE IF EXISTS passengers;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- Admins: back-office staff who can log into /admin
-- -------------------------------------------------------------
CREATE TABLE admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  last_login_at DATETIME      NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Passengers
-- -------------------------------------------------------------
CREATE TABLE passengers (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(100)  NOT NULL,
  email             VARCHAR(150)  NOT NULL UNIQUE,
  phone             VARCHAR(20)   NOT NULL,
  password_hash     VARCHAR(255)  NOT NULL,
  profile_photo     VARCHAR(255)  NULL,          -- path relative to project root, e.g. uploads/passengers/ab12.jpg
  date_of_birth     DATE          NULL,
  gender            ENUM('male','female') NULL,
  emergency_name    VARCHAR(100)  NULL,
  emergency_phone   VARCHAR(20)   NULL,
  preferred_payment ENUM('cash','card','wallet') NOT NULL DEFAULT 'cash',
  status            ENUM('active','suspended') NOT NULL DEFAULT 'active',
  status_note       VARCHAR(255)  NULL,          -- admin reason shown to the passenger when suspended
  last_login_at     DATETIME      NULL,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_passengers_status (status)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Passenger saved places (Home, Work, favourites) for quick booking
-- -------------------------------------------------------------
CREATE TABLE passenger_saved_places (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  passenger_id  INT UNSIGNED  NOT NULL,
  label         VARCHAR(40)   NOT NULL,       -- 'Home', 'Work' or a custom name
  address       VARCHAR(255)  NOT NULL,
  note          VARCHAR(120)  NULL,           -- e.g. "Lobby B, guard house"
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_places_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id) ON DELETE CASCADE,
  UNIQUE KEY uq_place_label (passenger_id, label)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Drivers (must be approved by an admin before taking rides)
-- -------------------------------------------------------------
CREATE TABLE drivers (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100)  NOT NULL,
  email           VARCHAR(150)  NOT NULL UNIQUE,
  phone           VARCHAR(20)   NOT NULL,
  password_hash   VARCHAR(255)  NOT NULL,
  profile_photo   VARCHAR(255)  NULL,          -- path relative to project root, e.g. uploads/drivers/ab12.jpg
  ic_no           VARCHAR(14)   NULL,          -- MyKad number, 900101-12-5678
  address         VARCHAR(255)  NULL,
  emergency_name  VARCHAR(100)  NULL,
  emergency_phone VARCHAR(20)   NULL,
  license_no      VARCHAR(30)   NOT NULL,
  license_expiry  DATE          NULL,
  vehicle_type    ENUM('car','premium','bike') NOT NULL DEFAULT 'car',
  vehicle_model   VARCHAR(80)   NOT NULL,
  vehicle_color   VARCHAR(30)   NULL,
  vehicle_year    SMALLINT UNSIGNED NULL,
  plate_no        VARCHAR(15)   NOT NULL UNIQUE,
  status          ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
  status_note     VARCHAR(255)  NULL,          -- admin reason shown to the driver (rejection / suspension)
  is_online       TINYINT(1)    NOT NULL DEFAULT 0,
  rating          DECIMAL(3,2)  NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_drivers_status (status),
  INDEX idx_drivers_online (is_online)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Rides (bookings)
-- -------------------------------------------------------------
CREATE TABLE rides (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  passenger_id     INT UNSIGNED  NOT NULL,
  driver_id        INT UNSIGNED  NULL,
  pickup_address   VARCHAR(255)  NOT NULL,
  dropoff_address  VARCHAR(255)  NOT NULL,
  pickup_lat       DECIMAL(10,7) NULL,
  pickup_lng       DECIMAL(10,7) NULL,
  dropoff_lat      DECIMAL(10,7) NULL,
  dropoff_lng      DECIMAL(10,7) NULL,
  distance_km      DECIMAL(6,2)  NOT NULL DEFAULT 0,
  fare             DECIMAL(8,2)  NOT NULL DEFAULT 0,
  payment_method   ENUM('cash','card','wallet') NOT NULL DEFAULT 'cash',
  status           ENUM('requested','accepted','ongoing','completed','cancelled') NOT NULL DEFAULT 'requested',
  rating           TINYINT UNSIGNED NULL,
  requested_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at     DATETIME      NULL,
  CONSTRAINT fk_rides_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id) ON DELETE CASCADE,
  CONSTRAINT fk_rides_driver    FOREIGN KEY (driver_id)    REFERENCES drivers(id)    ON DELETE SET NULL,
  INDEX idx_rides_status (status),
  INDEX idx_rides_requested (requested_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Driver profile change requests
--   Once a driver is approved, changes to identity, licence or
--   vehicle details must be re-checked by an admin. The driver
--   submits the new values here; an admin approves (values are
--   copied onto the drivers row) or rejects them with a note.
-- -------------------------------------------------------------
CREATE TABLE driver_change_requests (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_id    INT UNSIGNED  NOT NULL,
  changes      TEXT          NOT NULL,      -- JSON: {"plate_no": "SAA 1234", ...}
  status       ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  admin_note   VARCHAR(255)  NULL,
  reviewed_by  INT UNSIGNED  NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at  DATETIME      NULL,
  CONSTRAINT fk_dcr_driver FOREIGN KEY (driver_id)   REFERENCES drivers(id) ON DELETE CASCADE,
  CONSTRAINT fk_dcr_admin  FOREIGN KEY (reviewed_by) REFERENCES admins(id)  ON DELETE SET NULL,
  INDEX idx_dcr_status (status, driver_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Default admin account
--   email:    admin@crab.com
--   password: admin123   (change this after first login!)
-- -------------------------------------------------------------
INSERT INTO admins (name, email, password_hash) VALUES
('Crab Admin', 'admin@crab.com', '$2y$10$FBtHdboExopK1khCHEGoPOT06YtYN.0KYJAfK.X5T7XVUSv3hSc7K');
