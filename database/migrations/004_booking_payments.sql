-- =============================================================
--  Migration 004 — Ride booking & payments
--  Adds booking details to rides, an operating city to drivers, a CrabPay
--  wallet to passengers, and the payments / payment_cards / wallet_transactions
--  tables — then fills them in for the trips already in the database.
--  For databases created before this feature. Fresh installs do NOT need this.
--
--  Run once:  C:\xampp\mysql\bin\mysql -u root < database\migrations\004_booking_payments.sql
-- =============================================================

USE crab_db;

ALTER TABLE passengers
  ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER preferred_payment;

ALTER TABLE drivers
  ADD COLUMN city ENUM('klang_valley','kota_kinabalu') NOT NULL DEFAULT 'klang_valley' AFTER is_online;

ALTER TABLE rides
  ADD COLUMN service_type ENUM('car','premium','bike') NOT NULL DEFAULT 'car' AFTER rating,
  ADD COLUMN city ENUM('klang_valley','kota_kinabalu') NOT NULL DEFAULT 'klang_valley' AFTER service_type,
  ADD COLUMN pickup_note VARCHAR(120) NULL AFTER city,
  ADD COLUMN accepted_at DATETIME NULL AFTER requested_at,
  ADD COLUMN started_at DATETIME NULL AFTER accepted_at,
  ADD COLUMN cancelled_at DATETIME NULL AFTER completed_at,
  ADD COLUMN cancelled_by ENUM('passenger','driver','admin','system') NULL AFTER cancelled_at,
  ADD INDEX idx_rides_open (status, city, service_type);

-- -------------------------------------------------------------
-- Payments: saved cards, CrabPay wallet, one payment per ride
--   Card numbers are NEVER stored — only brand, last 4 digits and
--   expiry (a real app would store a token from a payment gateway).
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_cards (
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

CREATE TABLE IF NOT EXISTS payments (
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

CREATE TABLE IF NOT EXISTS wallet_transactions (
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
-- Ride booking & payments: fill in details for the demo trips
-- (service type, city, timestamps, payments, saved cards, wallet)
-- -------------------------------------------------------------

-- Service type comes from the driver's vehicle; city from where the trip is
UPDATE rides r LEFT JOIN drivers d ON d.id = r.driver_id SET r.service_type = COALESCE(d.vehicle_type, 'car');
UPDATE rides SET city = IF(pickup_lat > 4, 'kota_kinabalu', 'klang_valley');
UPDATE rides SET accepted_at  = requested_at + INTERVAL 1 MINUTE WHERE driver_id IS NOT NULL AND status IN ('accepted','ongoing','completed');
UPDATE rides SET started_at   = requested_at + INTERVAL 3 MINUTE WHERE status IN ('ongoing','completed');
UPDATE rides SET cancelled_at = requested_at + INTERVAL 4 MINUTE, cancelled_by = 'passenger' WHERE status = 'cancelled';

-- Drivers work in the city of their trips (drivers with no trips: Sabah plates -> Kota Kinabalu)
UPDATE drivers d SET d.city = 'kota_kinabalu', d.updated_at = d.updated_at
WHERE (SELECT COUNT(*) FROM rides r WHERE r.driver_id = d.id AND r.city = 'kota_kinabalu')
    > (SELECT COUNT(*) FROM rides r WHERE r.driver_id = d.id AND r.city = 'klang_valley')
   OR (d.plate_no LIKE 'S%' AND NOT EXISTS (SELECT 1 FROM rides r WHERE r.driver_id = d.id));

-- A saved (demo) Visa card for every passenger who has paid by card
INSERT INTO payment_cards (passenger_id, brand, last4, exp_month, exp_year, holder_name, is_default, created_at)
SELECT p.id, 'Visa', LPAD(MOD(p.id * 7919, 10000), 4, '0'), 1 + MOD(p.id, 12), YEAR(CURDATE()) + 2, UPPER(p.name), 1, p.created_at
FROM passengers p
WHERE EXISTS (SELECT 1 FROM rides r WHERE r.passenger_id = p.id AND r.payment_method = 'card');

-- One payment per trip: completed = paid, cancelled = cancelled, live = pending (charged when the trip ends)
INSERT INTO payments (ride_id, passenger_id, method, card_id, amount, status, reference, paid_at, created_at)
SELECT r.id, r.passenger_id, r.payment_method,
       IF(r.payment_method = 'card', (SELECT c.id FROM payment_cards c WHERE c.passenger_id = r.passenger_id ORDER BY c.is_default DESC, c.id LIMIT 1), NULL),
       r.fare,
       CASE r.status WHEN 'completed' THEN 'paid' WHEN 'cancelled' THEN 'cancelled' ELSE 'pending' END,
       CONCAT('CRB', DATE_FORMAT(r.requested_at, '%y%m%d'), LPAD(r.id, 7, '0')),
       IF(r.status = 'completed', r.completed_at, NULL),
       r.requested_at
FROM rides r;

-- CrabPay wallet: one top-up before the first trip (enough for every wallet trip + some spare),
-- then a deduction for each completed wallet trip. Balances are worked out from the history.
INSERT INTO wallet_transactions (passenger_id, type, amount, balance_after, description, created_at)
SELECT p.id, 'topup',
       CEIL((COALESCE((SELECT SUM(r.fare) FROM rides r WHERE r.passenger_id = p.id AND r.payment_method = 'wallet' AND r.status <> 'cancelled'), 0)
             + 30 + MOD(p.id * 37, 120)) / 10) * 10,
       0, 'Top-up via online banking (FPX)',
       LEAST(p.created_at, COALESCE((SELECT MIN(r.requested_at) FROM rides r WHERE r.passenger_id = p.id), p.created_at)) - INTERVAL 1 DAY
FROM passengers p;

INSERT INTO wallet_transactions (passenger_id, type, amount, balance_after, ride_id, description, created_at)
SELECT r.passenger_id, 'ride', -r.fare, 0, r.id, CONCAT('Trip #', r.id, ' · ', r.pickup_address, ' → ', r.dropoff_address), r.completed_at
FROM rides r WHERE r.payment_method = 'wallet' AND r.status = 'completed';

UPDATE wallet_transactions w
JOIN (SELECT id, SUM(amount) OVER (PARTITION BY passenger_id ORDER BY created_at, id) AS bal FROM wallet_transactions) t ON t.id = w.id
SET w.balance_after = t.bal;

UPDATE passengers p
SET p.wallet_balance = COALESCE((SELECT SUM(w.amount) FROM wallet_transactions w WHERE w.passenger_id = p.id), 0),
    p.updated_at = p.updated_at;   -- a wallet change is not a "profile update"
