package com.studentportal.dao;

import com.studentportal.model.User;
import org.mindrot.jbcrypt.BCrypt;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

/**
 * UserDAO – checks login details against the {@code users} table.
 *
 * Passwords are stored as bcrypt hashes created by PHP's password_hash().
 * jBCrypt's BCrypt.checkpw() verifies the typed password against that hash,
 * so the SAME account works in both the PHP admin and this servlet portal.
 */
public class UserDAO {

    /**
     * @return the User when username + password are correct, otherwise null
     */
    public User authenticate(String username, String password) throws SQLException {
        String sql = "SELECT id, username, full_name, password_hash FROM users WHERE username = ?";

        try (Connection conn = DBConnection.getConnection();
             PreparedStatement stmt = conn.prepareStatement(sql)) {
            stmt.setString(1, username);

            try (ResultSet rs = stmt.executeQuery()) {
                if (!rs.next()) {
                    return null;                          // unknown username
                }
                String hash = rs.getString("password_hash");
                if (!passwordMatches(password, hash)) {
                    return null;                          // wrong password
                }
                User user = new User(rs.getInt("id"), rs.getString("username"), rs.getString("full_name"));
                updateLastLogin(conn, user.getId());
                return user;
            }
        }
    }

    /**
     * PHP writes bcrypt hashes with the "$2y$" prefix; jBCrypt only understands
     * "$2a$". Both use the identical algorithm, so the prefix is swapped.
     */
    private boolean passwordMatches(String password, String hash) {
        if (hash == null || !hash.startsWith("$2")) {
            return false;
        }
        String compatibleHash = hash.startsWith("$2y$") ? "$2a$" + hash.substring(4) : hash;
        try {
            return BCrypt.checkpw(password, compatibleHash);
        } catch (IllegalArgumentException e) {
            return false;                                 // malformed hash in the database
        }
    }

    private void updateLastLogin(Connection conn, int userId) throws SQLException {
        try (PreparedStatement stmt = conn.prepareStatement("UPDATE users SET last_login = NOW() WHERE id = ?")) {
            stmt.setInt(1, userId);
            stmt.executeUpdate();
        }
    }
}
