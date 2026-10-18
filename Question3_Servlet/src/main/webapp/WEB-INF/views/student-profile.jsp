<%--
    student-profile.jsp – one student's profile (forwarded from StudentProfileServlet).
    Request attributes:
      student    – Student
      files      – List<StudentFile> (all files: photo + documents)
      documents  – List<StudentFile> (documents only)
      imageCount – int
    Demonstrates:
      * <img> tags whose src points to the FileDownloadServlet (/file?id=N)
      * <c:forEach> loop over multiple files
      * <c:choose>/<c:when> conditional rendering by file type (image / PDF)
      * View (inline) and Download (attachment) links through the servlet
--%>
<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<%@ taglib prefix="fn" uri="http://java.sun.com/jsp/jstl/functions" %>
<%@ taglib prefix="fmt" uri="http://java.sun.com/jsp/jstl/fmt" %>
<c:set var="pageTitle" value="${student.name}" />
<%@ include file="fragments/header.jspf" %>

<p class="breadcrumb"><a href="${ctx}/students">&larr; All students</a> / <c:out value="${student.name}" /></p>

<section class="profile-header card">
    <c:choose>
        <c:when test="${student.hasPhoto}">
            <%-- The image bytes are streamed by FileDownloadServlet --%>
            <img class="profile-photo" src="${ctx}/file?id=${student.profilePhotoId}"
                 alt="Profile photo of ${fn:escapeXml(student.name)}">
        </c:when>
        <c:otherwise>
            <span class="profile-photo initials"><c:out value="${student.initials}" /></span>
        </c:otherwise>
    </c:choose>

    <div class="profile-summary">
        <h1><c:out value="${student.name}" /></h1>
        <p><span class="badge"><c:out value="${student.rollNumber}" /></span>
           <span class="muted"><c:out value="${student.course}" /></span></p>
        <div class="chips">
            <span class="chip">${fn:length(files)} file${fn:length(files) == 1 ? '' : 's'}</span>
            <span class="chip chip-green">${imageCount} image${imageCount == 1 ? '' : 's'}</span>
            <span class="chip chip-blue">${fn:length(documents)} document${fn:length(documents) == 1 ? '' : 's'}</span>
        </div>
    </div>
</section>

<div class="profile-layout">
    <section class="card">
        <h2 class="section-title">Student Details</h2>
        <dl class="detail-list">
            <dt>Student ID</dt>    <dd>#${student.id}</dd>
            <dt>Email</dt>         <dd><c:out value="${student.email}" /></dd>
            <dt>Course</dt>        <dd><c:out value="${student.course}" /></dd>
            <dt>Mobile</dt>        <dd><c:out value="${student.phone}" default="—" /></dd>
            <dt>Date of Birth</dt>
            <dd>
                <c:choose>
                    <c:when test="${not empty student.dateOfBirth}">
                        <fmt:formatDate value="${student.dateOfBirth}" pattern="dd MMM yyyy" />
                    </c:when>
                    <c:otherwise>—</c:otherwise>
                </c:choose>
            </dd>
            <dt>Address</dt>       <dd><c:out value="${student.address}" default="—" /></dd>
            <dt>Registered On</dt> <dd><fmt:formatDate value="${student.createdAt}" pattern="dd MMM yyyy, hh:mm a" /></dd>
        </dl>
    </section>

    <section class="card">
        <h2 class="section-title">Uploaded Files <span class="count-pill">${fn:length(files)}</span></h2>

        <c:if test="${empty files}">
            <p class="empty">This student has not uploaded any files yet.</p>
        </c:if>

        <div class="file-list">
            <%-- Loop over every file of the student --%>
            <c:forEach items="${files}" var="f" varStatus="loop">
                <div class="file-card">
                    <div class="file-preview">
                        <%-- Conditional rendering depending on the file type --%>
                        <c:choose>
                            <c:when test="${f.image}">
                                <img src="${ctx}/file?id=${f.id}" alt="${fn:escapeXml(f.originalName)}">
                            </c:when>
                            <c:when test="${f.pdf}">
                                <span class="file-icon pdf">PDF</span>
                            </c:when>
                            <c:otherwise>
                                <span class="file-icon">FILE</span>
                            </c:otherwise>
                        </c:choose>
                    </div>

                    <div class="file-info">
                        <span class="tag ${f.profilePhoto ? 'tag-photo' : 'tag-doc'}"><c:out value="${f.displayLabel}" /></span>
                        <strong class="file-name"><c:out value="${f.originalName}" /></strong>
                        <span class="muted small">
                            ${f.typeLabel} · ${f.formattedSize} ·
                            <fmt:formatDate value="${f.uploadDate}" pattern="dd MMM yyyy, hh:mm a" />
                        </span>
                    </div>

                    <div class="file-actions">
                        <a class="btn btn-sm btn-info" href="${ctx}/file?id=${f.id}" target="_blank" rel="noopener">View</a>
                        <a class="btn btn-sm btn-secondary" href="${ctx}/file?id=${f.id}&amp;download=true">Download</a>
                    </div>
                </div>
            </c:forEach>
        </div>
    </section>
</div>

<%@ include file="fragments/footer.jspf" %>
