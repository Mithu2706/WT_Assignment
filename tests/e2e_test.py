"""
End-to-end HTTP tests for the running applications.

    python tests/e2e_test.py                    # full suite (about 30 s)
    python tests/e2e_test.py --session-expiry   # ALSO waits for real session expiry
                                                # (set SESSION_TIMEOUT_MINUTES=1 in .env
                                                #  and restart Tomcat first)

Requirements: Apache+PHP (XAMPP) serving the project at /wt-portal, Tomcat
running the WAR at /student-portal-servlet, MySQL with schema imported,
Python package "requests".

The suite creates its OWN data and removes it at the end:
  * two temporary accounts (e2e admin + staff) via php-admin/bin/create_user.php
  * a student added through the PHP admin form (with photo + document)
  * a student registered through the public registration endpoint
It then checks that the Java portal shows exactly the same records and
streams byte-identical files - proving both modules share one database and
one storage folder.
"""
import argparse
import hashlib
import os
import re
import secrets
import subprocess
import sys
import time
from pathlib import Path

import requests

ROOT = Path(__file__).resolve().parent.parent
FIX = ROOT / "tests" / "fixtures"
PHP_BIN = os.environ.get("PHP_BIN", r"C:\xampp\php\php.exe")
SESSION_DIR = Path(os.environ.get("PHP_SESSION_DIR", r"C:\xampp\tmp"))
SITE = os.environ.get("SMP_SITE_URL", "http://localhost/wt-portal/")
PHP = SITE + "php-admin/public/"
JAVA = os.environ.get("SMP_JAVA_URL", "http://localhost:8080/student-portal-servlet/")

RESULTS = []


def check(name, condition, detail=""):
    RESULTS.append((name, bool(condition), detail))
    print(("  PASS  " if condition else "  FAIL  ") + name + ("" if condition or not detail else f"   [{detail}]"))
    return bool(condition)


def section(title):
    print(f"\n== {title} ==")


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def token_from(html):
    m = re.search(r'name="csrf_token" value="([^"]+)"', html)
    return m.group(1) if m else None


def run_php_cli(script, *args, password=None):
    env = dict(os.environ)
    if password:
        env["SMP_NEW_PASSWORD"] = password
    return subprocess.run([PHP_BIN, str(ROOT / "php-admin" / "bin" / script), *args],
                          capture_output=True, text=True, env=env)


def strong_password():
    return "E2e!" + secrets.token_hex(6) + "Zz9"


def php_login(username, password):
    s = requests.Session()
    page = s.get(PHP + "login.php")
    r = s.post(PHP + "login.php", data={"csrf_token": token_from(page.text), "username": username,
                                        "password": password, "next": ""}, allow_redirects=False)
    return s, r


def java_login(username, password):
    s = requests.Session()
    page = s.get(JAVA + "login")
    r = s.post(JAVA + "login", data={"csrf_token": token_from(page.text), "username": username,
                                     "password": password, "next": ""}, allow_redirects=False)
    return s, r


def files_for(student_id, category):
    folder = ROOT / "storage" / "uploads" / "students" / str(student_id) / category
    return sorted(folder.glob("*"), key=lambda p: p.stat().st_mtime) if folder.is_dir() else []


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--session-expiry", action="store_true", help="wait for real session timeout (slow)")
    args = parser.parse_args()

    suffix = f"{secrets.randbelow(9000) + 1000}"
    admin_user, staff_user = f"e2e_admin_{suffix}", f"e2e_staff_{suffix}"
    admin_pw, staff_pw, student_pw = strong_password(), strong_password(), strong_password()
    roll_admin, roll_self = f"E2EA{suffix}", f"E2ES{suffix}"
    created_students = []

    section("Setup")
    for user, role, pw in ((admin_user, "admin", admin_pw), (staff_user, "staff", staff_pw)):
        res = run_php_cli("create_user.php", f"--username={user}", f"--role={role}", f"--name=E2E {role}", password=pw)
        check(f"create temporary {role} account via CLI", res.returncode == 0, res.stderr.strip())
    res = run_php_cli("create_user.php", "--username=e2e_weak", "--role=staff", password="weakpass")
    check("CLI refuses a weak password", res.returncode != 0 and "Password needs" in res.stderr)

    try:
        # ==================================================================
        section("Apache access rules (private folders, no listing, no execution)")
        for path in (".env", ".git/HEAD", "database/schema.sql", "php-admin/includes/Auth.php",
                     "php-admin/config/config.php", "storage/uploads/", "storage/uploads/students/1/",
                     "frontend/assets/", "java-servlet/pom.xml", "docs/SETUP.md"):
            code = requests.get(SITE + path, allow_redirects=False).status_code
            check(f"GET /{path} is blocked ({code})", code in (403, 404))
        r = requests.get(SITE + "frontend/register.html")
        check("registration page is public (200)", r.status_code == 200 and "Student registration" in r.text)

        # ==================================================================
        section("PHP admin - authentication & sessions")
        r = requests.get(PHP + "dashboard.php", allow_redirects=False)
        check("unauthenticated dashboard -> redirect to login", r.status_code == 303 and "login.php" in r.headers.get("Location", ""))
        r = requests.get(PHP + "file.php?id=1", allow_redirects=False)
        check("unauthenticated file download -> redirect, no file bytes", r.status_code == 303 and len(r.content) == 0)

        s, r = php_login(admin_user, "Wrong!Pass123")
        check("wrong password rejected with generic message", r.status_code == 200 and "Invalid username or password" in r.text)
        s = requests.Session()
        s.get(PHP + "login.php")
        r = s.post(PHP + "login.php", data={"username": admin_user, "password": admin_pw}, allow_redirects=False)
        check("login without CSRF token rejected", r.status_code == 200 and "expired" in r.text)
        s, r = php_login(staff_user, staff_pw)
        check("staff account cannot use the admin panel", "does not have access" in r.text)
        s, r = php_login("' OR '1'='1' -- ", "' OR '1'='1")
        check("SQL injection in login form fails", r.status_code == 200 and "Invalid username or password" in r.text)

        admin = requests.Session()
        login_page = admin.get(PHP + "login.php")
        before = admin.cookies.get("SMP_ADMIN_SESSION")
        r = admin.post(PHP + "login.php", data={"csrf_token": token_from(login_page.text), "username": admin_user,
                                                "password": admin_pw}, allow_redirects=False)
        after = admin.cookies.get("SMP_ADMIN_SESSION")
        check("admin login succeeds (303 -> dashboard)", r.status_code == 303 and "dashboard.php" in r.headers["Location"])
        check("session ID regenerated after login", before and after and before != after)
        dash = admin.get(PHP + "dashboard.php")
        check("dashboard loads with statistics", dash.status_code == 200 and "Uploaded files" in dash.text)
        check("security headers present (CSP, nosniff, frame)", "default-src 'self'" in dash.headers.get("Content-Security-Policy", "")
              and dash.headers.get("X-Content-Type-Options") == "nosniff" and dash.headers.get("X-Frame-Options") == "DENY")
        cookie_hdr = [c for c in admin.cookies if c.name == "SMP_ADMIN_SESSION"][0]
        check("session cookie is HttpOnly", cookie_hdr.has_nonstandard_attr("HttpOnly"))

        # ==================================================================
        section("PHP admin - CRUD with uploads")
        form = admin.get(PHP + "student_create.php")
        token = token_from(form.text)
        base = {"full_name": "Endtoend Admin Student", "roll_number": roll_admin.lower(), "email": f"e2e.a{suffix}@example.edu",
                "course": "BE-CSE", "document_category": "id_proof"}

        r = admin.post(PHP + "student_create.php", data=base, allow_redirects=False)
        check("add student without CSRF token -> 403", r.status_code == 403)
        r = admin.post(PHP + "student_create.php", data={**base, "csrf_token": token, "email": "not-an-email",
                                                         "full_name": "<script>alert(1)</script>", "course": "HACK"})
        check("invalid input -> field errors shown", "Enter a valid email address" in r.text and "Use letters" in r.text
              and "Select a course from the list" in r.text)
        check("submitted script tag is escaped in the re-displayed form", "<script>alert(1)</script>" not in r.text
              and "&lt;script&gt;alert(1)&lt;/script&gt;" in r.text)

        photo_bytes = (FIX / "valid-photo.png").read_bytes()
        pdf_bytes = (FIX / "valid-document.pdf").read_bytes()
        r = admin.post(PHP + "student_create.php", data={**base, "csrf_token": token},
                       files={"profile_photo": ("my photo.png", photo_bytes, "image/png"),
                              "document": ("ID card (front).pdf", pdf_bytes, "application/pdf")}, allow_redirects=False)
        loc = r.headers.get("Location", "")
        m = re.search(r"student_view\.php\?id=(\d+)", loc)
        check("add valid student with photo + document (303 -> profile)", r.status_code == 303 and m,
              f"{r.status_code} {loc} " + " | ".join(re.findall(r'<li><a href="#[^"]+">([^<]+)</a>', r.text)))
        sid = int(m.group(1)) if m else 0
        created_students.append(sid)

        view = admin.get(PHP + f"student_view.php?id={sid}")
        check("profile shows normalised roll number", roll_admin in view.text)
        check("profile lists the uploaded document by original name", "ID card (front).pdf" in view.text)
        file_ids = [int(x) for x in re.findall(r'file\.php\?id=(\d+)', view.text)]
        photo_id = int(re.search(r'<img src="file\.php\?id=(\d+)" alt="Profile photo', view.text).group(1))
        doc_id = int(re.search(r'file\.php\?id=(\d+)&amp;download=1', view.text).group(1))
        check("profile shows image via protected file.php endpoint", photo_id in file_ids)

        r = admin.post(PHP + "student_create.php", data={**base, "csrf_token": token, "email": f"other{suffix}@example.edu"})
        check("duplicate roll number rejected", "already exists" in r.text)

        lst = admin.get(PHP + "students.php", params={"q": roll_admin})
        check("list/search finds the new student", "Endtoend Admin Student" in lst.text and "1 student" in lst.text)
        lst = admin.get(PHP + "students.php", params={"q": "' OR 1=1 -- "})
        check("SQL injection in search -> no rows, no SQL error", lst.status_code == 200 and "No students match" in lst.text)
        lst = admin.get(PHP + "students.php", params={"q": "<script>alert('x')</script>"})
        check("reflected search text is HTML-escaped", "<script>alert('x')</script>" not in lst.text and "&lt;script&gt;" in lst.text)
        lst = admin.get(PHP + "students.php", params={"sort": "name; DROP TABLE students", "page": "-5"})
        check("malicious sort/page parameters ignored safely", lst.status_code == 200)

        r = admin.get(PHP + f"file.php?id={photo_id}")
        check("image streamed with correct type and length", r.status_code == 200 and r.headers["Content-Type"] == "image/png"
              and int(r.headers["Content-Length"]) == len(photo_bytes))
        check("streamed image is byte-identical to the upload", sha256(r.content) == sha256(photo_bytes))
        r = admin.get(PHP + f"file.php?id={doc_id}&download=1")
        cd = r.headers.get("Content-Disposition", "")
        check("document download uses attachment + safe filename", r.status_code == 200 and cd.startswith("attachment;")
              and "filename*=UTF-8''ID%20card%20%28front%29.pdf" in cd and r.headers["Content-Type"] == "application/pdf")
        for bad in ("999999999", "abc", "../../.env", "-1"):
            code = admin.get(PHP + f"file.php?id={bad}").status_code
            check(f"file.php?id={bad} -> 404", code == 404)

        # ---- uploads to an existing student ----
        def upload(name, data, category, mime="application/octet-stream", extra=None):
            page = admin.get(PHP + f"student_view.php?id={sid}")
            fields = {"csrf_token": token_from(page.text), "student_id": str(sid), "category": category, **(extra or {})}
            return admin.post(PHP + "file_upload.php", data=fields, files={"file": (name, data, mime)})

        r = upload("shell.php.jpg", (FIX / "shell.php.jpg").read_bytes(), "profile_photo", "image/jpeg")
        check("PHP script disguised as .jpg rejected", "Upload failed" in r.text and "must be" in r.text)
        r = upload("photo.jpg", (FIX / "polyglot.jpg").read_bytes(), "profile_photo", "image/jpeg")
        check("JPEG with embedded PHP code rejected", "embedded code" in r.text)
        r = upload("notes.txt", (FIX / "notes.txt").read_bytes(), "certificate", "text/plain")
        check("text file rejected as document", "Upload failed" in r.text)
        r = upload("page.html", (FIX / "page.html").read_bytes(), "other", "application/pdf")
        check("HTML with spoofed PDF MIME rejected", "Upload failed" in r.text)
        big_photo = (FIX / "valid-photo.jpg").read_bytes() + b"\0" * (2 * 1024 * 1024)
        r = upload("big.jpg", big_photo, "profile_photo", "image/jpeg")
        check("oversized photo (> 2 MB) rejected", "maximum size is 2 MB" in r.text)
        big_doc = b"%PDF-1.4\n" + b"% pad\n" * (6 * 1024 * 1024 // 6)
        r = upload("big.pdf", big_doc, "certificate", "application/pdf", {"MAX_FILE_SIZE": str(5 * 1024 * 1024)})
        check("document over MAX_FILE_SIZE rejected (UPLOAD_ERR_FORM_SIZE)", "too large" in r.text)
        r = upload("huge.pdf", b"%PDF-1.4\n" + b"0" * (41 * 1024 * 1024), "certificate", "application/pdf")
        check("request over post_max_size reported clearly", "too large" in r.text)
        r = upload("photo.png", photo_bytes, "../../etc", "image/png")
        check("invalid category rejected", "Upload failed" in r.text)

        before_ids = set(int(x) for x in re.findall(r'file\.php\?id=(\d+)&amp;download=1', admin.get(PHP + f"student_view.php?id={sid}").text))
        upload("certificate.pdf", pdf_bytes, "certificate", "application/pdf")
        upload("certificate.pdf", pdf_bytes, "certificate", "application/pdf")
        page = admin.get(PHP + f"student_view.php?id={sid}").text
        after_ids = set(int(x) for x in re.findall(r'file\.php\?id=(\d+)&amp;download=1', page))
        new_ids = sorted(after_ids - before_ids)
        check("same filename uploaded twice -> two separate files (no overwrite)", len(new_ids) == 2)
        cert_files = files_for(sid, "certificate")
        check("two distinct generated names on disk", len(cert_files) == 2 and cert_files[0].name != cert_files[1].name)
        check("both copies downloadable", all(admin.get(PHP + f"file.php?id={i}").status_code == 200 for i in new_ids))

        # ---- missing physical file ----
        missing_id = new_ids[-1]
        newest = files_for(sid, "certificate")[-1]
        newest.unlink()
        page = admin.get(PHP + f"student_view.php?id={sid}").text
        check("missing file flagged on profile page", "File missing from storage" in page)
        r = admin.get(PHP + f"file.php?id={missing_id}")
        check("missing file -> friendly 404", r.status_code == 404 and "missing from storage" in r.text)
        r = admin.post(PHP + "file_delete.php", data={"csrf_token": token_from(page), "id": str(missing_id)}, allow_redirects=False)
        check("record of a missing file can still be deleted", r.status_code == 303)

        # ---- edit ----
        edit = admin.get(PHP + f"student_edit.php?id={sid}")
        r = admin.post(PHP + "student_edit.php", data={"csrf_token": token_from(edit.text), "id": str(sid),
                                                        "full_name": "Endtoend Admin Student Edited", "roll_number": roll_admin,
                                                        "email": f"e2e.a{suffix}@example.edu", "course": "BE-ECE"},
                       allow_redirects=True)
        check("edit student saves changes", "Endtoend Admin Student Edited" in r.text and "Electronics" in r.text)
        r = admin.post(PHP + "student_edit.php", data={"csrf_token": token_from(edit.text), "id": str(sid),
                                                        "full_name": "X Y", "roll_number": "23CS001",
                                                        "email": f"e2e.a{suffix}@example.edu", "course": "BE-ECE"})
        check("edit to an existing roll number rejected", "already exists" in r.text)
        check("edit of unknown id -> 404", admin.get(PHP + "student_edit.php?id=999999999").status_code == 404)

        # ---- CSRF on state-changing actions ----
        r = admin.post(PHP + "student_delete.php", data={"id": str(sid)}, allow_redirects=False)
        check("delete without CSRF token -> 403", r.status_code == 403)
        r = admin.post(PHP + "student_delete.php", data={"id": str(sid), "csrf_token": "forged-token"}, allow_redirects=False)
        check("delete with forged CSRF token -> 403", r.status_code == 403)
        check("student still exists after forged requests", admin.get(PHP + f"student_view.php?id={sid}").status_code == 200)
        check("GET on file_delete.php -> 405", admin.get(PHP + "file_delete.php?id=1").status_code == 405)
        check("cross-site logout (no token) rejected", admin.post(PHP + "logout.php", allow_redirects=False).status_code == 403)

        # ==================================================================
        section("Public registration endpoint (Question 1 -> PHP)")
        reg = requests.Session()
        r = reg.post(PHP + "register.php", data={"full_name": "No Token"}, headers={"Accept": "application/json"})
        check("registration without token -> 403 JSON", r.status_code == 403 and r.json()["ok"] is False)
        tok = reg.get(PHP + "api/registration-token.php").json()["token"]
        bad = {"csrf_token": tok, "full_name": "A", "roll_number": "x!", "email": "bad", "course": "", "password": "weak",
               "confirm_password": "other"}
        r = reg.post(PHP + "register.php", data=bad, headers={"Accept": "application/json"})
        errs = r.json().get("errors", {})
        check("server-side validation returns field errors (422)", r.status_code == 422 and
              {"full_name", "roll_number", "email", "course", "password", "confirm_password", "declaration", "profile_photo"} <= set(errs))
        good = {"csrf_token": tok, "full_name": "Endtoend Self Registered", "roll_number": roll_self, "email": f"e2e.s{suffix}@example.edu",
                "course": "BTECH-IT", "password": student_pw, "confirm_password": student_pw, "declaration": "1"}
        r = reg.post(PHP + "register.php", data=good, headers={"Accept": "application/json"},
                     files={"profile_photo": ("selfie.webp", (FIX / "valid-photo.webp").read_bytes(), "image/webp"),
                            "id_proof": ("aadhaar.pdf", pdf_bytes, "application/pdf")})
        body = r.json()
        check("valid registration accepted (201)", r.status_code == 201 and body.get("ok") and body["student"]["roll_number"] == roll_self,
              str(body)[:200])
        r = reg.post(PHP + "register.php", data=good, headers={"Accept": "application/json"},
                     files={"profile_photo": ("selfie.webp", (FIX / "valid-photo.webp").read_bytes(), "image/webp")})
        check("re-submitting the same form (used token) rejected", r.status_code == 403)
        tok2 = reg.get(PHP + "api/registration-token.php").json()["token"]
        r = reg.post(PHP + "register.php", data={**good, "csrf_token": tok2}, headers={"Accept": "application/json"},
                     files={"profile_photo": ("selfie.webp", (FIX / "valid-photo.webp").read_bytes(), "image/webp")})
        check("duplicate roll number on registration rejected", r.status_code == 422 and "roll_number" in r.json().get("errors", {}))
        r = reg.post(PHP + "register.php", data={**good, "csrf_token": tok2}, headers={"Origin": "http://evil.example"})
        check("cross-origin registration POST rejected", r.status_code == 403)

        lst = admin.get(PHP + "students.php", params={"q": roll_self}).text
        m = re.search(r'student_view\.php\?id=(\d+)', lst)
        self_id = int(m.group(1)) if m else 0
        created_students.append(self_id)
        check("self-registered student visible in admin list", self_id > 0 and "Endtoend Self Registered" in lst)

        # ==================================================================
        section("Java Servlet portal - authentication")
        r = requests.get(JAVA + "students", allow_redirects=False)
        check("unauthenticated /students -> redirect to /login", r.status_code == 302 and "/login" in r.headers["Location"])
        r = requests.get(JAVA + f"file?id={photo_id}", allow_redirects=False)
        check("unauthenticated /file -> 401, no file bytes", r.status_code == 401 and sha256(r.content) != sha256(photo_bytes))
        r = requests.get(JAVA + f"photo?student={sid}", allow_redirects=False)
        check("unauthenticated /photo -> 401", r.status_code == 401)
        s, r = java_login(staff_user, "Wrong!Pass123")
        check("wrong password rejected", r.status_code == 401 and "Invalid username or password" in r.text)
        s, r = java_login("' OR 1=1 -- ", "x' OR '1'='1")
        check("SQL injection in login form fails", r.status_code == 401)
        s = requests.Session()
        s.get(JAVA + "login")
        r = s.post(JAVA + "login", data={"username": staff_user, "password": staff_pw}, allow_redirects=False)
        check("login without CSRF token rejected", r.status_code == 401 and "expired" in r.text)

        staff = requests.Session()
        page = staff.get(JAVA + "login")
        before = staff.cookies.get("SMP_PORTAL_SESSION")
        r = staff.post(JAVA + "login", data={"csrf_token": token_from(page.text), "username": staff_user, "password": staff_pw},
                       allow_redirects=False)
        after = staff.cookies.get("SMP_PORTAL_SESSION")
        check("staff login succeeds (PHP bcrypt hash verified in Java)", r.status_code == 302 and r.headers["Location"].endswith("/students"))
        check("session ID changed after login", before and after and before != after)

        # ==================================================================
        section("Java Servlet portal - same data as PHP (shared DB + storage)")
        lst = staff.get(JAVA + "students")
        check("student list renders multiple students", lst.status_code == 200 and lst.text.count('class="card student-card"') >= 2)
        zero = staff.get(JAVA + "students", params={"q": "23ME045"})
        check("list shows the 'no files' state for a student without files", "No files uploaded yet" in zero.text)
        check("Cache-Control no-store on protected pages", lst.headers.get("Cache-Control") == "no-store")
        r = staff.get(JAVA + "students", params={"q": roll_admin})
        check("PHP-created student found via Java search", "Endtoend Admin Student Edited" in r.text)
        check("<img> points to the photo servlet", f'src="/student-portal-servlet/photo?student={sid}"' in r.text)
        prof = staff.get(JAVA + f"student?id={sid}")
        check("Java profile shows same name/roll/course as PHP", prof.status_code == 200 and "Endtoend Admin Student Edited" in prof.text
              and roll_admin in prof.text and "Electronics and Communication" in prof.text)
        check("Java profile lists the document uploaded in PHP", "ID card (front).pdf" in prof.text)
        r = staff.get(JAVA + f"photo?student={sid}")
        check("photo servlet streams the PHP-uploaded image (byte-identical)", r.status_code == 200
              and r.headers["Content-Type"] == "image/png" and sha256(r.content) == sha256(photo_bytes))
        check("Content-Length set on streamed image", int(r.headers.get("Content-Length", -1)) == len(photo_bytes))
        r = staff.get(JAVA + f"file?id={doc_id}&download=1")
        cd = r.headers.get("Content-Disposition", "")
        check("document download: PDF type, attachment, RFC 5987 name", r.status_code == 200
              and r.headers["Content-Type"] == "application/pdf" and cd.startswith("attachment;")
              and "filename*=UTF-8''ID%20card%20%28front%29.pdf" in cd and sha256(r.content) == sha256(pdf_bytes))
        r = staff.get(JAVA + f"file?id={doc_id}")
        check("document view inline", r.headers.get("Content-Disposition", "").startswith("inline;"))
        multi = staff.get(JAVA + "students", params={"q": "23CS001"}).text
        check("student with many files shows file count + chips", re.search(r"\d+ files", multi) is not None and "chip-pdf" in multi)

        section("Java Servlet portal - invalid input & errors")
        for path, code in ((f"student?id=abc", 400), ("student?id=0", 400), ("student?id=999999999", 404), ("student", 400),
                           ("file?id=999999999", 404), ("file?id=../../.env", 400), ("photo?student=-3", 400),
                           (f"file?id={missing_id}", 404), ("no-such-page", 404)):
            r = staff.get(JAVA + path)
            check(f"/{path} -> {code}", r.status_code == code, str(r.status_code))
        r = staff.get(JAVA + "student?id=abc")
        check("error pages expose no stack trace", "Exception" not in r.text and "at edu." not in r.text)
        r = staff.get(JAVA + "students", params={"q": "' OR '1'='1"})
        check("SQL injection in Java search -> no rows", "No students match your search" in r.text)
        r = staff.get(JAVA + "students", params={"q": "<img src=x onerror=alert(1)>"})
        check("Java escapes reflected search text", "<img src=x onerror" not in r.text and "&lt;img src=x" in r.text)
        sneha = staff.get(JAVA + "students", params={"q": "23ME045"}).text
        m = re.search(r'student\?id=(\d+)', sneha)
        if m:
            p = staff.get(JAVA + f"student?id={m.group(1)}").text
            check("profile with zero files shows empty states", "No documents" in p and "No images" in p)
            check("photo servlet -> 404 for student without photo", staff.get(JAVA + f"photo?student={m.group(1)}").status_code == 404)

        section("Java Servlet portal - student role authorisation")
        stu, r = java_login(roll_self, student_pw)
        check("self-registered student can sign in to Java portal", r.status_code == 302 and f"/student?id={self_id}" in r.headers["Location"])
        r = stu.get(JAVA + "students", allow_redirects=False)
        check("student is redirected away from the full list", r.status_code == 302 and f"student?id={self_id}" in r.headers["Location"])
        check("student can open own profile", stu.get(JAVA + f"student?id={self_id}").status_code == 200)
        check("student cannot open another profile (403)", stu.get(JAVA + f"student?id={sid}").status_code == 403)
        check("student cannot download another student's file (403)", stu.get(JAVA + f"file?id={doc_id}").status_code == 403)
        check("student cannot fetch another student's photo (403)", stu.get(JAVA + f"photo?student={sid}").status_code == 403)
        own = stu.get(JAVA + f"photo?student={self_id}")
        check("student can see own photo (WebP)", own.status_code == 200 and own.headers["Content-Type"] == "image/webp")
        s_admin_panel, r = php_login(roll_self, student_pw)
        check("student account cannot use PHP admin panel", "does not have access" in r.text)

        section("Logout")
        check("GET /logout not allowed (405)", staff.get(JAVA + "logout").status_code == 405)
        check("POST /logout without CSRF -> 403", staff.post(JAVA + "logout", allow_redirects=False).status_code == 403)
        old_cookie = staff.cookies.get("SMP_PORTAL_SESSION")
        page = staff.get(JAVA + f"student?id={sid}")
        r = staff.post(JAVA + "logout", data={"csrf_token": token_from(page.text)}, allow_redirects=False)
        check("Java logout redirects to login", r.status_code == 302 and "logged_out" in r.headers["Location"])
        replay = requests.get(JAVA + "students", cookies={"SMP_PORTAL_SESSION": old_cookie}, allow_redirects=False)
        check("old Java session cookie is useless after logout", replay.status_code == 302 and "expired=1" in replay.headers["Location"])

        php_tmp, _ = php_login(admin_user, admin_pw)
        old_php = php_tmp.cookies.get("SMP_ADMIN_SESSION")
        page = php_tmp.get(PHP + "dashboard.php")
        r = php_tmp.post(PHP + "logout.php", data={"csrf_token": token_from(page.text)}, allow_redirects=False)
        check("PHP logout redirects to login", r.status_code == 303 and "logged_out" in r.headers["Location"])
        replay = requests.get(PHP + "dashboard.php", cookies={"SMP_ADMIN_SESSION": old_php}, allow_redirects=False)
        check("old PHP session cookie is useless after logout", replay.status_code == 303 and "login.php" in replay.headers["Location"])

        section("PHP idle timeout (session file aged on the server)")
        aged, _ = php_login(admin_user, admin_pw)
        sess_file = SESSION_DIR / f"sess_{aged.cookies.get('SMP_ADMIN_SESSION')}"
        if sess_file.exists():
            content = sess_file.read_text()
            sess_file.write_text(re.sub(r"last_activity\|i:(\d+);", lambda mm: f"last_activity|i:{int(mm.group(1)) - 86400};", content))
            r = aged.get(PHP + "dashboard.php", allow_redirects=False)
            check("idle session -> redirect to login with 'expired'", r.status_code == 303 and "expired=1" in r.headers["Location"])
            check("login page explains the session expired", "session expired" in aged.get(PHP + "login.php?expired=1").text)
        else:
            check("PHP session file found for timeout test", False, str(sess_file))

        # ==================================================================
        section("Delete (PHP) and effect on both modules")
        other_photo_before = admin.get(PHP + f"file.php?id={photo_id}")
        r_view = admin.get(PHP + f"student_view.php?id={self_id}")
        self_files = files_for(self_id, "profile_photo") + files_for(self_id, "id_proof")
        r = admin.post(PHP + "student_delete.php", data={"csrf_token": token_from(r_view.text), "id": str(self_id)}, allow_redirects=False)
        deleted_ok = r.status_code == 303 and "students.php" in r.headers.get("Location", "")
        check("delete student (POST + CSRF) -> 303 to list", deleted_ok)
        if deleted_ok:
            created_students.remove(self_id)
        check("deleted student's files removed from disk", self_files and not any(p.exists() for p in self_files))
        check("deleted student's folder removed", not (ROOT / "storage" / "uploads" / "students" / str(self_id)).exists())
        check("other student's photo untouched", admin.get(PHP + f"file.php?id={photo_id}").content == other_photo_before.content)
        check("deleted student's login removed (cascade)", java_login(roll_self, student_pw)[1].status_code == 401)
        staff2, _ = java_login(staff_user, staff_pw)
        check("Java portal returns 404 for the deleted student", staff2.get(JAVA + f"student?id={self_id}").status_code == 404)
        check("delete of unknown id -> 404", admin.post(PHP + "student_delete.php", data={"csrf_token": token_from(r_view.text),
                                                                                         "id": "999999999"}).status_code == 404)
        if args.session_expiry:
            section("REAL session expiry (waiting for SESSION_TIMEOUT_MINUTES=1)")
            p_sess, _ = php_login(admin_user, admin_pw)
            j_sess, _ = java_login(staff_user, staff_pw)
            check("both sessions valid before waiting", p_sess.get(PHP + "dashboard.php", allow_redirects=False).status_code == 200
                  and j_sess.get(JAVA + "students", allow_redirects=False).status_code == 200)
            print("  ... waiting 75 seconds for both sessions to expire")
            time.sleep(75)
            r = p_sess.get(PHP + "dashboard.php", allow_redirects=False)
            check("PHP session expired after idle timeout", r.status_code == 303 and "expired=1" in r.headers["Location"])
            r = j_sess.get(JAVA + "students", allow_redirects=False)
            check("Java session expired after idle timeout", r.status_code == 302 and "expired=1" in r.headers["Location"])
    finally:
        section("Cleanup")
        if created_students:
            cleaner, _ = php_login(admin_user, admin_pw)
            for sid_ in created_students:
                page = cleaner.get(PHP + f"student_view.php?id={sid_}")
                if page.status_code == 200:
                    cleaner.post(PHP + "student_delete.php", data={"csrf_token": token_from(page.text), "id": str(sid_)})
            print(f"  removed test students {created_students}")
        for user in (admin_user, staff_user):
            run_php_cli("delete_user.php", f"--username={user}")
        print("  removed temporary accounts")

    passed = sum(1 for _, ok, _ in RESULTS if ok)
    failed = len(RESULTS) - passed
    print(f"\n{passed} passed, {failed} failed")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
