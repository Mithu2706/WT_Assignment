package edu.webtech.portal.filter;

import javax.servlet.Filter;
import javax.servlet.FilterChain;
import javax.servlet.FilterConfig;
import javax.servlet.ServletException;
import javax.servlet.ServletRequest;
import javax.servlet.ServletResponse;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;

/**
 * First filter in the chain (see web.xml):
 *  - decodes request parameters as UTF-8
 *  - adds browser security headers to every response
 *  - stops browsers caching protected pages (Back after logout shows nothing)
 */
public class SecurityHeadersFilter implements Filter {

    private static final String CSP = "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            + "object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";

    @Override
    public void init(FilterConfig filterConfig) {
    }

    @Override
    public void doFilter(ServletRequest request, ServletResponse response, FilterChain chain)
            throws IOException, ServletException {
        HttpServletRequest req = (HttpServletRequest) request;
        HttpServletResponse resp = (HttpServletResponse) response;
        req.setCharacterEncoding("UTF-8");

        String path = req.getRequestURI().substring(req.getContextPath().length());
        resp.setHeader("X-Content-Type-Options", "nosniff");
        resp.setHeader("X-Frame-Options", "DENY");
        resp.setHeader("Referrer-Policy", "same-origin");
        if (path.startsWith("/assets/")) {
            resp.setHeader("Cache-Control", "public, max-age=3600");
        } else {
            resp.setHeader("Cache-Control", "no-store");
            if (!path.equals("/file") && !path.equals("/photo")) {
                // Not set on file responses: a CSP there can stop the browser's PDF viewer.
                resp.setHeader("Content-Security-Policy", CSP);
            }
        }
        chain.doFilter(request, response);
    }

    @Override
    public void destroy() {
    }
}
