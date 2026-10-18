package com.studentportal.controller;

import com.studentportal.dao.FileDAO;
import com.studentportal.dao.StudentDAO;
import com.studentportal.model.Student;
import com.studentportal.model.StudentFile;
import com.studentportal.util.SessionUtil;

import javax.servlet.ServletException;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

/**
 * StudentProfileServlet – mapped to /student?id=N.
 *
 *  1. Session check
 *  2. Validates the id parameter (must be a positive whole number)
 *  3. Loads the student (StudentDAO) and all of the student's files (FileDAO)
 *  4. Splits the files into photos and documents for the JSP
 *  5. Forwards to student-profile.jsp
 */
public class StudentProfileServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;

    private final StudentDAO studentDAO = new StudentDAO();
    private final FileDAO fileDAO = new FileDAO();

    @Override
    protected void doGet(HttpServletRequest request, HttpServletResponse response)
            throws ServletException, IOException {

        if (SessionUtil.redirectIfNotLoggedIn(request, response)) {
            return;
        }

        Integer studentId = parsePositiveInt(request.getParameter("id"));
        if (studentId == null) {
            response.sendError(HttpServletResponse.SC_BAD_REQUEST, "Invalid student ID.");
            return;
        }

        try {
            Student student = studentDAO.findById(studentId);
            if (student == null) {
                response.sendError(HttpServletResponse.SC_NOT_FOUND,
                        "Student not found. The record may have been deleted.");
                return;
            }

            List<StudentFile> files = fileDAO.findByStudentId(studentId);
            List<StudentFile> documents = new ArrayList<>();
            int imageCount = 0;
            for (StudentFile file : files) {
                if (file.isImage()) {
                    imageCount++;
                }
                if (!file.isProfilePhoto()) {
                    documents.add(file);
                }
            }

            request.setAttribute("student", student);
            request.setAttribute("files", files);
            request.setAttribute("documents", documents);
            request.setAttribute("imageCount", imageCount);
            request.getRequestDispatcher("/WEB-INF/views/student-profile.jsp").forward(request, response);

        } catch (SQLException e) {
            log("Could not load student profile " + studentId, e);
            response.sendError(HttpServletResponse.SC_INTERNAL_SERVER_ERROR,
                    "Unable to load the student profile from the database.");
        }
    }

    /** Returns the number, or null for missing / non-numeric / zero / negative input. */
    static Integer parsePositiveInt(String value) {
        if (value == null || !value.matches("\\d{1,9}")) {
            return null;
        }
        int number = Integer.parseInt(value);
        return number > 0 ? number : null;
    }
}
