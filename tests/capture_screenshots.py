"""
Captures genuine browser screenshots of the RUNNING applications into
screenshots/ and, on the way, checks the registration form's client-side
validation in a real browser (Chrome).

    pip install playwright==1.47.0          (uses your installed Google Chrome)
    python tests/capture_screenshots.py

Accounts are read from DEMO_CREDENTIALS.local.md (created when the demo
accounts were set up) or from the environment variables SMP_ADMIN_PASSWORD,
SMP_STAFF_PASSWORD and SMP_STUDENT_PASSWORD (student = 23CS001).

The script performs real actions: it registers one student through the
public form and adds one student through the PHP admin panel (skipped if
those roll numbers already exist).
"""
import os
import re
import secrets
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "screenshots"
ART = ROOT / "tests" / ".artifacts"
FIX = ROOT / "tests" / "fixtures"
SITE = os.environ.get("SMP_SITE_URL", "http://localhost/wt-portal/")
PHP = SITE + "php-admin/public/"
JAVA = os.environ.get("SMP_JAVA_URL", "http://localhost:8080/student-portal-servlet/")

RESULTS = []


def check(name, cond):
    RESULTS.append((name, bool(cond)))
    print(("  PASS  " if cond else "  FAIL  ") + name)


def credentials():
    creds = {"admin": os.environ.get("SMP_ADMIN_PASSWORD"), "staff": os.environ.get("SMP_STAFF_PASSWORD"),
             "23CS001": os.environ.get("SMP_STUDENT_PASSWORD")}
    local = ROOT / "DEMO_CREDENTIALS.local.md"
    if local.exists():
        for user, pw in re.findall(r"^\|\s*\w+\s*\|\s*(\S+)\s*\|\s*(\S+)\s*\|", local.read_text(encoding="utf-8"), re.M):
            if user in creds and not creds[user]:
                creds[user] = pw
    missing = [u for u, p in creds.items() if not p]
    if missing:
        sys.exit(f"Missing passwords for: {missing}. Set SMP_*_PASSWORD environment variables.")
    return creds


def make_avatar(path, initials, colour):
    """Small generated portrait used for the demo uploads (needs Pillow)."""
    from PIL import Image, ImageDraw, ImageFont
    img = Image.new("RGB", (360, 360), "#edf8f7")
    d = ImageDraw.Draw(img)
    d.ellipse((-90, 200, 450, 700), fill=colour)
    d.ellipse((110, 50, 250, 190), fill=colour)
    try:
        font = ImageFont.truetype("segoeuib.ttf", 54)
    except OSError:
        font = ImageFont.load_default()
    w = d.textbbox((0, 0), initials, font=font)[2]
    d.text(((360 - w) / 2, 275), initials, fill="#edf8f7", font=font)
    img.save(path, "JPEG", quality=88)


def submit(page, selector):
    """Click a submit button and wait for the resulting page load."""
    with page.expect_navigation():
        page.click(selector)


def shot(page, name, full=True):
    page.screenshot(path=str(OUT / f"{name}.png"), full_page=full)
    print(f"  saved  screenshots/{name}.png")


def main():
    creds = credentials()
    OUT.mkdir(exist_ok=True)
    ART.mkdir(parents=True, exist_ok=True)
    priya_photo, vikram_photo = ART / "priya-photo.jpg", ART / "vikram-photo.jpg"
    make_avatar(priya_photo, "PR", "#0f8b8d")
    make_avatar(vikram_photo, "VS", "#1c2a4a")
    big = ART / "oversized-photo.jpg"
    big.write_bytes((FIX / "valid-photo.jpg").read_bytes() + b"\0" * (3 * 1024 * 1024))

    with sync_playwright() as p:
        browser = p.chromium.launch(channel="chrome", headless=True)
        desktop = dict(viewport={"width": 1366, "height": 860}, device_scale_factor=1)

        # ================= Question 1: registration page =================
        print("\n== Registration form (browser checks) ==")
        ctx = browser.new_context(**desktop)
        page = ctx.new_page()
        page.goto(SITE + "frontend/index.html")
        shot(page, "01-landing-page")
        page.goto(SITE + "frontend/register.html")
        page.wait_for_function("document.getElementById('csrf_token').value.length > 10")
        shot(page, "02-registration-form")

        page.click("#submit-button")
        page.wait_for_selector("#error-summary:not([hidden])")
        errors = page.inner_text("#error-list")
        check("empty submit lists every required field", all(t in errors for t in
              ["Student name", "Roll number", "Course", "Email", "Password", "Profile photo", "Declaration"]))
        shot(page, "03-registration-empty-errors")

        page.fill("#full_name", "Priya Raman")
        page.fill("#roll_number", "23cs 45")
        page.fill("#email", "priya.raman@example")
        page.fill("#password", "priya123")
        page.fill("#confirm_password", "priya124")
        page.set_input_files("#profile_photo", str(FIX / "fake-image.jpg"))
        page.set_input_files("#id_proof", str(FIX / "notes.txt"))
        page.click("#submit-button")
        page.wait_for_timeout(400)
        check("invalid email message shown", "Enter an email address like" in page.inner_text("#email-error"))
        check("weak password explained", "uppercase letter" in page.inner_text("#password-error")
              and "symbol" in page.inner_text("#password-error"))
        check("strength meter shows Weak/Fair", page.inner_text("#strength-text") in ("Weak", "Fair"))
        check("password mismatch detected", "do not match" in page.inner_text("#confirm_password-error"))
        check("roll number with space rejected", "only letters and digits" in page.inner_text("#roll_number-error"))
        check("file whose bytes are not an image rejected", "do not match an allowed" in page.inner_text("#profile_photo-error"))
        check("invalid document type (.txt) rejected", "must be a PDF, JPG or PNG" in page.inner_text("#id_proof-error"))
        shot(page, "04-registration-validation-errors")

        page.set_input_files("#profile_photo", str(big))
        page.wait_for_timeout(300)
        check("oversized photo rejected on the client", "maximum size is 2 MB" in page.inner_text("#profile_photo-error"))
        shot(page, "05-registration-oversized-file", full=False)

        roll = os.environ.get("SMP_DEMO_ROLL", "23CS045")
        student_pw = "Kpr!" + secrets.token_hex(4) + "Ab"
        page.fill("#roll_number", roll)
        page.fill("#email", "priya.raman@example.edu")
        page.select_option("#course", "BE-CSE")
        page.fill("#password", student_pw)
        page.fill("#confirm_password", student_pw)
        page.set_input_files("#profile_photo", str(priya_photo))
        page.set_input_files("#id_proof", str(FIX / "valid-document.pdf"))
        page.check("#declaration")
        page.wait_for_timeout(300)
        check("strong password graded Good/Strong", page.inner_text("#strength-text") in ("Good", "Strong"))
        check("no field errors remain", page.eval_on_selector_all(".field-error", "els => els.every(e => !e.textContent)"))
        shot(page, "06-registration-valid-ready")
        page.click("#submit-button")
        page.wait_for_selector("#success-card:not([hidden]), #error-summary:not([hidden]), #form-notice:not([hidden])")
        registered = page.is_visible("#success-card")
        if registered:
            check("valid registration accepted by the server", "Registration received" in page.inner_text("#success-card"))
            with (ROOT / "DEMO_CREDENTIALS.local.md").open("a", encoding="utf-8") as fh:
                fh.write(f"| student | {roll} | {student_pw} | Java portal (self-registered via form) |\n")
            shot(page, "07-registration-success")
        else:
            print("  note   registration not accepted (roll number probably exists already) - keeping previous screenshot 07")
        ctx.close()

        mobile = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2, is_mobile=True)
        mpage = mobile.new_page()
        mpage.goto(SITE + "frontend/register.html")
        shot(mpage, "08-registration-mobile", full=False)

        # ================= Question 2: PHP admin =================
        print("\n== PHP admin panel ==")
        ctx = browser.new_context(**desktop)
        page = ctx.new_page()
        page.goto(PHP + "dashboard.php")
        check("unauthenticated dashboard redirects to login", "login.php" in page.url)
        page.fill("#username", "admin")
        page.fill("#password", "wrong-password")
        submit(page, "button[type=submit]")
        page.wait_for_selector(".alert-error")
        shot(page, "09-php-login-error")
        page.fill("#username", "admin")
        page.fill("#password", creds["admin"])
        submit(page, "button[type=submit]")
        page.wait_for_url("**/dashboard.php")
        shot(page, "10-php-dashboard")

        page.goto(PHP + "students.php")
        shot(page, "11-php-student-list")
        page.fill("#q", "CS")
        submit(page, "form.filters button[type=submit]")
        page.wait_for_load_state()
        shot(page, "12-php-student-search")
        page.goto(PHP + "students.php")
        page.click("a[data-confirm-delete] >> nth=0")
        page.wait_for_selector("#confirm-dialog[open]")
        shot(page, "13-php-delete-confirmation", full=False)
        page.click("[data-close-dialog]")

        page.goto(PHP + "student_create.php")
        page.fill("#full_name", "V1kram <b>")
        page.fill("#roll_number", "23CS001")
        page.fill("#email", "vikram@")
        # The browser's own HTML5 checks would stop this form; switch them off
        # (as an attacker could) to prove the SERVER rejects the bad input too.
        page.eval_on_selector("form.form-card", "f => { f.noValidate = true; }")
        page.eval_on_selector_all("form.form-card [required], form.form-card [pattern]",
                                  "els => els.forEach(e => { e.removeAttribute('required'); e.removeAttribute('pattern'); })")
        submit(page, "form.form-card button[type=submit]")
        page.wait_for_selector(".alert-error")
        check("server-side validation errors displayed", "Enter a valid email" in page.content())
        shot(page, "14-php-add-student-validation")

        page.goto(PHP + "student_create.php")
        page.fill("#full_name", "Vikram Sethu")
        page.fill("#roll_number", "23EE012")
        page.fill("#email", "vikram.sethu@example.edu")
        page.select_option("#course", "BE-EEE")
        page.set_input_files("#profile_photo", str(vikram_photo))
        page.set_input_files("#document", str(FIX / "valid-document.pdf"))
        page.select_option("#document_category", "certificate")
        shot(page, "15-php-add-student-form")
        submit(page, "form.form-card button[type=submit]")
        page.wait_for_load_state()
        if "student_view.php" in page.url:
            check("student added through the admin form", "was added" in page.content())
            vikram_url = page.url
        else:
            print("  note   23EE012 already exists - using existing record")
            page.goto(PHP + "students.php?q=23EE012")
            submit(page, "a.strong")
            vikram_url = page.url
        shot(page, "16-php-student-profile-new")

        page.goto(vikram_url.replace("student_view", "student_edit"))
        page.fill("#email", "vikram.s@example.edu")
        shot(page, "17-php-edit-student")
        submit(page, "form.form-card button[type=submit]")
        page.wait_for_load_state()
        check("edit saved", "were saved" in page.content())

        page.goto(PHP + "students.php?q=23CS001")
        submit(page, "a.strong")
        shot(page, "18-php-student-profile-documents")
        page.select_option("#category", "profile_photo")
        page.set_input_files("#file", str(FIX / "shell.php.jpg"))
        submit(page, "form.upload-form button[type=submit]")
        page.wait_for_load_state()
        check("disguised PHP upload rejected in the browser flow", "Upload failed" in page.content())
        shot(page, "19-php-upload-rejected", full=False)

        doc_href = page.get_attribute("a[href*='download=1']", "href")
        with page.expect_download() as dl:
            page.click(f"a[href='{doc_href.replace('&', '&')}']")
        download = dl.value
        check("document download delivers original filename", download.suggested_filename.endswith(".pdf"))
        img_doc = PHP + "students.php?q=23IT022"
        page.goto(img_doc)
        submit(page, "a.strong")
        file_link = page.get_attribute(".doc-actions a[target=_blank]", "href")
        page.goto(PHP + file_link)
        shot(page, "20-php-document-view", full=False)

        mobile_admin = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2, is_mobile=True,
                                           storage_state=ctx.storage_state())
        mp = mobile_admin.new_page()
        mp.goto(PHP + "students.php")
        shot(mp, "21-php-student-list-mobile", full=False)
        mobile_admin.close()
        ctx.close()

        # ================= Question 3: Java Servlet portal =================
        print("\n== Java Servlet portal ==")
        ctx = browser.new_context(**desktop)
        page = ctx.new_page()
        page.goto(JAVA + "students")
        check("unauthenticated /students redirected to login", "/login" in page.url)
        shot(page, "22-servlet-login")
        page.fill("#username", "staff")
        page.fill("#password", creds["staff"])
        submit(page, "button[type=submit]")
        page.wait_for_url("**/students")
        shot(page, "23-servlet-student-list")
        page.fill("#q", "23CS001")
        submit(page, "form.search button[type=submit]")
        page.wait_for_load_state()
        submit(page, ".student-card h2 a")
        shot(page, "24-servlet-profile-multiple-files")
        page.goto(JAVA + "students?q=23ME045")
        submit(page, ".student-card h2 a")
        check("zero-file profile shows empty states", "No documents" in page.content())
        shot(page, "25-servlet-profile-no-files")
        page.goto(JAVA + "students?q=23EE012")
        submit(page, ".student-card h2 a")
        check("student added in PHP visible in Java", "Vikram Sethu" in page.content())
        shot(page, "26-servlet-profile-created-in-php")
        page.goto(JAVA + f"students?q={roll}")
        if page.locator(".student-card h2 a").count():
            submit(page, ".student-card h2 a")
            shot(page, "27-servlet-profile-self-registered")
        sid = re.search(r"id=(\d+)", page.url).group(1)
        page.goto(JAVA + f"photo?student={sid}")
        shot(page, "28-servlet-photo-stream", full=False)
        page.goto(JAVA + "student?id=abc")
        shot(page, "29-servlet-invalid-id", full=False)
        page.goto(JAVA + "student?id=999999")
        shot(page, "30-servlet-missing-record", full=False)
        page.goto(JAVA + "students")
        submit(page, ".user-box button[type=submit]")
        shot(page, "31-servlet-logged-out", full=False)
        page.goto(JAVA + "students")
        check("after logout protected page needs login again", "/login" in page.url)
        resp = page.goto(JAVA + "file?id=1")
        check("after logout file access is 401", resp.status == 401)
        shot(page, "32-servlet-file-unauthenticated", full=False)
        ctx.close()

        ctx = browser.new_context(**desktop)
        page = ctx.new_page()
        page.goto(JAVA + "login")
        page.fill("#username", "23CS001")
        page.fill("#password", creds["23CS001"])
        submit(page, "button[type=submit]")
        page.wait_for_load_state()
        check("student lands on own profile", "/student?id=" in page.url)
        shot(page, "33-servlet-student-own-profile")
        resp = page.goto(JAVA + "student?id=2")
        check("student blocked from another profile (403)", resp.status == 403)
        shot(page, "34-servlet-student-forbidden", full=False)
        ctx.close()

        mobile = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2, is_mobile=True)
        mp = mobile.new_page()
        mp.goto(JAVA + "login")
        mp.fill("#username", "staff")
        mp.fill("#password", creds["staff"])
        submit(mp, "button[type=submit]")
        mp.wait_for_load_state()
        shot(mp, "35-servlet-student-list-mobile", full=False)
        browser.close()

    passed = sum(1 for _, ok in RESULTS if ok)
    print(f"\n{passed} passed, {len(RESULTS) - passed} failed")
    return 0 if passed == len(RESULTS) else 1


if __name__ == "__main__":
    sys.exit(main())
