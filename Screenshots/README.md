# Screenshots of Browser Output

All 40 screenshots were captured **from the running application** on 18-Oct-2026 (Apache + PHP 8.2 + MariaDB 10.4 from XAMPP, Apache Tomcat 8.5.96, Google Chrome). The browser was driven automatically through the Chrome DevTools protocol, which fills the forms, chooses the files and clicks the buttons the same way a user would, then saves what the browser rendered. None of them are mock-ups.

Desktop screenshots use a 1366 px wide window; "mobile" screenshots use a 390 × 844 phone viewport. The browser address bar is not part of headless screenshots, so the URL of each page is listed below.

## Question 1 – Frontend (Rubric: Front-End Design, HTML, CSS, JavaScript & Validation – 10 marks)

| File | URL / action | Demonstrates |
|---|---|---|
| `01-registration-page.png` | `http://localhost/student-portal/Question1_Frontend/register.html` | HTML registration form: name, roll number, email, mobile, course, password, confirm password, photo + ID-proof upload; CSS layout, colours, styled inputs and buttons |
| `02-registration-validation.png` | invalid values entered, *Submit* clicked | JavaScript validation: error summary, inline red messages, invalid email, weak password + strength meter, password mismatch, invalid file type, required course/declaration; submission blocked |
| `03-registration-valid.png` | valid values entered | All fields green, password strength "Strong" with all rules ticked, photo preview |
| `03b-registration-success.png` | valid form submitted → `Question2_PHP/register.php` | Form data re-validated and saved by PHP; confirmation page |
| `19-registration-mobile.png` | same page on a 390 px phone viewport, empty submit | Responsive design (single column) with validation messages |

## Question 2 – PHP + MySQL Admin Panel (Rubric: PHP, MySQL & CRUD Operations – 10 marks)

| File | URL / action | Demonstrates |
|---|---|---|
| `04-php-login.png` | `http://localhost/student-portal/Question2_PHP/login.php` | Admin login page (sessions) |
| `04b-php-login-error.png` | wrong password | Invalid-login error handling |
| `05-admin-dashboard.png` | `dashboard.php` after login | Dashboard: total students / files / photos / documents, student table with photo thumbnails, View/Edit/Delete buttons |
| `06-add-student.png` | `add_student.php` filled in | **Create** form with profile photo and document upload |
| `06b-add-student-validation.png` | duplicate roll number, invalid email, weak password | Server-side validation and duplicate check (prepared statement) |
| `06c-add-student-success.png` | after *Save Student* | Record inserted, files stored, metadata shown |
| `07-student-list.png` | `dashboard.php` | **Read** – list of all students including the new one |
| `07b-student-search.png` | `dashboard.php?q=CS&course=…` | Search + course filter |
| `08-edit-student.png` | `edit_student.php?id=…` | **Update** – existing data loaded, values changed |
| `09-student-details.png` | after *Update Student* | Update success + **View** page: details, profile image, uploaded files |
| `10-file-upload.png` | `view_student.php?id=1` → upload PDF | File upload handling – success message, new file listed |
| `10b-upload-invalid-type.png` | upload `notes.txt` | File-type validation |
| `10c-upload-oversized.png` | upload 6 MB PDF | File-size validation |
| `10d-upload-content-mismatch.png` | upload a text file renamed `.jpg` | Real MIME-type check (finfo) |
| `10e-upload-dangerous-extension.png` | upload `shell.php.jpg` | Dangerous-extension rejection |
| `11-uploaded-files.png` | `view_student.php?id=1` | Uploaded files with preview, category, type, size, upload date, View/Download/Delete; the same file name uploaded twice is stored twice (no overwriting) |
| `11b-php-view-document.png` | `view_file.php?id=13` | Stored PDF displayed through `view_file.php` |
| `11c-delete-file.png` | *Delete* on one file | File deleted (row + disk) |
| `18-delete-confirmation.png` | `delete_student.php?id=…` | **Delete** – confirmation page listing the files that will be removed |
| `18b-delete-success.png` | *Yes, Delete Student* | Student and associated files deleted |

| `21-database-tables.png` | phpMyAdmin → database `student_portal` | The three MySQL tables (`students`, `student_files`, `users`) with row counts |
| `22-student-files-metadata.png` | phpMyAdmin SQL tab: `SELECT … FROM student_files` | **File metadata stored in MySQL** – student id, category, original name, file path, MIME type, size and upload date for every upload |

## Question 3 – Servlet + JSP + JDBC (Rubric: Servlet, JDBC & Dynamic Web Content – 10 marks)

| File | URL / action | Demonstrates |
|---|---|---|
| `12-servlet-login.png` | `http://localhost:8080/student-portal/login` | Servlet login page (`LoginServlet` + `login.jsp`) |
| `12b-servlet-invalid-login.png` | wrong password | Invalid-login handling (JDBC query of `users`, bcrypt check) |
| `13-servlet-student-list.png` | `/student-portal/students` | `StudentListServlet` → `students.jsp`: `<c:forEach>` over all students, photos via `<img src="file?id=…">`, initials when no photo (`<c:choose>`), file-count chips |
| `13b-servlet-search.png` | `/student-portal/students?q=CS` | Search through the servlet (prepared `LIKE ?`) |
| `14-servlet-student-profile.png` | `/student-portal/student?id=1` | `StudentProfileServlet` → `student-profile.jsp`: details, profile image, loop over multiple files, PDF icon vs image preview, View/Download links |
| `14b-servlet-profile-image-document.png` | `/student-portal/student?id=3` | Conditional rendering of WEBP photo and PNG certificate previews |
| `15-servlet-image-stream.png` | `/student-portal/file?id=1` | `FileDownloadServlet` streaming a JPEG (`Content-Type: image/jpeg`, inline) |
| `15b-servlet-img-tag-source.png` | `view-source:` of `/student-portal/student?id=1` | Supporting evidence: the generated HTML contains `<img src="/student-portal/file?id=1">` – the image points to the Servlet, no filesystem path is exposed |
| `16-servlet-document.png` | `/student-portal/file?id=13` | PDF document streamed by the servlet and shown inline |
| `16b-servlet-file-not-found.png` | `/student-portal/file?id=99999` | Friendly 404 error page (no stack trace) |
| `18c-servlet-logout.png` | *Logout* clicked | `LogoutServlet` invalidated the session |
| `17-session-protection.png` | `/student-portal/student?id=1` after logout | Session protection: protected page redirects to `/login?msg=login_required` with "Please log in to access the student portal." |
| `20-servlet-profile-mobile.png` | profile on 390 px phone viewport | Responsive JSP pages |

## Rubric: Code Quality, Output & Documentation (10 marks)

All screenshots above, together with [`../README.md`](../README.md), [`../docs/CODE_EXPLANATION.md`](../docs/CODE_EXPLANATION.md), [`../docs/TESTING.md`](../docs/TESTING.md) and [`../docs/CONCLUSION.md`](../docs/CONCLUSION.md).

## Re-capturing manually

If you want to take the screenshots yourself (e.g. with the address bar visible), start Apache, MySQL and Tomcat, open the URLs listed above in your browser in the same order (log in first; `admin` / `Admin@123`), and press `Win + Shift + S` to capture each page.
