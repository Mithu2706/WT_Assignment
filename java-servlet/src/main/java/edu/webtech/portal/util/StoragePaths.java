package edu.webtech.portal.util;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.LinkOption;
import java.nio.file.Path;
import java.util.Optional;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Converts a relative path stored in student_files.relative_path into a real
 * file inside STORAGE_DIR - and refuses anything that could escape it.
 *
 * Three independent layers stop directory traversal:
 *   1. the path must match the exact shape the PHP module generates
 *      (students/&lt;id&gt;/&lt;category&gt;/&lt;32 hex&gt;.&lt;ext&gt;) - "..", "\", "C:" cannot match;
 *   2. the student id inside the path must equal the record's student_id;
 *   3. after resolving symlinks (toRealPath) the file must still be inside
 *      the storage root.
 */
public final class StoragePaths {

    private static final Pattern SAFE_PATH = Pattern.compile(
            "^students/([1-9][0-9]{0,9})/(profile_photo|id_proof|certificate|marksheet|other)/[a-f0-9]{32}\\.(jpg|png|webp|pdf)$");

    private final Path root;

    public StoragePaths(Path storageRoot) {
        this.root = storageRoot.toAbsolutePath().normalize();
    }

    public Path getRoot() {
        return root;
    }

    /** True if the string has the generated shape and belongs to the student. */
    public static boolean isWellFormed(String relativePath, long expectedStudentId) {
        if (relativePath == null) {
            return false;
        }
        Matcher m = SAFE_PATH.matcher(relativePath);
        return m.matches() && Long.parseLong(m.group(1)) == expectedStudentId;
    }

    /**
     * @return the absolute, existing, readable file - or empty when the path is
     *         malformed, belongs to another student, escapes the root, or is missing.
     */
    public Optional<Path> resolve(String relativePath, long expectedStudentId) {
        if (!isWellFormed(relativePath, expectedStudentId)) {
            return Optional.empty();
        }
        try {
            Path realRoot = root.toRealPath();
            Path candidate = realRoot.resolve(relativePath).normalize();
            if (!candidate.startsWith(realRoot) || !Files.exists(candidate, LinkOption.NOFOLLOW_LINKS)) {
                return Optional.empty();
            }
            Path real = candidate.toRealPath();          // follows links, then re-check containment
            if (!real.startsWith(realRoot) || !Files.isRegularFile(real) || !Files.isReadable(real)) {
                return Optional.empty();
            }
            return Optional.of(real);
        } catch (IOException | SecurityException e) {
            return Optional.empty();
        }
    }
}
