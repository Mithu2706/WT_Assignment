<?php
/**
 * Attaches the generated demo files in database/sample-files/ to the sample
 * students from database/sample_data.sql.
 *
 * The files go through exactly the same validation and storage code as a
 * browser upload (FileStorage::validate + StudentService::addFile), so the
 * resulting metadata is genuine.  Safe to re-run: students that already have
 * files are skipped.
 *
 *   php php-admin/bin/seed_sample_files.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/bootstrap.php';

$sampleDir = dirname(__DIR__, 2) . '/database/sample-files';
// roll number => list of [file name, category]
$plan = [
    '23CS001' => [['23CS001-photo.jpg', 'profile_photo'], ['23CS001-id-proof.pdf', 'id_proof'], ['23CS001-certificate.pdf', 'certificate']],
    '23CS014' => [['23CS014-photo.png', 'profile_photo'], ['23CS014-marksheet.pdf', 'marksheet']],
    '23EC007' => [['23EC007-photo.webp', 'profile_photo']],
    '23IT022' => [['23IT022-photo.jpg', 'profile_photo'], ['23IT022-id-proof.png', 'id_proof']],
    '23AD031' => [['23AD031-photo.png', 'profile_photo']],
    // 23ME045 (Sneha Iyer) intentionally has no files.
];

$storage = new FileStorage();
$service = new StudentService($storage);
$find = Database::connection()->prepare('SELECT id FROM students WHERE roll_number = ?');
$added = 0;

foreach ($plan as $roll => $files) {
    $find->execute([$roll]);
    $studentId = (int) $find->fetchColumn();
    if ($studentId === 0) {
        fwrite(STDOUT, "skip $roll: student not found (import database/sample_data.sql first)\n");
        continue;
    }
    if (FileRepository::forStudent($studentId)) {
        fwrite(STDOUT, "skip $roll: already has files\n");
        continue;
    }
    foreach ($files as [$name, $category]) {
        $path = $sampleDir . '/' . $name;
        if (!is_file($path)) {
            fwrite(STDOUT, "skip $name: sample file missing (run python tests/generate_fixtures.py)\n");
            continue;
        }
        // Build a $_FILES-like entry; $requireHttpUpload=false because this is a local file.
        $entry = ['name' => $name, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'type' => ''];
        $validated = $storage->validate($entry, $category, 'file', false);
        $service->addFile($studentId, $category, $validated, null, false);
        $added++;
        fwrite(STDOUT, "added $roll  $category  $name\n");
    }
}
fwrite(STDOUT, "Done. $added file(s) added.\n");
