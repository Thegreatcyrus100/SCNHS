-- ═══════════════════════════════════════════════════════════
-- SCNHS Enrollment System — Full Database Schema
-- Database: enrollment_db
-- Generated: 2025
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `enrollment_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `enrollment_db`;


-- ─────────────────────────────────────────────────────────
-- 1. STUDENTS — Main enrollment records
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `students` (
    `id`                      INT AUTO_INCREMENT PRIMARY KEY,
    `lrn`                     VARCHAR(20) NOT NULL UNIQUE COMMENT 'Learner Reference Number',
    `password`                VARCHAR(255) DEFAULT NULL COMMENT 'Hashed password for student portal',

    -- Personal Info
    `first_name`              VARCHAR(100) NOT NULL,
    `middle_name`             VARCHAR(100) DEFAULT NULL,
    `last_name`               VARCHAR(100) NOT NULL,
    `extension_name`          VARCHAR(20) DEFAULT NULL COMMENT 'Jr., Sr., III, etc.',
    `dob`                     DATE DEFAULT NULL COMMENT 'Date of birth',
    `age`                     VARCHAR(10) DEFAULT NULL,
    `gender`                  VARCHAR(20) DEFAULT NULL,
    `birthplace`              VARCHAR(255) DEFAULT NULL,
    `contact`                 VARCHAR(20) DEFAULT NULL,

    -- Academic Info
    `grade`                   INT DEFAULT NULL COMMENT 'Grade level (11 or 12)',
    `grade_level`             VARCHAR(20) DEFAULT NULL COMMENT 'Alternative grade level field',
    `track`                   VARCHAR(100) DEFAULT NULL COMMENT 'Academic, TVL, Sports, Arts',
    `strand`                  VARCHAR(100) DEFAULT NULL COMMENT 'STEM, ABM, HUMSS, GAS, etc.',
    `section`                 VARCHAR(100) DEFAULT NULL COMMENT 'Assigned section name',
    `section_id`              INT DEFAULT NULL COMMENT 'FK to sections table',
    `section_code`            VARCHAR(50) DEFAULT NULL,
    `learning_modality`       VARCHAR(100) DEFAULT NULL COMMENT 'Face-to-face, Online, etc.',
    `school_year`             VARCHAR(20) DEFAULT NULL COMMENT 'e.g. 2024-2025',
    `semester`                VARCHAR(20) DEFAULT NULL COMMENT '1st or 2nd semester',

    -- Current Address
    `province`                VARCHAR(100) DEFAULT NULL,
    `municipality`            VARCHAR(100) DEFAULT NULL,
    `barangay`                VARCHAR(100) DEFAULT NULL,
    `street`                  VARCHAR(255) DEFAULT NULL,
    `house_number`            VARCHAR(50) DEFAULT NULL,
    `zip_code`                VARCHAR(10) DEFAULT NULL,

    -- Permanent Address (used in print/PDF)
    `permanent_street`        VARCHAR(255) DEFAULT NULL,
    `permanent_barangay`      VARCHAR(100) DEFAULT NULL,
    `permanent_municipality`  VARCHAR(100) DEFAULT NULL,
    `permanent_province`      VARCHAR(100) DEFAULT NULL,
    `permanent_zip_code`      VARCHAR(10) DEFAULT NULL,

    -- Additional Info
    `disability`              VARCHAR(255) DEFAULT NULL,
    `ip_community`            VARCHAR(255) DEFAULT NULL COMMENT 'Indigenous Peoples community',
    `FourPs`                  VARCHAR(50) DEFAULT NULL COMMENT '4Ps beneficiary status',
    `indigenous`              VARCHAR(255) DEFAULT NULL,
    `mother_tongue`           VARCHAR(100) DEFAULT NULL,

    -- Father Info
    `father_last_name`        VARCHAR(100) DEFAULT NULL,
    `father_first_name`       VARCHAR(100) DEFAULT NULL,
    `father_middle_name`      VARCHAR(100) DEFAULT NULL,

    -- Mother Info
    `mother_last_name`        VARCHAR(100) DEFAULT NULL,
    `mother_first_name`       VARCHAR(100) DEFAULT NULL,
    `mother_middle_name`      VARCHAR(100) DEFAULT NULL,

    -- Guardian Info
    `guardian_last_name`      VARCHAR(100) DEFAULT NULL,
    `guardian_first_name`     VARCHAR(100) DEFAULT NULL,
    `guardian_middle_name`    VARCHAR(100) DEFAULT NULL,
    `contact_guardian_parent` VARCHAR(20) DEFAULT NULL,

    -- Returning/Transfer Student Info
    `is_returning_or_transfer` VARCHAR(50) DEFAULT NULL,
    `last_grade_level`         VARCHAR(20) DEFAULT NULL,
    `last_school_year`         VARCHAR(20) DEFAULT NULL,
    `previous_school`          VARCHAR(255) DEFAULT NULL,
    `previous_school_id`       VARCHAR(50) DEFAULT NULL,

    -- Timestamps
    `created_at`              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status`                  ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'Enrollment approval status',

    INDEX `idx_grade`    (`grade`),
    INDEX `idx_strand`   (`strand`),
    INDEX `idx_section`  (`section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 2. ADMINS — Admin login credentials (regular admins)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed via password_hash()',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 2b. REGISTRARS — Registrar login credentials
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `registrars` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed via password_hash()',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 3. ACCOUNTS — Superadmin accounts (elevated access)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `accounts` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed via password_hash()',
    `role`       VARCHAR(20) NOT NULL DEFAULT 'superadmin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 4. ADMIN — Legacy admin table (used by superadmin_dashboard)
--    NOTE: This table is referenced in superadmin_dashboard.php
--    for creating/listing admins. Keeping for compatibility.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed via password_hash()',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 5. SECTIONS — Class sections for strand-based grouping
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sections` (
    `section_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `section_name` VARCHAR(100) NOT NULL,
    `grade_level`  VARCHAR(20) DEFAULT NULL,
    `teacher_name` VARCHAR(100) DEFAULT NULL,
    `track`        VARCHAR(100) DEFAULT NULL,
    `strand`       VARCHAR(100) DEFAULT NULL,
    `section_code` VARCHAR(50) DEFAULT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 6. ATTENDANCE — Daily attendance tracking via barcode scan
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attendance` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `student_id`    INT NOT NULL,
    `lrn`           VARCHAR(20) NOT NULL,
    `student_name`  VARCHAR(100) DEFAULT NULL,
    `check_in_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `date`          DATE DEFAULT NULL,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX `idx_date`       (`date`),
    INDEX `idx_student_id` (`student_id`),
    UNIQUE KEY `unique_attendance` (`student_id`, `date`) COMMENT 'Prevent duplicate check-ins per day'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 7. TEACHERS — Teacher portal accounts
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `teachers` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id`   VARCHAR(50) NOT NULL UNIQUE COMMENT 'e.g. TCH-001',
    `password`      VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed via password_hash()',
    `first_name`    VARCHAR(100) NOT NULL,
    `last_name`     VARCHAR(100) NOT NULL,
    `department`    VARCHAR(100) DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 8. GRADES — Quarterly grades per student per subject
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `grades` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `student_id`    INT NOT NULL,
    `lrn`           VARCHAR(20) NOT NULL,
    `subject_name`  VARCHAR(100) NOT NULL,
    `term1`         DECIMAL(5,2) DEFAULT NULL,
    `term2`         DECIMAL(5,2) DEFAULT NULL,
    `term3`         DECIMAL(5,2) DEFAULT NULL,
    `final`         DECIMAL(5,2) DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `unique_grade` (`student_id`, `subject_name`),
    INDEX `idx_lrn` (`lrn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 9. ANNOUNCEMENTS — School announcements
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `announcements` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `title`           VARCHAR(255) NOT NULL,
    `content`         TEXT NOT NULL,
    `author`          VARCHAR(100) DEFAULT NULL,
    `published_date`  DATE DEFAULT NULL,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 10. CALENDAR_EVENTS — School calendar events
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `calendar_events` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `event_title`   VARCHAR(255) NOT NULL,
    `event_date`    DATE NOT NULL,
    `description`   TEXT DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 11. AUDIT_LOGS — Security audit trail
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`       VARCHAR(100) DEFAULT NULL COMMENT 'LRN, employee_id, or username',
    `role`          VARCHAR(50) DEFAULT NULL COMMENT 'student, teacher, admin, superadmin',
    `action`        VARCHAR(255) DEFAULT NULL,
    `ip_address`    VARCHAR(45) DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 12. SETTINGS — Global application settings
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` VARCHAR(255) DEFAULT NULL,
    `description`   VARCHAR(255) DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('enable_final_exam', '0', 'Toggle final exam visibility/access'),
('enable_show_grades', '0', 'Toggle visibility of grades to students');


-- ─────────────────────────────────────────────────────────
-- DEFAULT TEST ACCOUNTS (Uncomment to insert)
-- Passwords are bcrypt hashes — change after first login!
-- ─────────────────────────────────────────────────────────

-- Default admin (username: admin, password: admin123)
-- INSERT INTO `admins` (`username`, `password`) VALUES ('admin', '$2y$10$YourHashHere');

-- Default superadmin (username: superadmin, password: super123)
-- INSERT INTO `accounts` (`username`, `password`, `role`) VALUES ('superadmin', '$2y$10$YourHashHere', 'superadmin');

-- Default registrar (username: registrar, password: registrar123)
-- INSERT INTO `registrars` (`username`, `password`) VALUES ('registrar', '$2y$10$YourHashHere');

-- ─────────────────────────────────────────────────────────
-- 7. SETTINGS — Global system configuration
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) DEFAULT NULL,
    description   VARCHAR(255) DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
('enable_final_exam', '0', 'Toggle final exam visibility/access'),
('enable_show_grades', '0', 'Toggle visibility of grades to students'),
('enable_sslg_voting', '0', 'Toggle SSLG election voting for students');


-- ─────────────────────────────────────────────────────────
-- 8. DOCUMENT REQUESTS
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    purpose TEXT,
    status ENUM('pending','processing','ready','released') DEFAULT 'pending',
    
emarks TEXT,
    
equested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 9. SCHOOL CLEARANCES
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clearances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    adviser_status ENUM('pending','cleared') DEFAULT 'pending',
    library_status ENUM('pending','cleared') DEFAULT 'pending',
    cashier_status ENUM('pending','cleared') DEFAULT 'pending',
    property_status ENUM('pending','cleared') DEFAULT 'pending',
    clinic_status ENUM('pending','cleared') DEFAULT 'pending',
    guidance_status ENUM('pending','cleared') DEFAULT 'pending',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 10. HEALTH RECORDS (SF8)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS health_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    height_cm DECIMAL(5,2),
    weight_kg DECIMAL(5,2),
    bmi DECIMAL(4,2),
    nutritional_status VARCHAR(50),
    ision_left VARCHAR(20),
    ision_right VARCHAR(20),
    blood_type VARCHAR(5),
    medical_history TEXT,
    recorded_by VARCHAR(100),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 11. SSLG ELECTIONS
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sslg_candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position VARCHAR(100) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    grade_level VARCHAR(10),
    strand VARCHAR(50),
    platform TEXT,
    photo_path VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sslg_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    candidate_id INT NOT NULL,
    position VARCHAR(100),
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES sslg_candidates(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote_per_position (student_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─────────────────────────────────────────────────────────
-- 12. WORK IMMERSION (GRADE 12)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS immersion_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    company_name VARCHAR(200),
    company_address TEXT,
    supervisor_name VARCHAR(150),
    supervisor_contact VARCHAR(50),
    
equired_hours INT DEFAULT 80,
    completed_hours INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    status ENUM('not_started','ongoing','completed') DEFAULT 'not_started',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS immersion_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    log_date DATE NOT NULL,
    hours_rendered DECIMAL(4,2) NOT NULL,
    	asks_done TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
