package edu.webtech.portal.util;

import java.io.IOException;
import java.io.InputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.Properties;

/**
 * Immutable application settings read from the SAME ".env" file that the
 * PHP module uses (KEY=VALUE lines are valid java.util.Properties syntax).
 *
 * Where the file is found (first match wins):
 *   1. JVM system property  -Dsmp.config=C:/path/.env
 *   2. environment variable SMP_CONFIG   (set in Tomcat's bin/setenv.bat)
 *   3. context init-param   smp.config   (web.xml / context.xml)
 * Real environment variables DB_HOST, DB_USER ... override values in the file.
 */
public final class AppConfig {

    private final String dbHost;
    private final int dbPort;
    private final String dbName;
    private final String dbUser;
    private final String dbPass;
    private final Path storageDir;
    private final int sessionTimeoutMinutes;
    private final Path source;

    private AppConfig(Properties p, Path source) {
        this.source = source;
        this.dbHost = value(p, "DB_HOST", "127.0.0.1");
        this.dbPort = parseInt(value(p, "DB_PORT", "3306"), 3306);
        this.dbName = value(p, "DB_NAME", "wt_student_portal");
        this.dbUser = value(p, "DB_USER", "smp_app");
        this.dbPass = value(p, "DB_PASS", "");
        this.sessionTimeoutMinutes = Math.max(1, parseInt(value(p, "SESSION_TIMEOUT_MINUTES", "30"), 30));

        // A relative STORAGE_DIR is resolved against the folder that contains .env
        Path storage = Paths.get(value(p, "STORAGE_DIR", "storage/uploads"));
        if (!storage.isAbsolute() && source != null && source.getParent() != null) {
            storage = source.getParent().resolve(storage);
        }
        this.storageDir = storage.toAbsolutePath().normalize();
    }

    /** Load configuration from an explicit file path. */
    public static AppConfig load(Path file) throws IOException {
        Properties props = new Properties();
        try (InputStream in = Files.newInputStream(file)) {
            props.load(in);
        }
        // Properties keeps surrounding quotes - strip them like the PHP loader does.
        for (String key : props.stringPropertyNames()) {
            String v = props.getProperty(key).trim();
            if (v.length() >= 2 && (v.charAt(0) == '"' || v.charAt(0) == '\'') && v.charAt(v.length() - 1) == v.charAt(0)) {
                v = v.substring(1, v.length() - 1);
            }
            props.setProperty(key, v);
        }
        return new AppConfig(props, file.toAbsolutePath().normalize());
    }

    /**
     * Find the config file using the lookup order described above.
     * @param contextParam value of the "smp.config" context parameter (may be null)
     */
    public static AppConfig locate(String contextParam) throws IOException {
        String[] candidates = {System.getProperty("smp.config"), System.getenv("SMP_CONFIG"), contextParam};
        for (String candidate : candidates) {
            if (candidate != null && !candidate.trim().isEmpty()) {
                Path path = Paths.get(candidate.trim());
                if (Files.isRegularFile(path)) {
                    return load(path);
                }
                throw new IOException("Configuration file not found: " + path.toAbsolutePath());
            }
        }
        throw new IOException("No configuration file set. Define SMP_CONFIG (see docs/SETUP.md).");
    }

    private static String value(Properties p, String key, String def) {
        String env = System.getenv(key);
        if (env != null && !env.isEmpty()) {
            return env;
        }
        String v = p.getProperty(key);
        return (v == null || v.isEmpty()) ? def : v;
    }

    private static int parseInt(String s, int def) {
        try {
            return Integer.parseInt(s.trim());
        } catch (NumberFormatException e) {
            return def;
        }
    }

    /** JDBC URL - UTF-8, no SSL for localhost, short timeouts so failures are reported quickly. */
    public String jdbcUrl() {
        return "jdbc:mysql://" + dbHost + ":" + dbPort + "/" + dbName
                + "?useSSL=false&allowPublicKeyRetrieval=true&characterEncoding=UTF-8"
                + "&connectTimeout=5000&socketTimeout=20000";
    }

    public String getDbUser() { return dbUser; }
    public String getDbPass() { return dbPass; }
    public String getDbName() { return dbName; }
    public Path getStorageDir() { return storageDir; }
    public int getSessionTimeoutMinutes() { return sessionTimeoutMinutes; }
    public Path getSource() { return source; }
}
