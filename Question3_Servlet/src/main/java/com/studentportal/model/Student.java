package com.studentportal.model;

import java.util.Date;

/**
 * Student – a plain Java object (JavaBean / "model") holding one row of the
 * {@code students} table, plus two values calculated by the SQL query:
 * the id of the current profile photo and the number of uploaded files.
 *
 * JSP pages read these values through the getters, e.g. ${student.name}
 * calls getName() and ${student.initials} calls getInitials().
 */
public class Student {

    private int id;
    private String name;
    private String rollNumber;
    private String email;
    private String course;
    private String phone;
    private Date dateOfBirth;
    private String address;
    private Date createdAt;

    /** id of the latest 'profile' file, or null when the student has no photo. */
    private Integer profilePhotoId;
    private int fileCount;
    private int documentCount;

    /** First letters of the first two words of the name – shown when there is no photo. */
    public String getInitials() {
        if (name == null || name.trim().isEmpty()) {
            return "?";
        }
        StringBuilder initials = new StringBuilder();
        for (String word : name.trim().split("\\s+")) {
            if (initials.length() == 2) {
                break;
            }
            initials.append(Character.toUpperCase(word.charAt(0)));
        }
        return initials.toString();
    }

    public boolean isHasPhoto() {
        return profilePhotoId != null;
    }

    // ---------- Getters and setters ----------

    public int getId() { return id; }
    public void setId(int id) { this.id = id; }

    public String getName() { return name; }
    public void setName(String name) { this.name = name; }

    public String getRollNumber() { return rollNumber; }
    public void setRollNumber(String rollNumber) { this.rollNumber = rollNumber; }

    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }

    public String getCourse() { return course; }
    public void setCourse(String course) { this.course = course; }

    public String getPhone() { return phone; }
    public void setPhone(String phone) { this.phone = phone; }

    public Date getDateOfBirth() { return dateOfBirth; }
    public void setDateOfBirth(Date dateOfBirth) { this.dateOfBirth = dateOfBirth; }

    public String getAddress() { return address; }
    public void setAddress(String address) { this.address = address; }

    public Date getCreatedAt() { return createdAt; }
    public void setCreatedAt(Date createdAt) { this.createdAt = createdAt; }

    public Integer getProfilePhotoId() { return profilePhotoId; }
    public void setProfilePhotoId(Integer profilePhotoId) { this.profilePhotoId = profilePhotoId; }

    public int getFileCount() { return fileCount; }
    public void setFileCount(int fileCount) { this.fileCount = fileCount; }

    public int getDocumentCount() { return documentCount; }
    public void setDocumentCount(int documentCount) { this.documentCount = documentCount; }
}
