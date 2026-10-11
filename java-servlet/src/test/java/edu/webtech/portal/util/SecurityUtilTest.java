package edu.webtech.portal.util;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

/** Unit tests for password verification, id parsing, headers and path safety. */
class SecurityUtilTest {

    /** Hash produced by PHP: password_hash("Junit!Test2026", PASSWORD_BCRYPT, ["cost" => 10]). */
    private static final String PHP_HASH = "$2y$10$adan6q8PgnOZTEXWue7SHeKNc0Rjj9V1298dCpFWlUnFUXZCLUrcC";

    // ---------------------------------------------------------------- passwords

    @Test
    void verifiesBcryptHashCreatedByPhp() {
        assertTrue(PasswordUtil.verify("Junit!Test2026", PHP_HASH));
    }

    @Test
    void rejectsWrongMissingOrMalformedPasswords() {
        assertFalse(PasswordUtil.verify("junit!test2026", PHP_HASH));
        assertFalse(PasswordUtil.verify("", PHP_HASH));
        assertFalse(PasswordUtil.verify(null, PHP_HASH));
        assertFalse(PasswordUtil.verify("Junit!Test2026", "plaintext-password"));
        assertFalse(PasswordUtil.verify("Junit!Test2026", null));
        assertFalse(PasswordUtil.verify("x".repeat(80), PHP_HASH));        // longer than bcrypt's 72 bytes
    }

    // ---------------------------------------------------------------- ids

    @Test
    void parsesOnlyPositiveIds() {
        assertEquals(42L, WebUtil.parseId("42").getAsLong());
        for (String bad : new String[]{null, "", "0", "-1", "abc", "1e3", "1 OR 1=1", "42;", "../1", "007", "99999999999"}) {
            assertFalse(WebUtil.parseId(bad).isPresent(), "should reject: " + bad);
        }
    }

    // ---------------------------------------------------------------- Content-Disposition

    @Test
    void contentDispositionIsSafe() {
        String header = WebUtil.contentDisposition("evil\"\r\nSet-Cookie: x=1.pdf", true);
        assertTrue(header.startsWith("attachment; filename=\""));
        assertFalse(header.contains("\r") || header.contains("\n"), "no header injection");
        assertFalse(header.substring(22, header.indexOf("\";")).contains("\""), "no quote breaks out of filename");
        assertTrue(WebUtil.contentDisposition("photo.jpg", false).startsWith("inline; filename=\"photo.jpg\""));
        assertTrue(WebUtil.contentDisposition("சான்றிதழ்.pdf", true).contains("filename*=UTF-8''%E0%AE%9A"));
    }

    // ---------------------------------------------------------------- storage paths

    @Test
    void resolvesOnlySafePathsInsideStorage(@TempDir Path root) throws IOException {
        String good = "students/5/profile_photo/0123456789abcdef0123456789abcdef.jpg";
        Path file = root.resolve(good);
        Files.createDirectories(file.getParent());
        Files.write(file, new byte[]{1, 2, 3});
        Files.write(root.resolve("secret.txt"), new byte[]{9});

        StoragePaths storage = new StoragePaths(root);
        assertTrue(storage.resolve(good, 5).isPresent(), "generated path resolves");
        assertFalse(storage.resolve(good, 6).isPresent(), "file of another student is refused");

        String[] attacks = {
            "../secret.txt", "students/5/../../secret.txt", "students/5/profile_photo/../../../secret.txt",
            "/etc/passwd", "C:/Windows/win.ini", "students\\5\\profile_photo\\0123456789abcdef0123456789abcdef.jpg",
            "students/5/profile_photo/0123456789abcdef0123456789abcdef.php", "students/05/profile_photo/0123456789abcdef0123456789abcdef.jpg",
            "students/5/profile_photo/0123456789ABCDEF0123456789abcdef.jpg", "secret.txt", "", null
        };
        for (String attack : attacks) {
            assertFalse(storage.resolve(attack, 5).isPresent(), "should refuse: " + attack);
        }
    }

    @Test
    void missingFileResolvesToEmpty(@TempDir Path root) {
        StoragePaths storage = new StoragePaths(root);
        assertFalse(storage.resolve("students/5/id_proof/0123456789abcdef0123456789abcdef.pdf", 5).isPresent());
    }

    // ---------------------------------------------------------------- config

    @Test
    void loadsSharedEnvFile(@TempDir Path dir) throws IOException {
        Path env = dir.resolve(".env");
        Files.write(env, ("# comment\nDB_HOST=db.local\nDB_PORT=3307\nDB_NAME=\"quoted_db\"\n"
                + "DB_USER=u\nDB_PASS='p@ss'\nSTORAGE_DIR=storage/uploads\nSESSION_TIMEOUT_MINUTES=15\n").getBytes());
        AppConfig c = AppConfig.load(env);
        assertEquals("quoted_db", c.getDbName());
        assertEquals("p@ss", c.getDbPass());
        assertEquals(15, c.getSessionTimeoutMinutes());
        assertEquals(dir.resolve("storage/uploads").toAbsolutePath().normalize(), c.getStorageDir());
        assertTrue(c.jdbcUrl().startsWith("jdbc:mysql://db.local:3307/quoted_db?"));
    }
}
