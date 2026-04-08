-- Create Database
CREATE DATABASE doctor_appointment_system;
USE doctor_appointment_system;

-- =========================
-- USERS TABLE
-- =========================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(150) UNIQUE,
    password_hashed VARCHAR(255) NOT NULL,
    user_type ENUM('patient','doctor','admin') NOT NULL DEFAULT 'patient',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- DOCTOR SPECIALIZED CATEGORIES
-- =========================
CREATE TABLE doctors_specialized_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- DOCTORS TABLE
-- =========================
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialized_area INT NOT NULL,
    years_of_experience INT DEFAULT 0,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (specialized_area) REFERENCES doctors_specialized_categories(id) ON DELETE SET NULL
);

-- =========================
-- APPOINTMENTS (Doctor Setup)
-- =========================
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_user_id INT NOT NULL,
    hospital_location VARCHAR(255),
    hospital_name VARCHAR(150),
    chamber_location VARCHAR(255),
    visiting_fee DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (doctor_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =========================
-- APPOINTMENT SCHEDULES
-- =========================
CREATE TABLE appointment_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    apointment_day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    available_start_time TIME NOT NULL,
    appointment_duration_max INT NOT NULL COMMENT 'Duration in minutes',

    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

-- =========================
-- BOOKED APPOINTMENTS
-- =========================
CREATE TABLE booked_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_user_id INT NOT NULL,
    appointment_id INT NOT NULL,
    appointment_schedule_id INT NOT NULL,
    appointment_date DATE NOT NULL,

    FOREIGN KEY (booking_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_schedule_id) REFERENCES appointment_schedules(id) ON DELETE CASCADE
);