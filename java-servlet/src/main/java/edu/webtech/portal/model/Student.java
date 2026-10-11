package edu.webtech.portal.model;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;

/** A student record joined with its course and (optionally) its files. */
public class Student {

    private static final DateTimeFormatter DATE = DateTimeFormatter.ofPattern("dd MMM yyyy");
    private static final DateTimeFormatter DATE_TIME = DateTimeFormatter.ofPattern("dd MMM yyyy, HH:mm");

    private long id;
    private String rollNumber;
    private String fullName;
    private String email;
    private String courseCode;
    private String courseName;
    private String registrationSource;
    private LocalDateTime createdAt;
    private LocalDateTime updatedAt;
    private Long photoFileId;                       // newest profile photo, null if none
    private List<StudentFile> files = new ArrayList<>();

    public long getId() { return id; }
    public void setId(long id) { this.id = id; }

    public String getRollNumber() { return rollNumber; }
    public void setRollNumber(String rollNumber) { this.rollNumber = rollNumber; }

    public String getFullName() { return fullName; }
    public void setFullName(String fullName) { this.fullName = fullName; }

    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }

    public String getCourseCode() { return courseCode; }
    public void setCourseCode(String courseCode) { this.courseCode = courseCode; }

    public String getCourseName() { return courseName; }
    public void setCourseName(String courseName) { this.courseName = courseName; }

    public String getRegistrationSource() { return registrationSource; }
    public void setRegistrationSource(String registrationSource) { this.registrationSource = registrationSource; }

    public LocalDateTime getCreatedAt() { return createdAt; }
    public void setCreatedAt(LocalDateTime createdAt) { this.createdAt = createdAt; }

    public LocalDateTime getUpdatedAt() { return updatedAt; }
    public void setUpdatedAt(LocalDateTime updatedAt) { this.updatedAt = updatedAt; }

    public Long getPhotoFileId() { return photoFileId; }
    public void setPhotoFileId(Long photoFileId) { this.photoFileId = photoFileId; }

    public List<StudentFile> getFiles() { return files; }
    public void setFiles(List<StudentFile> files) { this.files = files; }

    // ---- convenience getters used by the JSP pages ----

    public String getCreatedDisplay() { return createdAt == null ? "—" : createdAt.format(DATE); }
    public String getCreatedDateTimeDisplay() { return createdAt == null ? "—" : createdAt.format(DATE_TIME); }
    public String getUpdatedDisplay() { return updatedAt == null ? "—" : updatedAt.format(DATE_TIME); }
    public String getInitials() { return initialsOf(fullName); }
    public boolean isSelfRegistered() { return "self".equals(registrationSource); }
    public int getFileCount() { return files.size(); }

    /** Files other than profile photos (ID proofs, certificates, marksheets ...). */
    public List<StudentFile> getDocuments() {
        List<StudentFile> docs = new ArrayList<>();
        for (StudentFile f : files) {
            if (!f.isProfilePhoto()) {
                docs.add(f);
            }
        }
        return docs;
    }

    /** All image files (profile photos and scanned image documents). */
    public List<StudentFile> getImages() {
        List<StudentFile> images = new ArrayList<>();
        for (StudentFile f : files) {
            if (f.isImage()) {
                images.add(f);
            }
        }
        return images;
    }

    static String initialsOf(String name) {
        if (name == null || name.trim().isEmpty()) {
            return "?";
        }
        StringBuilder sb = new StringBuilder();
        for (String part : name.trim().split("\\s+")) {
            if (sb.length() == 2) {
                break;
            }
            sb.appendCodePoint(Character.toUpperCase(part.codePointAt(0)));
        }
        return sb.toString();
    }
}
