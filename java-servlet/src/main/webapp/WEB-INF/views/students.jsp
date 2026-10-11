<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<%@ taglib prefix="fn" uri="http://java.sun.com/jsp/jstl/functions" %>
<%--
    Lists students dynamically.  Demonstrates:
      * <c:forEach> loop over students, and a nested loop over each student's files
      * <c:choose>/<c:when> conditional rendering for 0, 1 or many files
      * <img> tags whose src points to the FileServlet (/photo?student=ID)
--%>
<c:set var="pageTitle" value="Student profiles"/>
<%@ include file="fragments/header.jspf" %>

<section class="page-head">
    <div>
        <p class="eyebrow">Directory</p>
        <h1>Student profiles</h1>
        <p class="muted">
            <c:choose>
                <c:when test="${total == 1}">1 student</c:when>
                <c:otherwise><c:out value="${total}"/> students</c:otherwise>
            </c:choose>
            <c:if test="${not empty q}"> matching “<c:out value="${q}"/>”</c:if>
        </p>
    </div>
    <form class="search" method="get" action="${ctx}/students" role="search">
        <label for="q" class="visually-hidden">Search students</label>
        <input type="search" id="q" name="q" value="<c:out value='${q}'/>" maxlength="100" placeholder="Search name, roll number or course">
        <button type="submit" class="btn btn-primary">Search</button>
        <c:if test="${not empty q}"><a class="link-muted" href="${ctx}/students">Clear</a></c:if>
    </form>
</section>

<c:choose>
    <c:when test="${empty students}">
        <div class="card empty">
            <p class="empty-title">
                <c:choose>
                    <c:when test="${not empty q}">No students match your search</c:when>
                    <c:otherwise>No student records yet</c:otherwise>
                </c:choose>
            </p>
            <p>Records added in the PHP admin panel or through online registration appear here automatically.</p>
        </div>
    </c:when>
    <c:otherwise>
        <div class="student-grid">
            <c:forEach var="s" items="${students}">
                <article class="card student-card">
                    <header class="student-card-head">
                        <c:choose>
                            <c:when test="${s.photoFileId != null}">
                                <img class="avatar avatar-lg" src="${ctx}/photo?student=${s.id}"
                                     alt="Profile photo of ${fn:escapeXml(s.fullName)}" width="64" height="64" loading="lazy">
                            </c:when>
                            <c:otherwise>
                                <span class="avatar avatar-lg" aria-hidden="true"><c:out value="${s.initials}"/></span>
                            </c:otherwise>
                        </c:choose>
                        <div class="student-card-title">
                            <h2><a href="${ctx}/student?id=${s.id}"><c:out value="${s.fullName}"/></a></h2>
                            <p><code><c:out value="${s.rollNumber}"/></code></p>
                        </div>
                    </header>
                    <p class="course"><c:out value="${s.courseName}"/></p>

                    <div class="file-summary">
                        <c:choose>
                            <c:when test="${s.fileCount == 0}">
                                <p class="no-files">No files uploaded yet</p>
                            </c:when>
                            <c:when test="${s.fileCount == 1}">
                                <p class="files-label">1 file</p>
                            </c:when>
                            <c:otherwise>
                                <p class="files-label"><c:out value="${s.fileCount}"/> files</p>
                            </c:otherwise>
                        </c:choose>
                        <c:if test="${s.fileCount > 0}">
                            <ul class="chips">
                                <c:forEach var="f" items="${s.files}" varStatus="loop">
                                    <c:if test="${loop.index < 3}">
                                        <li><a class="chip ${f.pdf ? 'chip-pdf' : 'chip-img'}" href="${ctx}/file?id=${f.id}" target="_blank" rel="noopener"
                                               title="${fn:escapeXml(f.originalName)}"><c:out value="${f.categoryLabel}"/></a></li>
                                    </c:if>
                                </c:forEach>
                                <c:if test="${s.fileCount > 3}">
                                    <li class="chip chip-more">+<c:out value="${s.fileCount - 3}"/> more</li>
                                </c:if>
                            </ul>
                        </c:if>
                    </div>

                    <footer class="student-card-foot">
                        <span class="muted small">Added <c:out value="${s.createdDisplay}"/></span>
                        <a class="btn btn-ghost btn-sm" href="${ctx}/student?id=${s.id}">View profile →</a>
                    </footer>
                </article>
            </c:forEach>
        </div>

        <c:if test="${totalPages > 1}">
            <%-- c:url + c:param URL-encode the search text; c:out then HTML-escapes the result --%>
            <c:url var="prevUrl" value="/students"><c:param name="page" value="${page - 1}"/><c:param name="q" value="${q}"/></c:url>
            <c:url var="nextUrl" value="/students"><c:param name="page" value="${page + 1}"/><c:param name="q" value="${q}"/></c:url>
            <nav class="pagination" aria-label="Pagination">
                <c:if test="${page > 1}">
                    <a href="<c:out value='${prevUrl}'/>" rel="prev">← Previous</a>
                </c:if>
                <span>Page <c:out value="${page}"/> of <c:out value="${totalPages}"/></span>
                <c:if test="${page < totalPages}">
                    <a href="<c:out value='${nextUrl}'/>" rel="next">Next →</a>
                </c:if>
            </nav>
        </c:if>
    </c:otherwise>
</c:choose>

<%@ include file="fragments/footer.jspf" %>
