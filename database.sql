-- Pickle Book: Pickleball Court Reservation System
-- Import this file in phpMyAdmin (or: mysql -u root -p < database.sql)

CREATE DATABASE IF NOT EXISTS pickle_book_pdo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pickle_book_pdo;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('player','admin') NOT NULL DEFAULT 'player',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL UNIQUE,
    surface     VARCHAR(40) NOT NULL,
    hourly_rate DECIMAL(8,2) NOT NULL CHECK (hourly_rate >= 0),
    is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- MAIN ENTITY
CREATE TABLE IF NOT EXISTS reservations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no     VARCHAR(12) NULL UNIQUE,
    user_id          INT UNSIGNED NOT NULL,
    court_id         INT UNSIGNED NOT NULL,
    player_name      VARCHAR(100) NOT NULL,
    contact_number   VARCHAR(20)  NOT NULL,
    reservation_date DATE NOT NULL,
    start_time       TIME NOT NULL,
    end_time         TIME NOT NULL,
    players_count    TINYINT UNSIGNED NOT NULL DEFAULT 2,
    total_fee        DECIMAL(8,2) NOT NULL DEFAULT 0,
    status           ENUM('Booked','Completed','Cancelled') NOT NULL DEFAULT 'Booked',
    notes            VARCHAR(255) NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_res_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT fk_res_court FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE RESTRICT,
    CONSTRAINT chk_time CHECK (end_time > start_time),
    CONSTRAINT chk_players CHECK (players_count BETWEEN 1 AND 4),
    INDEX idx_slot (court_id, reservation_date, start_time, end_time)
) ENGINE=InnoDB;

-- Audit trail written inside every transaction
CREATE TABLE IF NOT EXISTS reservation_logs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED NULL,
    action         VARCHAR(20) NOT NULL,
    details        VARCHAR(255) NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO courts (id, name, surface, hourly_rate) VALUES
 (1, 'Court 1 - Smash', 'Indoor hardcourt', 250.00),
 (2, 'Court 2 - Dink',  'Indoor hardcourt', 250.00),
 (3, 'Court 3 - Kitchen', 'Outdoor acrylic', 180.00),
 (4, 'Court 4 - Lob',   'Outdoor acrylic', 180.00);

-- Default admin  ->  admin@picklebook.test / Admin@123  (change after first login)
INSERT IGNORE INTO users (id, full_name, email, password_hash, role) VALUES
 (1, 'Site Admin', 'admin@picklebook.test', '$2b$10$BG6.8thBAvCboXxrr28PZOyjLPC7kyl580rpE9NeqC/5hAMEJ.vo.', 'admin');
