# Code Explanation

This document walks through the important files and the flow of data through the system. It is written to help with the viva. Every source file also carries inline comments at the important points.

---

## 1. Database (`database/schema.sql`)

```
courses (id PK, code UNIQUE, name UNIQUE)
   1 ─────< students (id PK, roll_number UNIQUE, full_name, email UNIQUE, course_id FK, registration_source, created_at, updated_at)
                1 ─────< student_files (id PK, student_id FK, category, original_name, stored_name UNIQUE,
                │                       relative_path UNIQUE, mime_type, file_size, checksum_sha256, uploaded_by FK, uploaded_at)
                1 ───── 0..1 users (id PK, username UNIQUE, display_name, password_hash, role, student_id FK UNIQUE, is_active, ...)
```

| Design decision | Reason |
|---|---|
| `courses` lookup table | a course name is stored once (3NF); the form sends a short code (`BE-CSE`) |
| `UNIQUE(roll_number)`, `UNIQUE(email)` | the DB itself refuses duplicates, even if two requests race |
| `student_files.student_id ON DELETE CASCADE` | deleting a student removes their metadata rows automatically |
| `students.course_id ON DELETE RESTRICT` | a course with students cannot be deleted by accident |
| `users.student_id ON DELETE CASCADE` + `CHECK` | a student login exists only for role `student` and disappears with the student |
| `uploaded_by ON DELETE SET NULL` | removing an admin account keeps the files |
| Index `(student_id, category, uploaded_at)` | the "latest profile photo" and "files of a student" queries use it |
| `DATETIME` (not `TIMESTAMP`) | PHP and Java read the same value without time-zone conversion |

Cascades remove **rows**. Physical files are removed by PHP code (`StudentService::deleteStudent`), which deletes only paths that belong to that student.

## 2. Shared configuration (`.env`)
One file is read by both modules:
- PHP: [`php-admin/config/config.php`](../php-admin/config/config.php) parses `KEY=VALUE` lines; real environment variables take precedence.
- Java: [`AppConfig.java`](../java-servlet/src/main/java/edu/webtech/portal/util/AppConfig.java) loads it with `java.util.Properties` (same syntax). Tomcat finds it through `SMP_CONFIG` (set in `tomcat/bin/setenv.bat`).
- `STORAGE_DIR=storage/uploads` is resolved relative to the `.env` folder, so both modules point at the **same** upload directory.

---

## 3. Question 1: Registration form

| File | Role |
|---|---|
| `frontend/register.html` | Semantic form: `<form method="post" enctype="multipart/form-data" novalidate>`, `fieldset/legend`, labels, `required`, `pattern`, `maxlength`, `accept`, `autocomplete`, `aria-describedby` |
| `frontend/css/style.css` | Design tokens in `:root`, grid/flex layout, input states (`:focus`, `[aria-invalid=true]`), strength meter, responsive breakpoints at 960 px and 600 px, reduced-motion support |
| `frontend/js/validation.js` | **Part A**: pure functions (`validateName`, `validateEmail`, `assessPassword`, `validateFile`, `detectSignature`...) exported for Node tests. **Part B**: DOM wiring |

**Why `novalidate`?** The browser's own validation bubbles look different in each browser and are hard to make accessible. With `novalidate`, JavaScript shows consistent messages next to each field and in a summary at the top. The HTML attributes stay in place as documentation and as a fallback.

**Password strength** (`assessPassword`): five checks (length ≥ 8, upper, lower, digit, symbol), plus one point for length ≥ 12. Score → level 1–4 (*Weak, Fair, Good, Strong*) drives the coloured bar and the ✓ checklist. A password is *valid* only when all five checks pass, it is not in a small common-password list, and it does not contain the roll number.

**File checks** (`validateFile` + `detectSignature`): extension allow-list, declared MIME, empty/oversize, unsafe double extensions (`x.php.jpg`), and then the **first 12 bytes are read with `File.slice().arrayBuffer()`** and compared with the JPEG/PNG/WebP/PDF signatures. A text file renamed `.jpg` is therefore caught in the browser.

**Submission**: the page fetches a single-use token from `api/registration-token.php`, then posts with `fetch()`. An `isSubmitting` flag plus the disabled button prevent double submits. The server returns JSON; field errors from the server are shown next to the fields, the same way client errors are.

## 4. Question 2: PHP admin module

### Request lifecycle
Every page starts with `require __DIR__ . '/../includes/bootstrap.php';`, which:
1. loads the classes and the configuration;
2. turns off `display_errors` and logs to `storage/logs/php-error.log`;
3. installs an exception handler: known `AppException`s show their safe message, anything else shows a generic one (no stack traces);
4. sends security headers (CSP, `nosniff`, `X-Frame-Options`, `Referrer-Policy`) and removes `X-Powered-By`;
5. starts the session (`Session::start`).

Protected pages then call `Auth::requireAdmin()`.

### Key classes (`php-admin/includes/`)
| File | Responsibility |
|---|---|
| `Database.php` | PDO connection (`ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`, utf8mb4), `transaction()` helper, duplicate-key detection (error 1062) |
| `Session.php` | cookie name `SMP_ADMIN_SESSION`, `HttpOnly`, `SameSite=Lax`, strict mode, cookie path limited to `public/`, idle timeout via `last_activity` |
| `Auth.php` | `attempt()` (bcrypt `password_verify`, dummy hash for unknown users, random delay on failure, rehash on cost change), `login()` (`session_regenerate_id(true)`, new CSRF token), `requireAdmin()` (re-checks role/active in the DB on every request) |
| `Csrf.php` | per-session token (`random_bytes(32)`), `hash_equals` check, detects `post_max_size` overflow, single-use registration token with 1 h lifetime |
| `Validator.php` | `StudentValidator` (normalises: trims, upper-cases roll, lower-cases email; Unicode-aware name regex) and `PasswordPolicy` (same rules as the JS) |
| `FileStorage.php` | upload validation, safe storage, safe path resolution (details below) |
| `Repositories.php` | all SQL: `CourseRepository`, `StudentRepository` (search with escaped `LIKE`, whitelisted `ORDER BY`, pagination), `FileRepository`, `UserRepository` |
| `StudentService.php` | business rules combining DB + files in transactions (create/update/addFile/deleteFile/deleteStudent) |
| `helpers.php` | `e()` (escaping), `url()`/`redirect()` (303 Post/Redirect/Get), flash messages, `input_id()` (strict positive integer), formatting |
| `views/` | `layout_start.php`/`layout_end.php` (sidebar + top bar + confirm `<dialog>`), `student_form.php` (shared add/edit form), `error.php` |

### Pages (`php-admin/public/`)
| Page | Method | Purpose |
|---|---|---|
| `login.php` / `logout.php` | GET/POST, POST only | sign in / sign out (CSRF on both) |
| `dashboard.php` | GET | stats, students per course (CSS bars), recent students and uploads |
| `students.php` | GET | **list**: search, course filter, sort, pagination; avatars from `file.php` |
| `student_create.php` | GET/POST | **add**: validates fields + files, then `StudentService::create` |
| `student_view.php` | GET | **view**: details, documents (view/download/delete), image gallery, upload form; flags missing files |
| `student_edit.php` | GET/POST | **edit**: fields + optional replacement photo |
| `student_delete.php` | GET/POST | **delete**: GET shows a confirm page (no-JS fallback), POST deletes |
| `file_upload.php` / `file_delete.php` | POST | add / remove one file |
| `file.php` | GET | streams a file to an authenticated admin |
| `register.php` | POST | public endpoint for the Q1 form |
| `api/registration-token.php` | GET | issues the single-use registration token |

### Upload flow (the core of Q2)
```
browser ──multipart POST──▶ student_create.php / file_upload.php / register.php
   1. Csrf::requireValid()                       (token, and post_max_size overflow check)
   2. StudentValidator::validate($_POST)         (text fields)
   3. FileStorage::validate($_FILES[x], category)
        - PHP error code → friendly message (INI_SIZE, FORM_SIZE, PARTIAL, NO_FILE ...)
        - is_uploaded_file()                     (tmp_name really came from this upload)
        - size > 0 and ≤ 2 MB (photo) / 5 MB (document)
        - finfo(FILEINFO_MIME_TYPE) on the BYTES → must be in the allow-list
        - images: getimagesize() must decode, MIME must match, ≤ 40 MP
        - PDFs: must start with "%PDF-"
        - reject content containing "<?php" or "<script"
        - extension chosen from the detected MIME (jpg/png/webp/pdf)
        - original name cleaned (basename, no control chars) - display only
   4. Database::transaction:
        INSERT students → FileStorage::store() → INSERT student_files
        store(): mkdir students/<id>/<category>/, name = bin2hex(random_bytes(16)).ext,
                 move_uploaded_file(), chmod 0640
   5. on any failure: ROLLBACK + delete already-moved files (no orphans)
   6. 303 redirect → student_view.php?id=<id>   (Post/Redirect/Get)
```

### Download flow (`file.php`)
`Auth::requireAdmin()` → `input_id()` → `FileRepository::find(id)` → `FileStorage::resolve(relative_path, student_id)`, which checks:
1. the regex `^students/<id>/<category>/<32 hex>.<jpg|png|webp|pdf>$`;
2. that the `<id>` in the path equals the record's `student_id`;
3. that `realpath()` stays inside the storage root.

The script then sends `Content-Type` (from the allow-list), `Content-Length`, `Content-Disposition` (ASCII fallback plus RFC 5987 `filename*`), `nosniff` and `Cache-Control: private, no-store`, followed by `readfile()`. The session lock is released first (`session_write_close`) so thumbnails load in parallel.

### Safe delete
`StudentService::deleteStudent($id)` loads the student's file list, deletes the row (cascades), then calls `FileStorage::delete($path, $id)` for each file. `delete()` refuses any path whose `students/<id>/` part is not this student, so another student's files can never be removed. Finally the empty folders are removed.

### Output escaping
Every dynamic value in a template is printed as `<?= e($value) ?>`. The only unescaped output is static SVG icon markup written by the developer.

## 5. Question 3: Java Servlet module

### Package layout (`java-servlet/src/main/java/edu/webtech/portal/`)
| Package / class | Responsibility |
|---|---|
| `listener.AppContextListener` (`@WebListener`) | on start-up: `AppConfig.locate()` → `DatabaseUtil.init()` → stores `StoragePaths` in the `ServletContext`; on shutdown: deregisters the JDBC driver (prevents memory leaks on redeploy) |
| `util.AppConfig` | reads `.env`, builds the JDBC URL, resolves `STORAGE_DIR` |
| `util.DatabaseUtil` | `DriverManager.getConnection(url, user, pass)`; turns `SQLException` into `DataAccessException` with a safe message |
| `util.PasswordUtil` | verifies PHP's bcrypt hashes with jBCrypt (`$2y$` → `$2a$`), constant work for unknown users |
| `util.StoragePaths` | the Java twin of `FileStorage::resolve()`: regex + owner check + `toRealPath()` containment |
| `util.WebUtil` | `parseId()` (strict), CSRF token create/verify (`SecureRandom`, `MessageDigest.isEqual`), `contentDisposition()`, `sendError()` |
| `model.Student`, `StudentFile`, `User` | JavaBeans used by the JSPs (getters such as `getDocuments()`, `getImages()`, `isPdf()`, `getSizeDisplay()`) |
| `dao.StudentDao` | `findPage(search, page, size)`, `count()`, `findById()`; one SQL with a sub-query for the latest photo id |
| `dao.StudentFileDao` | `findByStudentId()`, `findByStudentIds()` (one `IN (?,?,…)` query for a whole page), `findById()`, `findLatestPhoto()` |
| `dao.UserDao` | `findByUsername()`, `findById()`, `updateLastLogin()` |
| `filter.SecurityHeadersFilter` | UTF-8 request decoding; `nosniff`, `X-Frame-Options`, CSP, `Cache-Control: no-store` |
| `filter.AuthFilter` | guards `/*`: allows `/login` and `/assets/*`; otherwise requires `authUser` in the session; pages → redirect, files → 401; re-checks the account in the DB |
| `servlet.LoginServlet` (`/login`) | CSRF check, `UserDao` + `PasswordUtil`, `request.changeSessionId()`, stores a password-free `User`, sets the idle timeout from `.env`, safe `next` redirect |
| `servlet.LogoutServlet` (`/logout`) | POST + CSRF → `session.invalidate()` |
| `servlet.StudentListServlet` (`/students`) | search + pagination, attaches files to each student, forwards to `students.jsp`; students are redirected to their own profile |
| `servlet.StudentProfileServlet` (`/student?id=`) | 400 invalid id, 403 not allowed, 404 not found, marks missing files, forwards to `student-profile.jsp` |
| `servlet.FileServlet` (`/file?id=`, `/photo?student=`) | streams files (details below) |

Filters are declared in `web.xml` so their **order** is fixed: headers first, then authentication. Servlets use `@WebServlet` annotations.

### JDBC pattern used everywhere
```java
String sql = "SELECT ... FROM student_files WHERE student_id = ?";
try (Connection con = DatabaseUtil.getConnection();
     PreparedStatement ps = con.prepareStatement(sql)) {
    ps.setLong(1, studentId);                 // value bound, never concatenated
    try (ResultSet rs = ps.executeQuery()) { ... map rows to StudentFile ... }
} catch (SQLException e) {
    throw new DataAccessException("safe message", e);   // servlet → 503 page
}
```
`try-with-resources` closes the result set, statement and connection even if an exception occurs.

### JSP: loops and conditions (`WEB-INF/views/students.jsp`)
```jsp
<c:forEach var="s" items="${students}">                      <%-- loop over students --%>
  <c:choose>
    <c:when test="${s.photoFileId != null}">
      <img src="${ctx}/photo?student=${s.id}" alt="...">      <%-- image served by FileServlet --%>
    </c:when>
    <c:otherwise><span class="avatar">${initials}</span></c:otherwise>
  </c:choose>
  <c:choose>                                                    <%-- 0 / 1 / many files --%>
    <c:when test="${s.fileCount == 0}">No files uploaded yet</c:when>
    <c:when test="${s.fileCount == 1}">1 file</c:when>
    <c:otherwise>${s.fileCount} files</c:otherwise>
  </c:choose>
  <c:forEach var="f" items="${s.files}" varStatus="loop">    <%-- nested loop over files --%>
    <c:if test="${loop.index < 3}"> ...chip linking to /file?id=${f.id}... </c:if>
  </c:forEach>
</c:forEach>
```
Text is printed with `<c:out>` (HTML-escaped). Values inside attributes use `fn:escapeXml`. JSPs live in `WEB-INF/views`, so they cannot be requested directly, only through a servlet forward.

### Streaming logic (`FileServlet`)
1. `parseId()`: only `[1-9][0-9]{0,9}` is accepted, otherwise 400.
2. Load metadata: `findById()` or `findLatestPhoto()`; none → 404.
3. Authorisation: `user.canView(file.studentId)`; a student viewing someone else's file → 403.
4. `StoragePaths.resolve(relativePath, studentId)`: missing or unsafe → 404 "file missing".
5. Headers: `setContentType` (allow-list, otherwise `application/octet-stream` + attachment), `setContentLengthLong(Files.size)`, `Content-Disposition` (`inline`, or `attachment` when `download=1`), `nosniff`, `Cache-Control: private, no-store`.
6. Body: `Files.copy(path, response.getOutputStream())` streams in chunks without loading the file into memory.

The URL contains **only a number**; the file path always comes from the database. That is why `../` attacks have nothing to work with.

### Session handling (Java)
- `web.xml`: cookie `SMP_PORTAL_SESSION`, `http-only`, tracking mode `COOKIE` (no `;jsessionid` in URLs), default timeout 30 min.
- Login: `changeSessionId()` (defeats session fixation), `setMaxInactiveInterval(SESSION_TIMEOUT_MINUTES*60)`, new CSRF token.
- Every request: `AuthFilter` checks `authUser`. If the browser sent a session id that is no longer valid, the filter redirects to `/login?expired=1`.
- Logout: POST with CSRF → `session.invalidate()`; the old cookie becomes useless (verified by test).

## 6. PHP vs Java authentication
They are **separate applications with separate sessions**, sharing only the `users` table:
- PHP hashes with `password_hash(PASSWORD_BCRYPT, cost 12)` → `$2y$12$…`
- Java verifies the same string with jBCrypt after mapping `$2y$` to `$2a$` (identical algorithm).
- Signing in to one does **not** sign you in to the other. Each protects its own endpoints (`Auth::requireAdmin`, `AuthFilter`).

## 7. Tests and tools
| File | Purpose |
|---|---|
| `frontend/tests/validation.test.js` | Node unit tests for the JS validators |
| `php-admin/tests/run_tests.php` | PHP unit + live-DB integration tests |
| `java-servlet/src/test/java/...` | JUnit 5: security utils, models, DAO integration |
| `tests/e2e_test.py` | HTTP tests across Apache, Tomcat and MySQL |
| `tests/capture_screenshots.py` | real-browser checks + screenshots (Playwright + installed Chrome) |
| `tests/generate_fixtures.py` | creates the synthetic demo files and malicious test files |
| `php-admin/bin/*.php` | CLI: create/delete user, seed demo files |
| `java-servlet/deploy-tomcat.bat` | build + deploy WAR, configure `SMP_CONFIG` |
