<%--
    login.jsp – login form of the Servlet portal.
    Shown by LoginServlet (GET, or POST with an error). It lives in WEB-INF,
    so it cannot be opened directly – only through /login.
    Request attributes: error, info, success, username.
--%>
<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" session="false" %>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<c:set var="ctx" value="${pageContext.request.contextPath}" />
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Student Profile Portal</title>
    <link rel="stylesheet" href="${ctx}/css/servlet.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="auth-header">
        <span class="brand-logo large" aria-hidden="true">SP</span>
        <h1>Student Profile Portal</h1>
        <p>Sign in to view student profiles and documents</p>
    </div>

    <%-- Conditional messages: only the ones that are set are rendered --%>
    <c:if test="${not empty info}">
        <div class="alert alert-info" role="status"><c:out value="${info}" /></div>
    </c:if>
    <c:if test="${not empty success}">
        <div class="alert alert-success" role="status"><c:out value="${success}" /></div>
    </c:if>
    <c:if test="${not empty error}">
        <div class="alert alert-error" role="alert"><c:out value="${error}" /></div>
    </c:if>

    <form method="post" action="${ctx}/login">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="50"
                   value="<c:out value='${username}' />" autocomplete="username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="72"
                   autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>

    <p class="auth-footer">Session-protected portal · Apache Tomcat</p>
</div>
</body>
</html>
