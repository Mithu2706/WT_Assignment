package edu.webtech.portal.util;

import javax.servlet.ServletException;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.IOException;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.util.Base64;
import java.util.OptionalLong;

/**
 * Small helpers shared by the servlets: id parsing, CSRF tokens,
 * Content-Disposition headers and the common error page.
 */
public final class WebUtil {

    public static final String CSRF_ATTR = "csrfToken";
    private static final SecureRandom RANDOM = new SecureRandom();

    private WebUtil() {
    }

    /**
     * Parse a positive database id from a request parameter.
     * "abc", "-1", "0", "1e3", "99999999999" and missing values -> empty.
     */
    public static OptionalLong parseId(String raw) {
        if (raw == null || !raw.matches("^[1-9][0-9]{0,9}$")) {
            return OptionalLong.empty();
        }
        long value = Long.parseLong(raw);
        return value <= 4_294_967_295L ? OptionalLong.of(value) : OptionalLong.empty();  // INT UNSIGNED max
    }

    // ------------------------------------------------------------------ CSRF

    /** Returns the session's CSRF token, creating it on first use. */
    public static String csrfToken(HttpSession session) {
        Object existing = session.getAttribute(CSRF_ATTR);
        if (existing instanceof String) {
            return (String) existing;
        }
        byte[] bytes = new byte[32];
        RANDOM.nextBytes(bytes);
        String token = Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
        session.setAttribute(CSRF_ATTR, token);
        return token;
    }

    /** Constant-time comparison of the submitted token with the session token. */
    public static boolean isValidCsrf(HttpServletRequest request) {
        HttpSession session = request.getSession(false);
        if (session == null) {
            return false;
        }
        Object expected = session.getAttribute(CSRF_ATTR);
        String submitted = request.getParameter("csrf_token");
        if (!(expected instanceof String) || submitted == null) {
            return false;
        }
        return MessageDigest.isEqual(((String) expected).getBytes(StandardCharsets.UTF_8),
                submitted.getBytes(StandardCharsets.UTF_8));
    }

    // ------------------------------------------------------- file downloads

    /**
     * Builds a safe Content-Disposition header value:
     *  - ASCII fallback name with every unusual character replaced by "_"
     *    (no quotes, CR/LF or path separators can reach the header);
     *  - RFC 5987 filename* with the full UTF-8 name, percent-encoded.
     */
    public static String contentDisposition(String originalName, boolean attachment) {
        String name = (originalName == null || originalName.trim().isEmpty()) ? "file" : originalName.trim();
        String ascii = name.replaceAll("[^A-Za-z0-9 ._()-]", "_");
        String encoded;
        try {
            encoded = URLEncoder.encode(name, "UTF-8").replace("+", "%20");
        } catch (java.io.UnsupportedEncodingException e) {
            encoded = ascii;
        }
        return (attachment ? "attachment" : "inline") + "; filename=\"" + ascii + "\"; filename*=UTF-8''" + encoded;
    }

    // ------------------------------------------------------------ errors

    /** Render WEB-INF/views/error.jsp with an HTTP status and a safe message. */
    public static void sendError(HttpServletRequest req, HttpServletResponse resp, int status,
                                 String title, String message) throws ServletException, IOException {
        if (resp.isCommitted()) {
            return;
        }
        resp.resetBuffer();
        resp.setStatus(status);
        req.setAttribute("errorStatus", status);
        req.setAttribute("errorTitle", title);
        req.setAttribute("errorMessage", message);
        req.getRequestDispatcher("/WEB-INF/views/error.jsp").forward(req, resp);
    }
}
