package com.studentportal.controller;

import com.studentportal.dao.DBConnection;
import com.studentportal.dao.FileDAO;
import com.studentportal.model.StudentFile;
import com.studentportal.util.SessionUtil;

import javax.servlet.ServletOutputStream;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.InputStream;
import java.io.UnsupportedEncodingException;
import java.net.URLEncoder;
import java.nio.file.Files;
import java.nio.file.InvalidPathException;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.sql.SQLException;
import java.util.Arrays;
import java.util.HashSet;
import java.util.Set;

/**
 * FileDownloadServlet – mapped to /file?id=N  (add &download=true to force a download).
 *
 * JSP pages use it in <img src="file?id=N"> and in View/Download links, so
 * the browser NEVER sees the real folder or file name on the server.
 *
 * Steps:
 *  1. Session check (only logged-in users can fetch files)
 *  2. Validate the id and load the file's metadata from MySQL (FileDAO)
 *  3. Resolve the stored relative path inside the uploads folder and make
 *     sure the result is still INSIDE that folder (blocks "../" traversal)
 *  4. Set Content-Type, Content-Length and Content-Disposition
 *     (inline = show in browser, attachment = download)
 *  5. Copy the bytes to the response with a ServletOutputStream
 */
public class FileDownloadServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;
    private static final int BUFFER_SIZE = 8192;

    /** Only these types are ever sent with their real Content-Type. */
    private static final Set<String> ALLOWED_TYPES = new HashSet<>(Arrays.asList(
            "image/jpeg", "image/png", "image/webp", "application/pdf"));

    private final FileDAO fileDAO = new FileDAO();

    @Override
    protected void doGet(HttpServletRequest request, HttpServletResponse response) throws IOException {

        // ---- 1. Session check ----
        if (SessionUtil.redirectIfNotLoggedIn(request, response)) {
            return;
        }

        // ---- 2. Validate id and load metadata ----
        Integer fileId = StudentProfileServlet.parsePositiveInt(request.getParameter("id"));
        if (fileId == null) {
            response.sendError(HttpServletResponse.SC_BAD_REQUEST, "Invalid file ID.");
            return;
        }

        StudentFile file;
        try {
            file = fileDAO.findById(fileId);
        } catch (SQLException e) {
            log("Could not load file metadata " + fileId, e);
            response.sendError(HttpServletResponse.SC_INTERNAL_SERVER_ERROR, "Unable to load the file information.");
            return;
        }
        if (file == null) {
            response.sendError(HttpServletResponse.SC_NOT_FOUND, "File not found.");
            return;
        }

        // ---- 3. Safe path resolution ----
        Path filePath = resolveSafely(file.getFilePath());
        if (filePath == null) {
            log("Blocked or missing file path for file id " + fileId);
            response.sendError(HttpServletResponse.SC_NOT_FOUND, "The requested file is missing on the server.");
            return;
        }

        // ---- 4. Response headers ----
        String contentType = ALLOWED_TYPES.contains(file.getFileType()) ? file.getFileType() : "application/octet-stream";
        boolean forceDownload = "true".equals(request.getParameter("download"));
        String disposition = (forceDownload || "application/octet-stream".equals(contentType)) ? "attachment" : "inline";

        response.setContentType(contentType);
        response.setContentLengthLong(Files.size(filePath));
        response.setHeader("Content-Disposition", buildDisposition(disposition, file.getOriginalName()));
        response.setHeader("X-Content-Type-Options", "nosniff");
        response.setHeader("Cache-Control", "private, max-age=300");

        // ---- 5. Stream the bytes ----
        try (InputStream in = Files.newInputStream(filePath);
             ServletOutputStream out = response.getOutputStream()) {
            byte[] buffer = new byte[BUFFER_SIZE];
            int bytesRead;
            while ((bytesRead = in.read(buffer)) != -1) {
                out.write(buffer, 0, bytesRead);
            }
            out.flush();
        }
    }

    /**
     * Turns the relative path from the database (e.g. "uploads/profiles/ab12.jpg")
     * into an absolute path. Returns null if the file does not exist or if the
     * path would leave the uploads folder (e.g. "uploads/../../windows/win.ini").
     */
    private Path resolveSafely(String relativePath) {
        if (relativePath == null || relativePath.isEmpty()) {
            return null;
        }
        try {
            Path baseDir = Paths.get(DBConnection.getUploadBaseDir()).toRealPath();
            Path uploadsDir = baseDir.resolve("uploads").toRealPath();
            Path candidate = baseDir.resolve(relativePath).normalize();

            if (!candidate.startsWith(uploadsDir) || !Files.isRegularFile(candidate)) {
                return null;
            }
            // toRealPath() also follows symbolic links – check the final target too
            Path realFile = candidate.toRealPath();
            return realFile.startsWith(uploadsDir) ? realFile : null;
        } catch (IOException | InvalidPathException e) {
            log("Upload folder or file could not be resolved: " + e.getMessage());
            return null;
        }
    }

    /** Content-Disposition with a safe ASCII name plus the UTF-8 original name (RFC 6266). */
    private static String buildDisposition(String type, String originalName) {
        String name = originalName == null ? "file" : originalName;
        String asciiName = name.replaceAll("[^A-Za-z0-9._-]", "_");
        try {
            String encoded = URLEncoder.encode(name, "UTF-8").replace("+", "%20");
            return type + "; filename=\"" + asciiName + "\"; filename*=UTF-8''" + encoded;
        } catch (UnsupportedEncodingException e) {
            return type + "; filename=\"" + asciiName + "\"";
        }
    }
}
