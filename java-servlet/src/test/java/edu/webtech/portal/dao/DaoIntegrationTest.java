package edu.webtech.portal.dao;

import edu.webtech.portal.model.Student;
import edu.webtech.portal.model.StudentFile;
import edu.webtech.portal.model.User;
import edu.webtech.portal.util.AppConfig;
import edu.webtech.portal.util.DatabaseUtil;
import edu.webtech.portal.util.PasswordUtil;
import org.junit.jupiter.api.AfterAll;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;

import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.Arrays;
import java.util.List;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.junit.jupiter.api.Assumptions.assumeTrue;

/**
 * Integration tests against the REAL MySQL database configured in ../.env.
 * They create their own rows (roll numbers starting with ZZJ) and delete them
 * afterwards.  If the database cannot be reached the tests are SKIPPED
 * (reported as skipped by Maven - never as passed).
 */
class DaoIntegrationTest {

    private static long studentNoFiles;
    private static long studentWithFiles;
    private static boolean available;

    @BeforeAll
    static void setUp() throws Exception {
        String cfg = System.getProperty("smp.config");
        assumeTrue(cfg != null && Files.isRegularFile(Paths.get(cfg)), "No .env file - integration tests skipped");
        AppConfig config = AppConfig.load(Paths.get(cfg));
        DatabaseUtil.init(config);
        try (Connection con = DatabaseUtil.getConnection()) {
            available = con.isValid(3);
        } catch (RuntimeException e) {
            available = false;
        }
        assumeTrue(available, "Database not reachable - integration tests skipped");

        try (Connection con = DatabaseUtil.getConnection(); Statement st = con.createStatement()) {
            st.executeUpdate("DELETE FROM students WHERE roll_number LIKE 'ZZJ%'");
            long courseId;
            try (ResultSet rs = st.executeQuery("SELECT id FROM courses ORDER BY id LIMIT 1")) {
                rs.next();
                courseId = rs.getLong(1);
            }
            studentNoFiles = insertStudent(con, "ZZJ0001", "Junit Nofiles", "zzj1@example.edu", courseId);
            studentWithFiles = insertStudent(con, "ZZJ0002", "Junit Manyfiles", "zzj2@example.edu", courseId);
            insertFile(con, studentWithFiles, "profile_photo", "image/png", "a");
            insertFile(con, studentWithFiles, "id_proof", "application/pdf", "b");
            insertFile(con, studentWithFiles, "certificate", "application/pdf", "c");
            try (PreparedStatement ps = con.prepareStatement(
                    "INSERT INTO users (username, display_name, password_hash, role, student_id) VALUES (?, ?, ?, 'student', ?)")) {
                ps.setString(1, "ZZJ0002");
                ps.setString(2, "Junit Manyfiles");
                ps.setString(3, "$2y$10$adan6q8PgnOZTEXWue7SHeKNc0Rjj9V1298dCpFWlUnFUXZCLUrcC");  // PHP hash of Junit!Test2026
                ps.setLong(4, studentWithFiles);
                ps.executeUpdate();
            }
        }
    }

    @AfterAll
    static void tearDown() throws Exception {
        if (!available) {
            return;
        }
        try (Connection con = DatabaseUtil.getConnection(); Statement st = con.createStatement()) {
            st.executeUpdate("DELETE FROM students WHERE roll_number LIKE 'ZZJ%'");   // cascades to files + user
        }
    }

    private static long insertStudent(Connection con, String roll, String name, String email, long courseId) throws Exception {
        try (PreparedStatement ps = con.prepareStatement(
                "INSERT INTO students (roll_number, full_name, email, course_id) VALUES (?, ?, ?, ?)", Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, roll);
            ps.setString(2, name);
            ps.setString(3, email);
            ps.setLong(4, courseId);
            ps.executeUpdate();
            try (ResultSet keys = ps.getGeneratedKeys()) {
                keys.next();
                return keys.getLong(1);
            }
        }
    }

    private static void insertFile(Connection con, long studentId, String category, String mime, String seed) throws Exception {
        String hex = String.join("", java.util.Collections.nCopies(32, seed));
        String ext = mime.equals("application/pdf") ? "pdf" : "png";
        String stored = hex + "." + ext;
        try (PreparedStatement ps = con.prepareStatement(
                "INSERT INTO student_files (student_id, category, original_name, stored_name, relative_path, mime_type, file_size, checksum_sha256)"
                        + " VALUES (?, ?, ?, ?, ?, ?, ?, ?)")) {
            ps.setLong(1, studentId);
            ps.setString(2, category);
            ps.setString(3, category + "." + ext);
            ps.setString(4, stored);
            ps.setString(5, "students/" + studentId + "/" + category + "/" + stored);
            ps.setString(6, mime);
            ps.setLong(7, 1234);
            ps.setString(8, String.join("", java.util.Collections.nCopies(64, "0")));
            ps.executeUpdate();
        }
    }

    @Test
    void fetchesMultipleStudentsWithSearch() {
        StudentDao dao = new StudentDao();
        List<Student> page = dao.findPage("ZZJ", 1, 10);
        assertEquals(2, page.size());
        assertEquals(2, dao.count("ZZJ"));
        assertEquals("Junit Manyfiles", page.get(0).getFullName(), "sorted by name");
    }

    @Test
    void fetchesStudentWithNoFiles() {
        Student s = new StudentDao().findById(studentNoFiles).orElseThrow(AssertionError::new);
        assertEquals("ZZJ0001", s.getRollNumber());
        assertEquals(null, s.getPhotoFileId());
        assertTrue(new StudentFileDao().findByStudentId(studentNoFiles).isEmpty());
    }

    @Test
    void fetchesStudentWithMultipleFiles() {
        Student s = new StudentDao().findById(studentWithFiles).orElseThrow(AssertionError::new);
        List<StudentFile> files = new StudentFileDao().findByStudentId(studentWithFiles);
        assertEquals(3, files.size());
        assertEquals("profile_photo", files.get(0).getCategory(), "photo listed first");
        assertEquals(files.get(0).getId(), s.getPhotoFileId().longValue());
        assertTrue(new StudentFileDao().findLatestPhoto(studentWithFiles).isPresent());
        assertFalse(new StudentFileDao().findLatestPhoto(studentNoFiles).isPresent());
    }

    @Test
    void batchLoadsFilesForSeveralStudents() {
        Map<Long, List<StudentFile>> map = new StudentFileDao().findByStudentIds(Arrays.asList(studentNoFiles, studentWithFiles));
        assertEquals(3, map.get(studentWithFiles).size());
        assertFalse(map.containsKey(studentNoFiles));
    }

    @Test
    void invalidIdsReturnEmpty() {
        assertFalse(new StudentDao().findById(4_000_000_000L).isPresent());
        assertFalse(new StudentFileDao().findById(4_000_000_000L).isPresent());
    }

    @Test
    void sqlInjectionIsTreatedAsPlainText() {
        StudentDao dao = new StudentDao();
        assertEquals(0, dao.count("' OR '1'='1' -- "));
        assertEquals(0, dao.count("%"), "LIKE wildcard escaped");
        assertEquals(2, dao.count("ZZJ"), "table intact");
    }

    @Test
    void studentLoginVerifiesPhpHash() {
        User u = new UserDao().findByUsername("ZZJ0002").orElseThrow(AssertionError::new);
        assertTrue(u.isStudent());
        assertEquals(studentWithFiles, u.getStudentId().longValue());
        assertTrue(PasswordUtil.verify("Junit!Test2026", u.getPasswordHash()));
        assertFalse(new UserDao().findByUsername("no-such-user-xyz").isPresent());
    }

    @Test
    void sharedStorageDirectoryIsConfigured() throws Exception {
        Path storage = AppConfig.load(Paths.get(System.getProperty("smp.config"))).getStorageDir();
        assertTrue(storage.toString().replace('\\', '/').endsWith("storage/uploads"));
    }
}
