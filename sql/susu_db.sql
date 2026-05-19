-- ============================================================
-- Susu Connect — Database Schema
-- Capstone: Digital Cooperative Management System
-- Run this file in phpMyAdmin: Import tab → choose this file
-- ============================================================

CREATE DATABASE IF NOT EXISTS susu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE susu_db;

-- Users (members, treasurers, collectors, admins)
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    phone       VARCHAR(15) NOT NULL UNIQUE,
    full_name   VARCHAR(120) NOT NULL,
    email       VARCHAR(120),
    password    VARCHAR(255) NOT NULL,
    pin_hash    VARCHAR(255),
    role        ENUM('ADMIN','TREASURER','COLLECTOR','MEMBER') DEFAULT 'MEMBER',
    network     ENUM('MTN','TELECEL','AT','UNKNOWN') DEFAULT 'UNKNOWN',
    member_code VARCHAR(20) UNIQUE,
    ghana_card  VARCHAR(20),
    language    ENUM('en','tw','ga','ee','ha') DEFAULT 'en',
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Susu Groups
CREATE TABLE groups_ (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(20) UNIQUE,
    name                VARCHAR(120) NOT NULL,
    description         TEXT,
    location            VARCHAR(120),
    treasurer_id        INT NOT NULL,
    collector_id        INT,
    contribution_amount DECIMAL(10,2) NOT NULL,
    frequency           ENUM('DAILY','WEEKLY','BIWEEKLY','MONTHLY') DEFAULT 'WEEKLY',
    max_members         INT DEFAULT 20,
    status              ENUM('PENDING','ACTIVE','PAUSED','COMPLETED','DISSOLVED') DEFAULT 'PENDING',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (treasurer_id) REFERENCES users(id),
    FOREIGN KEY (collector_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Group memberships with rotation position
CREATE TABLE memberships (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    group_id          INT NOT NULL,
    user_id           INT NOT NULL,
    rotation_position INT NOT NULL,
    is_active         TINYINT(1) DEFAULT 1,
    joined_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (group_id, user_id),
    UNIQUE KEY (group_id, rotation_position),
    FOREIGN KEY (group_id) REFERENCES groups_(id),
    FOREIGN KEY (user_id)  REFERENCES users(id)
);

-- Susu cycles (one complete rotation)
CREATE TABLE cycles (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    group_id          INT NOT NULL,
    cycle_number      INT NOT NULL,
    start_date        DATE NOT NULL,
    expected_end_date DATE NOT NULL,
    actual_end_date   DATE,
    total_rounds      INT NOT NULL,
    status            ENUM('PENDING','ACTIVE','COMPLETED','CANCELLED') DEFAULT 'PENDING',
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (group_id, cycle_number),
    FOREIGN KEY (group_id) REFERENCES groups_(id)
);

-- Individual rounds within a cycle
CREATE TABLE rounds (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id     INT NOT NULL,
    round_number INT NOT NULL,
    due_date     DATE NOT NULL,
    recipient_id INT NOT NULL,
    status       ENUM('PENDING','OPEN','CLOSED','PAID') DEFAULT 'PENDING',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (cycle_id, round_number),
    FOREIGN KEY (cycle_id)     REFERENCES cycles(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id)
);

-- Contributions (one per member per round)
CREATE TABLE contributions (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    round_id                    INT NOT NULL,
    member_id                   INT NOT NULL,
    amount                      DECIMAL(10,2) NOT NULL,
    method                      ENUM('MOMO','CASH','BANK') DEFAULT 'MOMO',
    status                      ENUM('PENDING','CONFIRMED','FAILED','REVERSED') DEFAULT 'PENDING',
    momo_reference              VARCHAR(64),
    momo_financial_txn_id       VARCHAR(64),
    confirmed_at                DATETIME,
    notes                       TEXT,
    created_at                  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (round_id, member_id),
    FOREIGN KEY (round_id)  REFERENCES rounds(id),
    FOREIGN KEY (member_id) REFERENCES users(id)
);

-- Payouts (one per round to the recipient)
CREATE TABLE payouts (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    round_id              INT UNIQUE NOT NULL,
    recipient_id          INT NOT NULL,
    amount                DECIMAL(10,2) NOT NULL,
    status                ENUM('PENDING','PROCESSING','COMPLETED','FAILED') DEFAULT 'PENDING',
    momo_reference        VARCHAR(64),
    momo_financial_txn_id VARCHAR(64),
    processed_at          DATETIME,
    notes                 TEXT,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (round_id)     REFERENCES rounds(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id)
);

-- MoMo API transaction log
CREATE TABLE momo_transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    type             ENUM('COLLECTION','DISBURSEMENT','TOKEN','STATUS','BALANCE'),
    reference_id     VARCHAR(64),
    external_id      VARCHAR(64),
    phone            VARCHAR(15),
    amount           DECIMAL(10,2),
    status           VARCHAR(20) DEFAULT 'PENDING',
    request_payload  JSON,
    response_payload JSON,
    error_message    TEXT,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for common queries
CREATE INDEX idx_contributions_member ON contributions(member_id);
CREATE INDEX idx_contributions_round  ON contributions(round_id);
CREATE INDEX idx_payouts_recipient    ON payouts(recipient_id);
CREATE INDEX idx_rounds_cycle         ON rounds(cycle_id);
CREATE INDEX idx_momo_reference       ON momo_transactions(reference_id);
