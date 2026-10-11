package edu.webtech.portal.model;

import org.junit.jupiter.api.Test;

import java.util.Arrays;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

/** Tests for the helpers used by the JSP pages and for authorisation rules. */
class ModelTest {

    private static StudentFile file(String category, String mime, long size) {
        StudentFile f = new StudentFile();
        f.setCategory(category);
        f.setMimeType(mime);
        f.setFileSize(size);
        return f;
    }

    @Test
    void splitsFilesIntoDocumentsAndImages() {
        Student s = new Student();
        s.setFiles(Arrays.asList(
                file("profile_photo", "image/png", 1000),
                file("id_proof", "application/pdf", 2000),
                file("certificate", "image/jpeg", 3000)));
        assertEquals(3, s.getFileCount());
        assertEquals(2, s.getDocuments().size(), "everything except the profile photo");
        assertEquals(2, s.getImages().size(), "photo + scanned JPEG certificate");
    }

    @Test
    void zeroFilesMeansEmptyLists() {
        Student s = new Student();
        assertEquals(0, s.getFileCount());
        assertTrue(s.getDocuments().isEmpty());
        assertTrue(s.getImages().isEmpty());
    }

    @Test
    void formatsSizesAndLabels() {
        assertEquals("512 B", file("other", "application/pdf", 512).getSizeDisplay());
        assertEquals("2 KB", file("other", "application/pdf", 2048).getSizeDisplay());
        assertEquals("1.5 MB", file("other", "application/pdf", 1572864).getSizeDisplay());
        assertEquals("ID proof", file("id_proof", "application/pdf", 1).getCategoryLabel());
        assertTrue(file("x", "application/pdf", 1).isPdf());
    }

    @Test
    void initialsHandleUnicodeAndBlankNames() {
        Student s = new Student();
        s.setFullName("aarav krishnan kumar");
        assertEquals("AK", s.getInitials());
        s.setFullName("  ");
        assertEquals("?", s.getInitials());
    }

    @Test
    void studentAccountsMayOnlyViewTheirOwnProfile() {
        User student = new User();
        student.setRole("student");
        student.setStudentId(7L);
        assertTrue(student.canView(7));
        assertFalse(student.canView(8));

        User staff = new User();
        staff.setRole("staff");
        assertTrue(staff.canView(8));
    }

    @Test
    void sessionCopyHasNoPasswordHash() {
        User u = new User();
        u.setPasswordHash("$2y$10$secret");
        u.setUsername("admin");
        assertEquals(null, u.withoutSecrets().getPasswordHash());
        assertEquals("admin", u.withoutSecrets().getUsername());
    }
}
