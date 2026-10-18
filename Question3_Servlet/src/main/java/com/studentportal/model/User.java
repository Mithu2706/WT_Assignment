package com.studentportal.model;

import java.io.Serializable;

/**
 * User – the logged-in portal user (a row of the {@code users} table).
 * An instance is stored in the HttpSession after a successful login, so it
 * implements Serializable (Tomcat may save sessions to disk on restart).
 * The password hash is deliberately NOT kept in this object.
 */
public class User implements Serializable {

    private static final long serialVersionUID = 1L;

    private final int id;
    private final String username;
    private final String fullName;

    public User(int id, String username, String fullName) {
        this.id = id;
        this.username = username;
        this.fullName = fullName;
    }

    public int getId() { return id; }
    public String getUsername() { return username; }
    public String getFullName() { return fullName; }
}
