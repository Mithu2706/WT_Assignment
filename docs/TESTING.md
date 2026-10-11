# Testing

All results below come from **real executions** on 11 Oct 2026 against the running stack:
Windows 11, Apache 2.4 + PHP 8.2.12 (XAMPP), MariaDB 10.4.32, Tomcat 8.5.96, JDK 24, Node 22.18, Python 3.11, Google Chrome.
Raw output of each run is reproduced in the appendix at the end of this file.

| # | Suite | What it covers | Command | Result |
|---|---|---|---|---|
| 1 | Frontend unit tests | pure validation rules in `validation.js` | `node --test frontend/tests/validation.test.js` | **13/13 passed** |
| 2 | Browser checks (Chrome) | the real registration form in a browser + screenshots | `python tests/capture_screenshots.py` | **23/23 passed** |
| 3 | PHP unit + integration | validator, password policy, upload security, path traversal, CRUD on the live DB | `php php-admin/tests/run_tests.php` | **94/94 passed** |
| 4 | Java JUnit 5 | password hashes from PHP, id parsing, headers, path safety, config, models, **DAO against the live DB** | `cd java-servlet && mvnw.cmd test` | **21/21 passed, 0 skipped** |
| 5 | End-to-end HTTP | both running apps + DB + storage together | `python tests/e2e_test.py` | **130/130 passed** |
| 6 | E2E with real session expiry | same + waits 75 s with `SESSION_TIMEOUT_MINUTES=1` | `python tests/e2e_test.py --session-expiry` | **133/133 passed** |
| 7 | Static checks | `php -l` on all 34 PHP files, `node --check` on both JS files, `javac -Xlint:all` via Maven | see below | **clean** |

Test data: suites 3, 4 and 5 create their own students (roll numbers `ZZT…`, `ZZJ…`, `E2E…`) and **delete them afterwards**. Suite 2 performs real actions and keeps their results as demo data: it registers *Priya Raman (23CS045)* through the form and adds *Vikram Sethu (23EE012)* through the admin panel.

---

## 1. Frontend (Question 1)

### Unit tests: `frontend/tests/validation.test.js`
| Required test | Cases |
|---|---|
| Empty fields | every required field returns a message; optional ID proof may be empty |
| Invalid email | `plainaddress`, `name@`, `@example.com`, `name@example`, spaces, double dots, leading dot, bad domain, 1-letter TLD, > 150 chars |
| Weak password | too short, no uppercase, no digit, no symbol, contains space, common password, contains roll number, > 64 chars; meter levels Weak/Fair/Good/Strong |
| Invalid file type | PDF as photo, `.php`, double extension `shell.php.jpg`, wrong MIME, empty file; magic-byte detection of JPEG/PNG/WebP/PDF and rejection of PHP source |
| Oversized file | 3 MB photo (limit 2 MB), 6 MB document (limit 5 MB); exactly 5 MB accepted |
| Valid submission | complete valid data passes every rule |

### In-browser checks: `tests/capture_screenshots.py` (Chrome, headless)
Submitting the empty form lists all 7 required fields in the error summary. Then: invalid email message; weak password explained, with the meter showing *Weak/Fair*; password mismatch; roll number with a space; a text file renamed `.jpg` rejected **by its bytes**; `.txt` ID proof rejected; 3 MB photo rejected; a strong password rated *Good/Strong*; no errors left on valid data; the server accepted the valid registration (screenshot 07). A second submit is blocked by the `isSubmitting` flag and the disabled button. On the server the token is single-use; see E2E "re-submitting the same form".

## 2. PHP + MySQL (Question 2)

`php-admin/tests/run_tests.php` needs no framework. It uses a temporary storage folder and the **real database**.

| Required test | Where | Result |
|---|---|---|
| Add a student | PHP integration "add student with photo + document"; E2E "add valid student…" | pass |
| List students | PHP "list/search returns both test students"; E2E "list/search finds the new student" | pass |
| View a student | PHP "view student returns stored values"; E2E "profile shows…" | pass |
| Edit a student | PHP "edit student saves changes"; E2E "edit student saves changes" | pass |
| Delete a student | PHP "delete student removes record" + cascade; E2E "delete student (POST + CSRF)" | pass |
| Duplicate roll number | PHP "duplicate roll number rejected", "edit to another student's roll number rejected"; E2E (form + registration) | pass |
| Invalid input | PHP validator (empty, bad email ×6, script tag, SQL string, unknown course, array injection); E2E "invalid input -> field errors shown" | pass |
| Valid upload | JPEG/PNG/WebP/PDF accepted; metadata (MIME, size, original + generated name) verified | pass |
| Invalid upload | PDF as photo, text-as-JPEG, `shell.php.jpg`, JPEG+PHP polyglot, `.txt`, `.html` with fake PDF MIME, bad category | pass |
| Oversized upload | > 2 MB photo, > 5 MB document, `UPLOAD_ERR_FORM_SIZE`, request > `post_max_size` (41 MB) | pass |
| Filename collision | same file stored twice → two different 32-hex names, both present; E2E: same name uploaded twice → two records, both downloadable | pass |
| Missing file | physical file deleted → profile shows "File missing from storage", `file.php` → 404, record can still be deleted | pass |
| Unauthorized document access | `file.php` without login → redirect with **0 bytes**; staff/student accounts refused by admin panel | pass |
| CSRF rejection | add/delete without token and with forged token → 403; login without token rejected; logout without token → 403 | pass |
| SQL injection attempts | search `' OR '1'='1' --`, sort `name; DROP TABLE…`, login `' OR '1'='1`, roll number with quotes; table intact afterwards | pass |
| Path traversal | `../../.env`, `students/7/../../../.env`, absolute paths, backslashes, `.php` names, other student's id, NUL byte → refused | pass |
| Access rules (Apache) | `.env`, `.git/HEAD`, `schema.sql`, `includes/`, `config/`, `storage/uploads/` (listing + direct file), `java-servlet/`, `docs/` → 403 | pass |

## 3. Java Servlet + JDBC (Question 3)

| Required test | Where | Result |
|---|---|---|
| Database connectivity | JUnit `DaoIntegrationTest` (live DB, not skipped); E2E login + pages | pass |
| Fetch multiple student records | JUnit `fetchesMultipleStudentsWithSearch`, `batchLoadsFilesForSeveralStudents`; E2E "student list renders multiple students" | pass |
| Student with no files | JUnit `fetchesStudentWithNoFiles`; E2E "no files" state + profile empty states + `/photo` 404 | pass |
| Student with multiple files | JUnit `fetchesStudentWithMultipleFiles`; E2E "N files + chips" | pass |
| Image streaming + content type | E2E: `/photo?student=ID` → `image/png`, `Content-Length` correct, **SHA-256 identical** to the file uploaded via PHP; student's WebP photo → `image/webp` | pass |
| Document download | E2E: `/file?id=…&download=1` → `application/pdf`, `attachment`, RFC 5987 filename, byte-identical; `/file?id=…` → `inline` | pass |
| Invalid student ID | `/student?id=abc`, `id=0`, missing id → 400; `/file?id=../../.env` → 400; `photo?student=-3` → 400 | pass |
| Missing record / file | `/student?id=999999999` → 404; `/file?id=999999999` → 404; metadata with missing physical file → 404 "missing" | pass |
| Unauthenticated page access | `/students` → 302 to `/login` | pass |
| Unauthenticated file access | `/file`, `/photo` → **401**, no file bytes | pass |
| Session expiration | (a) old cookie after logout → redirect `login?expired=1`; (b) **real 60 s idle timeout** (`--session-expiry`) → Java and PHP both expire | pass |
| Logout | GET → 405, POST without CSRF → 403, POST with token → session invalidated | pass |
| Authorisation | student account: own profile 200; other profile 403; other student's file 403; other photo 403; list redirects to own profile | pass |
| Shared hashes | Java verifies PHP's `$2y$` bcrypt hashes (JUnit + real login) | pass |
| Session fixation | session cookie value changes after login (PHP and Java) | pass |
| Error pages leak nothing | no "Exception" / stack frames in error pages | pass |

### Cross-module verification (Stage E)
The E2E suite proves the two modules share one database and one storage folder **using data created during the test run, not fabricated rows**:
1. A student is added with a PNG photo and a PDF through the PHP admin **form** (multipart upload).
2. The Java portal finds the same student by roll number, shows the same name, course and document name.
3. `/photo?student=ID` and `/file?id=ID` return bytes whose SHA-256 equals the uploaded files.
4. A student registered through the public form can sign in to the Java portal with the password chosen in the form.
5. Deleting in PHP → Java returns 404 and the student's Java login stops working (cascade).

## 4. Static checks
- `php -l` on all 34 PHP files: no syntax errors.
- `node --check` on `frontend/js/validation.js` and `php-admin/public/assets/js/admin.js`: OK.
- Maven compiles with `-Xlint:all`: no warnings reported.

## 5. What could NOT be tested
- **HTTPS / `Secure` cookies.** No TLS certificate is configured on localhost. The code sets `Secure` automatically when HTTPS is detected, but that path was not exercised.
- **Tomcat 9/10.** Only the installed Tomcat 8.5.96 was run. The code uses Servlet 3.1 APIs that Tomcat 9 also provides; Tomcat 10 needs the `jakarta` migration described in SETUP.md.
- **Other browsers.** Screenshots and in-browser checks used Google Chrome only.

## How to reproduce
```powershell
node --test frontend\tests\validation.test.js
C:\xampp\php\php.exe php-admin\tests\run_tests.php
cd java-servlet; .\mvnw.cmd test; cd ..
python tests\e2e_test.py                      # Apache + Tomcat + MySQL must be running
# Real expiry: set SESSION_TIMEOUT_MINUTES=1 in .env, restart Tomcat, then:
python tests\e2e_test.py --session-expiry     # afterwards set it back to 30 and restart Tomcat
# Browser checks + screenshots (pip install playwright==1.47.0 pillow; uses installed Chrome):
python tests\capture_screenshots.py
```

---

## Appendix: raw output of the final runs

<details><summary>Frontend unit tests</summary>

```text
✔ empty required fields are rejected (5.2748ms)
✔ optional ID proof may be empty (0.4907ms)
✔ name rules (3.0382ms)
✔ roll number rules (0.4004ms)
✔ invalid email formats are rejected (0.8813ms)
✔ course must be in the allowed list (0.2756ms)
✔ weak passwords are rejected with an explanation (0.9483ms)
✔ strong password is accepted and graded (0.3831ms)
✔ password confirmation must match (0.7678ms)
✔ invalid file types are rejected (1.5782ms)
✔ oversized files are rejected (0.6412ms)
✔ file signatures are detected from bytes, not names (0.4918ms)
✔ a fully valid submission passes every rule (0.3766ms)
ℹ tests 13
ℹ suites 0
ℹ pass 13
ℹ fail 0
ℹ cancelled 0
ℹ skipped 0
ℹ todo 0
ℹ duration_ms 231.0507
```

</details>

<details><summary>PHP test suite</summary>

```text

== Student validation ==
  PASS  valid input passes
  PASS  roll number normalised to upper case
  PASS  email normalised to lower case
  PASS  extra spaces in name collapsed
  PASS  course code mapped to id
  PASS  empty form -> 4 required-field errors
  PASS  invalid email rejected: plainaddress
  PASS  invalid email rejected: a@b
  PASS  invalid email rejected: a@@b.com
  PASS  invalid email rejected: name@example
  PASS  invalid email rejected: x y@example.com
  PASS  invalid email rejected: "><script>@x.com
  PASS  script tag in name rejected
  PASS  SQL injection string as roll number rejected
  PASS  unknown course rejected
  PASS  array-injected fields treated as empty

== Password policy ==
  PASS  empty password rejected
  PASS  short password rejected
  PASS  no uppercase rejected
  PASS  no digit rejected
  PASS  no symbol rejected
  PASS  common password rejected
  PASS  password containing roll number rejected
  PASS  over 64 characters rejected
  PASS  strong password accepted

== Upload validation (real file contents) ==
  PASS  valid JPEG photo accepted
  PASS  valid PNG photo accepted
  PASS  valid WebP photo accepted
  PASS  valid PDF document accepted
  PASS  SHA-256 checksum computed
  PASS  rejected: PDF uploaded as profile photo
  PASS  rejected: text file named .jpg (fake image)
  PASS  rejected: PHP script named shell.php.jpg
  PASS  rejected: real JPEG with PHP appended (polyglot)
  PASS  rejected: plain .txt document
  PASS  rejected: HTML document
  PASS  invalid category rejected
  PASS  oversized photo (> 2 MB) rejected
  PASS  oversized document (> 5 MB) rejected
  PASS  empty file rejected
  PASS  UPLOAD_ERR_INI_SIZE -> friendly "too large"
  PASS  UPLOAD_ERR_NO_FILE -> "choose a file"
  PASS  UPLOAD_ERR_PARTIAL -> "interrupted"
  PASS  multi-file array trick rejected
  PASS  spoofed tmp_name (not an HTTP upload) rejected

== Filenames and storage ==
  PASS  client path stripped from original name
  PASS  unsafe characters replaced
  PASS  anything before a slash is dropped
  PASS  displayed extension forced to real type
  PASS  same file stored twice -> two different generated names
  PASS  both copies exist (no overwrite)
  PASS  generated name is 32 hex chars + real extension
  PASS  relative path follows students/<id>/<category>/ layout

== Path traversal protection ==
  PASS  valid stored path resolves
  PASS  rejected path: ../../.env
  PASS  rejected path: students/7/../../../.env
  PASS  rejected path: students/7/profile_photo/../../x.jpg
  PASS  rejected path: C:/Windows/win.ini
  PASS  rejected path: /etc/passwd
  PASS  rejected path: students/7/profile_photo/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.php
  PASS  rejected path: students/7\profile_photo\ed20584f9c7b7e573a43ee259bd27b48.jpg
  PASS  rejected path: students/07/profile_photo/ed20584f9c7b7e573a43ee259bd27b48.jpg
  PASS  rejected path: students/7/profile_photo/ed20584f9c7b7e573a43ee259bd27b48.jpg\000.txt
  PASS  path of another student rejected when owner is checked
  PASS  delete() refuses a file that belongs to another student
  PASS  delete() removes own file
  PASS  missing file resolves to null (handled as 404)
  PASS  empty student folder cleaned up

== Database integration ==
  PASS  database connection works
  PASS  add student with photo + document
  PASS  view student returns stored values
  PASS  two file metadata rows linked to student
  PASS  metadata stores detected MIME, size, original + generated names
  PASS  physical files exist under students/<id>/
  PASS  duplicate roll number rejected
  PASS  duplicate email rejected
  PASS  list/search returns both test students
  PASS  edit student saves changes
  PASS  edit to another student's roll number rejected
  PASS  new profile photo replaces old one (1 row left)
  PASS  old photo file removed from disk
  PASS  delete file removes metadata row
  PASS  delete file removes physical file
  PASS  deleting a missing file id -> NotFound
  PASS  missing physical file detected (resolve -> null)
  PASS  SQL injection in search returns no rows
  PASS  SQL injection in sort is ignored (whitelist)
  PASS  students table still intact after injection attempts
  PASS  LIKE wildcards are escaped ("%" matches literally)
  PASS  delete student removes record
  PASS  cascade removed the student's file metadata
  PASS  other student's file untouched after delete
  PASS  deleting a missing student -> NotFound
  PASS  test data cleaned up

94 passed, 0 failed
```

</details>

<details><summary>Java JUnit (Maven)</summary>

```text
﻿[INFO] Building Student Profile Portal (Servlet/JSP/JDBC) 1.0.0
[INFO] Tests run: 8, Failures: 0, Errors: 0, Skipped: 0, Time elapsed: 1.162 s -- in edu.webtech.portal.dao.DaoIntegrationTest
[INFO] Tests run: 6, Failures: 0, Errors: 0, Skipped: 0, Time elapsed: 0.028 s -- in edu.webtech.portal.model.ModelTest
[INFO] Tests run: 7, Failures: 0, Errors: 0, Skipped: 0, Time elapsed: 1.606 s -- in edu.webtech.portal.util.SecurityUtilTest
[INFO] Tests run: 21, Failures: 0, Errors: 0, Skipped: 0
[INFO] BUILD SUCCESS
```

</details>

<details><summary>End-to-end (standard run)</summary>

```text

== Setup ==
  PASS  create temporary admin account via CLI
  PASS  create temporary staff account via CLI
  PASS  CLI refuses a weak password

== Apache access rules (private folders, no listing, no execution) ==
  PASS  GET /.env is blocked (403)
  PASS  GET /.git/HEAD is blocked (403)
  PASS  GET /database/schema.sql is blocked (403)
  PASS  GET /php-admin/includes/Auth.php is blocked (403)
  PASS  GET /php-admin/config/config.php is blocked (403)
  PASS  GET /storage/uploads/ is blocked (403)
  PASS  GET /storage/uploads/students/1/ is blocked (403)
  PASS  GET /frontend/assets/ is blocked (403)
  PASS  GET /java-servlet/pom.xml is blocked (403)
  PASS  GET /docs/SETUP.md is blocked (403)
  PASS  registration page is public (200)

== PHP admin - authentication & sessions ==
  PASS  unauthenticated dashboard -> redirect to login
  PASS  unauthenticated file download -> redirect, no file bytes
  PASS  wrong password rejected with generic message
  PASS  login without CSRF token rejected
  PASS  staff account cannot use the admin panel
  PASS  SQL injection in login form fails
  PASS  admin login succeeds (303 -> dashboard)
  PASS  session ID regenerated after login
  PASS  dashboard loads with statistics
  PASS  security headers present (CSP, nosniff, frame)
  PASS  session cookie is HttpOnly

== PHP admin - CRUD with uploads ==
  PASS  add student without CSRF token -> 403
  PASS  invalid input -> field errors shown
  PASS  submitted script tag is escaped in the re-displayed form
  PASS  add valid student with photo + document (303 -> profile)
  PASS  profile shows normalised roll number
  PASS  profile lists the uploaded document by original name
  PASS  profile shows image via protected file.php endpoint
  PASS  duplicate roll number rejected
  PASS  list/search finds the new student
  PASS  SQL injection in search -> no rows, no SQL error
  PASS  reflected search text is HTML-escaped
  PASS  malicious sort/page parameters ignored safely
  PASS  image streamed with correct type and length
  PASS  streamed image is byte-identical to the upload
  PASS  document download uses attachment + safe filename
  PASS  file.php?id=999999999 -> 404
  PASS  file.php?id=abc -> 404
  PASS  file.php?id=../../.env -> 404
  PASS  file.php?id=-1 -> 404
  PASS  PHP script disguised as .jpg rejected
  PASS  JPEG with embedded PHP code rejected
  PASS  text file rejected as document
  PASS  HTML with spoofed PDF MIME rejected
  PASS  oversized photo (> 2 MB) rejected
  PASS  document over MAX_FILE_SIZE rejected (UPLOAD_ERR_FORM_SIZE)
  PASS  request over post_max_size reported clearly
  PASS  invalid category rejected
  PASS  same filename uploaded twice -> two separate files (no overwrite)
  PASS  two distinct generated names on disk
  PASS  both copies downloadable
  PASS  missing file flagged on profile page
  PASS  missing file -> friendly 404
  PASS  record of a missing file can still be deleted
  PASS  edit student saves changes
  PASS  edit to an existing roll number rejected
  PASS  edit of unknown id -> 404
  PASS  delete without CSRF token -> 403
  PASS  delete with forged CSRF token -> 403
  PASS  student still exists after forged requests
  PASS  GET on file_delete.php -> 405
  PASS  cross-site logout (no token) rejected

== Public registration endpoint (Question 1 -> PHP) ==
  PASS  registration without token -> 403 JSON
  PASS  server-side validation returns field errors (422)
  PASS  valid registration accepted (201)
  PASS  re-submitting the same form (used token) rejected
  PASS  duplicate roll number on registration rejected
  PASS  cross-origin registration POST rejected
  PASS  self-registered student visible in admin list

== Java Servlet portal - authentication ==
  PASS  unauthenticated /students -> redirect to /login
  PASS  unauthenticated /file -> 401, no file bytes
  PASS  unauthenticated /photo -> 401
  PASS  wrong password rejected
  PASS  SQL injection in login form fails
  PASS  login without CSRF token rejected
  PASS  staff login succeeds (PHP bcrypt hash verified in Java)
  PASS  session ID changed after login

== Java Servlet portal - same data as PHP (shared DB + storage) ==
  PASS  student list renders multiple students
  PASS  list shows the 'no files' state for a student without files
  PASS  Cache-Control no-store on protected pages
  PASS  PHP-created student found via Java search
  PASS  <img> points to the photo servlet
  PASS  Java profile shows same name/roll/course as PHP
  PASS  Java profile lists the document uploaded in PHP
  PASS  photo servlet streams the PHP-uploaded image (byte-identical)
  PASS  Content-Length set on streamed image
  PASS  document download: PDF type, attachment, RFC 5987 name
  PASS  document view inline
  PASS  student with many files shows file count + chips

== Java Servlet portal - invalid input & errors ==
  PASS  /student?id=abc -> 400
  PASS  /student?id=0 -> 400
  PASS  /student?id=999999999 -> 404
  PASS  /student -> 400
  PASS  /file?id=999999999 -> 404
  PASS  /file?id=../../.env -> 400
  PASS  /photo?student=-3 -> 400
  PASS  /file?id=64 -> 404
  PASS  /no-such-page -> 404
  PASS  error pages expose no stack trace
  PASS  SQL injection in Java search -> no rows
  PASS  Java escapes reflected search text
  PASS  profile with zero files shows empty states
  PASS  photo servlet -> 404 for student without photo

== Java Servlet portal - student role authorisation ==
  PASS  self-registered student can sign in to Java portal
  PASS  student is redirected away from the full list
  PASS  student can open own profile
  PASS  student cannot open another profile (403)
  PASS  student cannot download another student's file (403)
  PASS  student cannot fetch another student's photo (403)
  PASS  student can see own photo (WebP)
  PASS  student account cannot use PHP admin panel

== Logout ==
  PASS  GET /logout not allowed (405)
  PASS  POST /logout without CSRF -> 403
  PASS  Java logout redirects to login
  PASS  old Java session cookie is useless after logout
  PASS  PHP logout redirects to login
  PASS  old PHP session cookie is useless after logout

== PHP idle timeout (session file aged on the server) ==
  PASS  idle session -> redirect to login with 'expired'
  PASS  login page explains the session expired

== Delete (PHP) and effect on both modules ==
  PASS  delete student (POST + CSRF) -> 303 to list
  PASS  deleted student's files removed from disk
  PASS  deleted student's folder removed
  PASS  other student's photo untouched
  PASS  deleted student's login removed (cascade)
  PASS  Java portal returns 404 for the deleted student
  PASS  delete of unknown id -> 404

== Cleanup ==
  removed test students [31]
  removed temporary accounts

130 passed, 0 failed
```

</details>

<details><summary>End-to-end with real session expiry</summary>

```text

== Setup ==
  PASS  create temporary admin account via CLI
  PASS  create temporary staff account via CLI
  PASS  CLI refuses a weak password

== Apache access rules (private folders, no listing, no execution) ==
  PASS  GET /.env is blocked (403)
  PASS  GET /.git/HEAD is blocked (403)
  PASS  GET /database/schema.sql is blocked (403)
  PASS  GET /php-admin/includes/Auth.php is blocked (403)
  PASS  GET /php-admin/config/config.php is blocked (403)
  PASS  GET /storage/uploads/ is blocked (403)
  PASS  GET /storage/uploads/students/1/ is blocked (403)
  PASS  GET /frontend/assets/ is blocked (403)
  PASS  GET /java-servlet/pom.xml is blocked (403)
  PASS  GET /docs/SETUP.md is blocked (403)
  PASS  registration page is public (200)

== PHP admin - authentication & sessions ==
  PASS  unauthenticated dashboard -> redirect to login
  PASS  unauthenticated file download -> redirect, no file bytes
  PASS  wrong password rejected with generic message
  PASS  login without CSRF token rejected
  PASS  staff account cannot use the admin panel
  PASS  SQL injection in login form fails
  PASS  admin login succeeds (303 -> dashboard)
  PASS  session ID regenerated after login
  PASS  dashboard loads with statistics
  PASS  security headers present (CSP, nosniff, frame)
  PASS  session cookie is HttpOnly

== PHP admin - CRUD with uploads ==
  PASS  add student without CSRF token -> 403
  PASS  invalid input -> field errors shown
  PASS  submitted script tag is escaped in the re-displayed form
  PASS  add valid student with photo + document (303 -> profile)
  PASS  profile shows normalised roll number
  PASS  profile lists the uploaded document by original name
  PASS  profile shows image via protected file.php endpoint
  PASS  duplicate roll number rejected
  PASS  list/search finds the new student
  PASS  SQL injection in search -> no rows, no SQL error
  PASS  reflected search text is HTML-escaped
  PASS  malicious sort/page parameters ignored safely
  PASS  image streamed with correct type and length
  PASS  streamed image is byte-identical to the upload
  PASS  document download uses attachment + safe filename
  PASS  file.php?id=999999999 -> 404
  PASS  file.php?id=abc -> 404
  PASS  file.php?id=../../.env -> 404
  PASS  file.php?id=-1 -> 404
  PASS  PHP script disguised as .jpg rejected
  PASS  JPEG with embedded PHP code rejected
  PASS  text file rejected as document
  PASS  HTML with spoofed PDF MIME rejected
  PASS  oversized photo (> 2 MB) rejected
  PASS  document over MAX_FILE_SIZE rejected (UPLOAD_ERR_FORM_SIZE)
  PASS  request over post_max_size reported clearly
  PASS  invalid category rejected
  PASS  same filename uploaded twice -> two separate files (no overwrite)
  PASS  two distinct generated names on disk
  PASS  both copies downloadable
  PASS  missing file flagged on profile page
  PASS  missing file -> friendly 404
  PASS  record of a missing file can still be deleted
  PASS  edit student saves changes
  PASS  edit to an existing roll number rejected
  PASS  edit of unknown id -> 404
  PASS  delete without CSRF token -> 403
  PASS  delete with forged CSRF token -> 403
  PASS  student still exists after forged requests
  PASS  GET on file_delete.php -> 405
  PASS  cross-site logout (no token) rejected

== Public registration endpoint (Question 1 -> PHP) ==
  PASS  registration without token -> 403 JSON
  PASS  server-side validation returns field errors (422)
  PASS  valid registration accepted (201)
  PASS  re-submitting the same form (used token) rejected
  PASS  duplicate roll number on registration rejected
  PASS  cross-origin registration POST rejected
  PASS  self-registered student visible in admin list

== Java Servlet portal - authentication ==
  PASS  unauthenticated /students -> redirect to /login
  PASS  unauthenticated /file -> 401, no file bytes
  PASS  unauthenticated /photo -> 401
  PASS  wrong password rejected
  PASS  SQL injection in login form fails
  PASS  login without CSRF token rejected
  PASS  staff login succeeds (PHP bcrypt hash verified in Java)
  PASS  session ID changed after login

== Java Servlet portal - same data as PHP (shared DB + storage) ==
  PASS  student list renders multiple students
  PASS  list shows the 'no files' state for a student without files
  PASS  Cache-Control no-store on protected pages
  PASS  PHP-created student found via Java search
  PASS  <img> points to the photo servlet
  PASS  Java profile shows same name/roll/course as PHP
  PASS  Java profile lists the document uploaded in PHP
  PASS  photo servlet streams the PHP-uploaded image (byte-identical)
  PASS  Content-Length set on streamed image
  PASS  document download: PDF type, attachment, RFC 5987 name
  PASS  document view inline
  PASS  student with many files shows file count + chips

== Java Servlet portal - invalid input & errors ==
  PASS  /student?id=abc -> 400
  PASS  /student?id=0 -> 400
  PASS  /student?id=999999999 -> 404
  PASS  /student -> 400
  PASS  /file?id=999999999 -> 404
  PASS  /file?id=../../.env -> 400
  PASS  /photo?student=-3 -> 400
  PASS  /file?id=50 -> 404
  PASS  /no-such-page -> 404
  PASS  error pages expose no stack trace
  PASS  SQL injection in Java search -> no rows
  PASS  Java escapes reflected search text
  PASS  profile with zero files shows empty states
  PASS  photo servlet -> 404 for student without photo

== Java Servlet portal - student role authorisation ==
  PASS  self-registered student can sign in to Java portal
  PASS  student is redirected away from the full list
  PASS  student can open own profile
  PASS  student cannot open another profile (403)
  PASS  student cannot download another student's file (403)
  PASS  student cannot fetch another student's photo (403)
  PASS  student can see own photo (WebP)
  PASS  student account cannot use PHP admin panel

== Logout ==
  PASS  GET /logout not allowed (405)
  PASS  POST /logout without CSRF -> 403
  PASS  Java logout redirects to login
  PASS  old Java session cookie is useless after logout
  PASS  PHP logout redirects to login
  PASS  old PHP session cookie is useless after logout

== PHP idle timeout (session file aged on the server) ==
  PASS  idle session -> redirect to login with 'expired'
  PASS  login page explains the session expired

== Delete (PHP) and effect on both modules ==
  PASS  delete student (POST + CSRF) -> 303 to list
  PASS  deleted student's files removed from disk
  PASS  deleted student's folder removed
  PASS  other student's photo untouched
  PASS  deleted student's login removed (cascade)
  PASS  Java portal returns 404 for the deleted student
  PASS  delete of unknown id -> 404

== REAL session expiry (waiting for SESSION_TIMEOUT_MINUTES=1) ==
  PASS  both sessions valid before waiting
  ... waiting 75 seconds for both sessions to expire
  PASS  PHP session expired after idle timeout
  PASS  Java session expired after idle timeout

== Cleanup ==
  removed test students [25]
  removed temporary accounts

133 passed, 0 failed
```

</details>

<details><summary>Browser checks + screenshots</summary>

```text

== Registration form (browser checks) ==
  saved  screenshots/01-landing-page.png
  saved  screenshots/02-registration-form.png
  PASS  empty submit lists every required field
  saved  screenshots/03-registration-empty-errors.png
  PASS  invalid email message shown
  PASS  weak password explained
  PASS  strength meter shows Weak/Fair
  PASS  password mismatch detected
  PASS  roll number with space rejected
  PASS  file whose bytes are not an image rejected
  PASS  invalid document type (.txt) rejected
  saved  screenshots/04-registration-validation-errors.png
  PASS  oversized photo rejected on the client
  saved  screenshots/05-registration-oversized-file.png
  PASS  strong password graded Good/Strong
  PASS  no field errors remain
  saved  screenshots/06-registration-valid-ready.png
  note   registration not accepted (roll number probably exists already) - keeping previous screenshot 07
  saved  screenshots/08-registration-mobile.png

== PHP admin panel ==
  PASS  unauthenticated dashboard redirects to login
  saved  screenshots/09-php-login-error.png
  saved  screenshots/10-php-dashboard.png
  saved  screenshots/11-php-student-list.png
  saved  screenshots/12-php-student-search.png
  saved  screenshots/13-php-delete-confirmation.png
  PASS  server-side validation errors displayed
  saved  screenshots/14-php-add-student-validation.png
  saved  screenshots/15-php-add-student-form.png
  note   23EE012 already exists - using existing record
  saved  screenshots/16-php-student-profile-new.png
  saved  screenshots/17-php-edit-student.png
  PASS  edit saved
  saved  screenshots/18-php-student-profile-documents.png
  PASS  disguised PHP upload rejected in the browser flow
  saved  screenshots/19-php-upload-rejected.png
  PASS  document download delivers original filename
  saved  screenshots/20-php-document-view.png
  saved  screenshots/21-php-student-list-mobile.png

== Java Servlet portal ==
  PASS  unauthenticated /students redirected to login
  saved  screenshots/22-servlet-login.png
  saved  screenshots/23-servlet-student-list.png
  saved  screenshots/24-servlet-profile-multiple-files.png
  PASS  zero-file profile shows empty states
  saved  screenshots/25-servlet-profile-no-files.png
  PASS  student added in PHP visible in Java
  saved  screenshots/26-servlet-profile-created-in-php.png
  saved  screenshots/27-servlet-profile-self-registered.png
  saved  screenshots/28-servlet-photo-stream.png
  saved  screenshots/29-servlet-invalid-id.png
  saved  screenshots/30-servlet-missing-record.png
  saved  screenshots/31-servlet-logged-out.png
  PASS  after logout protected page needs login again
  PASS  after logout file access is 401
  saved  screenshots/32-servlet-file-unauthenticated.png
  PASS  student lands on own profile
  saved  screenshots/33-servlet-student-own-profile.png
  PASS  student blocked from another profile (403)
  saved  screenshots/34-servlet-student-forbidden.png
  saved  screenshots/35-servlet-student-list-mobile.png

23 passed, 0 failed
```

</details>
