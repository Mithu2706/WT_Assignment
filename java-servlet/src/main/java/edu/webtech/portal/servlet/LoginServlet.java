package edu.webtech.portal.servlet;

import edu.webtech.portal.dao.UserDao;
import edu.webtech.portal.filter.AuthFilter;
import edu.webtech.portal.model.User;
import edu.webtech.portal.util.AppConfig;
import edu.webtech.portal.util.DataAccessException;
import edu.webtech.portal.util.PasswordUtil;
import edu.webtech.portal.util.WebUtil;
import edu.webtech.portal.listener.AppContextListener;

import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.IOException;
import java.util.Optional;

/**
 * GET  /login -> sign-in form
 * POST /login -> CSRF check, bcrypt verification against the shared users
 *               table, NEW session id (prevents session fixation), redirect.
 */
@WebServlet("/login")
public class LoginServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;
    private final UserDao userDao = new UserDao();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        HttpSession existing = req.getSession(false);
        if (existing != null && existing.getAttribute(AuthFilter.USER_ATTR) != null) {
            resp.sendRedirect(req.getContextPath() + "/students");
            return;
        }
        if (req.getParameter("expired") != null) {
            req.setAttribute("notice", "Your session expired after a period of inactivity. Please sign in again.");
        } else if (req.getParameter("logged_out") != null) {
            req.setAttribute("notice", "You have been signed out.");
        } else if (req.getParameter("disabled") != null) {
            req.setAttribute("notice", "Your account is no longer active.");
        }
        showForm(req, resp, null, "");
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        String username = trim(req.getParameter("username"));
        String password = req.getParameter("password");

        if (!WebUtil.isValidCsrf(req)) {
            showForm(req, resp, "Your sign-in form expired. Please try again.", username);
            return;
        }
        if (username.isEmpty() || password == null || password.isEmpty() || username.length() > 50) {
            showForm(req, resp, "Enter your username and password.", username);
            return;
        }

        try {
            Optional<User> found = userDao.findByUsername(username);
            if (!found.isPresent()) {
                PasswordUtil.burnTime(password);            // same response time as a wrong password
                showForm(req, resp, "Invalid username or password.", username);
                return;
            }
            User user = found.get();
            if (!PasswordUtil.verify(password, user.getPasswordHash()) || !user.isActive()) {
                showForm(req, resp, "Invalid username or password.", username);
                return;
            }

            // ---- successful login ----
            HttpSession session = req.getSession();
            req.changeSessionId();                           // new session id after authentication
            session.removeAttribute(WebUtil.CSRF_ATTR);      // fresh CSRF token for the new session
            session.setAttribute(AuthFilter.USER_ATTR, user.withoutSecrets());
            AppConfig config = (AppConfig) getServletContext().getAttribute(AppContextListener.CONFIG_ATTR);
            session.setMaxInactiveInterval(config.getSessionTimeoutMinutes() * 60);
            WebUtil.csrfToken(session);
            userDao.updateLastLogin(user.getId());

            resp.sendRedirect(req.getContextPath() + safeNext(req.getParameter("next"), user));
        } catch (DataAccessException e) {
            WebUtil.sendError(req, resp, 503, "Service unavailable", e.getMessage());
        }
    }

    private void showForm(HttpServletRequest req, HttpServletResponse resp, String error, String username)
            throws ServletException, IOException {
        WebUtil.csrfToken(req.getSession());                 // pre-login token protects the form itself
        req.setAttribute("error", error);
        req.setAttribute("username", username);
        req.setAttribute("next", safeNext(req.getParameter("next"), null));
        if (error != null) {
            resp.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
        }
        req.getRequestDispatcher("/WEB-INF/views/login.jsp").forward(req, resp);
    }

    /** Only redirect to our own pages ("open redirect" protection). */
    private static String safeNext(String next, User user) {
        if (user != null && user.isStudent()) {
            return "/student?id=" + user.getStudentId();     // students go straight to their own profile
        }
        if (next != null && next.matches("^/(students|student|file|photo)?(\\?[A-Za-z0-9=&%_.-]*)?$")) {
            return next;
        }
        return "/students";
    }

    private static String trim(String s) {
        return s == null ? "" : s.trim();
    }
}
