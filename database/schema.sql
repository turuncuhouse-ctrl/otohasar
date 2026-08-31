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
    role ENUM('advisor','manager','workshop') NOT NULL,
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
    note TEXT DEFAULT NULL,
    workshop_upload_until DATETIME DEFAULT NULL,
    workshop_upload_hours INT UNSIGNED DEFAULT NULL,
    workshop_upload_granted_by INT UNSIGNED DEFAULT NULL,
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
    uploaded_by INT UNSIGNED NOT NULL,
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

-- Demo password for all users: 1234
SET @pwd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

INSERT INTO users (name, username, role, email, phone, password) VALUES
('Ahmet Yılmaz', 'hasardanismandemo', 'advisor', 'ahmet@otohasar.demo', '05321234567', @pwd),
('Burak Şahin', 'hasardanisman2demo', 'advisor', 'burak@otohasar.demo', '05329876543', @pwd),
('Elif Kaya', 'yoneticidemo', 'manager', 'elif@otohasar.demo', '05321112233', @pwd),
('Mehmet Demir', 'atolyedemo', 'workshop', 'mehmet@otohasar.demo', '05324445566', @pwd);

INSERT INTO customers (name, phone, tc_vkn, email) VALUES
('Ali Veli', '05321111111', '12345678901', 'ali@email.com'),
('Ayşe Demir', '05322222222', '23456789012', 'ayse@email.com'),
('Mustafa Kaya', '05323333333', '34567890123', 'mustafa@email.com'),
('Fatma Öztürk', '05324444444', '45678901234', 'fatma@email.com'),
('Hasan Çelik', '05325555555', '56789012345', 'hasan@email.com'),
('Zeynep Arslan', '05326666666', '67890123456', 'zeynep@email.com');

INSERT INTO vehicles (customer_id, plate, chassis_no, brand, model, year, color) VALUES
(1, '35 ABC 123', 'WVWZZZ1JZ3W386752', 'Volkswagen', 'Golf', 2020, 'Beyaz'),
(2, '06 XYZ 456', 'WBA3A51050F123456', 'BMW', '320i', 2019, 'Siyah'),
(3, '34 DEF 789', 'KMHDN45D75U123456', 'Hyundai', 'i20', 2021, 'Gri'),
(4, '16 GHI 012', 'VF1RJA00261234567', 'Renault', 'Clio', 2018, 'Kırmızı'),
(5, '07 JKL 345', 'WDD2040491A123456', 'Mercedes', 'C180', 2017, 'Gümüş'),
(6, '41 MNO 678', 'NMTBZ3BE00R123456', 'Toyota', 'Corolla', 2022, 'Mavi');

INSERT INTO damage_files (vehicle_id, advisor_id, file_number, insurance_company, policy_no, claim_no, status, note) VALUES
(1, 1, 'HD-26-0001', 'Anadolu Sigorta', 'POL-2026-001', 'CLM-001', 'evrak_bekliyor', 'Ön tampon hasarı'),
(2, 1, 'HD-26-0002', 'Allianz', 'POL-2026-002', 'CLM-002', 'eksperde', 'Sağ ön çamurluk'),
(3, 2, 'HD-26-0003', 'Axa Sigorta', 'POL-2026-003', 'CLM-003', 'parca_bekliyor', 'Arka kapı değişimi'),
(4, 2, 'HD-26-0004', 'Mapfre', 'POL-2026-004', 'CLM-004', 'onarimda', 'Motor kaputu onarımı'),
(5, 1, 'HD-26-0005', 'HDI Sigorta', 'POL-2026-005', 'CLM-005', 'teslime_hazir', 'Tampon boyası tamamlandı'),
(6, 2, 'HD-26-0006', 'Groupama', 'POL-2026-006', 'CLM-006', 'tamamlandi', 'Dosya kapatıldı');

-- Seed documents (paths relative to public/)
INSERT INTO file_documents (damage_file_id, category, file_path, original_name, mime_type, file_size, uploaded_by) VALUES
(1, 'ruhsat', 'uploads/seed/doc1_ruhsat.jpg', 'ruhsat.jpg', 'image/jpeg', 2048, 1),
(1, 'hasar_foto', 'uploads/seed/doc1_hasar.jpg', 'hasar_on.jpg', 'image/jpeg', 3072, 1),
(2, 'ehliyet', 'uploads/seed/doc2_ehliyet.jpg', 'ehliyet.jpg', 'image/jpeg', 2048, 1),
(2, 'tutanak', 'uploads/seed/doc2_tutanak.jpg', 'tutanak.jpg', 'image/jpeg', 2560, 1),
(2, 'hasar_foto', 'uploads/seed/doc2_hasar.jpg', 'hasar.jpg', 'image/jpeg', 3072, 1),
(3, 'ekspertiz', 'uploads/seed/doc3_ekspertiz.jpg', 'ekspertiz.jpg', 'image/jpeg', 2048, 2),
(3, 'hasar_foto', 'uploads/seed/doc3_hasar.jpg', 'hasar.jpg', 'image/jpeg', 3072, 2),
(4, 'onarim', 'uploads/seed/doc4_onarim.jpg', 'onarim1.jpg', 'image/jpeg', 2560, 4),
(4, 'hasar_foto', 'uploads/seed/doc4_hasar.jpg', 'hasar.jpg', 'image/jpeg', 3072, 2),
(5, 'onarim', 'uploads/seed/doc5_onarim.jpg', 'onarim.jpg', 'image/jpeg', 2048, 4),
(5, 'ruhsat', 'uploads/seed/doc5_ruhsat.jpg', 'ruhsat.jpg', 'image/jpeg', 2048, 1),
(6, 'diger', 'uploads/seed/doc6_diger.jpg', 'fatura.jpg', 'image/jpeg', 2048, 2);

INSERT INTO file_logs (damage_file_id, user_id, action_description) VALUES
(1, 1, 'Hasar dosyası açıldı (HD-26-0001)'),
(1, 1, '2 evrak yüklendi (Ruhsat, Hasar Foto)'),
(1, 1, 'Durum: evrak_bekliyor'),
(2, 1, 'Hasar dosyası açıldı (HD-26-0002)'),
(2, 1, '3 evrak yüklendi (Ehliyet, Tutanak, Hasar Foto)'),
(2, 1, 'Durum evrak_bekliyor → eksperde'),
(3, 2, 'Hasar dosyası açıldı (HD-26-0003)'),
(3, 2, '2 evrak yüklendi (Ekspertiz, Hasar Foto)'),
(3, 2, 'Durum eksperde → parca_bekliyor'),
(4, 2, 'Hasar dosyası açıldı (HD-26-0004)'),
(4, 2, '2 evrak yüklendi (Onarım, Hasar Foto)'),
(4, 4, 'Durum parca_bekliyor → onarimda'),
(5, 1, 'Hasar dosyası açıldı (HD-26-0005)'),
(5, 4, '2 evrak yüklendi (Onarım, Ruhsat)'),
(5, 4, 'Durum onarimda → teslime_hazir'),
(6, 2, 'Hasar dosyası açıldı (HD-26-0006)'),
(6, 2, '1 evrak yüklendi (Diğer)'),
(6, 3, 'Durum teslime_hazir → tamamlandi');
