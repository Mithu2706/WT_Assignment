"""
Generates the demo files and test fixtures used by the project.

  database/sample-files/   generated avatars + PDFs used by
                           php-admin/bin/seed_sample_files.php (demo data only)
  tests/fixtures/          small valid and malicious files used by the PHP,
                           Java and end-to-end tests

Everything is synthetic - no real student documents are stored in the repo.
Usage:  python tests/generate_fixtures.py      (requires Pillow)
"""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent.parent
SAMPLES = ROOT / "database" / "sample-files"
FIXTURES = ROOT / "tests" / "fixtures"

PALETTE = [("#0f8b8d", "#d7f0ef"), ("#1c2a4a", "#e3e8f2"), ("#b5642c", "#f8e8dc"),
           ("#5b4a9e", "#ebe6f7"), ("#2f7a46", "#e2f1e6"), ("#9c3d54", "#f6e3e8")]


def font(size, bold=False):
    for name in (("segoeuib.ttf" if bold else "segoeui.ttf"), "arial.ttf", "DejaVuSans.ttf"):
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    return ImageFont.load_default()


def avatar(initials, index, size=480):
    """A simple geometric avatar with the student's initials."""
    fg, bg = PALETTE[index % len(PALETTE)]
    img = Image.new("RGB", (size, size), bg)
    d = ImageDraw.Draw(img)
    d.ellipse((-size * .25, size * .55, size * 1.25, size * 1.9), fill=fg)          # shoulders
    d.ellipse((size * .3, size * .14, size * .7, size * .54), fill=fg)              # head
    f = font(int(size * .16), bold=True)
    box = d.textbbox((0, 0), initials, font=f)
    d.text(((size - (box[2] - box[0])) / 2, size * .76), initials, fill=bg, font=f)
    return img


def document(title, lines, accent="#0f8b8d"):
    """A one-page A4-ish 'document' rendered as an image (saved as PDF/PNG)."""
    w, h = 1240, 1754
    img = Image.new("RGB", (w, h), "white")
    d = ImageDraw.Draw(img)
    d.rectangle((0, 0, w, 150), fill="#0b1324")
    d.rectangle((0, 150, w, 160), fill=accent)
    d.text((80, 50), "DEMO DOCUMENT - NOT A REAL RECORD", fill="#a9b4c7", font=font(30))
    d.text((80, 230), title, fill="#172033", font=font(64, bold=True))
    y = 380
    for line in lines:
        d.text((80, y), line, fill="#3a4a6b", font=font(36))
        y += 70
    d.rectangle((80, h - 300, w - 80, h - 296), fill="#e0dbd1")
    d.text((80, h - 260), "Generated for the Student Management Portal sample data.", fill="#5b6475", font=font(28))
    return img


def main():
    SAMPLES.mkdir(parents=True, exist_ok=True)
    FIXTURES.mkdir(parents=True, exist_ok=True)

    # --- Demo files for the seeded students (roll number -> files) ---
    students = [("23CS001", "Aarav Krishnan"), ("23CS014", "Diya Ramesh"), ("23EC007", "Karthik Subramanian"),
                ("23IT022", "Meera Nair"), ("23AD031", "Rahul Varma"), ("23ME045", "Sneha Iyer")]
    for i, (roll, name) in enumerate(students):
        if roll == "23ME045":
            continue  # Sneha is the "student with zero files" demo case
        initials = "".join(p[0] for p in name.split()[:2])
        fmt = ["JPEG", "PNG", "WEBP"][i % 3]
        ext = {"JPEG": "jpg", "PNG": "png", "WEBP": "webp"}[fmt]
        avatar(initials, i).save(SAMPLES / f"{roll}-photo.{ext}", fmt, quality=88)

    document("Identity Proof", ["Name: Aarav Krishnan", "Roll number: 23CS001", "Document no: DEMO-0001"]) \
        .save(SAMPLES / "23CS001-id-proof.pdf", "PDF", resolution=150)
    document("Certificate of Merit", ["Awarded to Aarav Krishnan", "Inter-college coding contest", "First place"], "#b5642c") \
        .save(SAMPLES / "23CS001-certificate.pdf", "PDF", resolution=150)
    document("Semester Marksheet", ["Name: Diya Ramesh", "Semester IV", "CGPA: 8.9"], "#5b4a9e") \
        .save(SAMPLES / "23CS014-marksheet.pdf", "PDF", resolution=150)
    document("Identity Proof", ["Name: Meera Nair", "Roll number: 23IT022", "Document no: DEMO-0004"]) \
        .resize((620, 877)).save(SAMPLES / "23IT022-id-proof.png", "PNG")
    # Karthik (23EC007) and Rahul (23AD031) get a photo only and Sneha (23ME045) gets
    # nothing, so the portals demonstrate students with zero, one and many files.

    # --- Test fixtures ---
    avatar("TS", 0, 200).save(FIXTURES / "valid-photo.jpg", "JPEG", quality=85)
    avatar("TS", 1, 200).save(FIXTURES / "valid-photo.png", "PNG")
    avatar("TS", 2, 200).save(FIXTURES / "valid-photo.webp", "WEBP")
    document("Test Document", ["Fixture for upload tests"]).resize((310, 438)) \
        .save(FIXTURES / "valid-document.pdf", "PDF")

    (FIXTURES / "fake-image.jpg").write_text("This is plain text pretending to be a JPEG.\n")
    (FIXTURES / "shell.php.jpg").write_text("<?php echo 'pwned'; system($_GET['c']); ?>\n")
    (FIXTURES / "notes.txt").write_text("Plain text files are not an allowed upload type.\n")
    (FIXTURES / "page.html").write_text("<html><script>alert(document.cookie)</script></html>\n")
    # Polyglot: a real JPEG with PHP code appended after the image data
    polyglot = (FIXTURES / "valid-photo.jpg").read_bytes() + b"\n<?php system($_GET['c']); ?>\n"
    (FIXTURES / "polyglot.jpg").write_bytes(polyglot)
    print("Generated", len(list(SAMPLES.iterdir())), "sample files and", len(list(FIXTURES.iterdir())), "fixtures")


if __name__ == "__main__":
    main()
