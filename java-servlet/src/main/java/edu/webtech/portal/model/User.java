package edu.webtech.portal.model;

import java.io.Serializable;

/**
 * A login account (row of the users table).  The password hash is kept
 * only while authenticating and is never stored in the HTTP session.
 */
public class User implements Serializable {

    private static final long serialVersionUID = 1L;

    private long id;
    private String username;
    private String displayName;
    private String role;            // admin | staff | student
    private Long studentId;         // only for role = student
    private boolean active;
    private transient String passwordHash;

    public long getId() { return id; }
    public void setId(long id) { this.id = id; }

    public String getUsername() { return username; }
    public void setUsername(String username) { this.username = username; }

    public String getDisplayName() { return displayName; }
    public void setDisplayName(String displayName) { this.displayName = displayName; }

    public String getRole() { return role; }
    public void setRole(String role) { this.role = role; }

    public Long getStudentId() { return studentId; }
    public void setStudentId(Long studentId) { this.studentId = studentId; }

    public boolean isActive() { return active; }
    public void setActive(boolean active) { this.active = active; }

    public String getPasswordHash() { return passwordHash; }
    public void setPasswordHash(String passwordHash) { this.passwordHash = passwordHash; }

    /** Students may only see their own profile; admin and staff see everyone. */
    public boolean isStudent() { return "student".equals(role); }

    public boolean canView(long otherStudentId) {
        return !isStudent() || (studentId != null && studentId == otherStudentId);
    }

    /** Copy without the password hash - this is what goes into the session. */
    public User withoutSecrets() {
        User u = new User();
        u.id = id;
        u.username = username;
        u.displayName = displayName;
        u.role = role;
        u.studentId = studentId;
        u.active = active;
        return u;
    }

    public String getInitials() {
        return Student.initialsOf(displayName);
    }
}
