package edu.webtech.portal.servlet;

import edu.webtech.portal.util.WebUtil;

import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.IOException;

/**
 * POST /logout - CSRF-protected; invalidates the session on the server so the
 * old JSESSIONID cookie becomes useless, then returns to the login page.
 */
@WebServlet("/logout")
public class LogoutServlet extends HttpServlet {

    private static final long serialVersionUID = 1L;

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        resp.setHeader("Allow", "POST");
        WebUtil.sendError(req, resp, 405, "Method not allowed", "Use the Sign out button to log out.");
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        if (!WebUtil.isValidCsrf(req)) {
            WebUtil.sendError(req, resp, 403, "Request rejected",
                    "The sign-out request could not be verified (security token mismatch). Reload the page and try again.");
            return;
        }
        HttpSession session = req.getSession(false);
        if (session != null) {
            session.invalidate();
        }
        resp.sendRedirect(req.getContextPath() + "/login?logged_out=1");
    }
}
