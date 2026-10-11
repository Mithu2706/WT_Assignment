package edu.webtech.portal.servlet;

import edu.webtech.portal.dao.StudentDao;
import edu.webtech.portal.dao.StudentFileDao;
import edu.webtech.portal.listener.AppContextListener;
import edu.webtech.portal.model.Student;
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
import java.util.HashSet;
import java.util.Optional;
import java.util.OptionalLong;
import java.util.Set;

/**
 * GET /student?id=N - one student's profile with photo and documents.
 * Handles: invalid id (400), not allowed (403), unknown id (404),
 * files whose metadata exists but whose file is missing on disk (flagged).
 */
@WebServlet("/student")
public class StudentProfileServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;

    private final StudentDao studentDao = new StudentDao();
    private final StudentFileDao fileDao = new StudentFileDao();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        User user = (User) req.getAttribute("currentUser");
        OptionalLong id = WebUtil.parseId(req.getParameter("id"));
        if (!id.isPresent()) {
            WebUtil.sendError(req, resp, 400, "Invalid student ID", "The student ID in the address must be a positive number.");
            return;
        }
        if (!user.canView(id.getAsLong())) {
            WebUtil.sendError(req, resp, 403, "Access denied", "You can only view your own profile.");
            return;
        }

        try {
            Optional<Student> found = studentDao.findById(id.getAsLong());
            if (!found.isPresent()) {
                WebUtil.sendError(req, resp, 404, "Student not found",
                        "No student record exists with ID " + id.getAsLong() + ". It may have been deleted.");
                return;
            }
            Student student = found.get();
            student.setFiles(fileDao.findByStudentId(student.getId()));

            // Mark files whose physical copy is missing so the page can say so.
            StoragePaths storage = (StoragePaths) getServletContext().getAttribute(AppContextListener.STORAGE_ATTR);
            Set<Long> missing = new HashSet<>();
            for (StudentFile f : student.getFiles()) {
                if (!storage.resolve(f.getRelativePath(), f.getStudentId()).isPresent()) {
                    missing.add(f.getId());
                }
            }
            boolean photoMissing = student.getPhotoFileId() != null && missing.contains(student.getPhotoFileId());

            req.setAttribute("student", student);
            req.setAttribute("missingFiles", missing);
            req.setAttribute("photoMissing", photoMissing);
            req.getRequestDispatcher("/WEB-INF/views/student-profile.jsp").forward(req, resp);
        } catch (DataAccessException e) {
            log("Student profile failed", e.getCause());
            WebUtil.sendError(req, resp, 503, "Service unavailable", e.getMessage());
        }
    }
}
