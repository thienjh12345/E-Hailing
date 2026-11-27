-- =============================================================
--  Migration 006 — Emergency (SOS) button during a ride
--  For databases created before this feature. Fresh installs do NOT need this.
--
--  Run once:  C:\xampp\mysql\bin\mysql -u root < database\migrations\006_ride_emergencies.sql
-- =============================================================

USE crab_db;

-- -------------------------------------------------------------
-- Ride emergencies (SIMULATED — no real emergency service is contacted)
--   Created when the passenger or the driver presses "Emergency" on a live
--   trip. One row per trip leg (ride + driver + on the way to pick-up / on the
--   trip): if both press it, both times are kept and
--   the same help is on its way. help_arrives_at is a made-up arrival time.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ride_emergencies (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ride_id              INT UNSIGNED  NOT NULL,
  driver_id            INT UNSIGNED  NOT NULL,          -- the driver on the ride when it happened
  trip_status          ENUM('accepted','ongoing') NOT NULL,   -- on the way to pick-up / on the trip
  location             VARCHAR(255)  NOT NULL,          -- where help is sent
  passenger_called_at  DATETIME      NULL,
  driver_called_at     DATETIME      NULL,
  help_arrives_at      DATETIME      NOT NULL,
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_emergency_ride   FOREIGN KEY (ride_id)   REFERENCES rides(id)   ON DELETE CASCADE,
  CONSTRAINT fk_emergency_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
  UNIQUE KEY uq_emergency_leg (ride_id, driver_id, trip_status)
) ENGINE=InnoDB;
