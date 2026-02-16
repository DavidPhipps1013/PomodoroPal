-- db/pomodoropal_dev_seed.sql
-- Dev schema + fake data (safe to commit)
-- Works on MySQL 8+ / MariaDB (typical XAMPP)

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS focus_stats;
DROP TABLE IF EXISTS profile;
DROP TABLE IF EXISTS to_do_list;
DROP TABLE IF EXISTS user;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE user (
  user_id        INT AUTO_INCREMENT PRIMARY KEY,
  f_name         VARCHAR(50)  NOT NULL,
  l_name         VARCHAR(50)  NOT NULL,
  email          VARCHAR(100) NOT NULL UNIQUE,
  phone_number   VARCHAR(15)  NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login     DATETIME     NULL,
  INDEX idx_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE to_do_list (
  task_id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  task_name        VARCHAR(255) NOT NULL,
  task_description TEXT NULL,
  status           VARCHAR(50) NOT NULL DEFAULT 'pending',
  priority         VARCHAR(50) NOT NULL DEFAULT 'medium',
  due_date         DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at     DATETIME NULL,
  CONSTRAINT fk_todo_user
    FOREIGN KEY (user_id) REFERENCES user(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_todo_user (user_id),
  INDEX idx_todo_status (status),
  INDEX idx_todo_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE profile (
  profile_id    INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL UNIQUE,
  display_name  VARCHAR(100) NOT NULL,
  avatar_url    VARCHAR(255) NULL,
  white_list    VARCHAR(255) NULL,
  CONSTRAINT fk_profile_user
    FOREIGN KEY (user_id) REFERENCES user(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_profile_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE focus_stats (
  focus_id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id             INT NOT NULL,
  focus_date          DATE NOT NULL,
  focus_minutes       INT NOT NULL DEFAULT 0,
  sessions_completed  INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_focus_user
    FOREIGN KEY (user_id) REFERENCES user(user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_focus_user_date (user_id, focus_date),
  INDEX idx_focus_user (user_id),
  INDEX idx_focus_date (focus_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fake users (password_hash values are dummy "bcrypt-looking" strings for dev)
INSERT INTO user (f_name, l_name, email, phone_number, password_hash, created_at, last_login)
VALUES
('Jane', 'Doe', 'Jane@example.com', '734-555-0101',
 '$2y$10$devhash0000000000000000000000000000000000000000000000',
 '2026-01-10 09:15:00', '2026-02-14 21:05:00'),
('Rick', 'Steves', 'rick@example.com', '734-555-0102',
 '$2y$10$devhash1111111111111111111111111111111111111111111111',
 '2026-01-12 14:40:00', '2026-02-13 19:12:00'),
('Keanu', 'Reeves', 'keanu@example.com', NULL,
 '$2y$10$devhash2222222222222222222222222222222222222222222222',
 '2026-01-18 08:05:00', '2026-02-10 08:44:00'),
('Wade', 'Kinsella', 'wade@example.com', '313-555-0199',
 '$2y$10$devhash3333333333333333333333333333333333333333333333',
 '2026-01-22 17:20:00', '2026-02-12 22:01:00');

INSERT INTO profile (user_id, display_name, avatar_url, white_list)
VALUES
(1, 'Jane', NULL, 'https://ieee.org'),
(2, 'Rick', NULL, 'https://github.com'),
(3, 'Keanu', NULL, 'https://docs.google.com'),
(4, 'Wade', NULL, 'https://stackoverflow.com');

INSERT INTO to_do_list (user_id, task_name, task_description, status, priority, due_date, created_at, completed_at)
VALUES
(1, 'Finish local DB setup', 'Import db/pomodoropal_dev_seed.sql into local MySQL.', 'complete', 'high',
 '2026-02-15 18:00:00', '2026-02-15 15:10:00', '2026-02-15 15:25:00'),
(1, 'Connect extension settings to backend', 'Hook Chrome extension settings UI to PHP endpoints.', 'pending', 'high',
 '2026-02-18 20:00:00', '2026-02-15 15:30:00', NULL),
(2, 'Draft About page', 'Write short About section for website.', 'pending', 'medium',
 '2026-02-20 17:00:00', '2026-02-14 10:00:00', NULL),
(3, 'Create help/FAQ', 'List 6–8 common questions and answers.', 'pending', 'low',
 NULL, '2026-02-12 09:00:00', NULL),
(4, 'Stats page UI', 'Render totals from focus_stats.', 'pending', 'medium',
 '2026-02-22 12:00:00', '2026-02-11 11:30:00', NULL);

INSERT INTO focus_stats (user_id, focus_date, focus_minutes, sessions_completed)
VALUES
(1, '2026-02-12', 75, 3),
(1, '2026-02-13', 50, 2),
(1, '2026-02-14', 100, 4),
(2, '2026-02-13', 25, 1),
(2, '2026-02-14', 60, 2),
(3, '2026-02-10', 30, 1),
(4, '2026-02-12', 45, 2);
