<%--
    error.jsp – friendly error page for 400/403/404/405/500 and uncaught
    exceptions (configured with <error-page> in web.xml).
    The status code and the message passed to response.sendError(...) are
    shown; for real exceptions only a generic message is displayed, so no
    stack trace or internal detail ever reaches the browser.
--%>
<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" isErrorPage="true" %>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<c:set var="status" value="${requestScope['javax.servlet.error.status_code']}" />
<c:set var="message" value="${requestScope['javax.servlet.error.message']}" />
<c:set var="pageTitle" value="Error ${status}" />
<%@ include file="fragments/header.jspf" %>

<section class="card error-card">
    <div class="error-code"><c:out value="${status}" default="Error" /></div>
    <c:choose>
        <c:when test="${not empty pageContext.exception or status == 500}">
            <h1>Something went wrong</h1>
            <p class="muted">
                <c:choose>
                    <c:when test="${empty pageContext.exception and not empty message}"><c:out value="${message}" /></c:when>
                    <c:otherwise>An unexpected error occurred. Please try again later.</c:otherwise>
                </c:choose>
            </p>
        </c:when>
        <c:when test="${status == 404}">
            <h1>Not found</h1>
            <p class="muted"><c:out value="${empty message ? 'The page or file you requested does not exist.' : message}" /></p>
        </c:when>
        <c:otherwise>
            <h1>Request could not be completed</h1>
            <p class="muted"><c:out value="${empty message ? 'The request was not valid.' : message}" /></p>
        </c:otherwise>
    </c:choose>
    <a class="btn btn-primary" href="${ctx}/students">Back to Students</a>
</section>

<%@ include file="fragments/footer.jspf" %>
