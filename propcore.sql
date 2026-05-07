-- ============================================
-- PROPCORE DATABASE SCHEMA
-- Run this in phpMyAdmin or MySQL CLI
-- ============================================

CREATE DATABASE IF NOT EXISTS propcore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE propcore;

-- ============================================
-- TABLE 1: PROPERTIES & UNITS
-- ============================================
CREATE TABLE IF NOT EXISTS properties (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    address     VARCHAR(255) NOT NULL,
    city        VARCHAR(100) NOT NULL,
    total_units INT DEFAULT 0,
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS units (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    property_id   INT NOT NULL,
    unit_number   VARCHAR(20) NOT NULL,
    floor         INT DEFAULT 1,
    bedrooms      INT DEFAULT 1,
    rent_amount   DECIMAL(10,2) NOT NULL,
    status        ENUM('vacant','occupied','maintenance') DEFAULT 'vacant',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE 2: TENANTS
-- ============================================
CREATE TABLE IF NOT EXISTS tenants (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    unit_id       INT,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) UNIQUE NOT NULL,
    phone         VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    lease_start   DATE,
    lease_end     DATE,
    status        ENUM('active','inactive','evicted') DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
);

-- ============================================
-- TABLE 3: WORKERS / STAFF
-- ============================================
CREATE TABLE IF NOT EXISTS workers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(150) UNIQUE NOT NULL,
    phone       VARCHAR(20),
    skill       ENUM('plumbing','electrical','carpentry','cleaning','general','HVAC') NOT NULL,
    status      ENUM('available','busy','offline') DEFAULT 'available',
    latitude    DECIMAL(10,8),
    longitude   DECIMAL(11,8),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE 4: MAINTENANCE REQUESTS
-- ============================================
CREATE TABLE IF NOT EXISTS maintenance_requests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    unit_id       INT NOT NULL,
    tenant_id     INT NOT NULL,
    worker_id     INT,
    title         VARCHAR(200) NOT NULL,
    description   TEXT,
    category      ENUM('plumbing','electrical','carpentry','cleaning','general','HVAC') NOT NULL,
    priority      ENUM('low','medium','high','critical') DEFAULT 'medium',
    status        ENUM('open','assigned','in_progress','resolved','closed') DEFAULT 'open',
    photo_path    VARCHAR(255),
    submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_at   TIMESTAMP NULL,
    resolved_at   TIMESTAMP NULL,
    FOREIGN KEY (unit_id)    REFERENCES units(id)    ON DELETE CASCADE,
    FOREIGN KEY (tenant_id)  REFERENCES tenants(id)  ON DELETE CASCADE,
    FOREIGN KEY (worker_id)  REFERENCES workers(id)  ON DELETE SET NULL
);

-- ============================================
-- TABLE 5: ADMIN USERS
-- ============================================
CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- SAMPLE DATA
-- ============================================

INSERT INTO properties (name, address, city, total_units) VALUES
('Sunrise Apartments', '12 Njoro Road', 'Nakuru', 20),
('Greenview Estate', '45 Moi Avenue', 'Nairobi', 15),
('Lakeside Towers', '8 Lake Road', 'Kisumu', 10);

INSERT INTO units (property_id, unit_number, floor, bedrooms, rent_amount, status) VALUES
(1, 'A101', 1, 2, 18000.00, 'occupied'),
(1, 'A102', 1, 1, 12000.00, 'vacant'),
(1, 'B201', 2, 3, 25000.00, 'occupied'),
(2, 'C101', 1, 2, 22000.00, 'occupied'),
(2, 'C102', 1, 2, 22000.00, 'maintenance'),
(3, 'D101', 1, 1, 9000.00,  'occupied');

INSERT INTO tenants (unit_id, first_name, last_name, email, phone, password_hash, lease_start, lease_end) VALUES
(1, 'James',  'Mwangi',  'james@email.com',  '0712345678', SHA2('pass123', 256), '2024-01-01', '2025-01-01'),
(3, 'Sarah',  'Kimani',  'sarah@email.com',  '0723456789', SHA2('pass123', 256), '2024-03-01', '2025-03-01'),
(4, 'Peter',  'Ochieng', 'peter@email.com',  '0734567890', SHA2('pass123', 256), '2024-06-01', '2025-06-01'),
(6, 'Grace',  'Wanjiku', 'grace@email.com',  '0745678901', SHA2('pass123', 256), '2024-02-01', '2025-02-01');

INSERT INTO workers (first_name, last_name, email, phone, skill, status) VALUES
('Ali',    'Hassan',  'ali@propcore.com',    '0756789012', 'plumbing',   'available'),
('David',  'Njoroge', 'david@propcore.com',  '0767890123', 'electrical', 'busy'),
('Mary',   'Achieng', 'mary@propcore.com',   '0778901234', 'cleaning',   'available'),
('Kevin',  'Mutua',   'kevin@propcore.com',  '0789012345', 'carpentry',  'offline'),
('Fatuma', 'Omar',    'fatuma@propcore.com', '0790123456', 'HVAC',       'available');

INSERT INTO maintenance_requests (unit_id, tenant_id, worker_id, title, description, category, priority, status, submitted_at, assigned_at) VALUES
(1, 1, 1, 'Leaking kitchen pipe',     'Water dripping under the sink for 2 days.',         'plumbing',   'high',     'assigned',    NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 1 DAY),
(3, 2, 2, 'Power socket not working', 'The socket near the bedroom door has no power.',    'electrical', 'medium',   'in_progress', NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 2 DAY),
(4, 3, NULL,'Broken door hinge',      'The main door hinge is loose and squeaking badly.', 'carpentry',  'low',      'open',        NOW() - INTERVAL 1 DAY, NULL),
(6, 4, 3, 'Deep cleaning needed',     'Moving in, unit needs thorough cleaning.',           'cleaning',   'low',      'resolved',    NOW() - INTERVAL 7 DAY, NOW() - INTERVAL 6 DAY);

INSERT INTO admins (name, email, password_hash) VALUES
('PropCore Admin', 'admin@propcore.com', SHA2('admin123', 256));

-- ============================================
-- USEFUL VIEWS
-- ============================================

CREATE OR REPLACE VIEW v_requests_full AS
SELECT
    mr.id,
    mr.title,
    mr.category,
    mr.priority,
    mr.status,
    mr.submitted_at,
    mr.resolved_at,
    CONCAT(t.first_name, ' ', t.last_name) AS tenant_name,
    t.phone AS tenant_phone,
    u.unit_number,
    p.name AS property_name,
    p.city,
    CONCAT(w.first_name, ' ', w.last_name) AS worker_name,
    w.skill AS worker_skill,
    w.status AS worker_status
FROM maintenance_requests mr
JOIN units u    ON mr.unit_id   = u.id
JOIN properties p ON u.property_id = p.id
JOIN tenants t  ON mr.tenant_id = t.id
LEFT JOIN workers w ON mr.worker_id = w.id;

CREATE OR REPLACE VIEW v_property_summary AS
SELECT
    p.id,
    p.name,
    p.city,
    p.total_units,
    COUNT(CASE WHEN u.status = 'occupied'    THEN 1 END) AS occupied,
    COUNT(CASE WHEN u.status = 'vacant'      THEN 1 END) AS vacant,
    COUNT(CASE WHEN u.status = 'maintenance' THEN 1 END) AS in_maintenance,
    COUNT(CASE WHEN mr.status = 'open'       THEN 1 END) AS open_requests
FROM properties p
LEFT JOIN units u ON p.id = u.property_id
LEFT JOIN maintenance_requests mr ON u.id = mr.unit_id
GROUP BY p.id;
