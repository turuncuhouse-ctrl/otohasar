-- OTOHASAR Database Schema
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS auth_tokens;
DROP TABLE IF EXISTS file_logs;
DROP TABLE IF EXISTS file_documents;
DROP TABLE IF EXISTS damage_files;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    role ENUM('advisor','manager','workshop','admin') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    tc_vkn VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    plate VARCHAR(20) NOT NULL UNIQUE,
    chassis_no VARCHAR(50) DEFAULT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year SMALLINT UNSIGNED DEFAULT NULL,
    color VARCHAR(30) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE damage_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    advisor_id INT UNSIGNED NOT NULL,
    file_number VARCHAR(20) NOT NULL UNIQUE,
    insurance_company VARCHAR(100) DEFAULT NULL,
    policy_no VARCHAR(50) DEFAULT NULL,
    claim_no VARCHAR(50) DEFAULT NULL,
    status ENUM('evrak_bekliyor','eksperde','parca_bekliyor','onarimda','teslime_hazir','tamamlandi') NOT NULL DEFAULT 'evrak_bekliyor',
    status_changed_at DATETIME DEFAULT NULL,
    note TEXT DEFAULT NULL,
    workshop_upload_until DATETIME DEFAULT NULL,
    workshop_upload_hours INT UNSIGNED DEFAULT NULL,
    workshop_upload_granted_by INT UNSIGNED DEFAULT NULL,
    customer_upload_until DATETIME DEFAULT NULL,
    customer_upload_hours INT UNSIGNED DEFAULT NULL,
    customer_upload_granted_by INT UNSIGNED DEFAULT NULL,
    customer_upload_token VARCHAR(64) DEFAULT NULL,
    customer_upload_note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE file_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    damage_file_id INT UNSIGNED NOT NULL,
    category ENUM('ruhsat','ehliyet','tutanak','hasar_foto','ekspertiz','onarim','diger') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (damage_file_id) REFERENCES damage_files(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE file_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    damage_file_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action_description VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (damage_file_id) REFERENCES damage_files(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial system admin — password 1234 (change after first login)
-- Placeholder hash; Docker entrypoint / migrate_v8 resets to bcrypt(1234) on create
SET @pwd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

INSERT INTO users (name, username, role, email, phone, password) VALUES
('Sistem Admin', 'admin', 'admin', 'admin@otohasar.local', NULL, @pwd);
