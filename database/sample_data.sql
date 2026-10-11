-- =====================================================================
--  Sample data for local demonstration ONLY.
--  Import AFTER schema.sql:   mysql -u root -p wt_student_portal < database/sample_data.sql
--
--  * Courses and students only - NO user accounts and NO passwords here.
--    Create the first admin securely with:
--        php php-admin/bin/create_user.php --role=admin --username=admin
--  * Demo files (generated avatars / PDFs, not real documents) are imported
--    through the real upload validator with:
--        php php-admin/bin/seed_sample_files.php
-- =====================================================================

USE wt_student_portal;

INSERT INTO courses (code, name) VALUES
    ('BE-CSE',   'B.E. Computer Science and Engineering'),
    ('BE-ECE',   'B.E. Electronics and Communication Engineering'),
    ('BE-EEE',   'B.E. Electrical and Electronics Engineering'),
    ('BE-MECH',  'B.E. Mechanical Engineering'),
    ('BTECH-IT', 'B.Tech. Information Technology'),
    ('BTECH-AD', 'B.Tech. Artificial Intelligence and Data Science');

-- Course ids are looked up by code so the script does not depend on AUTO_INCREMENT values.
INSERT INTO students (roll_number, full_name, email, course_id, registration_source)
SELECT v.roll_number, v.full_name, v.email, c.id, 'admin'
FROM (
    SELECT '23CS001' AS roll_number, 'Aarav Krishnan'   AS full_name, 'aarav.krishnan@example.edu'   AS email, 'BE-CSE'   AS code UNION ALL
    SELECT '23CS014',                'Diya Ramesh',                   'diya.ramesh@example.edu',                'BE-CSE'            UNION ALL
    SELECT '23EC007',                'Karthik Subramanian',           'karthik.s@example.edu',                  'BE-ECE'            UNION ALL
    SELECT '23IT022',                'Meera Nair',                    'meera.nair@example.edu',                 'BTECH-IT'          UNION ALL
    SELECT '23AD031',                'Rahul Varma',                   'rahul.varma@example.edu',                'BTECH-AD'          UNION ALL
    SELECT '23ME045',                'Sneha Iyer',                    'sneha.iyer@example.edu',                 'BE-MECH'
) AS v
JOIN courses c ON c.code = v.code;
