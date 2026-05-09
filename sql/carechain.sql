-- CareChain Database Schema
-- Import this via phpMyAdmin: http://localhost/phpmyadmin

CREATE DATABASE IF NOT EXISTS carechain;
USE carechain;

-- Users table (both workers and facilities)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('worker', 'facility', 'admin') NOT NULL,
    wallet_address VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Worker profiles
CREATE TABLE worker_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    job_title ENUM('nurse', 'hca', 'carer', 'midwife', 'physio', 'other') NOT NULL,
    nmbi_number VARCHAR(50) DEFAULT NULL,
    fetac_level VARCHAR(50) DEFAULT NULL,
    years_experience INT DEFAULT 0,
    bio TEXT,
    profile_photo VARCHAR(255) DEFAULT NULL,
    availability ENUM('full_time', 'part_time', 'flexible') DEFAULT 'flexible',
    hourly_rate DECIMAL(10,2) DEFAULT NULL,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_shifts INT DEFAULT 0,
    is_verified TINYINT(1) DEFAULT 0,
    credential_nft_address VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Facility profiles
CREATE TABLE facility_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    facility_name VARCHAR(255) NOT NULL,
    facility_type ENUM('nursing_home', 'hospital', 'home_care', 'clinic', 'rehab', 'other') NOT NULL,
    address VARCHAR(500) NOT NULL,
    city VARCHAR(100) NOT NULL,
    county VARCHAR(100) NOT NULL,
    eircode VARCHAR(10),
    phone VARCHAR(20),
    contact_person VARCHAR(200),
    description TEXT,
    logo VARCHAR(255) DEFAULT NULL,
    rating DECIMAL(3,2) DEFAULT 0.00,
    is_verified TINYINT(1) DEFAULT 0,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Shifts posted by facilities
CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    shift_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL,
    total_pay DECIMAL(10,2) NOT NULL,
    required_role ENUM('nurse', 'hca', 'carer', 'midwife', 'physio', 'any') DEFAULT 'any',
    required_experience INT DEFAULT 0,
    urgency ENUM('normal', 'urgent', 'critical') DEFAULT 'normal',
    status ENUM('open', 'claimed', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    escrow_address VARCHAR(64) DEFAULT NULL,
    escrow_tx_signature VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Shift applications / claims
CREATE TABLE shift_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    worker_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'completed') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    payment_tx_signature VARCHAR(128) DEFAULT NULL,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Documents uploaded by workers
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_type ENUM('nmbi_registration', 'garda_vetting', 'fetac_cert', 'manual_handling', 'patient_moving', 'first_aid', 'covid_cert', 'other') NOT NULL,
    doc_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    nft_mint_address VARCHAR(64) DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Reviews / ratings
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert admin user (password: admin123)
INSERT INTO users (email, password, role) VALUES 
('admin@carechain.io', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
