package edu.webtech.portal.dao;

import edu.webtech.portal.model.StudentFile;
import edu.webtech.portal.util.DataAccessException;
import edu.webtech.portal.util.DatabaseUtil;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.Collection;
import java.util.Collections;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;

/** JDBC access to student_files (file metadata written by the PHP module). */
public class StudentFileDao {

    private static final String COLUMNS =
            "id, student_id, category, original_name, relative_path, mime_type, file_size, uploaded_at";
    private static final String ORDER =
            " ORDER BY FIELD(category, 'profile_photo', 'id_proof', 'certificate', 'marksheet', 'other'), uploaded_at DESC, id DESC";

    public List<StudentFile> findByStudentId(long studentId) {
        String sql = "SELECT " + COLUMNS + " FROM student_files WHERE student_id = ?" + ORDER;
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setLong(1, studentId);
            return readAll(ps);
        } catch (SQLException e) {
            throw new DataAccessException("The student's files could not be loaded right now.", e);
        }
    }

    /**
     * Files for several students in ONE query (avoids one query per student).
     * The IN (...) list contains only "?" placeholders - values are bound.
     */
    public Map<Long, List<StudentFile>> findByStudentIds(Collection<Long> studentIds) {
        Map<Long, List<StudentFile>> result = new LinkedHashMap<>();
        if (studentIds.isEmpty()) {
            return result;
        }
        String placeholders = String.join(",", Collections.nCopies(studentIds.size(), "?"));
        String sql = "SELECT " + COLUMNS + " FROM student_files WHERE student_id IN (" + placeholders + ")" + ORDER;
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            int i = 1;
            for (Long id : studentIds) {
                ps.setLong(i++, id);
            }
            for (StudentFile f : readAll(ps)) {
                result.computeIfAbsent(f.getStudentId(), k -> new ArrayList<>()).add(f);
            }
            return result;
        } catch (SQLException e) {
            throw new DataAccessException("File information could not be loaded right now.", e);
        }
    }

    public Optional<StudentFile> findById(long fileId) {
        String sql = "SELECT " + COLUMNS + " FROM student_files WHERE id = ?";
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setLong(1, fileId);
            List<StudentFile> list = readAll(ps);
            return list.isEmpty() ? Optional.empty() : Optional.of(list.get(0));
        } catch (SQLException e) {
            throw new DataAccessException("The file could not be loaded right now.", e);
        }
    }

    public Optional<StudentFile> findLatestPhoto(long studentId) {
        String sql = "SELECT " + COLUMNS + " FROM student_files WHERE student_id = ? AND category = 'profile_photo'"
                   + " ORDER BY uploaded_at DESC, id DESC LIMIT 1";
        try (Connection con = DatabaseUtil.getConnection();
             PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setLong(1, studentId);
            List<StudentFile> list = readAll(ps);
            return list.isEmpty() ? Optional.empty() : Optional.of(list.get(0));
        } catch (SQLException e) {
            throw new DataAccessException("The profile photo could not be loaded right now.", e);
        }
    }

    private static List<StudentFile> readAll(PreparedStatement ps) throws SQLException {
        List<StudentFile> list = new ArrayList<>();
        try (ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                StudentFile f = new StudentFile();
                f.setId(rs.getLong("id"));
                f.setStudentId(rs.getLong("student_id"));
                f.setCategory(rs.getString("category"));
                f.setOriginalName(rs.getString("original_name"));
                f.setRelativePath(rs.getString("relative_path"));
                f.setMimeType(rs.getString("mime_type"));
                f.setFileSize(rs.getLong("file_size"));
                f.setUploadedAt(rs.getObject("uploaded_at", LocalDateTime.class));
                list.add(f);
            }
        }
        return list;
    }
}
