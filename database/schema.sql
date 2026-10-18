-- =====================================================================
--  Student Portal – Web Technology Assignment II (U21CS501)
--  File   : database/schema.sql
--  Purpose: Creates the shared MySQL database used by BOTH
--           (a) the PHP admin panel   (Question 2) and
--           (b) the Servlet/JSP portal (Question 3).
--
--  Import :  mysql -u root -p < database/schema.sql
--            (or phpMyAdmin → Import → choose this file → Go)
--
--  NOTE   :  Running this script again DROPS and re-creates the tables,
--            so all existing data is reset to the demo data below.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS student_portal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE student_portal;

-- Drop in child → parent order so foreign keys do not block the drop.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS student_files;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. users : accounts allowed to log in to the admin panel (PHP) and
--            the student-profile portal (Servlet). Passwords are stored
--            ONLY as bcrypt hashes created by PHP password_hash().
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id             INT          NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50)  NOT NULL,
    full_name      VARCHAR(100) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login     DATETIME     NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_users_username UNIQUE (username)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 2. students : one row per student record managed by the admin.
--    roll_number and email are UNIQUE so duplicates are rejected by
--    the database itself (the PHP code also checks before inserting).
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id             INT          NOT NULL AUTO_INCREMENT,
    name           VARCHAR(100) NOT NULL,
    roll_number    VARCHAR(20)  NOT NULL,
    email          VARCHAR(150) NOT NULL,
    course         VARCHAR(100) NOT NULL,
    phone          VARCHAR(15)  NULL,
    date_of_birth  DATE         NULL,
    address        VARCHAR(255) NULL,
    password_hash  VARCHAR(255) NULL,   -- student's own portal password (bcrypt)
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_students_roll  UNIQUE (roll_number),
    CONSTRAINT uq_students_email UNIQUE (email),
    INDEX idx_students_course (course),
    INDEX idx_students_name (name)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 3. student_files : METADATA of every uploaded file.
--    The file itself lives on disk (Question2_PHP/uploads/...). Here we keep:
--      student_id    → which student owns it (foreign key)
--      original_name → name on the user's computer (display only)
--      stored_name   → random unique name used on disk (never overwritten)
--      file_path     → path RELATIVE to the Question2_PHP folder
--      file_type     → MIME type detected from the file content
--      file_size     → size in bytes
--      file_category → 'profile' (photo) or 'document' (ID proof etc.)
--      upload_date   → when it was uploaded
--    ON DELETE CASCADE removes metadata automatically when a student is
--    deleted (the PHP code deletes the physical files as well).
-- ---------------------------------------------------------------------
CREATE TABLE student_files (
    id              INT             NOT NULL AUTO_INCREMENT,
    student_id      INT             NOT NULL,
    file_category   VARCHAR(20)     NOT NULL,
    document_label  VARCHAR(50)     NULL,
    original_name   VARCHAR(255)    NOT NULL,
    stored_name     VARCHAR(100)    NOT NULL,
    file_path       VARCHAR(255)    NOT NULL,
    file_type       VARCHAR(100)    NOT NULL,
    file_size       BIGINT UNSIGNED NOT NULL,
    upload_date     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_files_stored_name UNIQUE (stored_name),
    CONSTRAINT fk_files_student FOREIGN KEY (student_id)
        REFERENCES students (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT chk_files_category CHECK (file_category IN ('profile', 'document')),
    CONSTRAINT chk_files_size CHECK (file_size > 0),
    INDEX idx_files_student (student_id, file_category)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 4. Demo login accounts (works in BOTH the PHP admin and the Servlet portal)
--      admin   / Admin@123
--      faculty / Faculty@123
--    The values below are bcrypt hashes generated with PHP:
--      php -r "echo password_hash('Admin@123', PASSWORD_DEFAULT);"
-- ---------------------------------------------------------------------
INSERT INTO users (username, full_name, password_hash) VALUES
('admin',   'Portal Administrator', '$2y$10$rtNkATLoMhzfYSfWd7eFnegrB3XSRUkyxVxvPk0iILTiKtD1rlohG'),
('faculty', 'Faculty Coordinator',  '$2y$10$5scaqvaYwUGbqBW2BVTB.O4Ac5XUvHCsQsOrBkOQuhZ4numAOsu.2');

-- ---------------------------------------------------------------------
-- 5. Sample students. Every sample student's portal password is
--    Student@123 (stored as a bcrypt hash, never as plain text).
--    Profile photos / documents are uploaded through the PHP admin panel.
-- ---------------------------------------------------------------------
INSERT INTO students (name, roll_number, email, course, phone, date_of_birth, address, password_hash) VALUES
('Arjun Krishnan',      '23CS101', 'arjun.krishnan@example.com',  'B.E. Computer Science and Engineering',
 '9876543210', '2005-04-12', '12, Gandhi Nagar, Coimbatore',  '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse'),
('Priya Ramesh',        '23CS102', 'priya.ramesh@example.com',    'B.E. Computer Science and Engineering',
 '9876501234', '2005-08-23', '45, Race Course Road, Coimbatore', '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse'),
('Karthik Subramanian', '23IT105', 'karthik.s@example.com',       'B.Tech. Information Technology',
 '9123456780', '2004-12-02', '8, Anna Salai, Tiruppur',       '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse'),
('Divya Lakshmi',       '23EC110', 'divya.lakshmi@example.com',   'B.E. Electronics and Communication Engineering',
 '9988776655', '2005-02-17', '21, Avinashi Road, Coimbatore', '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse'),
('Mohammed Faizal',     '23AD103', 'faizal.m@example.com',        'B.Tech. Artificial Intelligence and Data Science',
 '9445566778', '2005-06-30', '3, Big Bazaar Street, Erode',   '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse'),
('Sneha Venkatesh',     '23ME108', 'sneha.v@example.com',         'B.E. Mechanical Engineering',
 '9001122334', '2004-10-09', '67, Mettupalayam Road, Coimbatore', '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse');
