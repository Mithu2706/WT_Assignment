<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" isErrorPage="true" %>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<%--
    Shared error page.  Used both by servlets (WebUtil.sendError sets
    errorStatus / errorTitle / errorMessage) and by the container (web.xml
    <error-page>).  It NEVER prints the exception or stack trace - those are
    written to Tomcat's log only.
--%>
<c:set var="ctx" value="${pageContext.request.contextPath}"/>
<c:set var="status" value="${not empty errorStatus ? errorStatus : pageContext.errorData.statusCode}"/>
<c:if test="${status == 0}"><c:set var="status" value="500"/></c:if>
<c:choose>
    <c:when test="${not empty errorTitle}"><c:set var="title" value="${errorTitle}"/><c:set var="message" value="${errorMessage}"/></c:when>
    <c:when test="${status == 404}"><c:set var="title" value="Page not found"/><c:set var="message" value="The page you asked for does not exist."/></c:when>
    <c:when test="${status == 403}"><c:set var="title" value="Access denied"/><c:set var="message" value="You do not have permission to view this page."/></c:when>
    <c:when test="${status == 401}"><c:set var="title" value="Sign-in required"/><c:set var="message" value="Please sign in to continue."/></c:when>
    <c:when test="${status == 405}"><c:set var="title" value="Method not allowed"/><c:set var="message" value="This address does not accept that kind of request."/></c:when>
    <c:otherwise><c:set var="title" value="Something went wrong"/><c:set var="message" value="An unexpected error occurred. It has been logged; please try again."/></c:otherwise>
</c:choose>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><c:out value="${title}"/> · Student Profile Portal</title>
    <link rel="stylesheet" href="${ctx}/assets/css/portal.css">
</head>
<body class="auth-page">
<main class="auth-card error-card">
    <p class="error-code"><c:out value="${status}"/></p>
    <h1><c:out value="${title}"/></h1>
    <p><c:out value="${message}"/></p>
    <div class="error-actions">
        <c:choose>
            <c:when test="${not empty sessionScope.authUser}">
                <a class="btn btn-primary" href="${ctx}/students">Back to the portal</a>
            </c:when>
            <c:otherwise>
                <a class="btn btn-primary" href="${ctx}/login">Go to sign in</a>
            </c:otherwise>
        </c:choose>
    </div>
</main>
</body>
</html>
