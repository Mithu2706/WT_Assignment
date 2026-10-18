package com.studentportal.controller;

import com.studentportal.dao.StudentDAO;
import com.studentportal.model.Student;
import com.studentportal.util.SessionUtil;

import javax.servlet.ServletException;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.sql.SQLException;
import java.util.List;

/**
 * StudentListServlet – mapped to /students.
 *
 *  1. Session check – only logged-in users may continue
 *  2. Reads optional search text (?q=) and course filter (?course=)
 *  3. Fetches the students from MySQL through StudentDAO (JDBC)
 *  4. Stores the list as a request attribute and FORWARDS to students.jsp,
 *     which loops over it with <c:forEach>.
 */
public class StudentListServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;
    private static final int MAX_SEARCH_LENGTH = 100;

    private final StudentDAO studentDAO = new StudentDAO();

    @Override
    protected void doGet(HttpServletRequest request, HttpServletResponse response)
            throws ServletException, IOException {

        if (SessionUtil.redirectIfNotLoggedIn(request, response)) {
            return;
        }

        request.setCharacterEncoding("UTF-8");
        String search = clean(request.getParameter("q"));
        String course = clean(request.getParameter("course"));

        try {
            List<String> courses = studentDAO.findAllCourses();
            if (!course.isEmpty() && !courses.contains(course)) {
                course = "";                       // ignore unknown/tampered course values
            }
            List<Student> students = studentDAO.findAll(search, course);

            request.setAttribute("students", students);
            request.setAttribute("courses", courses);
            request.setAttribute("totalStudents", studentDAO.countAll());
            request.setAttribute("q", search);
            request.setAttribute("selectedCourse", course);
            request.getRequestDispatcher("/WEB-INF/views/students.jsp").forward(request, response);

        } catch (SQLException e) {
            log("Could not load the student list", e);
            response.sendError(HttpServletResponse.SC_INTERNAL_SERVER_ERROR,
                    "Unable to load students from the database. Please check that MySQL is running.");
        }
    }

    /** Trim, and cut overly long input so it cannot be abused. */
    private static String clean(String value) {
        if (value == null) {
            return "";
        }
        String trimmed = value.trim();
        return trimmed.length() > MAX_SEARCH_LENGTH ? trimmed.substring(0, MAX_SEARCH_LENGTH) : trimmed;
    }
}
