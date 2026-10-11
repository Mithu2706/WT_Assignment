<?php
/**
 * PHP test suite (no external framework needed).
 *
 *   php php-admin/tests/run_tests.php
 *
 * Part 1 - unit tests: validation, password policy, upload validation,
 *          filename generation, path-traversal protection.
 * Part 2 - integration tests against the REAL database configured in .env:
 *          CRUD, duplicates, file replacement, safe deletion, SQL injection.
 *          Test rows use roll numbers starting with "ZZT" and are removed at
 *          the end; files go to a temporary folder, not the real storage.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';
restore_exception_handler();

$fixtures = dirname(__DIR__, 2) . '/tests/fixtures';
$passed = 0;
$failed = 0;
$section = '';

function section(string $name): void
{
    global $section;
    $section = $name;
    fwrite(STDOUT, "\n== $name ==\n");
}

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        fwrite(STDOUT, "  PASS  $name\n");
    } else {
        $failed++;
        fwrite(STDOUT, "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n");
    }
}

/** Run $fn and return the exception it throws (or null). */
function thrown(callable $fn): ?Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e;
    }
    return null;
}

/** Build a $_FILES-like entry for a local file. */
function fileEntry(string $path, string $name = null, int $error = UPLOAD_ERR_OK): array
{
    return ['name' => $name ?? basename($path), 'tmp_name' => $path, 'error' => $error, 'size' => is_file($path) ? filesize($path) : 0, 'type' => 'image/jpeg'];
}

$tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smp-tests-' . bin2hex(random_bytes(4));
mkdir($tmpRoot . '/work', 0777, true);
mkdir($tmpRoot . '/storage', 0777, true);
$storage = new FileStorage($tmpRoot . '/storage');

/* =====================================================================
   PART 1 - UNIT TESTS
   ===================================================================== */
section('Student validation');
$courses = ['BE-CSE' => 1, 'BE-ECE' => 2];
[$clean, $errors] = StudentValidator::validate(['full_name' => '  Priya   Raman ', 'roll_number' => '23cs045', 'email' => 'Priya@Example.EDU', 'course' => 'BE-CSE'], $courses);
check('valid input passes', $errors === [], json_encode($errors));
check('roll number normalised to upper case', $clean['roll_number'] === '23CS045');
check('email normalised to lower case', $clean['email'] === 'priya@example.edu');
check('extra spaces in name collapsed', $clean['full_name'] === 'Priya Raman');
check('course code mapped to id', ($clean['course_id'] ?? null) === 1);

[, $errors] = StudentValidator::validate([], $courses);
check('empty form -> 4 required-field errors', count($errors) === 4 && isset($errors['full_name'], $errors['roll_number'], $errors['email'], $errors['course']));

foreach (['plainaddress', 'a@b', 'a@@b.com', 'name@example', 'x y@example.com', '"><script>@x.com'] as $bad) {
    [, $errors] = StudentValidator::validate(['full_name' => 'Ab', 'roll_number' => '23CS001', 'email' => $bad, 'course' => 'BE-CSE'], $courses);
    check("invalid email rejected: $bad", isset($errors['email']));
}
[, $errors] = StudentValidator::validate(['full_name' => '<script>alert(1)</script>', 'roll_number' => "1' OR '1'='1", 'email' => 'a@b.co', 'course' => 'XX'], $courses);
check('script tag in name rejected', isset($errors['full_name']));
check('SQL injection string as roll number rejected', isset($errors['roll_number']));
check('unknown course rejected', isset($errors['course']));
[, $errors] = StudentValidator::validate(['full_name' => ['array'], 'roll_number' => ['x'], 'email' => ['y'], 'course' => ['z']], $courses);
check('array-injected fields treated as empty', count($errors) === 4);

section('Password policy');
check('empty password rejected', PasswordPolicy::check('') !== '');
check('short password rejected', str_contains(PasswordPolicy::check('Ab1!'), '8 characters'));
check('no uppercase rejected', str_contains(PasswordPolicy::check('lowercase1!'), 'uppercase'));
check('no digit rejected', str_contains(PasswordPolicy::check('NoDigits!!'), 'number'));
check('no symbol rejected', str_contains(PasswordPolicy::check('NoSymbol123'), 'symbol'));
check('common password rejected', str_contains(PasswordPolicy::check('P@ssw0rd'), 'common'));
check('password containing roll number rejected', str_contains(PasswordPolicy::check('Xx!23CS045yy', '23CS045'), 'roll'));
check('over 64 characters rejected', PasswordPolicy::check('Aa1!' . str_repeat('x', 70)) !== '');
check('strong password accepted', PasswordPolicy::check('Kpr!Tech2026') === '');

section('Upload validation (real file contents)');
$ok = $storage->validate(fileEntry("$fixtures/valid-photo.jpg"), 'profile_photo', 'f', false);
check('valid JPEG photo accepted', $ok['mime'] === 'image/jpeg' && $ok['ext'] === 'jpg');
check('valid PNG photo accepted', $storage->validate(fileEntry("$fixtures/valid-photo.png"), 'profile_photo', 'f', false)['ext'] === 'png');
check('valid WebP photo accepted', $storage->validate(fileEntry("$fixtures/valid-photo.webp"), 'profile_photo', 'f', false)['ext'] === 'webp');
check('valid PDF document accepted', $storage->validate(fileEntry("$fixtures/valid-document.pdf"), 'id_proof', 'f', false)['mime'] === 'application/pdf');
check('SHA-256 checksum computed', strlen($ok['sha256']) === 64);

$cases = [
    'PDF uploaded as profile photo'        => ['valid-document.pdf', 'profile_photo', 'must be'],
    'text file named .jpg (fake image)'    => ['fake-image.jpg', 'profile_photo', 'must be'],
    'PHP script named shell.php.jpg'       => ['shell.php.jpg', 'profile_photo', 'must be'],
    'real JPEG with PHP appended (polyglot)' => ['polyglot.jpg', 'profile_photo', 'embedded code'],
    'plain .txt document'                  => ['notes.txt', 'certificate', 'must be'],
    'HTML document'                        => ['page.html', 'other', 'must be'],
];
foreach ($cases as $label => [$file, $category, $expect]) {
    $e = thrown(fn() => $storage->validate(fileEntry("$fixtures/$file"), $category, 'f', false));
    check("rejected: $label", $e instanceof UploadException && str_contains($e->getMessage(), $expect), $e?->getMessage() ?? 'accepted');
}
check('invalid category rejected', thrown(fn() => $storage->validate(fileEntry("$fixtures/valid-photo.jpg"), '../../evil', 'f', false)) instanceof UploadException);

// Oversized files are produced on the fly (valid header + padding).
$big = "$tmpRoot/work/big.jpg";
file_put_contents($big, file_get_contents("$fixtures/valid-photo.jpg") . str_repeat("\0", 2 * 1024 * 1024));
$e = thrown(fn() => $storage->validate(fileEntry($big), 'profile_photo', 'f', false));
check('oversized photo (> 2 MB) rejected', $e instanceof UploadException && str_contains($e->getMessage(), 'maximum size is 2 MB'), $e?->getMessage() ?? 'accepted');
$bigPdf = "$tmpRoot/work/big.pdf";
file_put_contents($bigPdf, "%PDF-1.4\n" . str_repeat("% padding line\n", 360000));
$e = thrown(fn() => $storage->validate(fileEntry($bigPdf), 'certificate', 'f', false));
check('oversized document (> 5 MB) rejected', $e instanceof UploadException && str_contains($e->getMessage(), 'maximum size is 5 MB'), $e?->getMessage() ?? 'accepted');
$empty = "$tmpRoot/work/empty.png";
touch($empty);
check('empty file rejected', str_contains((string) thrown(fn() => $storage->validate(fileEntry($empty), 'profile_photo', 'f', false))?->getMessage(), 'empty'));

check('UPLOAD_ERR_INI_SIZE -> friendly "too large"', str_contains((string) thrown(fn() => $storage->validate(fileEntry($big, null, UPLOAD_ERR_INI_SIZE), 'profile_photo'))?->getMessage(), 'too large'));
check('UPLOAD_ERR_NO_FILE -> "choose a file"', str_contains((string) thrown(fn() => $storage->validate(fileEntry('', null, UPLOAD_ERR_NO_FILE), 'id_proof'))?->getMessage(), 'Choose a file'));
check('UPLOAD_ERR_PARTIAL -> "interrupted"', str_contains((string) thrown(fn() => $storage->validate(fileEntry('', null, UPLOAD_ERR_PARTIAL), 'id_proof'))?->getMessage(), 'interrupted'));
check('multi-file array trick rejected', thrown(fn() => $storage->validate(['error' => [0, 0], 'tmp_name' => ['a', 'b']], 'id_proof')) instanceof UploadException);
check('spoofed tmp_name (not an HTTP upload) rejected', str_contains((string) thrown(fn() => $storage->validate(fileEntry("$fixtures/valid-photo.jpg"), 'profile_photo', 'f', true))?->getMessage(), 'could not be verified'));

section('Filenames and storage');
check('client path stripped from original name', FileStorage::cleanOriginalName('..\\..\\Windows\\evil.jpg', 'jpg') === 'evil.jpg');
check('unsafe characters replaced', FileStorage::cleanOriginalName('<b>my"photo;rm -rf.png', 'png') === '_b_my_photo_rm -rf.png');
check('anything before a slash is dropped', FileStorage::cleanOriginalName('<b>x</b>.png', 'png') === 'b_.png');
check('displayed extension forced to real type', FileStorage::cleanOriginalName('report.php', 'pdf') === 'report.pdf');

$a = $storage->store($ok, 7, 'profile_photo', false);
$b = $storage->store($ok, 7, 'profile_photo', false);
check('same file stored twice -> two different generated names', $a['stored_name'] !== $b['stored_name']);
check('both copies exist (no overwrite)', is_file($a['absolute_path']) && is_file($b['absolute_path']));
check('generated name is 32 hex chars + real extension', (bool) preg_match('/^[a-f0-9]{32}\.jpg$/', $a['stored_name']));
check('relative path follows students/<id>/<category>/ layout', str_starts_with($a['relative_path'], 'students/7/profile_photo/'));

section('Path traversal protection');
check('valid stored path resolves', $storage->resolve($a['relative_path']) === realpath($a['absolute_path']));
foreach (['../../.env', 'students/7/../../../.env', 'students/7/profile_photo/../../x.jpg', 'C:/Windows/win.ini', '/etc/passwd',
             'students/7/profile_photo/' . str_repeat('a', 32) . '.php', 'students/7\\profile_photo\\' . basename($a['stored_name']),
             'students/07/profile_photo/' . $a['stored_name'], "students/7/profile_photo/{$a['stored_name']}\0.txt"] as $evil) {
    check('rejected path: ' . addcslashes($evil, "\0"), $storage->resolve($evil) === null);
}
check('path of another student rejected when owner is checked', $storage->resolve($a['relative_path'], 8) === null);
check('delete() refuses a file that belongs to another student', $storage->delete($a['relative_path'], 8) === false && is_file($a['absolute_path']));
check('delete() removes own file', $storage->delete($a['relative_path'], 7) === true && !is_file($a['absolute_path']));
check('missing file resolves to null (handled as 404)', $storage->resolve($a['relative_path']) === null);
$storage->delete($b['relative_path'], 7);
$storage->removeEmptyStudentDirectory(7);
check('empty student folder cleaned up', !is_dir($tmpRoot . '/storage/students/7'));

/* =====================================================================
   PART 2 - DATABASE INTEGRATION TESTS (real MySQL/MariaDB from .env)
   ===================================================================== */
section('Database integration');
$dbError = thrown(fn() => Database::connection());
check('database connection works', $dbError === null, $dbError?->getMessage() ?? '');
if ($dbError !== null) {
    fwrite(STDOUT, "\nSkipping integration tests: database unavailable.\n");
} else {
    $pdo = Database::connection();
    $pdo->exec("DELETE FROM students WHERE roll_number LIKE 'ZZT%'");   // leftovers from an aborted run
    $service = new StudentService($storage);
    $codes = CourseRepository::codeMap();
    $courseCode = array_key_first($codes);
    $base = ['full_name' => 'Test Student', 'email' => 'zzt.one@example.edu', 'course' => $courseCode];

    [$data, $errors] = StudentValidator::validate($base + ['roll_number' => 'ZZT0001'], $codes);
    $photo = $storage->validate(fileEntry("$fixtures/valid-photo.png"), 'profile_photo', 'profile_photo', false);
    $doc = $storage->validate(fileEntry("$fixtures/valid-document.pdf"), 'id_proof', 'document', false);
    $idA = $service->create($data, 'admin', [
        ['field' => 'profile_photo', 'category' => 'profile_photo', 'validated' => $photo],
        ['field' => 'document', 'category' => 'id_proof', 'validated' => $doc],
    ], null, null, false);
    check('add student with photo + document', $idA > 0);
    $row = StudentRepository::find($idA);
    check('view student returns stored values', $row && $row['roll_number'] === 'ZZT0001' && $row['course_code'] === $courseCode);
    $files = FileRepository::forStudent($idA);
    check('two file metadata rows linked to student', count($files) === 2);
    $pdfRow = array_values(array_filter($files, fn($f) => $f['category'] === 'id_proof'))[0] ?? null;
    check('metadata stores detected MIME, size, original + generated names', $pdfRow
        && $pdfRow['mime_type'] === 'application/pdf'
        && (int) $pdfRow['file_size'] === filesize("$fixtures/valid-document.pdf")
        && $pdfRow['original_name'] === 'valid-document.pdf'
        && preg_match('/^[a-f0-9]{32}\.pdf$/', $pdfRow['stored_name']));
    check('physical files exist under students/<id>/', $storage->resolve($pdfRow['relative_path'], $idA) !== null);

    $e = thrown(fn() => $service->create($data, 'admin', [], null, null, false));
    check('duplicate roll number rejected', $e instanceof ValidationException && isset($e->errors['roll_number']));
    [$dataDupEmail] = StudentValidator::validate($base + ['roll_number' => 'ZZT0099'], $codes);
    $e = thrown(fn() => $service->create($dataDupEmail, 'admin', [], null, null, false));
    check('duplicate email rejected', $e instanceof ValidationException && isset($e->errors['email']));

    [$dataB] = StudentValidator::validate(['full_name' => 'Second Tester', 'email' => 'zzt.two@example.edu', 'course' => $courseCode, 'roll_number' => 'ZZT0002'], $codes);
    $photoB = $storage->validate(fileEntry("$fixtures/valid-photo.jpg"), 'profile_photo', 'profile_photo', false);
    $idB = $service->create($dataB, 'admin', [['field' => 'p', 'category' => 'profile_photo', 'validated' => $photoB]], null, null, false);
    $filesB = FileRepository::forStudent($idB);

    $search = StudentRepository::search('ZZT', null, 1, 10, 'roll');
    check('list/search returns both test students', $search['total'] === 2 && $search['rows'][0]['roll_number'] === 'ZZT0001');

    [$upd] = StudentValidator::validate(['full_name' => 'Test Student Edited', 'email' => 'zzt.one@example.edu', 'course' => $courseCode, 'roll_number' => 'ZZT0001'], $codes);
    $service->update($idA, $upd);
    check('edit student saves changes', StudentRepository::find($idA)['full_name'] === 'Test Student Edited');
    [$clash] = StudentValidator::validate(['full_name' => 'X Y', 'email' => 'zzt.one@example.edu', 'course' => $courseCode, 'roll_number' => 'ZZT0002'], $codes);
    $e = thrown(fn() => $service->update($idA, $clash));
    check('edit to another student\'s roll number rejected', $e instanceof ValidationException && isset($e->errors['roll_number']));

    $oldPhoto = array_values(array_filter(FileRepository::forStudent($idA), fn($f) => $f['category'] === 'profile_photo'))[0];
    $newPhoto = $storage->validate(fileEntry("$fixtures/valid-photo.webp"), 'profile_photo', 'f', false);
    $service->addFile($idA, 'profile_photo', $newPhoto, null, false);
    $photos = array_values(array_filter(FileRepository::forStudent($idA), fn($f) => $f['category'] === 'profile_photo'));
    check('new profile photo replaces old one (1 row left)', count($photos) === 1 && $photos[0]['mime_type'] === 'image/webp');
    check('old photo file removed from disk', $storage->resolve($oldPhoto['relative_path']) === null);

    $service->deleteFile((int) $pdfRow['id']);
    check('delete file removes metadata row', FileRepository::find((int) $pdfRow['id']) === null);
    check('delete file removes physical file', $storage->resolve($pdfRow['relative_path']) === null);
    check('deleting a missing file id -> NotFound', thrown(fn() => $service->deleteFile((int) $pdfRow['id'])) instanceof NotFoundException);

    // Missing physical file: metadata exists but file was removed outside the app
    $orphan = FileRepository::forStudent($idA)[0];
    unlink($storage->resolve($orphan['relative_path']));
    check('missing physical file detected (resolve -> null)', $storage->resolve($orphan['relative_path'], $idA) === null);

    // SQL injection attempts go through prepared statements -> treated as text
    $inj = StudentRepository::search("' OR '1'='1' -- ", null, 1, 10, 'newest');
    check('SQL injection in search returns no rows', $inj['total'] === 0);
    $inj2 = StudentRepository::search('x', null, 1, 10, "name; DROP TABLE students; --");
    check('SQL injection in sort is ignored (whitelist)', is_array($inj2['rows']));
    check('students table still intact after injection attempts', (int) $pdo->query("SELECT COUNT(*) FROM students WHERE roll_number LIKE 'ZZT%'")->fetchColumn() === 2);
    check('LIKE wildcards are escaped ("%" matches literally)', StudentRepository::search('%', null, 1, 10, 'newest')['total'] === 0);

    $service->deleteStudent($idA);
    check('delete student removes record', StudentRepository::find($idA) === null);
    check('cascade removed the student\'s file metadata', FileRepository::forStudent($idA) === []);
    check('other student\'s file untouched after delete', $storage->resolve($filesB[0]['relative_path'], $idB) !== null);
    check('deleting a missing student -> NotFound', thrown(fn() => $service->deleteStudent($idA)) instanceof NotFoundException);

    $service->deleteStudent($idB);
    check('test data cleaned up', (int) $pdo->query("SELECT COUNT(*) FROM students WHERE roll_number LIKE 'ZZT%'")->fetchColumn() === 0);
}

// ---- remove the temporary folder ----
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmpRoot);

fwrite(STDOUT, sprintf("\n%d passed, %d failed\n", $passed, $failed));
exit($failed === 0 ? 0 : 1);
