package edu.webtech.portal.dao;

import edu.webtech.portal.model.Student;
import edu.webtech.portal.util.DataAccessException;
import edu.webtech.portal.util.DatabaseUtil;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

/**
 * JDBC access to students (joined with courses).
 * All values are bound with PreparedStatement.setXxx() - never concatenated.
 */
public class StudentDao {

    private static final String SELECT =
            "SELECT s.id, s.roll_number, s.full_name, s.email, s.registration_source, s.created_at, s.updated_at, "
          + "       c.code AS course_code, c.name AS course_name, "
          + "       (SELECT f.id FROM student_files f WHERE f.student_id = s.id AND f.category = 'profile_photo' "
          + "         ORDER BY f.uploaded_at DESC, f.id DESC LIMIT 1) AS photo_id "
          + "  FROM students s JOIN courses c ON c.id = s.course_id ";

    private static final String SEARCH_WHERE =
            " WHERE (? = '' OR s.full_name LIKE ? OR s.roll_number LIKE ? OR c.name LIKE ?) ";

    /** One page of students, newest first, optionally filtered by a search term. */
    public List<Student> findPage(String search, int page, int pageSize) {
        String term = search == null ? "" : search.trim();
        String like = "%" + escapeLike(term) + "%";
        String sql = SELECT + SEARCH_WHERE + " ORDER BY s.full_name ASC, s.id ASC LIMIT ? OFFSET ?";
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setString(1, term);
            ps.setString(2, like);
            ps.setString(3, like);
            ps.setString(4, like);
            ps.setInt(5, pageSize);
            ps.setInt(6, Math.max(0, (page - 1) * pageSize));
            List<Student> list = new ArrayList<>();
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    list.add(map(rs));
                }
            }
            return list;
        } catch (SQLException e) {
            throw new DataAccessException("Student records could not be loaded right now. Please try again.", e);
        }
    }

    public int count(String search) {
        String term = search == null ? "" : search.trim();
        String like = "%" + escapeLike(term) + "%";
        String sql = "SELECT COUNT(*) FROM students s JOIN courses c ON c.id = s.course_id" + SEARCH_WHERE;
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setString(1, term);
            ps.setString(2, like);
            ps.setString(3, like);
            ps.setString(4, like);
            try (ResultSet rs = ps.executeQuery()) {
                rs.next();
                return rs.getInt(1);
            }
        } catch (SQLException e) {
            throw new DataAccessException("Student records could not be counted right now. Please try again.", e);
        }
    }

    public Optional<Student> findById(long id) {
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(SELECT + " WHERE s.id = ?")) {
            ps.setLong(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? Optional.of(map(rs)) : Optional.empty();
            }
        } catch (SQLException e) {
            throw new DataAccessException("The student profile could not be loaded right now. Please try again.", e);
        }
    }

    /** Escape LIKE wildcards so a user typing "%" or "_" searches for them literally. */
    static String escapeLike(String s) {
        return s.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_");
    }

    private static Student map(ResultSet rs) throws SQLException {
        Student s = new Student();
        s.setId(rs.getLong("id"));
        s.setRollNumber(rs.getString("roll_number"));
        s.setFullName(rs.getString("full_name"));
        s.setEmail(rs.getString("email"));
        s.setRegistrationSource(rs.getString("registration_source"));
        s.setCreatedAt(rs.getObject("created_at", LocalDateTime.class));
        s.setUpdatedAt(rs.getObject("updated_at", LocalDateTime.class));
        s.setCourseCode(rs.getString("course_code"));
        s.setCourseName(rs.getString("course_name"));
        long photo = rs.getLong("photo_id");
        s.setPhotoFileId(rs.wasNull() ? null : photo);
        return s;
    }
}
