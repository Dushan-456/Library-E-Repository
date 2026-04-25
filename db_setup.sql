-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS pgim_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pgim_library;

-- Table for Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    speciality VARCHAR(255),
    id_number VARCHAR(100) NOT NULL UNIQUE,
    slmc_number VARCHAR(100),
    role ENUM('Admin', 'User') DEFAULT 'User',
    status ENUM('active', 'inactive') DEFAULT 'active',
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP NULL,
    logout_time TIMESTAMP NULL,
    total_active_time INT COMMENT 'Total active time in seconds',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for Document Access Logs
CREATE TABLE IF NOT EXISTS document_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    access_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for Global Settings
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
);
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('safe_browser_only', '0');

-- Table for File Index (Pre-indexed file paths for fast search)
CREATE TABLE IF NOT EXISTS file_index (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(500) NOT NULL,
    virtual_path VARCHAR(1000) NOT NULL,
    is_dir TINYINT(1) DEFAULT 0,
    file_size BIGINT DEFAULT 0,
    indexed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_filename (file_name),
    FULLTEXT INDEX idx_fulltext (file_name, virtual_path)
) ENGINE=InnoDB;

-- Insert initial Admin user
-- Password is 'PgimLibrary@2026' (bcrypt hash)
INSERT INTO users (first_name, last_name, email, speciality, id_number, slmc_number, role, status, password_hash)
VALUES (
    'Admin',
    'User',
    'library.admin@pgim.ac.lk',
    'Administration',
    'ADM001',
    'SLMC001',
    'Admin',
    'active',
    '$2y$10$cq6t80T5Ta1Ny2jKCPgQae9A6dY7IulurSWawVdRjVh0ZxrA6egcq'
)
ON DUPLICATE KEY UPDATE id_number=id_number;
