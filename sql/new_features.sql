-- ============================================================
-- Susu Connect v3 — New Features SQL
-- Run this in phpMyAdmin SQL tab (susu_group database)
-- ============================================================

USE susu_group;

-- Audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    action     VARCHAR(100) NOT NULL,
    details    TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMS log table
CREATE TABLE IF NOT EXISTS sms_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    recipient   VARCHAR(15) NOT NULL,
    message     TEXT NOT NULL,
    status      ENUM('SENT','FAILED','PENDING') DEFAULT 'PENDING',
    response    TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add Ghana Card and language to users if not exists
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS ghana_card VARCHAR(25) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS preferred_lang ENUM('en','tw') DEFAULT 'en',
    ADD COLUMN IF NOT EXISTS payout_pin VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS dark_mode TINYINT(1) DEFAULT 0;

-- Add invitation token to groups
ALTER TABLE groups_
    ADD COLUMN IF NOT EXISTS invite_token VARCHAR(32) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS invite_active TINYINT(1) DEFAULT 0;

-- Generate invite tokens for existing groups
UPDATE groups_ SET invite_token = SUBSTRING(MD5(RAND()), 1, 16) WHERE invite_token IS NULL;

-- Indexes
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_log(action);
CREATE INDEX IF NOT EXISTS idx_sms_recipient ON sms_log(recipient);
