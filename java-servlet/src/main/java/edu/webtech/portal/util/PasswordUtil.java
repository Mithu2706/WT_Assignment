package edu.webtech.portal.util;

import org.mindrot.jbcrypt.BCrypt;

import java.nio.charset.StandardCharsets;

/**
 * Verifies bcrypt password hashes created by PHP's password_hash().
 *
 * PHP writes the prefix "$2y$"; jBCrypt understands "$2a$".  Both variants
 * use the identical algorithm for normal passwords, so the prefix is
 * translated before checking.  Plain-text passwords are never stored.
 */
public final class PasswordUtil {

    /** A real bcrypt hash of a random discarded string, used for unknown users (timing safety). */
    private static final String DUMMY_HASH = "$2a$12$cekMoviHRWlUA5DO/qQji.STqZKzOAnhee8z6V56HMXW/usEabYwu";

    private PasswordUtil() {
    }

    public static boolean verify(String plainPassword, String storedHash) {
        if (plainPassword == null || plainPassword.isEmpty()
                || plainPassword.getBytes(StandardCharsets.UTF_8).length > 72) {
            return false;                                  // bcrypt only uses 72 bytes
        }
        String hash = storedHash;
        if (hash == null || !hash.matches("^\\$2[aby]\\$\\d{2}\\$[./A-Za-z0-9]{53}$")) {
            BCrypt.checkpw(plainPassword, DUMMY_HASH);     // same work as a real check
            return false;
        }
        if (hash.startsWith("$2y$") || hash.startsWith("$2b$")) {
            hash = "$2a$" + hash.substring(4);
        }
        try {
            return BCrypt.checkpw(plainPassword, hash);
        } catch (IllegalArgumentException e) {
            return false;
        }
    }

    /** Spend the same time as a real verification (used when a username does not exist). */
    public static void burnTime(String plainPassword) {
        if (plainPassword != null && !plainPassword.isEmpty()) {
            BCrypt.checkpw(plainPassword, DUMMY_HASH);
        }
    }
}
