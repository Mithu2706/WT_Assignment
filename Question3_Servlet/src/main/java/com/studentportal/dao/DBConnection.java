package com.studentportal.dao;

import java.io.IOException;
import java.io.InputStream;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.Properties;

/**
 * DBConnection – creates JDBC connections to the SAME MySQL database that the
 * PHP admin panel uses (student_portal).
 *
 * Settings are read once from {@code db.properties} (src/main/resources →
 * WEB-INF/classes/db.properties inside the WAR):
 * <pre>
 *   db.url, db.user, db.password   – JDBC connection details
 *   upload.base.dir                – the Question2_PHP folder that contains uploads/
 * </pre>
 * Every setting can be overridden with an environment variable
 * (SP_DB_URL, SP_DB_USER, SP_DB_PASSWORD, SP_UPLOAD_BASE_DIR) so a real
 * password never has to be committed to GitHub.
 *
 * Each DAO method opens a connection with getConnection() inside a
 * try-with-resources block, so the connection is always closed again.
 */
public final class DBConnection {

    private static final Properties SETTINGS = new Properties();
    private static String configError;

    // Static block: runs once when the class is first used.
    static {
        try (InputStream in = DBConnection.class.getClassLoader().getResourceAsStream("db.properties")) {
            if (in == null) {
                configError = "db.properties was not found on the classpath (WEB-INF/classes).";
            } else {
                SETTINGS.load(in);
                // Load the MySQL Connector/J driver class (registers it with DriverManager)
                Class.forName(get("db.driver", "com.mysql.cj.jdbc.Driver"));
            }
        } catch (IOException e) {
            configError = "db.properties could not be read: " + e.getMessage();
        } catch (ClassNotFoundException e) {
            configError = "MySQL JDBC driver not found. Put mysql-connector-j-*.jar in WEB-INF/lib.";
        }
    }

    private DBConnection() {
        // utility class – no objects
    }

    /** Value from an environment variable (if set) or from db.properties. */
    private static String get(String key, String defaultValue) {
        String envName = "SP_" + key.toUpperCase().replace('.', '_');   // db.user → SP_DB_USER
        String fromEnv = System.getenv(envName);
        if (fromEnv != null) {
            return fromEnv;
        }
        return SETTINGS.getProperty(key, defaultValue);
    }

    /** Opens a new connection. The caller must close it (use try-with-resources). */
    public static Connection getConnection() throws SQLException {
        if (configError != null) {
            throw new SQLException("Database configuration error: " + configError);
        }
        return DriverManager.getConnection(
                get("db.url", "jdbc:mysql://localhost:3306/student_portal"),
                get("db.user", "root"),
                get("db.password", ""));
    }

    /** Folder that contains the PHP admin's uploads/ directory (see db.properties). */
    public static String getUploadBaseDir() {
        return get("upload.base.dir", "C:/xampp/htdocs/student-portal/Question2_PHP");
    }
}
