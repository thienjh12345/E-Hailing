-- =============================================================
--  Migration 003 — In-app chat (passenger <-> driver, passenger <-> Crab Support)
--  For databases created before the chat feature. Fresh installs do NOT need this.
--
--  Run once:  C:\xampp\mysql\bin\mysql -u root < database\migrations\003_in_app_chat.sql
-- =============================================================

USE crab_db;

-- -------------------------------------------------------------
-- In-app chat
--   type 'ride'    : passenger <-> driver, one conversation per ride
--   type 'support' : passenger <-> Crab Support (any admin), one per passenger
--   *_last_read    : id of the last message each side has seen (for unread
--                    badges and "Seen"). Admins share one support inbox.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_conversations (
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

CREATE TABLE IF NOT EXISTS chat_messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED  NOT NULL,
  sender_role     ENUM('passenger','driver','admin') NOT NULL,
  sender_id       INT UNSIGNED  NOT NULL,
  body            TEXT          NOT NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
  INDEX idx_msg_conversation (conversation_id, id)
) ENGINE=InnoDB;
