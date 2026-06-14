CREATE DATABASE IF NOT EXISTS Mam_track;
USE Mam_track;

-- Drop existing tables to ensure schema update
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS ordinance_sponsors;
DROP TABLE IF EXISTS sponsors;
DROP TABLE IF EXISTS ordinance_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS ordinances;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Ordinances Table
CREATE TABLE IF NOT EXISTS ordinances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordinance_number VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    date_enacted DATE NOT NULL,
    status ENUM('active', 'under_review', 'draft', 'amended', 'repealed') DEFAULT 'draft',
    department VARCHAR(100),
    main_author VARCHAR(100),
    soft_copy LONGTEXT, -- Rich text/Markdown content
    hard_copy_path VARCHAR(255), -- Path to PDF
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tags Table
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color_theme VARCHAR(20) DEFAULT 'tc-blue',
    description VARCHAR(255)
);

-- Ordinance Tags Junction Table
CREATE TABLE IF NOT EXISTS ordinance_tags (
    ordinance_id INT,
    tag_id INT,
    PRIMARY KEY (ordinance_id, tag_id),
    FOREIGN KEY (ordinance_id) REFERENCES ordinances(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Sponsors/Authors Table
CREATE TABLE IF NOT EXISTS sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Ordinance Sponsors Junction Table (for co-authors)
CREATE TABLE IF NOT EXISTS ordinance_sponsors (
    ordinance_id INT,
    sponsor_id INT,
    PRIMARY KEY (ordinance_id, sponsor_id),
    FOREIGN KEY (ordinance_id) REFERENCES ordinances(id) ON DELETE CASCADE,
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE
);

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'user') DEFAULT 'user',
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Activity Log Table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL, -- create, update, delete, login, logout
    target_type VARCHAR(50), -- ordinance, tag, user
    target_id INT,
    target_name VARCHAR(255),
    description TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert sample tags with colors
INSERT IGNORE INTO tags (name, color_theme) VALUES 
('Zoning', 'tc-blue'), 
('Budget', 'tc-green'), 
('Finance', 'tc-green'), 
('Public Safety', 'tc-red'), 
('Health', 'tc-purple'), 
('Education', 'tc-orange'), 
('Environment', 'tc-teal'), 
('Infrastructure', 'tc-slate'), 
('Agriculture', 'tc-green'), 
('Ordinance', 'tc-blue');

-- Insert sample users (password is 'admin123' and 'user123' hashed)
INSERT IGNORE INTO users (username, password, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@mambajao.gov.ph', 'admin'),
('juan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', 'juan@mambajao.gov.ph', 'user');

-- Insert sample ordinances
INSERT IGNORE INTO ordinances (ordinance_number, title, description, date_enacted, status, department, main_author, soft_copy) VALUES
('ORD-2024-089', 'Zoning Amendment for Mixed-Use Districts', 'An ordinance amending Chapter 15 of the Municipal Code relating to zoning regulations for mixed-use commercial districts.', '2024-10-24', 'under_review', 'Planning Department', 'Cllr. Davis', 'Full text of the zoning amendment...'),
('RES-2024-112', 'Municipal Budget Allocation 2025', 'Resolution approving the municipal budget allocations for the upcoming fiscal year for public parks and recreation facilities.', '2024-10-22', 'active', 'Budget Office', "Mayor's Office", 'Full text of the budget resolution...'),
('ORD-2024-090', 'Downtown Parking Enforcement Hours', 'Proposed changes to downtown parking enforcement hours and fee structures.', '2024-10-20', 'draft', 'Dept. of Transportation', 'Admin', 'Full text of the parking ordinance...');

-- Link tags to ordinances
INSERT IGNORE INTO ordinance_tags (ordinance_id, tag_id) VALUES
(1, 4), -- ORD-2024-089 -> Zoning
(2, 5), -- RES-2024-112 -> Budget
(2, 6), -- RES-2024-112 -> Parks
(3, 7); -- ORD-2024-090 -> Transportation
