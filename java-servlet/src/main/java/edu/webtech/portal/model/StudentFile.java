package edu.webtech.portal.model;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.Locale;

/** Metadata of one uploaded file (row of student_files). */
public class StudentFile {

    private static final DateTimeFormatter DATE_TIME = DateTimeFormatter.ofPattern("dd MMM yyyy, HH:mm");

    private long id;
    private long studentId;
    private String category;
    private String originalName;
    private String relativePath;
    private String mimeType;
    private long fileSize;
    private LocalDateTime uploadedAt;

    public long getId() { return id; }
    public void setId(long id) { this.id = id; }

    public long getStudentId() { return studentId; }
    public void setStudentId(long studentId) { this.studentId = studentId; }

    public String getCategory() { return category; }
    public void setCategory(String category) { this.category = category; }

    public String getOriginalName() { return originalName; }
    public void setOriginalName(String originalName) { this.originalName = originalName; }

    public String getRelativePath() { return relativePath; }
    public void setRelativePath(String relativePath) { this.relativePath = relativePath; }

    public String getMimeType() { return mimeType; }
    public void setMimeType(String mimeType) { this.mimeType = mimeType; }

    public long getFileSize() { return fileSize; }
    public void setFileSize(long fileSize) { this.fileSize = fileSize; }

    public LocalDateTime getUploadedAt() { return uploadedAt; }
    public void setUploadedAt(LocalDateTime uploadedAt) { this.uploadedAt = uploadedAt; }

    // ---- helpers for the JSP pages ----

    public boolean isImage() { return mimeType != null && mimeType.startsWith("image/"); }
    public boolean isPdf() { return "application/pdf".equals(mimeType); }
    public boolean isProfilePhoto() { return "profile_photo".equals(category); }
    public String getUploadedDisplay() { return uploadedAt == null ? "—" : uploadedAt.format(DATE_TIME); }

    public String getCategoryLabel() {
        if (category == null) {
            return "File";
        }
        switch (category) {
            case "profile_photo": return "Profile photo";
            case "id_proof": return "ID proof";
            case "certificate": return "Certificate";
            case "marksheet": return "Marksheet";
            default: return "Other document";
        }
    }

    public String getSizeDisplay() {
        if (fileSize >= 1024 * 1024) {
            return String.format(Locale.ROOT, "%.1f MB", fileSize / (1024.0 * 1024.0)).replace(".0 MB", " MB");
        }
        if (fileSize >= 1024) {
            return Math.round(fileSize / 1024.0) + " KB";
        }
        return fileSize + " B";
    }
}
