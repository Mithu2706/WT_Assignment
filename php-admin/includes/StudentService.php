<?php
/**
 * Business logic that combines database rows and physical files so they
 * always stay consistent:
 *
 *  - create(): validates ALL files first, then inserts the student, moves the
 *    files and inserts their metadata inside one DB transaction.  If anything
 *    fails, the transaction is rolled back AND the already-moved files are
 *    deleted again, so no orphan files or half-created students remain.
 *  - deleteStudent(): deletes only the files whose metadata belongs to that
 *    student (paths are re-checked to start with students/<id>/).
 */
declare(strict_types=1);

final class StudentService
{
    public function __construct(private readonly FileStorage $storage)
    {
    }

    /**
     * Returns the $_FILES entry for a field, or null when no file was chosen.
     */
    public static function uploadedFile(string $field): ?array
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || !isset($file['error'])) {
            return null;
        }
        if (!is_array($file['error']) && (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    /**
     * Validate a list of uploads, collecting one error per field.
     * @param list<array{field:string, category:string, file:array}> $uploads
     * @return array{0: list<array>, 1: array<string,string>}
     */
    public function validateUploads(array $uploads, bool $isHttp = true): array
    {
        $valid = [];
        $errors = [];
        foreach ($uploads as $upload) {
            try {
                $upload['validated'] = $this->storage->validate($upload['file'], $upload['category'], $upload['field'], $isHttp);
                $valid[] = $upload;
            } catch (UploadException $e) {
                $errors[$e->field] = $e->getMessage();
            }
        }
        return [$valid, $errors];
    }

    /**
     * Create a student with optional files (and optional login account for
     * self-registration).  Throws ValidationException with field errors.
     *
     * @param array      $data     clean data from StudentValidator
     * @param list<array> $uploads already validated uploads (see validateUploads)
     * @param array|null $account  ['password' => ...] to create a student login
     */
    public function create(array $data, string $source, array $uploads, ?int $actorId, ?array $account = null, bool $isHttp = true): int
    {
        $duplicates = StudentRepository::duplicateErrors($data['roll_number'], $data['email']);
        if ($account !== null && UserRepository::usernameExists($data['roll_number'])) {
            $duplicates['roll_number'] = 'A login for roll number ' . $data['roll_number'] . ' already exists.';
        }
        if ($duplicates) {
            throw new ValidationException($duplicates);
        }

        $movedFiles = [];
        try {
            return Database::transaction(function () use ($data, $source, $uploads, $actorId, $account, $isHttp, &$movedFiles): int {
                $studentId = StudentRepository::insert($data, $source);
                if ($account !== null) {
                    UserRepository::create($data['roll_number'], $data['full_name'], $account['password'], 'student', $studentId);
                }
                foreach ($uploads as $upload) {
                    $stored = $this->storage->store($upload['validated'], $studentId, $upload['category'], $isHttp);
                    $movedFiles[] = [$stored['relative_path'], $studentId];
                    FileRepository::insert($studentId, $upload['category'], $upload['validated'], $stored, $actorId);
                }
                return $studentId;
            });
        } catch (Throwable $e) {
            // Roll back the file system too.
            foreach ($movedFiles as [$path, $sid]) {
                $this->storage->delete($path, $sid);
            }
            if (isset($sid)) {
                $this->storage->removeEmptyStudentDirectory($sid);
            }
            if ($e instanceof PDOException && Database::isDuplicateKey($e)) {
                // Another request inserted the same roll/email a moment earlier.
                throw new ValidationException(StudentRepository::duplicateErrors($data['roll_number'], $data['email'])
                    ?: ['roll_number' => 'This roll number or email is already registered.']);
            }
            throw $e;
        }
    }

    /** Update student fields (files are managed separately). */
    public function update(int $id, array $data): void
    {
        StudentRepository::findOrFail($id);
        $duplicates = StudentRepository::duplicateErrors($data['roll_number'], $data['email'], $id);
        if ($duplicates) {
            throw new ValidationException($duplicates);
        }
        try {
            StudentRepository::update($id, $data);
            // Keep a student's own login username in step with the roll number.
            $stmt = Database::connection()->prepare("UPDATE users SET username = ?, display_name = ? WHERE student_id = ? AND role = 'student'");
            $stmt->execute([$data['roll_number'], $data['full_name'], $id]);
        } catch (PDOException $e) {
            if (Database::isDuplicateKey($e)) {
                throw new ValidationException(['roll_number' => 'This roll number or email is already in use.']);
            }
            throw $e;
        }
    }

    /**
     * Add one file to an existing student.  A new profile photo replaces the
     * previous one (old row + old file of THIS student are removed afterwards).
     */
    public function addFile(int $studentId, string $category, array $validated, ?int $actorId, bool $isHttp = true): int
    {
        StudentRepository::findOrFail($studentId);
        $stored = $this->storage->store($validated, $studentId, $category, $isHttp);
        try {
            $fileId = FileRepository::insert($studentId, $category, $validated, $stored, $actorId);
        } catch (Throwable $e) {
            $this->storage->delete($stored['relative_path'], $studentId);   // no orphan file
            throw $e;
        }

        if ($category === 'profile_photo') {
            foreach (FileRepository::forStudent($studentId) as $old) {
                if ($old['category'] === 'profile_photo' && (int) $old['id'] !== $fileId) {
                    FileRepository::delete((int) $old['id']);
                    $this->storage->delete($old['relative_path'], $studentId);
                }
            }
        }
        return $fileId;
    }

    /** Delete one file record + its physical file. Returns the student id. */
    public function deleteFile(int $fileId): int
    {
        $file = FileRepository::find($fileId) ?? throw new NotFoundException('That file no longer exists.');
        $studentId = (int) $file['student_id'];
        FileRepository::delete($fileId);
        if (!$this->storage->delete($file['relative_path'], $studentId)) {
            error_log("[storage] metadata for file $fileId removed but physical file could not be deleted");
        }
        return $studentId;
    }

    /** Delete a student, their metadata (CASCADE) and ONLY their own files. */
    public function deleteStudent(int $id): array
    {
        $student = StudentRepository::findOrFail($id);
        $files = FileRepository::forStudent($id);
        StudentRepository::delete($id);          // CASCADE removes student_files rows and the student's login

        $failed = 0;
        foreach ($files as $file) {
            // FileStorage::delete re-checks that the path starts with students/<id>/
            if (!$this->storage->delete($file['relative_path'], $id)) {
                $failed++;
            }
        }
        $this->storage->removeEmptyStudentDirectory($id);
        if ($failed > 0) {
            error_log("[storage] $failed file(s) of deleted student $id could not be removed");
        }
        return $student;
    }
}
