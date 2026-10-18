package com.studentportal.dao;

import com.studentportal.model.StudentFile;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

/**
 * FileDAO – Data Access Object for the {@code student_files} table
 * (the metadata of uploaded photos and documents).
 */
public class FileDAO {

    private static final String COLUMNS =
            "id, student_id, file_category, document_label, original_name, stored_name, "
          + "file_path, file_type, file_size, upload_date";

    /** All files of one student: profile photo first, then newest documents first. */
    public List<StudentFile> findByStudentId(int studentId) throws SQLException {
        String sql = "SELECT " + COLUMNS + " FROM student_files WHERE student_id = ? "
                   + "ORDER BY (file_category = 'profile') DESC, upload_date DESC, id DESC";
        List<StudentFile> files = new ArrayList<>();
        try (Connection conn = DBConnection.getConnection();
             PreparedStatement stmt = conn.prepareStatement(sql)) {
            stmt.setInt(1, studentId);
            try (ResultSet rs = stmt.executeQuery()) {
                while (rs.next()) {
                    files.add(mapRow(rs));
                }
            }
        }
        return files;
    }

    /** One file by id (used by FileDownloadServlet), or null if not found. */
    public StudentFile findById(int fileId) throws SQLException {
        String sql = "SELECT " + COLUMNS + " FROM student_files WHERE id = ?";
        try (Connection conn = DBConnection.getConnection();
             PreparedStatement stmt = conn.prepareStatement(sql)) {
            stmt.setInt(1, fileId);
            try (ResultSet rs = stmt.executeQuery()) {
                return rs.next() ? mapRow(rs) : null;
            }
        }
    }

    private StudentFile mapRow(ResultSet rs) throws SQLException {
        StudentFile file = new StudentFile();
        file.setId(rs.getInt("id"));
        file.setStudentId(rs.getInt("student_id"));
        file.setFileCategory(rs.getString("file_category"));
        file.setDocumentLabel(rs.getString("document_label"));
        file.setOriginalName(rs.getString("original_name"));
        file.setStoredName(rs.getString("stored_name"));
        file.setFilePath(rs.getString("file_path"));
        file.setFileType(rs.getString("file_type"));
        file.setFileSize(rs.getLong("file_size"));
        file.setUploadDate(rs.getTimestamp("upload_date"));
        return file;
    }
}
