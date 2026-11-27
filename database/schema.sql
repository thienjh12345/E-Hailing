-- =============================================================
--  Crab E-Hailing — database schema
--  Import order: schema.sql  ->  seed.sql (optional demo data)
-- =============================================================

CREATE DATABASE IF NOT EXISTS crab_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE crab_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ride_emergencies;
DROP TABLE IF EXISTS wallet_transactions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS payment_cards;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_conversations;
DROP TABLE IF EXISTS passenger_saved_places;
DROP TABLE IF EXISTS driver_documents;
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
  wallet_balance    DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- CrabPay wallet (see wallet_transactions)
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
  city            ENUM('klang_valley','kota_kinabalu') NOT NULL DEFAULT 'klang_valley',  -- where they take ride requests
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
  service_type     ENUM('car','premium','bike') NOT NULL DEFAULT 'car',
  city             ENUM('klang_valley','kota_kinabalu') NOT NULL DEFAULT 'klang_valley',
  pickup_note      VARCHAR(120)  NULL,          -- e.g. "Main entrance, blue shirt"
  requested_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at      DATETIME      NULL,
  started_at       DATETIME      NULL,
  completed_at     DATETIME      NULL,
  cancelled_at     DATETIME      NULL,
  cancelled_by     ENUM('passenger','driver','admin','system') NULL,
  CONSTRAINT fk_rides_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id) ON DELETE CASCADE,
  CONSTRAINT fk_rides_driver    FOREIGN KEY (driver_id)    REFERENCES drivers(id)    ON DELETE SET NULL,
  INDEX idx_rides_status (status),
  INDEX idx_rides_requested (requested_at),
  INDEX idx_rides_open (status, city, service_type)
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
-- In-app chat
--   type 'ride'    : passenger <-> driver, one conversation per ride
--   type 'support' : passenger <-> Crab Support (any admin), one per passenger
--   *_last_read    : id of the last message each side has seen (for unread
--                    badges and "Seen"). Admins share one support inbox.
-- -------------------------------------------------------------
CREATE TABLE chat_conversations (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type                ENUM('ride','support') NOT NULL,
  passenger_id        INT UNSIGNED  NOT NULL,
  driver_id           INT UNSIGNED  NULL,
  ride_id             INT UNSIGNED  NULL,
  status              ENUM('open','resolved') NOT NULL DEFAULT 'open',   -- used by support chats
  passenger_last_read INT UNSIGNED  NOT NULL DEFAULT 0,
  driver_last_read    INT UNSIGNED  NOT NULL DEFAULT 0,
  admin_last_read     INT UNSIGNED  NOT NULL DEFAULT 0,
  support_owner       INT UNSIGNED  AS (IF(type = 'support', passenger_id, NULL)) STORED,  -- enforces one support chat per passenger
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_message_at     DATETIME      NULL,
  CONSTRAINT fk_chat_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_driver    FOREIGN KEY (driver_id)    REFERENCES drivers(id)    ON DELETE SET NULL,
  CONSTRAINT fk_chat_ride      FOREIGN KEY (ride_id)      REFERENCES rides(id)      ON DELETE CASCADE,
  UNIQUE KEY uq_chat_ride (ride_id),
  UNIQUE KEY uq_chat_support (support_owner),
  INDEX idx_chat_driver (driver_id),
  INDEX idx_chat_inbox (type, status, last_message_at)
) ENGINE=InnoDB;

CREATE TABLE chat_messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED  NOT NULL,
  sender_role     ENUM('passenger','driver','admin') NOT NULL,
  sender_id       INT UNSIGNED  NOT NULL,
  body            TEXT          NOT NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
  INDEX idx_msg_conversation (conversation_id, id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Payments: saved cards, CrabPay wallet, one payment per ride
--   Card numbers are NEVER stored — only brand, last 4 digits and
--   expiry (a real app would store a token from a payment gateway).
-- -------------------------------------------------------------
CREATE TABLE payment_cards (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  passenger_id  INT UNSIGNED  NOT NULL,
  brand         VARCHAR(20)   NOT NULL,          -- Visa, Mastercard, Amex
  last4         CHAR(4)       NOT NULL,
  exp_month     TINYINT UNSIGNED  NOT NULL,
  exp_year      SMALLINT UNSIGNED NOT NULL,
  holder_name   VARCHAR(100)  NOT NULL,
  is_default    TINYINT(1)    NOT NULL DEFAULT 0,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_card_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id) ON DELETE CASCADE,
  INDEX idx_card_passenger (passenger_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ride_id       INT UNSIGNED  NOT NULL,
  passenger_id  INT UNSIGNED  NOT NULL,
  method        ENUM('cash','card','wallet') NOT NULL,
  card_id       INT UNSIGNED  NULL,
  amount        DECIMAL(8,2)  NOT NULL,
  status        ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  reference     VARCHAR(30)   NOT NULL,          -- receipt number, e.g. CRB2611250000173
  note          VARCHAR(255)  NULL,              -- e.g. "Wallet balance too low — paid in cash"
  paid_at       DATETIME      NULL,
  refunded_at   DATETIME      NULL,
  refunded_by   INT UNSIGNED  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_ride      FOREIGN KEY (ride_id)      REFERENCES rides(id)         ON DELETE CASCADE,
  CONSTRAINT fk_pay_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id)    ON DELETE CASCADE,
  CONSTRAINT fk_pay_card      FOREIGN KEY (card_id)      REFERENCES payment_cards(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_admin     FOREIGN KEY (refunded_by)  REFERENCES admins(id)        ON DELETE SET NULL,
  UNIQUE KEY uq_pay_ride (ride_id),
  UNIQUE KEY uq_pay_reference (reference),
  INDEX idx_pay_status (status, paid_at)
) ENGINE=InnoDB;

CREATE TABLE wallet_transactions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  passenger_id  INT UNSIGNED  NOT NULL,
  type          ENUM('topup','ride','refund','adjustment') NOT NULL,
  amount        DECIMAL(10,2) NOT NULL,          -- + money in, - money out
  balance_after DECIMAL(10,2) NOT NULL,
  ride_id       INT UNSIGNED  NULL,
  description   VARCHAR(255)  NOT NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_passenger FOREIGN KEY (passenger_id) REFERENCES passengers(id) ON DELETE CASCADE,
  CONSTRAINT fk_wallet_ride      FOREIGN KEY (ride_id)      REFERENCES rides(id)      ON DELETE SET NULL,
  INDEX idx_wallet_passenger (passenger_id, created_at)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Driver documents: 4 car photos + PUSPAKOM inspection certificate
--   Required when a driver registers; the driver can replace any of
--   them later from Licence & vehicle. One row per driver per type.
-- -------------------------------------------------------------
CREATE TABLE driver_documents (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_id      INT UNSIGNED  NOT NULL,
  doc_type       ENUM('car_front','car_back','car_left','car_right','puspakom') NOT NULL,
  file_path      VARCHAR(255)  NOT NULL,          -- relative to project root, e.g. uploads/vehicles/ab12.jpg
  original_name  VARCHAR(255)  NOT NULL,
  mime_type      VARCHAR(50)   NOT NULL,
  file_size      INT UNSIGNED  NOT NULL,
  uploaded_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_doc_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
  UNIQUE KEY uq_driver_doc (driver_id, doc_type)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Ride emergencies (SIMULATED — no real emergency service is contacted)
--   Created when the passenger or the driver presses "Emergency" on a live
--   trip. One row per trip leg (ride + driver + on the way to pick-up / on the
--   trip): if both press it, both times are kept and
--   the same help is on its way. help_arrives_at is a made-up arrival time.
-- -------------------------------------------------------------
CREATE TABLE ride_emergencies (
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

-- -------------------------------------------------------------
-- Default admin account
--   email:    admin@crab.com
--   password: admin123   (change this after first login!)
-- -------------------------------------------------------------
INSERT INTO admins (name, email, password_hash) VALUES
('Crab Admin', 'admin@crab.com', '$2y$10$FBtHdboExopK1khCHEGoPOT06YtYN.0KYJAfK.X5T7XVUSv3hSc7K');
