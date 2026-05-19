-- ============================================================
-- Susu Connect — Trust Score & Default Protection SQL
-- Run this in phpMyAdmin SQL tab
-- ============================================================

USE susu_group;

-- Default warnings table
CREATE TABLE IF NOT EXISTS default_warnings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NOT NULL,
    round_id     INT NOT NULL,
    group_id     INT NOT NULL,
    warning_level ENUM('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'LOW',
    reason       VARCHAR(255),
    issued_by    INT,
    resolved     TINYINT(1) DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups_(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add trust score caching to users (optional, for performance)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS trust_score DECIMAL(5,2) DEFAULT 100.00,
  ADD COLUMN IF NOT EXISTS total_contributions INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS missed_contributions INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_score_update DATETIME DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_warnings_member ON default_warnings(member_id);
CREATE INDEX IF NOT EXISTS idx_warnings_round ON default_warnings(round_id);
