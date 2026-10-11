package edu.webtech.portal.servlet;

import edu.webtech.portal.dao.StudentDao;
import edu.webtech.portal.dao.StudentFileDao;
import edu.webtech.portal.model.Student;
import edu.webtech.portal.model.StudentFile;
import edu.webtech.portal.model.User;
import edu.webtech.portal.util.DataAccessException;
import edu.webtech.portal.util.WebUtil;

import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Map;

/**
 * GET /students?q=&page= - lists students with their files.
 * Data: StudentDao (one page) + StudentFileDao (files of all those students
 * in a single query).  View: WEB-INF/views/students.jsp loops over the list.
 */
@WebServlet("/students")
public class StudentListServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;
    private static final int PAGE_SIZE = 9;

    private final StudentDao studentDao = new StudentDao();
    private final StudentFileDao fileDao = new StudentFileDao();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        User user = (User) req.getAttribute("currentUser");
        if (user.isStudent()) {
            // Students may only see their own profile.
            resp.sendRedirect(req.getContextPath() + "/student?id=" + user.getStudentId());
            return;
        }

        String q = req.getParameter("q");
        q = q == null ? "" : q.trim();
        if (q.length() > 100) {
            q = q.substring(0, 100);
        }
        int page = (int) WebUtil.parseId(req.getParameter("page")).orElse(1);

        try {
            int total = studentDao.count(q);
            int totalPages = Math.max(1, (int) Math.ceil(total / (double) PAGE_SIZE));
            page = Math.min(page, totalPages);
            List<Student> students = studentDao.findPage(q, page, PAGE_SIZE);

            List<Long> ids = new ArrayList<>();
            for (Student s : students) {
                ids.add(s.getId());
            }
            Map<Long, List<StudentFile>> files = fileDao.findByStudentIds(ids);
            for (Student s : students) {
                s.setFiles(files.getOrDefault(s.getId(), Collections.emptyList()));
            }

            req.setAttribute("students", students);
            req.setAttribute("q", q);
            req.setAttribute("total", total);
            req.setAttribute("page", page);
            req.setAttribute("totalPages", totalPages);
            req.getRequestDispatcher("/WEB-INF/views/students.jsp").forward(req, resp);
        } catch (DataAccessException e) {
            log("Student list failed", e.getCause());
            WebUtil.sendError(req, resp, 503, "Service unavailable", e.getMessage());
        }
    }
}
