# Final Submission Audit

Audit date: 11 Oct 2026. "Verification" names the test or check that was **actually executed** (see [TESTING.md](TESTING.md)).
Result legend: **PASS** = implemented and verified · **FAIL** = not working · **BLOCKED** = could not be verified in this environment.

## Assignment questions (from `Assignment_2_WebTechnology (1).pdf`)

### Q1: Registration form with validation and styling (8 marks)
| # | Requirement | Source | Verification | Result |
|---|---|---|---|---|
| 1.1 | HTML form: name, roll number, email, course, file upload (+ password) | `frontend/register.html` | screenshot 02; browser checks | PASS |
| 1.2 | CSS layout, colours, responsive design, styled inputs/buttons/errors | `frontend/css/style.css` | screenshots 02–08 (desktop + 390 px mobile) | PASS |
| 1.3 | JS validation: required fields | `frontend/js/validation.js` | Node test "empty required fields"; browser check "empty submit lists every required field" | PASS |
| 1.4 | JS validation: email format | `validateEmail()` | Node test (15 cases); browser check | PASS |
| 1.5 | JS validation: password strength | `assessPassword()`, meter + checklist | Node tests; browser checks (Weak/Fair → Good/Strong) | PASS |
| 1.6 | Semantic HTML, accessible labels | labels, fieldsets, aria attributes, error summary | code review; screenshots | PASS |
| 1.7 | Client file type + size validation | `validateFile()`, `detectSignature()` | Node tests; browser: fake JPEG, `.txt`, 3 MB photo rejected | PASS |
| 1.8 | Duplicate-submission prevention + clear feedback | `isSubmitting`, disabled button, single-use server token | E2E "re-submitting the same form rejected"; success screenshot 07 | PASS |
| 1.9 | Separate HTML/CSS/JS files | `register.html`, `style.css`, `validation.js` | file structure | PASS |
| 1.10 | Client validation not a substitute for server validation | `php-admin/public/register.php` | E2E "server-side validation returns field errors (422)" | PASS |

### Q2: PHP pages: CRUD, uploads, metadata (16 marks)
| # | Requirement | Source | Verification | Result |
|---|---|---|---|---|
| 2.1 | Add student | `student_create.php`, `StudentService::create` | PHP integration; E2E; screenshot 15–16 | PASS |
| 2.2 | View students / student | `students.php`, `student_view.php` | PHP + E2E; screenshots 11, 12, 18 | PASS |
| 2.3 | Edit student | `student_edit.php` | PHP + E2E; screenshot 17 | PASS |
| 2.4 | Delete student | `student_delete.php`, `deleteStudent()` | PHP + E2E (files removed, other student untouched); screenshot 13 | PASS |
| 2.5 | Upload profile images and documents (ID proof, certificates) | `file_upload.php`, `FileStorage` | PHP + E2E | PASS |
| 2.6 | Files stored in an organised server directory | `storage/uploads/students/<id>/<category>/` | E2E checks files on disk | PASS |
| 2.7 | Metadata in MySQL (student id, original + generated name, path, MIME, size, date, category) | `student_files` table, `FileRepository::insert` | PHP integration "metadata stores…" | PASS |
| 2.8 | Display uploaded images/documents on the profile | `student_view.php` | E2E; screenshot 18 | PASS |
| 2.9 | Server-side size + real type validation, allow-list only | `FileStorage::validate` | PHP (12 rejection cases) + E2E (8 cases) | PASS |
| 2.10 | Unique generated names, no overwriting | `FileStorage::store` | PHP "same file stored twice"; E2E filename collision | PASS |
| 2.11 | Never trust client filename/extension/MIME | `finfo`, extension from detected type | E2E "HTML with spoofed PDF MIME rejected", `shell.php.jpg` | PASS |
| 2.12 | Prevent path traversal and executable uploads | `resolve()`, `.htaccess` (engine off), polyglot check | PHP (10 path cases); E2E Apache rules | PASS |
| 2.13 | Graceful handling: missing files, duplicates, invalid input, failed uploads, DB errors | exception classes, `bootstrap.php` handler | PHP + E2E (missing file 404, duplicate, 413-style, invalid ids) | PASS |
| 2.14 | Delete/update never touches another student's files | owner check in `FileStorage::delete` | PHP "delete() refuses…"; E2E "other student's photo untouched" | PASS |
| 2.15 | Prepared statements (PDO) | `Repositories.php`, `Database.php` | code review; SQL-injection tests | PASS |
| 2.16 | Output escaping (XSS) | `e()` in every template | E2E reflected-XSS checks | PASS |
| 2.17 | POST for state changes + CSRF | `Csrf.php`, all forms | E2E (no token / forged token → 403, GET → 405) | PASS |
| 2.18 | Server validation of all student fields | `StudentValidator` | PHP validator tests | PASS |
| 2.19 | Download endpoint | `file.php?download=1` | E2E headers + bytes; browser download check | PASS |
| 2.20 | Normalised schema: PK, FK, UNIQUE, indexes | `database/schema.sql` | imported into MariaDB 10.4; FK cascade verified | PASS |

### Q3: Servlet pages: JDBC, streaming, dynamic JSP, sessions (16 marks)
| # | Requirement | Source | Verification | Result |
|---|---|---|---|---|
| 3.1 | Connect to MySQL with JDBC | `DatabaseUtil`, DAOs | JUnit `DaoIntegrationTest` (live DB, 0 skipped) | PASS |
| 3.2 | Fetch student details and file paths | `StudentDao`, `StudentFileDao` | JUnit + E2E | PASS |
| 3.3 | Retrieve and stream images/documents from the storage folder | `FileServlet` | E2E: byte-identical SHA-256, correct types | PASS |
| 3.4 | Dynamic profiles in JSP with `<img>` pointing to the Servlet | `students.jsp`, `student-profile.jsp` | E2E `src=".../photo?student=ID"`; screenshots 23–28 | PASS |
| 3.5 | Loops and conditional logic for 0 / 1 / many files | `<c:forEach>`, `<c:choose>` | E2E + screenshots 23, 24, 25 | PASS |
| 3.6 | Session handling: only logged-in users | `AuthFilter`, `LoginServlet` | E2E: redirect for pages, 401 for files | PASS |
| 3.7 | Invalid IDs, missing records, missing files, DB errors, unauthorised requests | servlets + `WebUtil.sendError` | E2E (9 status-code cases); 503 path via `DataAccessException` (code review) | PASS |
| 3.8 | Prepared statements for all queries | all DAOs | code review; injection tests | PASS |
| 3.9 | Content-Type, Content-Length, safe Content-Disposition | `FileServlet`, `WebUtil.contentDisposition` | E2E headers; JUnit header-injection test | PASS |
| 3.10 | Prevent directory traversal / unauthorised file access | `StoragePaths`, `User.canView` | JUnit (12 attack paths); E2E student-role 403s | PASS |
| 3.11 | Invalidate session on logout | `LogoutServlet` | E2E "old Java session cookie is useless after logout" | PASS |
| 3.12 | Servlets, JSP, DAO layer, model classes, DB utility; Maven WAR; correct namespace | `java-servlet/` (javax for Tomcat 8.5) | `mvnw package` BUILD SUCCESS; deployed on Tomcat 8.5.96 | PASS |
| 3.13 | Session expiration | timeout from `.env` | E2E `--session-expiry`: real 60 s idle expiry | PASS |

### Submission requirements (PDF)
| Item | Location | Result |
|---|---|---|
| PHP, Servlet WEB-INF files, HTML/CSS/JS | `php-admin/`, `java-servlet/src/main/webapp/WEB-INF/`, `frontend/` | PASS |
| DB schema script | `database/schema.sql` (+ `sample_data.sql`) | PASS |
| Screenshots of output in browser | `screenshots/` (35 real captures) | PASS |
| Explanation of code with inline comments | inline comments in all sources + `docs/CODE_EXPLANATION.md` | PASS |
| Brief conclusion (3–5 lines) | `docs/CONCLUSION.md` | PASS |
| GitHub repository link | must be created and pushed by the student (steps in the final report) | **BLOCKED** (needs your GitHub account) |

## Rubric mapping (40 marks)
| Rubric area | Marks | Evidence |
|---|---|---|
| Front-end design: HTML, CSS, JavaScript & validation | 10 | Q1 rows above; 13 unit + 12 in-browser checks; screenshots 01–08 |
| PHP, MySQL & CRUD | 10 | Q2 rows; 94 PHP tests; ≈75 PHP-related E2E checks; screenshots 09–21 |
| Servlet, JDBC & dynamic web content | 10 | Q3 rows; 21 JUnit tests; ≈45 Java-related E2E checks; screenshots 22–35 |
| Code quality, output & documentation | 10 | layered code, inline comments, README + 5 docs, reproducible tests, security review below |

## Security review checklist
| Control | Status | Evidence |
|---|---|---|
| SQL injection prevention | PASS | prepared statements only; injection tests (PHP, JUnit, E2E) |
| XSS prevention | PASS | escaping everywhere + CSP; reflected-XSS E2E checks |
| CSRF for state-changing forms | PASS | E2E 403 checks |
| Server-side validation | PASS | PHP validator + registration 422 test |
| Secure password hashing | PASS | bcrypt cost 12; no plaintext; no default passwords |
| Session ID regeneration after login | PASS | E2E cookie comparison (PHP + Java) |
| Session timeout and logout | PASS | real expiry test; logout replay test |
| Authorisation on every protected endpoint | PASS | `requireAdmin()` in every admin page; `AuthFilter` on `/*` |
| Safe uploads, unique names, size + MIME validation | PASS | see Q2 rows |
| Path traversal prevention | PASS | PHP + JUnit attack lists |
| Secure document streaming | PASS | headers + auth + owner checks |
| No directory listing / no execution / no public access to uploads | PASS | E2E Apache rules (403) |
| No stack traces or secrets in user-facing errors | PASS | E2E "error pages expose no stack trace"; `display_errors=0` |
| Credentials not committed | PASS | `.env`, `*.local.*`, uploads git-ignored; `.env.example` only |

## Remaining work / limitations
1. **Publish to GitHub and submit the link**. This needs your account (see the final report for the commands).
2. HTTPS was not configured locally, so the `Secure` cookie flag was not exercised (documented in README).
3. There is no brute-force lockout (only slowed responses), no connection pool, and account management is CLI-only. These are known limitations, not assignment requirements.
4. Tomcat 10+ would need the `jakarta.*` migration described in SETUP.md. Tomcat 8.5 (installed) works and was verified.

## Submission-readiness re-audit (second pass, same day)
- All required files present: 20 Java classes, 7 JSP/JSPF views + `web.xml`, 34 PHP files, HTML/CSS/JS, `schema.sql`, 35 screenshots, 4-line conclusion.
- Added explanatory comments to `login.jsp` and `footer.jspf` (the two views that had none), then rebuilt and redeployed the WAR.
- Re-ran every suite after the changes: Node 13/13, PHP lint 34/34, PHP 94/94, JUnit 21/21, end-to-end 130/130, all passed.
- **Fixed a `.gitignore` gap**: `database/create_app_user.local.sql` (which SETUP.md tells you to fill with the real DB password) was not ignored. Added `*.local.*`, `.env.*`, key/keystore and `*.log` patterns, and verified them with `git check-ignore`.
- Inspected all 166 files a commit would include. None contains the DB password, any demo password, a private key or a real user's password hash. Bundled sample PDFs and images are generated demo files labelled "DEMO DOCUMENT - NOT A REAL RECORD". Real uploads, `.env`, credentials, logs and `target/` are ignored.
- Nothing was staged, committed or pushed.
