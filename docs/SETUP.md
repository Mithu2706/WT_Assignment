# Setup Guide (Windows)

This guide was verified on **Windows 11** with **XAMPP** (Apache 2.4, PHP 8.2.12, MariaDB 10.4.32, Tomcat 8.5.96) and **JDK 24**. Replace `C:\Mithuna\WT_Assi2` with your own project folder throughout.

---

## 1. Prerequisites

| Tool | Needed for | Check |
|---|---|---|
| XAMPP 8.x | Apache + PHP + MySQL/MariaDB (+ Tomcat) | `C:\xampp\xampp-control.exe` |
| JDK 11 or newer | compiling and running the Servlet module | `java -version` |
| Maven | **not needed**: `java-servlet\mvnw.cmd` downloads Maven 3.9.9 on first use | – |
| Python 3 + `requests` | only for the automated end-to-end tests | `python -c "import requests"` |
| Node.js 18+ | only for the frontend unit tests | `node -v` |
| VS Code (optional) | editing | extensions: *PHP Intelephense*, *Extension Pack for Java*, *MySQL* (optional) |

If `JAVA_HOME` is not set, set it for the current PowerShell window:

```powershell
$env:JAVA_HOME = 'C:\Program Files\Java\jdk-24'
```

## 2. Open the project in VS Code

```powershell
code C:\Mithuna\WT_Assi2
```

Use **Terminal → New Terminal** (PowerShell) for every command below. Run them from the project folder.

## 3. Start Apache and MySQL

Open the **XAMPP Control Panel** and click **Start** next to *Apache* and *MySQL*.
(Do not start Tomcat yet; it needs the configuration from step 7.)

## 4. Create the database

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p < database\schema.sql
C:\xampp\mysql\bin\mysql.exe -u root -p wt_student_portal < database\sample_data.sql
```

XAMPP's default `root` has no password: just press Enter at the prompt.
`schema.sql` creates the database `wt_student_portal` with 4 tables. **Re-running it drops existing data.**
Alternative: phpMyAdmin (http://localhost/phpmyadmin) → *Import* → choose `schema.sql`, then `sample_data.sql`.

## 5. Create the application's database user

The applications do **not** use `root`. Create a low-privilege user:

```powershell
copy database\create_app_user.sql.example database\create_app_user.local.sql
notepad database\create_app_user.local.sql      # replace CHANGE_ME (2 places) with a long random password
C:\xampp\mysql\bin\mysql.exe -u root -p < database\create_app_user.local.sql
```

`*.local.sql` is not committed. You can delete the file afterwards.

## 6. Configure `.env`

```powershell
copy .env.example .env
notepad .env
```

Set `DB_PASS` to the password from step 5. Keep the other defaults:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=wt_student_portal
DB_USER=smp_app
DB_PASS=<your password>
STORAGE_DIR=storage/uploads        # relative to the folder that contains .env
SESSION_TIMEOUT_MINUTES=30
```

Both PHP and Java read this one file. Use forward slashes if you give an absolute `STORAGE_DIR` (`C:/data/uploads`), and avoid backslashes inside `DB_PASS`. `.env` is git-ignored.

## 7. Create accounts (secure first-admin procedure)

There are **no default passwords** in the code or SQL. Create accounts with the CLI tool; it asks for the password twice and enforces the password policy (8–64 characters, upper + lower case letter, digit, symbol):

```powershell
C:\xampp\php\php.exe php-admin\bin\create_user.php --role=admin --username=admin --name="Portal Admin"
C:\xampp\php\php.exe php-admin\bin\create_user.php --role=staff --username=staff --name="Office Staff"
C:\xampp\php\php.exe php-admin\bin\create_user.php --role=student --student-roll=23CS001
```

(For scripting you may pass the password in the `SMP_NEW_PASSWORD` environment variable instead of typing it.)
Remove an account: `C:\xampp\php\php.exe php-admin\bin\delete_user.php --username=staff`.

| Role | PHP admin panel | Servlet portal |
|---|---|---|
| admin | full CRUD | all profiles |
| staff | no access | all profiles (read-only) |
| student | no access | own profile only |

## 8. Add the demo files (optional)

```powershell
python tests\generate_fixtures.py               # only if database\sample-files is empty (needs Pillow)
C:\xampp\php\php.exe php-admin\bin\seed_sample_files.php
```

This attaches generated avatars/PDFs to the sample students through the **same validation code as a browser upload**. Sneha Iyer (23ME045) deliberately gets no files.

## 9. Run the PHP application (Apache)

Apache serves `C:\xampp\htdocs`. Link the project there with a directory junction. Nothing is copied, and edits are live:

```powershell
cmd /c mklink /J C:\xampp\htdocs\wt-portal C:\Mithuna\WT_Assi2
```

Open:
- http://localhost/wt-portal/ → landing page
- http://localhost/wt-portal/frontend/register.html → **Q1** registration form
- http://localhost/wt-portal/php-admin/public/ → **Q2** admin panel (sign in as `admin`)

The project's `.htaccess` files make `.env`, `php-admin/includes`, `php-admin/config`, `database/`, `storage/` and every dotfile return **403**. Only `frontend/` and `php-admin/public/` are reachable.
*Alternative without a junction:* copy the whole folder to `C:\xampp\htdocs\wt-portal`. `.env` must then also be in that copy.

> Opening `frontend/register.html` directly from disk (`file://`) still shows all client-side validation, but submitting requires the page to be served by Apache, as above.

## 10. Build and deploy the Servlet application (Tomcat)

### Option A: one command (recommended)

```powershell
java-servlet\deploy-tomcat.bat
```

The script:
1. runs `mvnw.cmd clean package`, which compiles, runs the 21 JUnit tests and builds `java-servlet\target\student-portal-servlet.war`;
2. copies the WAR into `C:\xampp\tomcat\webapps\`;
3. creates `C:\xampp\tomcat\bin\setenv.bat` containing `set "SMP_CONFIG=<project>\.env"`, which tells the web app where `.env` is. It never overwrites an existing `setenv.bat`; if one exists, it prints the line to add.

Use another Tomcat with `set TOMCAT_HOME=D:\apache-tomcat-9.0.x` before running the script.

### Option B: manual steps

```powershell
cd java-servlet
.\mvnw.cmd clean package
copy target\student-portal-servlet.war C:\xampp\tomcat\webapps\
notepad C:\xampp\tomcat\bin\setenv.bat      # add:  set "SMP_CONFIG=C:\Mithuna\WT_Assi2\.env"
```

### Start Tomcat

XAMPP Control Panel → **Start** next to *Tomcat* (or run `C:\xampp\catalina_start.bat`). Wait for the deployment to finish (~5 s), then open:

- http://localhost:8080/student-portal-servlet/ → **Q3** profile portal (sign in as `staff`, `admin`, or a student's roll number)

Tomcat's log (`C:\xampp\tomcat\logs\catalina.<date>.log`) should contain
`Student portal configured from C:\Mithuna\WT_Assi2\.env (database wt_student_portal, storage ...)`.

### Which servlet API / Tomcat version?

XAMPP ships **Tomcat 8.5** (Servlet 3.1, `javax.servlet`). The module therefore uses `javax.servlet-api 3.1.0` and JSTL 1.2, which also run unchanged on **Tomcat 9**.
For **Tomcat 10/11** (`jakarta.*`): change the imports `javax.servlet` → `jakarta.servlet`, use `jakarta.servlet-api 6.0.0` and `jakarta.servlet.jsp.jstl` 3.0, and update the JSTL taglib URIs to `jakarta.tags.core`. Don't mix the two namespaces.

## 11. URLs at a glance

| Application | Server | URL |
|---|---|---|
| Registration (Q1) | Apache :80 | http://localhost/wt-portal/frontend/register.html |
| PHP admin (Q2) | Apache :80 | http://localhost/wt-portal/php-admin/public/ |
| Servlet portal (Q3) | Tomcat :8080 | http://localhost:8080/student-portal-servlet/ |
| phpMyAdmin | Apache :80 | http://localhost/phpmyadmin |

## 12. Run the tests

See [TESTING.md](TESTING.md). Short version:

```powershell
node --test frontend\tests\validation.test.js
C:\xampp\php\php.exe php-admin\tests\run_tests.php
cd java-servlet; .\mvnw.cmd test; cd ..
python tests\e2e_test.py
```

## 13. Troubleshooting

| Symptom | Fix |
|---|---|
| PHP page: "The database is not available right now" | MySQL not started, or wrong `DB_PASS` in `.env`. Details: `storage\logs\php-error.log` |
| Servlet page: "Portal not configured" (503) | `SMP_CONFIG` not set for Tomcat → check `C:\xampp\tomcat\bin\setenv.bat`, restart Tomcat |
| Servlet login works but photos are missing | `STORAGE_DIR` must point to the same folder PHP uses; check the "configured from" line in the Tomcat log |
| `mvnw.cmd` says JAVA_HOME is not defined | `$env:JAVA_HOME = 'C:\Program Files\Java\jdk-24'` |
| Port 80 or 8080 already in use | stop the other program, or change `Listen` in `C:\xampp\apache\conf\httpd.conf` / the `Connector port` in `C:\xampp\tomcat\conf\server.xml` |
| Upload error "too large for the server" | file above PHP `post_max_size`; the app limits are 2 MB (photos) / 5 MB (documents) anyway |
| Registration page says "server is not reachable" | the page was opened from disk or Apache is stopped; open it via `http://localhost/wt-portal/...` |
| 403 on every page under `/wt-portal/` | Apache `AllowOverride` must allow `.htaccess` (XAMPP's default `AllowOverride All` does) |
