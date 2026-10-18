<%--
    index.jsp – welcome page of the web app (see <welcome-file-list> in web.xml).
    It simply sends the visitor to /students. StudentListServlet then decides:
    logged in → student list, not logged in → login page.
--%>
<%@ page contentType="text/html;charset=UTF-8" session="false" %>
<% response.sendRedirect(request.getContextPath() + "/students"); %>
