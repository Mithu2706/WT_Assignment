package com.studentportal.model;

import java.util.Date;
import java.util.Locale;

/**
 * StudentFile – one row of the {@code student_files} table, i.e. the
 * METADATA of an uploaded photo or document (the bytes stay on disk).
 *
 * The helper methods isImage(), isPdf() and getFormattedSize() are used by
 * student-profile.jsp for conditional rendering, e.g.
 * {@code <c:when test="${file.image}">} shows an <img> preview.
 */
public class StudentFile {

    public static final String CATEGORY_PROFILE = "profile";
    public static final String CATEGORY_DOCUMENT = "document";

    private int id;
    private int studentId;
    private String fileCategory;
    private String documentLabel;
    private String originalName;
    private String storedName;
    private String filePath;      // relative path, e.g. uploads/documents/ab12…ef.pdf (never sent to the browser)
    private String fileType;      // MIME type, e.g. image/jpeg or application/pdf
    private long fileSize;
    private Date uploadDate;

    public boolean isImage() {
        return fileType != null && fileType.startsWith("image/");
    }

    public boolean isPdf() {
        return "application/pdf".equals(fileType);
    }

    public boolean isProfilePhoto() {
        return CATEGORY_PROFILE.equals(fileCategory);
    }

    /** Text shown on the file card: "Profile Photo", "ID Proof", "Certificate"… */
    public String getDisplayLabel() {
        if (isProfilePhoto()) {
            return "Profile Photo";
        }
        return documentLabel != null ? documentLabel : "Document";
    }

    /** Short type badge: PDF, JPEG, PNG, WEBP. */
    public String getTypeLabel() {
        if (fileType == null) {
            return "FILE";
        }
        switch (fileType) {
            case "application/pdf": return "PDF";
            case "image/jpeg":      return "JPEG";
            case "image/png":       return "PNG";
            case "image/webp":      return "WEBP";
            default:                return "FILE";
        }
    }

    /** 1536 → "1.5 KB", 2097152 → "2.00 MB". */
    public String getFormattedSize() {
        if (fileSize >= 1024L * 1024L) {
            return String.format(Locale.ROOT, "%.2f MB", fileSize / (1024.0 * 1024.0));
        }
        if (fileSize >= 1024L) {
            return String.format(Locale.ROOT, "%.1f KB", fileSize / 1024.0);
        }
        return fileSize + " bytes";
    }

    // ---------- Getters and setters ----------

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public int getStudentId() { return studentId; }
    public void setStudentId(int studentId) { this.studentId = studentId; }

    public String getFileCategory() { return fileCategory; }
    public void setFileCategory(String fileCategory) { this.fileCategory = fileCategory; }

    public String getDocumentLabel() { return documentLabel; }
    public void setDocumentLabel(String documentLabel) { this.documentLabel = documentLabel; }

    public String getOriginalName() { return originalName; }
    public void setOriginalName(String originalName) { this.originalName = originalName; }

    public String getStoredName() { return storedName; }
    public void setStoredName(String storedName) { this.storedName = storedName; }

    public String getFilePath() { return filePath; }
    public void setFilePath(String filePath) { this.filePath = filePath; }

    public String getFileType() { return fileType; }
    public void setFileType(String fileType) { this.fileType = fileType; }

    public long getFileSize() { return fileSize; }
    public void setFileSize(long fileSize) { this.fileSize = fileSize; }

    public Date getUploadDate() { return uploadDate; }
    public void setUploadDate(Date uploadDate) { this.uploadDate = uploadDate; }
}
