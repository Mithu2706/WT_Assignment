package com.studentportal.dao;

import com.studentportal.model.Student;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

/**
 * StudentDAO – Data Access Object for the {@code students} table.
 *
 * DAO pattern: ALL SQL for students lives here, so servlets never contain
 * SQL. Every query uses a PreparedStatement with ? placeholders – user input
 * is sent separately from the SQL text, which prevents SQL injection.
 */
public class StudentDAO {

    /**
     * Base SELECT used by both queries. Sub-queries add:
     *  profile_photo_id – id of the newest 'profile' file (NULL if none)
     *  file_count       – total uploaded files
     *  document_count   – number of 'document' files
     */
    private static final String BASE_SELECT =
            "SELECT s.id, s.name, s.roll_number, s.email, s.course, s.phone, s.date_of_birth, "
          + "       s.address, s.created_at, "
          + "       (SELECT p.id FROM student_files p "
          + "         WHERE p.student_id = s.id AND p.file_category = 'profile' "
          + "         ORDER BY p.upload_date DESC, p.id DESC LIMIT 1) AS profile_photo_id, "
          + "       (SELECT COUNT(*) FROM student_files f WHERE f.student_id = s.id) AS file_count, "
          + "       (SELECT COUNT(*) FROM student_files d "
          + "         WHERE d.student_id = s.id AND d.file_category = 'document') AS document_count "
          + "FROM students s ";

    /**
     * Returns all students, optionally filtered by a search text (name, roll
     * number or e-mail) and/or an exact course name.
     */
    public List<Student> findAll(String search, String course) throws SQLException {
        StringBuilder sql = new StringBuilder(BASE_SELECT).append("WHERE 1 = 1 ");
        List<String> params = new ArrayList<>();

        if (search != null && !search.isEmpty()) {
            sql.append("AND (s.name LIKE ? OR s.roll_number LIKE ? OR s.email LIKE ?) ");
            String like = "%" + search + "%";
            params.add(like);
            params.add(like);
            params.add(like);
        }
        if (course != null && !course.isEmpty()) {
            sql.append("AND s.course = ? ");
            params.add(course);
        }
        sql.append("ORDER BY s.roll_number");

        List<Student> students = new ArrayList<>();
        try (Connection conn = DBConnection.getConnection();
             PreparedStatement stmt = conn.prepareStatement(sql.toString())) {

            for (int i = 0; i < params.size(); i++) {
                stmt.setString(i + 1, params.get(i));      // JDBC parameters start at 1
            }
            try (ResultSet rs = stmt.executeQuery()) {
                while (rs.next()) {                         // loop over every row
                    students.add(mapRow(rs));
                }
            }
        }
        return students;
    }

    /** Returns one student, or null when the id does not exist. */
    public Student findById(int id) throws SQLException {
        String sql = BASE_SELECT + "WHERE s.id = ?";
        try (Connection conn = DBConnection.getConnection();
             PreparedStatement stmt = conn.prepareStatement(sql)) {
            stmt.setInt(1, id);
            try (ResultSet rs = stmt.executeQuery()) {
                return rs.next() ? mapRow(rs) : null;
            }
        }
    }

    /** Distinct course names – used for the course filter drop-down. */
    public List<String> findAllCourses() throws SQLException {
        List<String> courses = new ArrayList<>();
        try (Connection conn = DBConnection.getConnection();
             PreparedStatement stmt = conn.prepareStatement("SELECT DISTINCT course FROM students ORDER BY course");
             ResultSet rs = stmt.executeQuery()) {
            while (rs.next()) {
                courses.add(rs.getString("course"));
            }
        }
        return courses;
    }

    /** Total number of students (shown on the list page). */
    public int countAll() throws SQLException {
        try (Connection conn = DBConnection.getConnection();
             PreparedStatement stmt = conn.prepareStatement("SELECT COUNT(*) FROM students");
             ResultSet rs = stmt.executeQuery()) {
            return rs.next() ? rs.getInt(1) : 0;
        }
    }

    /** Copies one ResultSet row into a Student object. */
    private Student mapRow(ResultSet rs) throws SQLException {
        Student student = new Student();
        student.setId(rs.getInt("id"));
        student.setName(rs.getString("name"));
        student.setRollNumber(rs.getString("roll_number"));
        student.setEmail(rs.getString("email"));
        student.setCourse(rs.getString("course"));
        student.setPhone(rs.getString("phone"));
        student.setDateOfBirth(rs.getDate("date_of_birth"));
        student.setAddress(rs.getString("address"));
        student.setCreatedAt(rs.getTimestamp("created_at"));

        int photoId = rs.getInt("profile_photo_id");
        student.setProfilePhotoId(rs.wasNull() ? null : photoId);   // SQL NULL → Java null
        student.setFileCount(rs.getInt("file_count"));
        student.setDocumentCount(rs.getInt("document_count"));
        return student;
    }
}
