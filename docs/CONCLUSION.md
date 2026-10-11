# Conclusion

This assignment produced a single Student Management Portal built on three web technologies. An HTML5/CSS3/JavaScript registration form validates input in the browser. A PHP–MySQL admin panel performs full CRUD and stores uploaded photos and documents securely, keeping their metadata in the database. A Java Servlet/JSP portal uses JDBC to list the same students dynamically and streams their files through a protected Servlet.
Both server modules share one normalised schema and one private storage folder; tests confirmed that a file uploaded in PHP is served byte-for-byte by the Servlet.
Security was built in at every layer: prepared statements, output escaping, CSRF tokens, bcrypt passwords, session regeneration and timeouts, content-based upload validation, and path-traversal checks.
The work shows how client-side validation improves usability but cannot replace server-side validation, and how different server technologies can cooperate around a common database.
