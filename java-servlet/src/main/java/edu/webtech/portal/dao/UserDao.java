package edu.webtech.portal.dao;

import edu.webtech.portal.model.User;
import edu.webtech.portal.util.DataAccessException;
import edu.webtech.portal.util.DatabaseUtil;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Optional;

/** JDBC access to the users table (prepared statements only). */
public class UserDao {

    private static final String COLUMNS = "id, username, display_name, password_hash, role, student_id, is_active";

    public Optional<User> findByUsername(String username) {
        String sql = "SELECT " + COLUMNS + " FROM users WHERE username = ?";
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setString(1, username);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? Optional.of(map(rs)) : Optional.empty();
            }
        } catch (SQLException e) {
            throw new DataAccessException("Could not check your login right now. Please try again.", e);
        }
    }

    public Optional<User> findById(long id) {
        String sql = "SELECT " + COLUMNS + " FROM users WHERE id = ?";
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setLong(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? Optional.of(map(rs)) : Optional.empty();
            }
        } catch (SQLException e) {
            throw new DataAccessException("Could not load your account right now. Please try again.", e);
        }
    }

    public void updateLastLogin(long id) {
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement("UPDATE users SET last_login_at = NOW() WHERE id = ?")) {
            ps.setLong(1, id);
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new DataAccessException("Could not complete sign-in. Please try again.", e);
        }
    }

    private static User map(ResultSet rs) throws SQLException {
        User u = new User();
        u.setId(rs.getLong("id"));
        u.setUsername(rs.getString("username"));
        u.setDisplayName(rs.getString("display_name"));
        u.setPasswordHash(rs.getString("password_hash"));
        u.setRole(rs.getString("role"));
        long sid = rs.getLong("student_id");
        u.setStudentId(rs.wasNull() ? null : sid);
        u.setActive(rs.getBoolean("is_active"));
        return u;
    }
}
