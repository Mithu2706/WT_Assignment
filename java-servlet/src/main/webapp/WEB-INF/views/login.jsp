<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<%--
    Sign-in page, rendered by LoginServlet (GET /login).
    - csrf_token: hidden token checked by LoginServlet before the password is verified
    - next: the page to return to after login (validated again on the server)
    - notice/error/username are printed with <c:out> so they are HTML-escaped
--%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Student Profile Portal</title>
    <link rel="icon" href="${ctx}/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="${ctx}/assets/css/portal.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="auth-brand">
        <img src="${ctx}/assets/img/favicon.svg" alt="" width="44" height="44">
        <div>
            <p class="eyebrow">Student Profile Portal</p>
            <h1>Sign in</h1>
        </div>
    </div>
    <p class="auth-intro">Staff and students can view profiles and documents here. Use the same account as the admin panel, or your roll number if you registered online.</p>

    <c:if test="${not empty notice}">
        <div class="alert alert-info" role="status"><c:out value="${notice}"/></div>
    </c:if>
    <c:if test="${not empty error}">
        <div class="alert alert-error" role="alert"><c:out value="${error}"/></div>
    </c:if>

    <form method="post" action="${ctx}/login" novalidate>
        <input type="hidden" name="csrf_token" value="<c:out value='${sessionScope.csrfToken}'/>">
        <input type="hidden" name="next" value="<c:out value='${next}'/>">
        <div class="field">
            <label for="username">Username or roll number</label>
            <input type="text" id="username" name="username" value="<c:out value='${username}'/>"
                   required maxlength="50" autocomplete="username" autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required maxlength="72" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>
</main>
</body>
</html>
