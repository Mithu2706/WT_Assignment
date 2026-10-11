<?php
/**
 * Data-access classes.  Every query uses a PDO prepared statement with
 * placeholders (?), so user input can never change the SQL (no SQL injection).
 */
declare(strict_types=1);

final class CourseRepository
{
    /** @return list<array{id:int,code:string,name:string}> */
    public static function all(): array
    {
        return Database::connection()->query('SELECT id, code, name FROM courses ORDER BY name')->fetchAll();
    }

    /** @return array<string,int> map code => id (used by the validator) */
    public static function codeMap(): array
    {
        $map = [];
        foreach (self::all() as $row) {
            $map[$row['code']] = (int) $row['id'];
        }
        return $map;
    }
}

final class StudentRepository
{
    private const SELECT = 'SELECT s.id, s.roll_number, s.full_name, s.email, s.course_id, s.registration_source,
                                   s.created_at, s.updated_at, c.code AS course_code, c.name AS course_name
                              FROM students s
                              JOIN courses c ON c.id = s.course_id';

    /**
     * Paginated search.  The search text is bound as a parameter and LIKE
     * wildcards typed by the user (% and _) are escaped.
     *
     * @return array{rows: list<array>, total: int}
     */
    public static function search(string $q, ?int $courseId, int $page, int $perPage, string $sort): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(s.full_name LIKE ? OR s.roll_number LIKE ? OR s.email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if ($courseId !== null) {
            $where[] = 's.course_id = ?';
            $params[] = $courseId;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // ORDER BY cannot use placeholders, so only whitelisted values are allowed.
        $orderBy = match ($sort) {
            'name'   => 's.full_name ASC',
            'roll'   => 's.roll_number ASC',
            'oldest' => 's.created_at ASC, s.id ASC',
            default  => 's.created_at DESC, s.id DESC',
        };

        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM students s' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT s.id, s.roll_number, s.full_name, s.email, s.created_at, c.name AS course_name, c.code AS course_code,
                       (SELECT COUNT(*) FROM student_files f WHERE f.student_id = s.id) AS file_count,
                       (SELECT f.id FROM student_files f WHERE f.student_id = s.id AND f.category = \'profile_photo\'
                         ORDER BY f.uploaded_at DESC, f.id DESC LIMIT 1) AS photo_id
                  FROM students s JOIN courses c ON c.id = s.course_id'
            . $whereSql . " ORDER BY $orderBy LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::SELECT . ' WHERE s.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findOrFail(int $id): array
    {
        return self::find($id) ?? throw new NotFoundException('Student record not found. It may have been deleted.');
    }

    /** Checks used to give friendly "already exists" messages before INSERT/UPDATE. */
    public static function duplicateErrors(string $roll, string $email, ?int $exceptId = null): array
    {
        $errors = [];
        $stmt = Database::connection()->prepare(
            'SELECT roll_number, email FROM students WHERE (roll_number = ? OR email = ?) AND id <> ?'
        );
        $stmt->execute([$roll, $email, $exceptId ?? 0]);
        foreach ($stmt->fetchAll() as $row) {
            if (strcasecmp($row['roll_number'], $roll) === 0) {
                $errors['roll_number'] = 'A student with roll number ' . $roll . ' already exists.';
            }
            if (strcasecmp($row['email'], $email) === 0) {
                $errors['email'] = 'This email address is already registered to another student.';
            }
        }
        return $errors;
    }

    public static function insert(array $data, string $source = 'admin'): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO students (roll_number, full_name, email, course_id, registration_source) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$data['roll_number'], $data['full_name'], $data['email'], $data['course_id'], $source]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE students SET roll_number = ?, full_name = ?, email = ?, course_id = ? WHERE id = ?'
        );
        $stmt->execute([$data['roll_number'], $data['full_name'], $data['email'], $data['course_id'], $id]);
    }

    public static function delete(int $id): void
    {
        // student_files and the student's own login are removed by ON DELETE CASCADE.
        $stmt = Database::connection()->prepare('DELETE FROM students WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function stats(): array
    {
        $pdo = Database::connection();
        return [
            'students'     => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
            'self'         => (int) $pdo->query("SELECT COUNT(*) FROM students WHERE registration_source = 'self'")->fetchColumn(),
            'files'        => (int) $pdo->query('SELECT COUNT(*) FROM student_files')->fetchColumn(),
            'bytes'        => (int) $pdo->query('SELECT COALESCE(SUM(file_size), 0) FROM student_files')->fetchColumn(),
            'without_photo' => (int) $pdo->query(
                "SELECT COUNT(*) FROM students s WHERE NOT EXISTS
                   (SELECT 1 FROM student_files f WHERE f.student_id = s.id AND f.category = 'profile_photo')"
            )->fetchColumn(),
            'by_course'    => $pdo->query(
                'SELECT c.name, c.code, COUNT(s.id) AS total FROM courses c
                   LEFT JOIN students s ON s.course_id = c.id GROUP BY c.id ORDER BY total DESC, c.name'
            )->fetchAll(),
            'recent'       => $pdo->query(
                self::SELECT . ' ORDER BY s.created_at DESC, s.id DESC LIMIT 5'
            )->fetchAll(),
        ];
    }
}

final class FileRepository
{
    private const COLUMNS = 'id, student_id, category, original_name, stored_name, relative_path, mime_type, file_size, uploaded_at';

    /** @return list<array> */
    public static function forStudent(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM student_files WHERE student_id = ?
              ORDER BY FIELD(category, \'profile_photo\', \'id_proof\', \'certificate\', \'marksheet\', \'other\'), uploaded_at DESC, id DESC'
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT ' . self::COLUMNS . ' FROM student_files WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function latestPhoto(int $studentId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM student_files WHERE student_id = ? AND category = \'profile_photo\'
              ORDER BY uploaded_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$studentId]);
        return $stmt->fetch() ?: null;
    }

    public static function insert(int $studentId, string $category, array $validated, array $stored, ?int $uploadedBy): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_files
                (student_id, category, original_name, stored_name, relative_path, mime_type, file_size, checksum_sha256, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $studentId, $category, $validated['original'], $stored['stored_name'], $stored['relative_path'],
            $validated['mime'], $validated['size'], $validated['sha256'], $uploadedBy,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM student_files WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function recent(int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.id, f.student_id, f.category, f.original_name, f.mime_type, f.file_size, f.uploaded_at, s.full_name
               FROM student_files f JOIN students s ON s.id = f.student_id
              ORDER BY f.uploaded_at DESC, f.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

final class UserRepository
{
    public static function usernameExists(string $username): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return (bool) $stmt->fetchColumn();
    }

    public static function create(string $username, string $displayName, string $password, string $role, ?int $studentId = null): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (username, display_name, password_hash, role, student_id) VALUES (?, ?, ?, ?, ?)'
        );
        // bcrypt ($2y$) - verified by PHP password_verify() and by jBCrypt in the Java module.
        $stmt->execute([$username, $displayName, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $role, $studentId]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function forStudent(int $studentId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, username, last_login_at FROM users WHERE student_id = ?');
        $stmt->execute([$studentId]);
        return $stmt->fetch() ?: null;
    }
}
