# Code Explanation (Viva Guide)

This document explains the important files in simple language. Every source file also contains inline comments that explain the code at the point where it is written.

---

## 1. HTML form – `Question1_Frontend/register.html`

- A normal `<form>` with `method="post"`, `enctype="multipart/form-data"` (needed to send files) and `action="../Question2_PHP/register.php"`.
- Fields: **full name, roll number, email, mobile (optional), course (`<select>`), password, confirm password, profile photo (`<input type="file">`), ID proof (optional file)** and a declaration checkbox.
- Every input has a `<label for="…">` (accessibility) and an empty `<small class="error-message" id="FIELD-error">` where JavaScript writes the error.
- The form has the **`novalidate`** attribute: this switches off the browser's built-in HTML5 bubbles so that *our own JavaScript* does all validation and shows consistent messages.
- `accept=".jpg,.jpeg,.png,.webp"` only filters the file-chooser dialog; real checking is done in JS and again in PHP.
- `Question1_Frontend/index.html` is a landing page that links the three modules.

**Viva question:** *Why `multipart/form-data`?* – Because the default encoding (`application/x-www-form-urlencoded`) cannot carry binary file contents.

## 2. CSS – `Question1_Frontend/css/style.css` (and `admin.css`, `servlet.css`)

- **Design tokens:** colours, radius and shadow are CSS custom properties in `:root` (`--primary`, `--danger`…). Changing one value re-themes the whole site.
- **Layout:** CSS Grid. The page is two columns (`grid-template-columns: 300px 1fr`) and the form itself is a two-column grid (`.form-grid`).
- **Styled inputs/buttons:** rounded borders, focus ring (`box-shadow` on `:focus`), primary/secondary buttons with hover states.
- **Error UI:** `.is-invalid` gives a red border and pink background, `.is-valid` a green border; `.error-message` is red text under the field; `.alert-error` is the summary box.
- **Password meter:** `.strength-bar` width and colour are changed by JavaScript; the checklist uses `::before` to show ✓ / ✕.
- **Responsive:** `@media (max-width: 900px)` stacks the columns, `@media (max-width: 640px)` makes the form one column and the buttons full width. The same palette is reused in the admin panel and the servlet portal for a consistent look.

## 3. JavaScript validation – `Question1_Frontend/js/validation.js`

- Wrapped in an IIFE `(function () { 'use strict'; … })();` so no variables leak into the global scope.
- `validators` is an object with **one function per field**; each returns an error message or `""`:
  - *name* – required, 3–100 chars, regex `/^[A-Za-z][A-Za-z .']*$/`
  - *roll number* – required, `/^[A-Za-z0-9]{5,15}$/` (converted to upper case while typing)
  - *email* – required + regex for `name@domain.tld`
  - *course* – a value must be selected
  - *password* – required and must pass 5 rules: length ≥ 8, upper case, lower case, digit, special character
  - *confirm password* – must equal the password
  - *files* – extension whitelist, browser MIME type, not empty, max 2 MB (photo) / 5 MB (ID proof)
- `validateField(id)` runs the validator, writes the message into the error element (`textContent`, never `innerHTML`, so no HTML injection), toggles `is-invalid` / `is-valid` and sets `aria-invalid`.
- **Events:** `blur` validates when the user leaves a field; `input`/`change` re-validate live after the field was touched; the password `input` event also updates the **strength meter**.
- **Submit:** all fields are validated; if any fails we call **`event.preventDefault()`** – the form is not sent – show an error summary listing all problems and focus the first wrong field. If everything is valid the form is submitted to PHP (or, when opened from disk, a success message is shown).
- **Reset:** clears every message and colour after the browser resets the values.

## 4. PHP database connection – `Question2_PHP/config/database.php`

- Constants for host, database, user, password (with optional environment-variable overrides), upload folder and size limits, the allowed course list and document labels.
- `db()` creates **one PDO object** (stored in a `static` variable so the connection is reused):
  - `PDO::ERRMODE_EXCEPTION` – SQL errors throw `PDOException`, which we catch
  - `PDO::ATTR_EMULATE_PREPARES => false` – real prepared statements on the MySQL server
  - `charset=utf8mb4` – full Unicode
- If the connection fails, the real reason goes to the PHP error log and the user sees a friendly "Database connection failed" page (`render_error_page()`), never a stack trace.

## 5. PHP CRUD

| Operation | File | Key SQL (always prepared) |
|---|---|---|
| **Create** | `add_student.php` → `create_student_with_files()` | `INSERT INTO students (...) VALUES (:name, :roll, ...)` |
| **Read (list)** | `dashboard.php` | `SELECT ... FROM students s WHERE (s.name LIKE :q1 ...) AND s.course = :course` |
| **Read (one)** | `view_student.php` | `SELECT * FROM students WHERE id = :id` + `SELECT ... FROM student_files WHERE student_id = :sid` |
| **Update** | `edit_student.php` | `UPDATE students SET name = :name, ... WHERE id = :id` |
| **Delete** | `delete_student.php` | `DELETE FROM students WHERE id = :id` |

Common flow of a form page: `require_login()` → on POST check the **CSRF token** → `validate_student_data()` (server-side validation of every field, same rules as JS) → `add_duplicate_errors()` (roll number / email must be unique) → run the prepared statement → `set_flash()` success message → **redirect** (Post/Redirect/Get, so refreshing does not resubmit). On errors the form is shown again with the typed values and a message under each wrong field (shared markup in `includes/student_form.php`).

Delete uses a **confirmation page** (GET) and only deletes on POST. `ON DELETE CASCADE` removes the file metadata rows, then PHP removes the physical files with `delete_physical_file()`.

Other helpers: `includes/auth.php` (session + `require_login()`), `includes/functions.php` (`e()` escaping, CSRF, flash messages, validation), `includes/header.php` / `footer.php` (layout).

## 6. File upload – `Question2_PHP/includes/upload_handler.php`, `upload_file.php`

`validate_upload($file, $category)` checks, in order:

1. `$_FILES['file']['error']` is `UPLOAD_ERR_OK` (otherwise a readable message from `upload_error_message()`)
2. `is_uploaded_file()` – the temp file really came from an HTTP upload
3. size > 0 and ≤ **2 MB** (photo) / **5 MB** (document)
4. extension in the whitelist: photo = jpg, jpeg, png, webp; document = pdf, jpg, jpeg, png
5. no dangerous extension anywhere in the name (`shell.php.jpg` is rejected)
6. the **real MIME type** read from the file bytes with `finfo` must match the extension (a text file renamed to `.jpg` is rejected)
7. images must load with `getimagesize()`

`store_upload()` then:

- generates a **random stored name** `bin2hex(random_bytes(16)) . '.' . $ext` (and loops if it somehow exists) – so two uploads named `id-proof.pdf` never overwrite each other and the original filename is never trusted
- moves the file with `move_uploaded_file()` into `uploads/profiles/` or `uploads/documents/`
- inserts the metadata row; if the insert fails, the file is deleted again

`upload_file.php` (POST only) ties this together and, for a new profile photo, removes the previous photo (`remove_old_profile_photos()`). `delete_file.php` deletes one file (row + disk).

**Uploaded files are never executed:** `uploads/.htaccess` contains `Require all denied` and turns the PHP engine off. Files are delivered by `view_file.php`, which checks the login, looks the file up by numeric id, resolves the path safely and sends it with a whitelisted `Content-Type`, `X-Content-Type-Options: nosniff` and `Content-Disposition: inline` (view) or `attachment` (download).

## 7. File metadata

Each upload creates one row in **`student_files`**:

| Column | Meaning |
|---|---|
| `student_id` | owner (foreign key to `students.id`) |
| `file_category` | `profile` or `document` |
| `document_label` | ID Proof / Certificate / Mark Sheet / Other |
| `original_name` | name on the user's PC – display only |
| `stored_name` | random name on disk (UNIQUE) |
| `file_path` | path relative to `Question2_PHP`, e.g. `uploads/documents/3f9c…e1.pdf` |
| `file_type` | MIME type detected from content |
| `file_size` | bytes |
| `upload_date` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` |

`view_student.php` reads these rows to show the photo, a preview, type badge, size, upload date and the View/Download/Delete buttons.

## 8. MySQL schema – `database/schema.sql`

- `CREATE DATABASE IF NOT EXISTS student_portal` with `utf8mb4`.
- `users` – `username` UNIQUE, bcrypt `password_hash`.
- `students` – `roll_number` UNIQUE, `email` UNIQUE, `created_at`/`updated_at` timestamps, indexes on `course` and `name`.
- `student_files` – foreign key `fk_files_student` with **`ON DELETE CASCADE`**, `CHECK` constraints on category and size, UNIQUE `stored_name`.
- Inserts 2 demo users (`admin`, `faculty`) and 6 sample students. Passwords are bcrypt hashes generated by PHP's `password_hash()` – never plain text.

## 9. JDBC connection – `Question3_Servlet/.../dao/DBConnection.java`

- A static block loads `db.properties` from the classpath (`WEB-INF/classes`) once and loads the driver with `Class.forName("com.mysql.cj.jdbc.Driver")`.
- `getConnection()` returns `DriverManager.getConnection(url, user, password)`. Every DAO method opens the connection in a **try-with-resources** block so it is always closed, even when an exception occurs.
- `getUploadBaseDir()` returns the Question2_PHP folder so the servlet can find the uploaded files.
- Each setting can be overridden by an environment variable (`SP_DB_PASSWORD` etc.).

## 10. DAO pattern – `StudentDAO`, `FileDAO`, `UserDAO`

**DAO (Data Access Object)** = a class that contains *all* SQL for one table. Servlets call simple methods (`findAll`, `findById`, `findByStudentId`, `authenticate`) and never write SQL themselves. Benefits: separation of concerns, reuse, easier testing.

- Every query uses **`PreparedStatement`** with `?` placeholders and `setString/setInt` – user input is never concatenated into SQL.
- `StudentDAO.findAll(search, course)` builds an optional `WHERE` with `LIKE ?` and returns `List<Student>`. Sub-queries add the current profile-photo id and file counts.
- `mapRow(ResultSet)` copies one row into a model object; `rs.wasNull()` converts SQL `NULL` into Java `null`.
- `UserDAO.authenticate()` loads the bcrypt hash and checks it with `BCrypt.checkpw()`. PHP writes `$2y$` hashes; jBCrypt expects `$2a$` – the algorithm is identical, so only the prefix is swapped.
- **Models** (`Student`, `StudentFile`, `User`) are JavaBeans with getters used by JSP EL, plus helpers like `getInitials()`, `isImage()`, `isPdf()`, `getFormattedSize()`.

## 11. Servlets (`controller` package, mapped in `WEB-INF/web.xml`)

| Servlet | URL | What it does |
|---|---|---|
| `LoginServlet` | `/login` | GET shows the form; POST validates input, calls `UserDAO.authenticate()`, invalidates any old session, creates a new `HttpSession`, stores the `User`, sets a 30-min timeout, redirects to `/students` |
| `LogoutServlet` | `/logout` | `request.getSession(false)` → `invalidate()` → redirect to login |
| `StudentListServlet` | `/students` | session check → read `q` and `course` → `StudentDAO.findAll()` → `request.setAttribute("students", list)` → **forward** to `students.jsp` |
| `StudentProfileServlet` | `/student?id=N` | session check → validate id (400 if invalid) → `findById` (404 if missing) → `FileDAO.findByStudentId` → forward to `student-profile.jsp` |
| `FileDownloadServlet` | `/file?id=N[&download=true]` | session check → load metadata → safe path → stream bytes |

`web.xml` also defines the welcome file, session config (30 min, HttpOnly cookie, cookie-only tracking), UTF-8 for JSPs, a default JSTL locale and **error pages** that map 400/403/404/405/500 and exceptions to `error.jsp`.

**forward vs redirect:** *forward* keeps the same request (attributes reach the JSP, URL does not change); *redirect* tells the browser to make a new request (used after login/logout – Post/Redirect/Get).

## 12. JSP dynamic rendering – `students.jsp`, `student-profile.jsp`

- JSPs are in **`WEB-INF/views`**, so they can only be reached through a servlet `forward` (which already checked the session).
- **JSTL core** tags:
  - `<c:forEach items="${students}" var="s">` – **loop** over all students (cards) and `<c:forEach items="${files}" var="f">` – loop over all files of a student.
  - `<c:choose>/<c:when>/<c:otherwise>` – **conditional rendering**: show the photo if the student has one, otherwise the initials; show an `<img>` preview for image files, a "PDF" icon for PDFs; "No files uploaded" when the count is 0.
  - `<c:if>` – optional messages and chips.
  - `<c:out>` – prints values with HTML escaping (XSS protection).
- **EL** (`${student.name}`) calls the getters of the model objects.
- **`<img src="${ctx}/file?id=${s.profilePhotoId}">`** – the image tag points to the **FileDownloadServlet**, not to a real file path.
- `fmt:formatDate` formats dates; `fn:length` and `fn:escapeXml` come from the JSTL functions library.
- `fragments/header.jspf` / `footer.jspf` are included with `<%@ include %>` to avoid repeating the layout.

## 13. File streaming – `FileDownloadServlet.java`

1. `SessionUtil.redirectIfNotLoggedIn()` – no session → redirect to `/login`.
2. Parse the id (`\d{1,9}`, > 0) – otherwise **400**.
3. `FileDAO.findById(id)` – not found → **404**.
4. Resolve the path: `base = upload.base.dir.toRealPath()`, `uploads = base/uploads`, `candidate = base.resolve(file_path).normalize()`. The file is served **only if `candidate.startsWith(uploads)`** and it is a regular file (also re-checked after `toRealPath()` to defeat symbolic links). A tampered path such as `uploads/../../Windows/win.ini` gives 404.
5. Headers: `Content-Type` (only image/jpeg, image/png, image/webp, application/pdf – anything else becomes `application/octet-stream`), `Content-Length`, `Content-Disposition: inline` (show in browser) or `attachment` (`&download=true`), `X-Content-Type-Options: nosniff`.
6. Copy the file to `response.getOutputStream()` (**`ServletOutputStream`**) with an 8 KB buffer in a `while (read != -1)` loop inside try-with-resources.

The browser therefore only ever sees `file?id=7`, never `C:\…\uploads\…`.

## 14. Session management

**PHP (`includes/auth.php`):**
- `session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax'])` then `session_start()`.
- Login stores `$_SESSION['admin_id']`; `session_regenerate_id(true)` gives a new id after login (prevents session fixation).
- `require_login()` at the top of every protected page redirects to `login.php`.
- 30 minutes of inactivity (`last_activity`) ends the session.
- Logout (POST + CSRF) clears `$_SESSION`, deletes the cookie, `session_destroy()`.

**Java (`SessionUtil`, `LoginServlet`, `LogoutServlet`):**
- Protected servlets call `request.getSession(false)` – it **returns the existing session or `null` and never creates one**. No session or no `loggedInUser` attribute → redirect to `/login?msg=login_required`.
- On login the old session is invalidated and a new one created; `setMaxInactiveInterval(1800)`.
- Protected responses send `Cache-Control: no-store` so the Back button cannot show data after logout.
- `web.xml` makes the `JSESSIONID` cookie HttpOnly and disables URL rewriting.

## 15. Security validation (summary)

| Threat | Defence |
|---|---|
| SQL injection | prepared statements in PHP (PDO) and Java (PreparedStatement) |
| XSS | `htmlspecialchars` (`e()`), `<c:out>`, `fn:escapeXml`, `textContent` |
| Weak/stolen passwords | password strength rules (JS + PHP), bcrypt hashing, generic login error |
| CSRF | per-session token in all PHP POST forms, `hash_equals` |
| Malicious uploads | whitelist + MIME sniffing + size limit + random names + no-execute folder |
| Path traversal | `realpath`/`toRealPath` + "must stay inside uploads/" check |
| Unauthorised access | `require_login()`, `getSession(false)` checks, JSPs inside WEB-INF, `.htaccess` deny rules |
| Information leakage | friendly error pages; technical details only in server logs |
| Tampered form values | course and document type checked against whitelists; ids validated as positive integers |
