# Screenshots

Genuine captures of the running applications (Chrome via Playwright, `tests/capture_screenshots.py`, 11 Oct 2026).
Re-create them with `python tests/capture_screenshots.py` while Apache, MySQL and Tomcat are running.

| File | Shows |
|---|---|
| [01-landing-page.png](01-landing-page.png) | Landing page linking the three modules |
| [02-registration-form.png](02-registration-form.png) | Q1 registration form (empty) |
| [03-registration-empty-errors.png](03-registration-empty-errors.png) | Submitting the empty form: error summary + field errors |
| [04-registration-validation-errors.png](04-registration-validation-errors.png) | Invalid email, weak password + strength meter, mismatch, fake image, .txt document |
| [05-registration-oversized-file.png](05-registration-oversized-file.png) | Client-side rejection of a 3 MB photo |
| [06-registration-valid-ready.png](06-registration-valid-ready.png) | Valid form, strong password, photo preview |
| [07-registration-success.png](07-registration-success.png) | Server accepted the registration |
| [08-registration-mobile.png](08-registration-mobile.png) | Registration form at 390 px (mobile) |
| [09-php-login-error.png](09-php-login-error.png) | PHP admin login with wrong password |
| [10-php-dashboard.png](10-php-dashboard.png) | PHP admin dashboard |
| [11-php-student-list.png](11-php-student-list.png) | Student list (avatars streamed by file.php) |
| [12-php-student-search.png](12-php-student-search.png) | Search results |
| [13-php-delete-confirmation.png](13-php-delete-confirmation.png) | Delete confirmation dialog |
| [14-php-add-student-validation.png](14-php-add-student-validation.png) | Server-side validation errors (browser checks bypassed) |
| [15-php-add-student-form.png](15-php-add-student-form.png) | Add student with photo + certificate |
| [16-php-student-profile-new.png](16-php-student-profile-new.png) | New student's profile after saving |
| [17-php-edit-student.png](17-php-edit-student.png) | Edit student form |
| [18-php-student-profile-documents.png](18-php-student-profile-documents.png) | Profile with image gallery and documents |
| [19-php-upload-rejected.png](19-php-upload-rejected.png) | Disguised PHP file rejected |
| [20-php-document-view.png](20-php-document-view.png) | Image document streamed through file.php |
| [21-php-student-list-mobile.png](21-php-student-list-mobile.png) | Admin list on mobile (stacked rows) |
| [22-servlet-login.png](22-servlet-login.png) | Servlet portal login (after redirect) |
| [23-servlet-student-list.png](23-servlet-student-list.png) | JSP list: loop over students, 0/1/many files |
| [24-servlet-profile-multiple-files.png](24-servlet-profile-multiple-files.png) | JSP profile with several files |
| [25-servlet-profile-no-files.png](25-servlet-profile-no-files.png) | Student with zero files (empty states) |
| [26-servlet-profile-created-in-php.png](26-servlet-profile-created-in-php.png) | Student created in PHP shown by Java |
| [27-servlet-profile-self-registered.png](27-servlet-profile-self-registered.png) | Self-registered student shown by Java |
| [28-servlet-photo-stream.png](28-servlet-photo-stream.png) | Image streamed directly by the photo Servlet |
| [29-servlet-invalid-id.png](29-servlet-invalid-id.png) | Invalid student ID -> 400 page |
| [30-servlet-missing-record.png](30-servlet-missing-record.png) | Unknown student -> 404 page |
| [31-servlet-logged-out.png](31-servlet-logged-out.png) | After logout |
| [32-servlet-file-unauthenticated.png](32-servlet-file-unauthenticated.png) | File request after logout -> 401 |
| [33-servlet-student-own-profile.png](33-servlet-student-own-profile.png) | Student account sees own profile |
| [34-servlet-student-forbidden.png](34-servlet-student-forbidden.png) | Student blocked from another profile -> 403 |
| [35-servlet-student-list-mobile.png](35-servlet-student-list-mobile.png) | Servlet list on mobile |
