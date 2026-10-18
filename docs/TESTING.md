# Testing Report

**Environment:** Windows 11 · XAMPP 8.2.12 (Apache 2.4, PHP 8.2.12, MariaDB 10.4.32) · Apache Tomcat 8.5.96 · JDK 24.0.2 · Google Chrome (headless, driven through the DevTools protocol) · curl.
**Date of test run:** 07-Oct-2026, repeated on 18-Oct-2026 after renaming the folders to `Question1_Frontend` / `Question2_PHP` / `Question3_Servlet`.
**Test data:** `database/schema.sql` (6 students, 2 users) + files in `sample-data/files`; invalid files in `sample-data/test-files`.

"Screenshot" refers to files in [`../Screenshots`](../Screenshots). Every test below was actually executed; the *Actual result* column records what happened.

**Summary: 71 test cases (18 frontend, 29 PHP, 24 Servlet), 71 passed, 0 failed.**

---

## 1. Frontend – Registration Form (Question 1)

| Test ID | Input / Action | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| F01 | Click *Submit* with every field empty | Submission blocked; summary "Please correct 8 errors"; message under each required field | Blocked; summary listed 8 errors (name, roll, email, course, password, confirm, photo, declaration) – screenshot 19 | PASS |
| F02 | Name = `A` | "Name must be at least 3 characters long." | Shown, field red – screenshot 02 | PASS |
| F03 | Roll number = `23 CS` (space) | Roll-number format error | "Roll number must be 5–15 letters/digits with no spaces" – screenshot 02 | PASS |
| F04 | Email = `meera@mail` | Invalid email message | "Please enter a valid email address (e.g. name@example.com)." – screenshot 02 | PASS |
| F05 | Mobile = `12345` | 10-digit mobile error | "Enter a valid 10-digit mobile number starting with 6–9." – screenshot 02 | PASS |
| F06 | Course not selected | "Please select a course." | Shown – screenshot 02 | PASS |
| F07 | Weak password `pass123` | Weak-password message listing missing rules; meter shows Weak | "Weak password – add 8+ characters, an upper-case letter, a special character."; red/orange meter, checklist ✕/✓ – screenshot 02 | PASS |
| F08 | Password `pass123`, confirm `pass12` | "Passwords do not match." | Shown – screenshot 02 | PASS |
| F09 | Profile photo = `notes.txt` | Invalid file type error | "Invalid file type ".txt". Allowed: JPG, JPEG, PNG, WEBP." – screenshot 02 | PASS |
| F10 | Declaration not ticked | Declaration error | "You must accept the declaration." – screenshot 02 | PASS |
| F11 | Profile photo = `large-photo.jpg` (3.01 MB) | Size error (max 2 MB) | "File is too large (3.01 MB). Maximum size is 2 MB." | PASS |
| F12 | ID proof = `large-scan.pdf` (6 MB) | Size error (max 5 MB) | "File is too large (6.00 MB). Maximum size is 5 MB." | PASS |
| F13 | Valid data: Meera Nair / 23cs120 / meera.nair@example.com / CSE / `Meera@2026` ×2 / photo / ID proof / declaration | All fields green, roll converted to `23CS120`, strength "Strong", photo preview | Exactly as expected – screenshot 03 | PASS |
| F14 | Submit the valid form (served by Apache) | Saved by `register.php`; confirmation page; student + 2 files in DB | "Registration Successful … Files received 2" – screenshot 03b; record visible in admin dashboard (screenshot 05) | PASS |
| F15 | Press *Reset* after errors | All values, red borders, messages and summary cleared | 0 invalid fields, summary hidden, no messages | PASS |
| F16 | Open `register.html` from disk (`file://`) and submit valid data | Validation works; offline success message (no server) | "All fields are valid! (Offline demo mode – …)" | PASS |
| F17 | Bypass JavaScript: POST empty fields directly to `register.php` with curl | Server-side validation rejects | Response listed "Name is required.", "Roll number is required.", "Please enter a valid email address.", "Please select a valid course.", "Password is required." … | PASS |
| F18 | Mobile viewport 390 × 844 | One-column layout, full-width buttons, readable errors | Layout stacked correctly – screenshot 19 | PASS |

## 2. PHP Admin Panel (Question 2)

| Test ID | Input / Action | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| P01 | Open `dashboard.php` without logging in | Redirect to `login.php` with message | `302 → login.php`; "Please log in to access the admin panel." | PASS |
| P02 | Login `admin` / `WrongPass1!` | Generic error, no session | "Invalid username or password." – screenshot 04b | PASS |
| P03 | Login `admin` / `Admin@123` | Dashboard with welcome message, stats and table | "Welcome back, Portal Administrator!", 7 students, 10 files – screenshot 05 | PASS |
| P04 | Add student with duplicate roll `23CS101`, email `rohan.mehta@invalid`, password `rohan` | Server-side errors, record NOT inserted | "Roll number 23CS101 is already registered.", "Please enter a valid email address.", password-rule error – screenshot 06b | PASS |
| P05 | Add student Rohan Mehta (23IT122) with `rohan-photo.png` + `id-proof.pdf` | Inserted in one transaction; redirect to details page with success | "Student "Rohan Mehta" was added successfully with 2 file(s)." – screenshots 06, 06c | PASS |
| P06 | Dashboard after adding | New student listed with thumbnail and file count | Listed (8 students) – screenshot 07 | PASS |
| P07 | Search `CS` + course filter "B.E. Computer Science and Engineering" | Only matching CSE students | 3 of 8 students shown – screenshot 07b | PASS |
| P08 | Edit Rohan: change mobile and address | Prepared UPDATE; success message; new values displayed | "Student "Rohan Mehta" was updated successfully.", mobile 9790099887, new address – screenshots 08, 09 | PASS |
| P09 | View student (Rohan / Arjun) | Details, profile photo, file list with type, size, upload date, View/Download/Delete | All shown – screenshots 09, 11 | PASS |
| P10 | Upload image (`arjun-photo.jpg`, profile) | Stored as random name in `uploads/profiles`, metadata row created, photo displayed | Stored as `<32 hex>.jpg`, `image/jpeg`, 9320 bytes; displayed on dashboard & details; metadata row visible in phpMyAdmin – screenshot 22 | PASS |
| P11 | Upload PDF (`arjun-id-proof.pdf`, ID Proof) | Stored in `uploads/documents`, success message | ""arjun-id-proof.pdf" (31.0 KB) was uploaded successfully as a document (ID Proof)." – screenshot 10 | PASS |
| P12 | Upload `notes.txt` as document | Rejected – unsupported type | "Upload failed: Unsupported file type ".txt". Allowed types: PDF, JPG, JPEG, PNG." – screenshot 10b | PASS |
| P13 | Upload `large-scan.pdf` (6 MB) | Rejected – too large | "Upload failed: File is too large (6.00 MB). Maximum allowed size is 5.00 MB." – screenshot 10c | PASS |
| P14 | Upload `fake-photo.jpg` (text file renamed) as profile photo | Rejected – content does not match | "Upload failed: The file content (text/plain) does not match the .jpg extension." – screenshot 10d | PASS |
| P15 | Upload `shell.php.jpg` | Rejected – dangerous extension | "Upload failed: This file type is not allowed for security reasons." – screenshot 10e | PASS |
| P16 | Upload `arjun-id-proof.pdf` a second time (duplicate filename) | Both kept; different stored names; nothing overwritten | Two rows: `c213c0d7…bccca83.pdf` and `74f70926…a616990.pdf` – screenshot 11 | PASS |
| P17 | Click *View* on a PDF (`view_file.php?id=13`) | PDF shown inline in the browser | Rendered in Chrome PDF viewer – screenshot 11b | PASS |
| P18 | `view_file.php?id=13&download=1` | `Content-Disposition: attachment` | `attachment; filename="arjun-id-proof.pdf"`, `Content-Type: application/pdf`, `X-Content-Type-Options: nosniff` | PASS |
| P19 | Delete the duplicate file | Row removed + file removed from disk | "File "arjun-id-proof.pdf" was deleted." – screenshot 11c | PASS |
| P20 | Delete student "Test Student" that has 2 files | Confirmation page first; after confirm, record + metadata + physical files removed | Confirmation listed 2 files (screenshot 18); "Student "Test Student" and 2 associated file(s) were deleted." (screenshot 18b); afterwards DB rows = files on disk = 13 | PASS |
| P21 | Request `uploads/profiles/<file>.jpg` directly | Forbidden | HTTP 403 | PASS |
| P22 | Request `config/database.php`, `includes/functions.php` directly | Forbidden | HTTP 403, 403 | PASS |
| P23 | `GET delete_file.php?file_id=1` | Method not allowed | HTTP 405 page | PASS |
| P24 | `POST delete_file.php` without CSRF token | Rejected, file kept | Redirect with "Your form has expired…", row id 1 still exists | PASS |
| P25 | `view_student.php?id=xyz` / `id=9999` | Friendly 400 / 404 page | 400 "Invalid student ID" / 404 "Student not found" | PASS |
| P26 | Search `' OR 1=1 -- ` | Treated as text (prepared statement) | "Showing 0 of 8 students", no SQL error | PASS |
| P27 | Search `<script>alert(1)</script>` | Escaped in output | Rendered as `&lt;script&gt;alert(1)&lt;/script&gt;` | PASS |
| P28 | Tampered DB row with `file_path = uploads/../../../../../../Windows/win.ini` | Not served | `view_file.php` → 404 | PASS |
| P29 | Logout (POST + CSRF) then open dashboard | Session destroyed; redirect to login | `302 → login.php`; dashboard afterwards `302 → login.php` | PASS |

## 3. Servlet + JSP + JDBC Portal (Question 3)

| Test ID | Input / Action | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| S01 | Open `/student-portal/login` | Login page | Rendered – screenshot 12 | PASS |
| S02 | Login `admin` / `nope` | Error message, no session user | "Invalid username or password." – screenshot 12b | PASS |
| S03 | Login `admin` / `Admin@123` (bcrypt hash created by PHP) | New HttpSession, redirect to `/students` | `302 → /student-portal/students`; `Set-Cookie: JSESSIONID=…; Path=/student-portal; HttpOnly` | PASS |
| S04 | Open `/students`, `/student?id=1`, `/file?id=1` without a session | Redirect to login with message | All `302 → /login?msg=login_required`; "Please log in to access the student portal." – screenshot 17 | PASS |
| S05 | Student list | All 8 students looped as cards; photo via servlet; initials when no photo; file chips | 8 cards, Sneha Venkatesh shows "SV" + "No files uploaded" – screenshot 13 | PASS |
| S06 | Search `CS` | Only matching students | Filtered list – screenshot 13b | PASS |
| S07 | Profile of Arjun (`/student?id=1`) | Details + photo + loop over 3 files, PDF icon for PDFs | 3 file cards (photo, ID proof, certificate) – screenshot 14 | PASS |
| S08 | Profile of Karthik (`/student?id=3`) – WEBP photo + PNG certificate | Conditional rendering shows image previews for both | Both previews rendered; chips "2 files · 2 images · 1 document" – screenshot 14b | PASS |
| S09 | `GET /file?id=1` (JPEG) | Streams image inline | `200`, `Content-Type: image/jpeg`, `Content-Length: 9320`, `inline`, `nosniff`; image displayed – screenshot 15 | PASS |
| S10 | Profile HTML source | `<img>` tag points to the servlet, no filesystem path | `<img class="profile-photo" src="/student-portal/file?id=1" …>` – screenshot 15b | PASS |
| S11 | `GET /file?id=5` (WEBP) | Correct MIME type | `Content-Type: image/webp` | PASS |
| S12 | `GET /file?id=13` (PDF) | Displayed inline | `Content-Type: application/pdf`, `inline`; shown in PDF viewer – screenshot 16 | PASS |
| S13 | `GET /file?id=13&download=true` | Download | `Content-Disposition: attachment; filename="arjun-id-proof.pdf"`; body starts with `%PDF-1.4` | PASS |
| S14 | `GET /file?id=99999` | Friendly 404 | "404 Not found – File not found." – screenshot 16b | PASS |
| S15 | `GET /file?id=abc`, `/student?id=abc` | 400 | HTTP 400 "Invalid file ID." / "Invalid student ID." | PASS |
| S16 | `/student?id=999` | Friendly 404 | "Student not found. The record may have been deleted." | PASS |
| S17 | Tampered DB `file_path` pointing outside `uploads/` | Not streamed | HTTP 404 (blocked by `startsWith(uploadsDir)` check) | PASS |
| S18 | `GET /WEB-INF/web.xml`, `/WEB-INF/views/students.jsp` | Not accessible | HTTP 404, 404 | PASS |
| S19 | Protected page response headers | No caching | `Cache-Control: no-store, no-cache, must-revalidate`, `Pragma: no-cache` | PASS |
| S20 | Search `' OR 1=1 -- ` | Treated as text | "Showing 0 of 8 students" | PASS |
| S21 | Logout | Session invalidated, login page with message | "You have been logged out successfully." – screenshot 18c | PASS |
| S22 | Re-use the old JSESSIONID after logout on `/students` and `/student?id=1` | Access denied | `302 → /login?msg=login_required` – screenshot 17 | PASS |
| S23 | Login as `faculty` and open a profile on a 390 px mobile viewport | Responsive layout | Stacked layout – screenshot 20 | PASS |
| S24 | Redeploy the WAR on running Tomcat | No errors / memory-leak warnings | Clean redeploy (driver deregistered by `AppLifecycleListener`) | PASS |

## 4. Build / Static Checks

| Check | Command | Result |
|---|---|---|
| PHP syntax | `php -l` on all 19 PHP files | No syntax errors |
| Java compile + WAR | `Question3_Servlet\build.bat deploy` (javac `-source 8 -target 8`, JDK 24) | Built `target\student-portal.war`, deployed to Tomcat 8.5.96 without errors |
| SQL script | `mysql -u root < database\schema.sql` (MariaDB 10.4.32) | Imported; 3 tables, 2 users, 6 students |
| Maven | `mvn clean package` | `pom.xml` provided; Maven was **not installed** on the test machine, so the WAR was built with the equivalent `build.bat` |

## 5. Reproducing the curl checks

```bash
# log in to the servlet portal and keep the cookie
curl -c cookies.txt -d "username=admin&password=Admin@123" http://localhost:8080/student-portal/login
# image streamed by the servlet – look at the headers
curl -b cookies.txt -D - -o NUL "http://localhost:8080/student-portal/file?id=1"
# forced download
curl -b cookies.txt -D - -o doc.pdf "http://localhost:8080/student-portal/file?id=13&download=true"
# no session → redirect
curl -i "http://localhost:8080/student-portal/students"
# uploads folder is not web-accessible
curl -i http://localhost/student-portal/Question2_PHP/uploads/profiles/
```
