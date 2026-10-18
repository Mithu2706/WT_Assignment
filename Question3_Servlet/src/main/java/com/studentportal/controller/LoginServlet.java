package com.studentportal.controller;

import com.studentportal.dao.UserDAO;
import com.studentportal.model.User;
import com.studentportal.util.SessionUtil;

import javax.servlet.ServletException;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.IOException;
import java.sql.SQLException;

/**
 * LoginServlet – mapped to /login in WEB-INF/web.xml.
 *
 *  GET  → shows login.jsp (with an info message if the user was redirected here)
 *  POST → validates the input, checks username/password in the users table
 *         through JDBC (UserDAO), creates an HttpSession and redirects to /students.
 */
public class LoginServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;
    private static final String LOGIN_VIEW = "/WEB-INF/views/login.jsp";

    private final UserDAO userDAO = new UserDAO();

    @Override
    protected void doGet(HttpServletRequest request, HttpServletResponse response)
            throws ServletException, IOException {

        // Already logged in? Go straight to the portal.
        if (SessionUtil.getLoggedInUser(request) != null) {
            response.sendRedirect(request.getContextPath() + "/students");
            return;
        }

        // Friendly message depending on why the user arrived here
        String msg = request.getParameter("msg");
        if ("login_required".equals(msg)) {
            request.setAttribute("info", "Please log in to access the student portal.");
        } else if ("logged_out".equals(msg)) {
            request.setAttribute("success", "You have been logged out successfully.");
        }
        request.getRequestDispatcher(LOGIN_VIEW).forward(request, response);
    }

    @Override
    protected void doPost(HttpServletRequest request, HttpServletResponse response)
            throws ServletException, IOException {

        request.setCharacterEncoding("UTF-8");
        String username = trim(request.getParameter("username"));
        String password = request.getParameter("password") == null ? "" : request.getParameter("password");

        // ---- 1. Input validation ----
        String error = null;
        if (username.isEmpty() || password.isEmpty()) {
            error = "Please enter both username and password.";
        } else if (username.length() > 50 || password.length() > 72) {
            error = "Invalid username or password.";
        }

        // ---- 2. Check credentials in MySQL ----
        User user = null;
        if (error == null) {
            try {
                user = userDAO.authenticate(username, password);
                if (user == null) {
                    // Same message for unknown user and wrong password
                    error = "Invalid username or password.";
                }
            } catch (SQLException e) {
                log("Login failed because of a database error", e);   // details go to the Tomcat log only
                error = "Unable to connect to the database. Please try again later.";
            }
        }

        if (error != null) {
            request.setAttribute("error", error);
            request.setAttribute("username", username);
            request.getRequestDispatcher(LOGIN_VIEW).forward(request, response);
            return;
        }

        // ---- 3. Create the login session ----
        // Throw away any old session first (prevents session-fixation attacks)
        HttpSession oldSession = request.getSession(false);
        if (oldSession != null) {
            oldSession.invalidate();
        }
        HttpSession session = request.getSession(true);
        session.setAttribute(SessionUtil.USER_ATTRIBUTE, user);
        session.setMaxInactiveInterval(SessionUtil.SESSION_TIMEOUT_SECONDS);

        // Post/Redirect/Get: refreshing the next page will not re-submit the password
        response.sendRedirect(request.getContextPath() + "/students");
    }

    private static String trim(String value) {
        return value == null ? "" : value.trim();
    }
}
