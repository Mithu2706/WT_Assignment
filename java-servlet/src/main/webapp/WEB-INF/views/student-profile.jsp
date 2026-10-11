<%@ page contentType="text/html;charset=UTF-8" pageEncoding="UTF-8" %>
<%@ taglib prefix="c" uri="http://java.sun.com/jsp/jstl/core" %>
<%@ taglib prefix="fn" uri="http://java.sun.com/jsp/jstl/functions" %>
<%--
    One student's profile.  The photo <img> and every document link point to
    the FileServlet, which streams the file from private storage after
    checking the session and the user's permission.
--%>
<c:set var="pageTitle" value="${student.fullName}"/>
<%@ include file="fragments/header.jspf" %>

<c:if test="${not currentUser.student}">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="${ctx}/students">All students</a> <span aria-hidden="true">/</span> <c:out value="${student.fullName}"/></nav>
</c:if>

<section class="card profile-hero">
    <div class="profile-photo">
        <c:choose>
            <c:when test="${student.photoFileId != null and not photoMissing}">
                <img src="${ctx}/photo?student=${student.id}" alt="Profile photo of ${fn:escapeXml(student.fullName)}" width="140" height="140">
            </c:when>
            <c:otherwise>
                <span class="avatar avatar-xl" aria-hidden="true"><c:out value="${student.initials}"/></span>
            </c:otherwise>
        </c:choose>
    </div>
    <div class="profile-main">
        <p class="eyebrow"><c:out value="${student.courseName}"/></p>
        <h1><c:out value="${student.fullName}"/></h1>
        <dl class="facts">
            <div><dt>Student ID</dt><dd><c:out value="${student.id}"/></dd></div>
            <div><dt>Roll number</dt><dd><code><c:out value="${student.rollNumber}"/></code></dd></div>
            <div><dt>Email</dt><dd><c:out value="${student.email}"/></dd></div>
            <div><dt>Registered</dt><dd><c:out value="${student.createdDateTimeDisplay}"/></dd></div>
            <div><dt>Last updated</dt><dd><c:out value="${student.updatedDisplay}"/></dd></div>
            <div><dt>Source</dt><dd>${student.selfRegistered ? 'Online registration' : 'Admin office'}</dd></div>
        </dl>
    </div>
</section>

<div class="profile-columns">
    <section class="card panel">
        <h2 class="panel-title">Documents <span class="count"><c:out value="${fn:length(student.documents)}"/></span></h2>
        <c:choose>
            <c:when test="${empty student.documents}">
                <div class="empty compact">
                    <p class="empty-title">No documents</p>
                    <p>ID proofs, certificates and marksheets uploaded by the admin office will appear here.</p>
                </div>
            </c:when>
            <c:otherwise>
                <ul class="doc-list">
                    <c:forEach var="d" items="${student.documents}">
                        <c:set var="isMissing" value="${missingFiles.contains(d.id)}"/>
                        <li class="${isMissing ? 'is-missing' : ''}">
                            <span class="doc-icon ${d.pdf ? 'pdf' : 'img'}" aria-hidden="true">${d.pdf ? 'PDF' : 'IMG'}</span>
                            <div class="doc-body">
                                <strong><c:out value="${d.originalName}"/></strong>
                                <small><c:out value="${d.categoryLabel}"/> · <c:out value="${d.sizeDisplay}"/> · <c:out value="${d.uploadedDisplay}"/></small>
                                <c:if test="${isMissing}"><small class="text-danger">File missing from storage</small></c:if>
                            </div>
                            <c:if test="${not isMissing}">
                                <div class="doc-actions">
                                    <a class="btn btn-ghost btn-sm" href="${ctx}/file?id=${d.id}" target="_blank" rel="noopener">View</a>
                                    <a class="btn btn-primary btn-sm" href="${ctx}/file?id=${d.id}&amp;download=1">Download</a>
                                </div>
                            </c:if>
                        </li>
                    </c:forEach>
                </ul>
            </c:otherwise>
        </c:choose>
    </section>

    <section class="card panel">
        <h2 class="panel-title">Images <span class="count"><c:out value="${fn:length(student.images)}"/></span></h2>
        <c:choose>
            <c:when test="${empty student.images}">
                <div class="empty compact"><p class="empty-title">No images</p><p>This student has no photo or scanned images yet.</p></div>
            </c:when>
            <c:otherwise>
                <div class="gallery">
                    <c:forEach var="img" items="${student.images}">
                        <figure>
                            <c:choose>
                                <c:when test="${missingFiles.contains(img.id)}">
                                    <div class="gallery-missing">File missing</div>
                                </c:when>
                                <c:otherwise>
                                    <a href="${ctx}/file?id=${img.id}" target="_blank" rel="noopener">
                                        <img src="${ctx}/file?id=${img.id}" alt="${fn:escapeXml(img.categoryLabel)} of ${fn:escapeXml(student.fullName)}" loading="lazy">
                                    </a>
                                </c:otherwise>
                            </c:choose>
                            <figcaption><c:out value="${img.categoryLabel}"/><small><c:out value="${img.originalName}"/></small></figcaption>
                        </figure>
                    </c:forEach>
                </div>
            </c:otherwise>
        </c:choose>
    </section>
</div>

<%@ include file="fragments/footer.jspf" %>
