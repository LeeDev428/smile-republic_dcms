-- Smile Republic Dental Clinic Management System Database
-- Database: simple_republic_dental_clinic_dc

DROP DATABASE IF EXISTS simple_republic_dental_clinic_dc;
CREATE DATABASE simple_republic_dental_clinic_dc;
USE simple_republic_dental_clinic_dc;

-- Users table (for admin, dentist, frontdesk)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'dentist', 'frontdesk') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Dentist profiles (additional information for dentists)
CREATE TABLE dentist_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    specialization VARCHAR(100),
    license_number VARCHAR(50),
    years_of_experience INT DEFAULT 0,
    education TEXT,
    bio TEXT,
    working_hours JSON, -- Store working schedule as JSON
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Dental operations/services
CREATE TABLE dental_operations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    duration_minutes INT NOT NULL, -- Duration in 15-minute increments
    category VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Patients
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    address TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    medical_history TEXT,
    allergies TEXT,
    insurance_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Appointments
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    dentist_id INT NOT NULL,
    operation_id INT NOT NULL,
    frontdesk_id INT NOT NULL, -- Who created the appointment
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration_minutes INT NOT NULL,
    status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    total_cost DECIMAL(10, 2),
    payment_status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (dentist_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (operation_id) REFERENCES dental_operations(id) ON DELETE CASCADE,
    FOREIGN KEY (frontdesk_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Treatment history
CREATE TABLE treatment_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    dentist_id INT NOT NULL,
    operation_id INT NOT NULL,
    treatment_date DATE NOT NULL,
    diagnosis TEXT,
    treatment_notes TEXT,
    prescription TEXT,
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (dentist_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (operation_id) REFERENCES dental_operations(id) ON DELETE CASCADE
);

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: "password")
INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES 
('admin', 'admin@smilerepublic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', '1234567890');

-- Insert sample dental operations
INSERT INTO dental_operations (name, description, price, duration_minutes, category) VALUES 
('Dental Cleaning', 'Regular dental cleaning and checkup', 75.00, 30, 'Preventive'),
('Tooth Filling', 'Composite or amalgam tooth filling', 150.00, 45, 'Restorative'),
('Root Canal', 'Root canal treatment', 800.00, 90, 'Endodontic'),
('Tooth Extraction', 'Simple tooth extraction', 200.00, 30, 'Oral Surgery'),
('Crown Placement', 'Dental crown installation', 1200.00, 60, 'Restorative'),
('Teeth Whitening', 'Professional teeth whitening', 300.00, 60, 'Cosmetic'),
('Dental Implant', 'Single tooth implant', 2500.00, 120, 'Oral Surgery'),
('Orthodontic Consultation', 'Initial orthodontic evaluation', 100.00, 45, 'Orthodontics');

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES 
('clinic_name', 'Smile Republic Dental Clinic', 'Name of the dental clinic'),
('clinic_address', '123 Dental Street, Health City, HC 12345', 'Clinic physical address'),
('clinic_phone', '(555) 123-SMILE', 'Main clinic phone number'),
('clinic_email', 'info@smilerepublic.com', 'Main clinic email address'),
('appointment_slot_duration', '15', 'Default appointment slot duration in minutes'),
('clinic_hours_start', '08:00', 'Clinic opening time'),
('clinic_hours_end', '18:00', 'Clinic closing time'),
('working_days', 'Monday,Tuesday,Wednesday,Thursday,Friday', 'Working days of the week');
