package edu.webtech.portal.util;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.logging.Level;
import java.util.logging.Logger;

/**
 * Opens JDBC connections to the shared MySQL database.
 *
 * Each DAO call opens a connection in try-with-resources and closes it
 * immediately, which is simple and safe for a small application.  (A
 * production system would use a connection pool such as Tomcat JDBC.)
 */
public final class DatabaseUtil {

    private static final Logger LOG = Logger.getLogger(DatabaseUtil.class.getName());
    private static volatile AppConfig config;

    private DatabaseUtil() {
    }

    /** Called once at start-up by AppContextListener (and by tests). */
    public static void init(AppConfig appConfig) {
        try {
            // Explicit registration: needed when the driver jar is inside WEB-INF/lib.
            Class.forName("com.mysql.cj.jdbc.Driver");
        } catch (ClassNotFoundException e) {
            throw new IllegalStateException("MySQL JDBC driver missing from WEB-INF/lib", e);
        }
        config = appConfig;
    }

    public static boolean isConfigured() {
        return config != null;
    }

    /**
     * Returns a new connection or throws DataAccessException with a message
     * that is safe to show to the user (details go to the server log only).
     */
    public static Connection getConnection() {
        AppConfig c = config;
        if (c == null) {
            throw new DataAccessException("The portal is not configured yet. Please contact the administrator.", null);
        }
        try {
            return DriverManager.getConnection(c.jdbcUrl(), c.getDbUser(), c.getDbPass());
        } catch (SQLException e) {
            LOG.log(Level.SEVERE, "Database connection failed: {0}", e.getMessage());
            throw new DataAccessException("The database is not available right now. Please try again later.", e);
        }
    }
}
