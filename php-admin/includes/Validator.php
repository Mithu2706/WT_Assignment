<?php
/**
 * Server-side validation.  These rules mirror frontend/js/validation.js but
 * are the ones that really count: browser checks can be bypassed, these
 * cannot.
 */
declare(strict_types=1);

final class StudentValidator
{
    /**
     * Validate and normalise student fields.
     *
     * @param array<string,mixed> $input        raw $_POST
     * @param array<string,int>   $courseCodes  map course code => course id
     * @return array{0: array<string,mixed>, 1: array<string,string>}  [clean data, errors]
     */
    public static function validate(array $input, array $courseCodes): array
    {
        $errors = [];
        $clean = [];

        // --- name ---
        $name = self::str($input, 'full_name');
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        if ($name === '') {
            $errors['full_name'] = 'Enter the student\'s full name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['full_name'] = 'Name must be between 2 and 100 characters.';
        } elseif (!preg_match('/^\p{L}[\p{L}\p{M} .\'\-]*$/u', $name)) {
            $errors['full_name'] = 'Use letters, spaces, dots, apostrophes or hyphens only.';
        }
        $clean['full_name'] = $name;

        // --- roll number (stored upper-case) ---
        $roll = strtoupper(trim(self::str($input, 'roll_number')));
        if ($roll === '') {
            $errors['roll_number'] = 'Enter the roll number.';
        } elseif (!preg_match('/^[A-Z0-9]{5,15}$/', $roll)) {
            $errors['roll_number'] = 'Roll number must be 5–15 letters or digits (no spaces or symbols).';
        }
        $clean['roll_number'] = $roll;

        // --- email (stored lower-case) ---
        $email = strtolower(trim(self::str($input, 'email')));
        if ($email === '') {
            $errors['email'] = 'Enter the email address.';
        } elseif (strlen($email) > 150
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || !preg_match('/@[^@]+\.[a-z]{2,24}$/', $email)) {
            $errors['email'] = 'Enter a valid email address like name@example.edu.';
        }
        $clean['email'] = $email;

        // --- course: must be one of the codes in the courses table ---
        $course = trim(self::str($input, 'course'));
        if ($course === '') {
            $errors['course'] = 'Select a course.';
        } elseif (!isset($courseCodes[$course])) {
            $errors['course'] = 'Select a course from the list.';
        } else {
            $clean['course_id'] = $courseCodes[$course];
        }
        $clean['course'] = $course;

        return [$clean, $errors];
    }

    /** Safely read a string field (arrays / missing values become ''). */
    private static function str(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        return is_string($value) ? $value : '';
    }
}

final class PasswordPolicy
{
    private const COMMON = ['password1!', 'password@123', 'welcome@123', 'admin@123', 'qwerty@123', 'p@ssw0rd', 'abc@1234'];

    /** Returns '' when acceptable, otherwise a message explaining what is missing. */
    public static function check(string $password, string $rollNumber = ''): string
    {
        if ($password === '') {
            return 'Create a password.';
        }
        if (strlen($password) > 64) {
            return 'Password must be 64 characters or fewer.';
        }
        if (preg_match('/\s/', $password)) {
            return 'Password must not contain spaces.';
        }
        $missing = [];
        if (strlen($password) < 8) {
            $missing[] = 'at least 8 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $missing[] = 'an uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $missing[] = 'a lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $missing[] = 'a number';
        }
        if (!preg_match('/[^A-Za-z0-9\s]/', $password)) {
            $missing[] = 'a symbol';
        }
        if ($missing) {
            return 'Password needs ' . self::joinList($missing) . '.';
        }
        if (in_array(strtolower($password), self::COMMON, true)) {
            return 'This password is too common. Choose something less predictable.';
        }
        if (strlen($rollNumber) >= 5 && stripos($password, $rollNumber) !== false) {
            return 'Password must not contain your roll number.';
        }
        return '';
    }

    private static function joinList(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }
}
