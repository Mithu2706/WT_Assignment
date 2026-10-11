<%-- Entry point: the AuthFilter has already ensured the user is signed in. --%>
<% response.sendRedirect(request.getContextPath() + "/students"); %>
