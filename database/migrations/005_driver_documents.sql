-- =============================================================
--  Migration 005 — Driver car photos & PUSPAKOM certificate
--  For databases created before this feature. Fresh installs do NOT need this.
--  (Drivers registered before this have no photos yet; they can upload them
--   from Licence & vehicle in the driver portal.)
--
--  Run once:  C:\xampp\mysql\bin\mysql -u root < database\migrations\005_driver_documents.sql
-- =============================================================

USE crab_db;

-- -------------------------------------------------------------
-- Driver documents: 4 car photos + PUSPAKOM inspection certificate
--   Required when a driver registers; the driver can replace any of
--   them later from Licence & vehicle. One row per driver per type.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS driver_documents (
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
