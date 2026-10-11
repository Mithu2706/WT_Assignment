-- =====================================================================
--  Student Management Portal  -  U21CS501 Web Technology, Assignment II
--  Shared MySQL / MariaDB schema used by BOTH the PHP admin module and
--  the Java Servlet portal.
--
--  Import:   mysql -u root -p < database/schema.sql
--  WARNING:  re-running this script DROPS and recreates all tables.
--
--  Normalisation (3NF):
--    courses        - one row per course (code + name stored once)
--    students       - one row per student, references a course
--    users          - login accounts (admin / staff / student)
--    student_files  - metadata for every uploaded file, linked to a student
--
--  Cascade decisions (documented in docs/CODE_EXPLANATION.md):
--    students.course_id      -> courses   ON DELETE RESTRICT (cannot delete a
--                                          course that still has students)
--    student_files.student_id-> students  ON DELETE CASCADE  (metadata rows go
--                                          with the student; the PHP code
--                                          deletes the physical files of THAT
--                                          student only)
--    users.student_id        -> students  ON DELETE CASCADE  (a student's own
--                                          login disappears with the student)
--    student_files.uploaded_by -> users   ON DELETE SET NULL (keep the file
--                                          even if the uploader account goes)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS wt_student_portal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE wt_student_portal;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS student_files;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS courses;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- courses: lookup table so a course name is never duplicated per student
-- ---------------------------------------------------------------------
CREATE TABLE courses (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(20)  NOT NULL,             -- e.g. 'BE-CSE' (used by forms)
    name        VARCHAR(120) NOT NULL,             -- e.g. 'B.E. Computer Science and Engineering'
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_courses_code (code),
    UNIQUE KEY uq_courses_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- students: core student record
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,   -- Student ID
    roll_number          VARCHAR(20)  NOT NULL,                  -- stored upper-case
    full_name            VARCHAR(100) NOT NULL,
    email                VARCHAR(150) NOT NULL,                  -- stored lower-case
    course_id            SMALLINT UNSIGNED NOT NULL,
    registration_source  ENUM('admin','self') NOT NULL DEFAULT 'admin',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_roll  (roll_number),
    UNIQUE KEY uq_students_email (email),
    KEY idx_students_name    (full_name),
    KEY idx_students_course  (course_id),
    KEY idx_students_created (created_at),
    CONSTRAINT fk_students_course
        FOREIGN KEY (course_id) REFERENCES courses (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- users: login accounts. Passwords are bcrypt hashes produced by PHP's
-- password_hash(); the Java module verifies the same hashes with jBCrypt.
--   admin   - full CRUD in the PHP admin panel + Java portal
--   staff   - read-only access to every profile in the Java portal
--   student - Java portal access to *their own* profile only
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50)  NOT NULL,
    display_name   VARCHAR(100) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('admin','staff','student') NOT NULL,
    student_id     INT UNSIGNED NULL,                 -- only for role = 'student'
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at  DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_student  (student_id),        -- max one login per student
    KEY idx_users_role (role),
    CONSTRAINT fk_users_student
        FOREIGN KEY (student_id) REFERENCES students (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_users_student_link
        CHECK ((role = 'student' AND student_id IS NOT NULL)
            OR (role <> 'student' AND student_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- student_files: metadata of every uploaded image / document.
-- The physical file lives in STORAGE_DIR/<relative_path>; the path is
-- always generated by the server:  students/<student_id>/<category>/<hex>.<ext>
-- ---------------------------------------------------------------------
CREATE TABLE student_files (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id       INT UNSIGNED NOT NULL,
    category         ENUM('profile_photo','id_proof','certificate','marksheet','other') NOT NULL,
    original_name    VARCHAR(255) NOT NULL,            -- display only, never used as a path
    stored_name      VARCHAR(80)  NOT NULL,            -- random server-generated name
    relative_path    VARCHAR(255) NOT NULL,            -- relative to STORAGE_DIR
    mime_type        VARCHAR(100) NOT NULL,            -- detected on the server (finfo)
    file_size        INT UNSIGNED NOT NULL,            -- bytes
    checksum_sha256  CHAR(64)     NOT NULL,
    uploaded_by      INT UNSIGNED NULL,
    uploaded_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_files_stored_name   (stored_name),
    UNIQUE KEY uq_files_relative_path (relative_path),
    KEY idx_files_student_category (student_id, category, uploaded_at),
    KEY idx_files_uploaded_by (uploaded_by),
    CONSTRAINT fk_files_student
        FOREIGN KEY (student_id) REFERENCES students (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_files_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_files_size CHECK (file_size > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
