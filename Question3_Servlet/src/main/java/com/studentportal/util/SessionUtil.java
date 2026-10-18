package com.studentportal.util;

import com.studentportal.model.User;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.IOException;

/**
 * SessionUtil – session handling shared by all protected servlets.
 *
 * request.getSession(false) returns the EXISTING session or null; it never
 * creates a new one. So a visitor who has not logged in has no session (or
 * a session without the "loggedInUser" attribute) and is redirected to the
 * login page.
 */
public final class SessionUtil {

    /** Name of the session attribute that holds the logged-in User. */
    public static final String USER_ATTRIBUTE = "loggedInUser";

    /** 30 minutes of inactivity ends the session. */
    public static final int SESSION_TIMEOUT_SECONDS = 30 * 60;

    private SessionUtil() {
    }

    /** The logged-in user, or null if there is no valid login session. */
    public static User getLoggedInUser(HttpServletRequest request) {
        HttpSession session = request.getSession(false);
        if (session == null) {
            return null;
        }
        Object user = session.getAttribute(USER_ATTRIBUTE);
        return user instanceof User ? (User) user : null;
    }

    /**
     * Call at the start of every protected servlet:
     * <pre>if (SessionUtil.redirectIfNotLoggedIn(request, response)) return;</pre>
     *
     * @return true when the user was NOT logged in and has been redirected
     */
    public static boolean redirectIfNotLoggedIn(HttpServletRequest request, HttpServletResponse response)
            throws IOException {
        if (getLoggedInUser(request) == null) {
            response.sendRedirect(request.getContextPath() + "/login?msg=login_required");
            return true;
        }
        // Protected pages must not be cached, so "Back" after logout cannot show them
        response.setHeader("Cache-Control", "no-store, no-cache, must-revalidate");
        response.setHeader("Pragma", "no-cache");
        response.setDateHeader("Expires", 0);
        return false;
    }
}
