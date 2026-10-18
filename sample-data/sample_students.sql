-- =====================================================================
--  sample-data/sample_students.sql
--  OPTIONAL extra demo students. Import AFTER database/schema.sql:
--      mysql -u root -p student_portal < sample-data/sample_students.sql
--  INSERT IGNORE skips rows whose roll number / email already exist,
--  so the script is safe to run more than once.
--  Portal password for every student below: Student@123 (bcrypt hash).
-- =====================================================================

USE student_portal;

INSERT IGNORE INTO students (name, roll_number, email, course, phone, date_of_birth, address, password_hash) VALUES
('Harini Balaji',     '23CS115', 'harini.b@example.com',      'B.E. Computer Science and Engineering',
 '9786543120', '2005-01-05', '14, Saibaba Colony, Coimbatore', '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse'),
('Vignesh Kumar',     '23EE104', 'vignesh.kumar@example.com', 'B.E. Electrical and Electronics Engineering',
 '9677889900', '2004-09-14', '5, Palladam Road, Tiruppur',     '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse'),
('Ananya Suresh',     '23CE102', 'ananya.s@example.com',      'B.E. Civil Engineering',
 '9566778899', '2005-03-28', '90, Trichy Road, Coimbatore',    '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse'),
('Rahul Narayanan',   '23AD118', 'rahul.n@example.com',       'B.Tech. Artificial Intelligence and Data Science',
 '9345678123', '2005-11-11', '27, Perundurai Road, Erode',     '$2y$10$Tqr4hlj5gve.yTyltBd2yuUxfmiGtA7oFg3Q4sAiaoDo3wLto.zse');
