package edu.webtech.portal.filter;

import edu.webtech.portal.dao.UserDao;
import edu.webtech.portal.model.User;
import edu.webtech.portal.util.DataAccessException;
import edu.webtech.portal.util.DatabaseUtil;
import edu.webtech.portal.util.WebUtil;

import javax.servlet.Filter;
import javax.servlet.FilterChain;
import javax.servlet.FilterConfig;
import javax.servlet.ServletException;
import javax.servlet.ServletRequest;
import javax.servlet.ServletResponse;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.IOException;
import java.net.URLEncoder;
import java.util.Optional;

/**
 * Protects EVERY URL of the portal except the login page and static assets.
 *
 *  - no logged-in user in the session  -> pages redirect to /login,
 *    files/images answer 401 (an <img> cannot follow a login redirect)
 *  - expired session (cookie sent but no longer valid) -> "session expired" notice
 *  - the account is re-checked in the database on each request, so a
 *    deactivated user is thrown out immediately
 */
public class AuthFilter implements Filter {

    public static final String USER_ATTR = "authUser";
    private final UserDao userDao = new UserDao();

    @Override
    public void init(FilterConfig filterConfig) {
    }

    @Override
    public void doFilter(ServletRequest request, ServletResponse response, FilterChain chain)
            throws IOException, ServletException {
        HttpServletRequest req = (HttpServletRequest) request;
        HttpServletResponse resp = (HttpServletResponse) response;
        String path = req.getRequestURI().substring(req.getContextPath().length());

        if (path.startsWith("/assets/") || path.equals("/favicon.ico")) {
            chain.doFilter(request, response);
            return;
        }
        if (!DatabaseUtil.isConfigured()) {
            WebUtil.sendError(req, resp, 503, "Portal not configured",
                    "The portal's configuration file could not be loaded. Ask the administrator to set SMP_CONFIG (see docs/SETUP.md).");
            return;
        }
        if (path.equals("/login")) {
            chain.doFilter(request, response);
            return;
        }

        HttpSession session = req.getSession(false);
        User user = session == null ? null : (User) session.getAttribute(USER_ATTR);
        boolean isFileRequest = path.equals("/file") || path.equals("/photo");

        if (user == null) {
            boolean expired = req.getRequestedSessionId() != null && !req.isRequestedSessionIdValid();
            if (isFileRequest) {
                WebUtil.sendError(req, resp, 401, "Sign-in required", "You must be signed in to view student files.");
                return;
            }
            String next = path + (req.getQueryString() == null ? "" : "?" + req.getQueryString());
            String target = req.getContextPath() + "/login?next=" + URLEncoder.encode(next, "UTF-8")
                    + (expired ? "&expired=1" : "");
            resp.sendRedirect(target);
            return;
        }

        try {
            // Re-validate the account against the database.
            Optional<User> fresh = userDao.findById(user.getId());
            if (!fresh.isPresent() || !fresh.get().isActive() || !fresh.get().getRole().equals(user.getRole())) {
                session.invalidate();
                resp.sendRedirect(req.getContextPath() + "/login?disabled=1");
                return;
            }
        } catch (DataAccessException e) {
            WebUtil.sendError(req, resp, 503, "Service unavailable", e.getMessage());
            return;
        }

        WebUtil.csrfToken(session);         // make sure forms (logout) have a token
        req.setAttribute("currentUser", user);
        chain.doFilter(request, response);
    }

    @Override
    public void destroy() {
    }
}
