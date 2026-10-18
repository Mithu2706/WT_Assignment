<%--
    students.jsp – list of ALL students (forwarded from StudentListServlet).
    Request attributes:
      students       – List<Student>   (looped with <c:forEach>)
      courses        – List<String>    (course filter drop-down)
      totalStudents  – int
      q / selectedCourse – current search values
    Each card shows the profile photo through <img src=".../file?id=N">, i.e.
    the image is STREAMED by FileDownloadServlet; when a student has no photo
    the initials are shown instead (conditional logic with <c:choose>).
--%>
<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<%@ taglib prefix="fn" uri="http://java.sun.com/jsp/jstl/functions" %>
<c:set var="pageTitle" value="Students" />
<%@ include file="fragments/header.jspf" %>

<div class="page-header">
    <div>
        <h1>Student Profiles</h1>
        <p class="muted">Showing ${fn:length(students)} of ${totalStudents} students · data loaded live from MySQL via JDBC</p>
    </div>
</div>

<%-- Search / filter (GET request back to the same servlet) --%>
<form method="get" action="${ctx}/students" class="filter-bar card">
    <input type="search" name="q" value="<c:out value='${q}' />" maxlength="100"
           placeholder="Search by name, roll number or email" aria-label="Search students">
    <select name="course" aria-label="Filter by course">
        <option value="">All courses</option>
        <c:forEach items="${courses}" var="course">
            <option value="<c:out value='${course}' />" ${course eq selectedCourse ? 'selected' : ''}>
                <c:out value="${course}" />
            </option>
        </c:forEach>
    </select>
    <button type="submit" class="btn btn-primary">Search</button>
    <c:if test="${not empty q or not empty selectedCourse}">
        <a href="${ctx}/students" class="btn btn-secondary">Clear</a>
    </c:if>
</form>

<c:choose>
    <%-- No results --%>
    <c:when test="${empty students}">
        <div class="card empty-state">
            <h2>No students found</h2>
            <p class="muted">Try a different search, or add students in the PHP admin panel.</p>
        </div>
    </c:when>

    <%-- Loop over every student --%>
    <c:otherwise>
        <section class="student-grid">
            <c:forEach items="${students}" var="s">
                <article class="student-card">
                    <c:choose>
                        <c:when test="${s.hasPhoto}">
                            <img class="avatar-lg" src="${ctx}/file?id=${s.profilePhotoId}"
                                 alt="Photo of ${fn:escapeXml(s.name)}">
                        </c:when>
                        <c:otherwise>
                            <span class="avatar-lg initials"><c:out value="${s.initials}" /></span>
                        </c:otherwise>
                    </c:choose>

                    <h2><c:out value="${s.name}" /></h2>
                    <span class="badge"><c:out value="${s.rollNumber}" /></span>
                    <p class="course"><c:out value="${s.course}" /></p>
                    <p class="email"><c:out value="${s.email}" /></p>

                    <p class="file-summary">
                        <c:choose>
                            <c:when test="${s.fileCount == 0}">
                                <span class="muted">No files uploaded</span>
                            </c:when>
                            <c:otherwise>
                                <span class="chip">${s.fileCount} file${s.fileCount == 1 ? '' : 's'}</span>
                                <c:if test="${s.hasPhoto}"><span class="chip chip-green">Photo</span></c:if>
                                <c:if test="${s.documentCount > 0}">
                                    <span class="chip chip-blue">${s.documentCount} document${s.documentCount == 1 ? '' : 's'}</span>
                                </c:if>
                            </c:otherwise>
                        </c:choose>
                    </p>

                    <a class="btn btn-primary btn-block" href="${ctx}/student?id=${s.id}">View Profile</a>
                </article>
            </c:forEach>
        </section>
    </c:otherwise>
</c:choose>

<%@ include file="fragments/footer.jspf" %>
