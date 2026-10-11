package edu.webtech.portal.servlet;

import edu.webtech.portal.dao.StudentFileDao;
import edu.webtech.portal.listener.AppContextListener;
import edu.webtech.portal.model.StudentFile;
import edu.webtech.portal.model.User;
import edu.webtech.portal.util.DataAccessException;
import edu.webtech.portal.util.StoragePaths;
import edu.webtech.portal.util.WebUtil;

import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.OutputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Arrays;
import java.util.HashSet;
import java.util.Optional;
import java.util.OptionalLong;
import java.util.Set;

/**
 * Streams uploaded images and documents from the private storage folder.
 *
 *   GET /file?id=FILE_ID              -> shown in the browser (images, PDFs)
 *   GET /file?id=FILE_ID&download=1   -> "Save as" download
 *   GET /photo?student=STUDENT_ID     -> newest profile photo (used by <img> tags)
 *
 * The URL contains only a numeric id.  The real path comes from the
 * database and is checked by StoragePaths, so "../" tricks are impossible.
 * Access requires a logged-in user (AuthFilter) who may view that student.
 */
@WebServlet(urlPatterns = {"/file", "/photo"})
public class FileServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;
    private static final Set<String> ALLOWED_TYPES =
            new HashSet<>(Arrays.asList("image/jpeg", "image/png", "image/webp", "application/pdf"));

    private final StudentFileDao fileDao = new StudentFileDao();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        User user = (User) req.getAttribute("currentUser");
        boolean isPhoto = req.getServletPath().equals("/photo");
        OptionalLong id = WebUtil.parseId(req.getParameter(isPhoto ? "student" : "id"));
        if (!id.isPresent()) {
            WebUtil.sendError(req, resp, 400, "Invalid request", "The file ID must be a positive number.");
            return;
        }

        Optional<StudentFile> found;
        try {
            if (isPhoto) {
                if (!user.canView(id.getAsLong())) {
                    WebUtil.sendError(req, resp, 403, "Access denied", "You may not view this file.");
                    return;
                }
                found = fileDao.findLatestPhoto(id.getAsLong());
            } else {
                found = fileDao.findById(id.getAsLong());
            }
        } catch (DataAccessException e) {
            WebUtil.sendError(req, resp, 503, "Service unavailable", e.getMessage());
            return;
        }

        if (!found.isPresent()) {
            WebUtil.sendError(req, resp, 404, "File not found", "That file does not exist or has been deleted.");
            return;
        }
        StudentFile file = found.get();
        // Authorisation: a student account may only open its own files.
        if (!user.canView(file.getStudentId())) {
            WebUtil.sendError(req, resp, 403, "Access denied", "You may not view this file.");
            return;
        }

        StoragePaths storage = (StoragePaths) getServletContext().getAttribute(AppContextListener.STORAGE_ATTR);
        Optional<Path> path = storage.resolve(file.getRelativePath(), file.getStudentId());
        if (!path.isPresent()) {
            log("File record " + file.getId() + " exists but the file is missing or unsafe: " + file.getRelativePath());
            WebUtil.sendError(req, resp, 404, "File missing",
                    "The file record exists, but the file itself is missing from storage.");
            return;
        }

        // Only content types accepted at upload time are ever sent; anything else is a download.
        String mime = ALLOWED_TYPES.contains(file.getMimeType()) ? file.getMimeType() : "application/octet-stream";
        boolean attachment = req.getParameter("download") != null || "application/octet-stream".equals(mime);

        resp.setContentType(mime);
        resp.setContentLengthLong(Files.size(path.get()));
        resp.setHeader("Content-Disposition", WebUtil.contentDisposition(file.getOriginalName(), attachment));
        resp.setHeader("X-Content-Type-Options", "nosniff");
        resp.setHeader("Cache-Control", "private, no-store");

        try (OutputStream out = resp.getOutputStream()) {
            Files.copy(path.get(), out);                 // streams in chunks, no full read into memory
        }
    }
}
