# PHP admin module (Question 2)

- `public/` - the only web-reachable folder (pages, `file.php`, `register.php`, `api/`, `assets/`)
- `includes/` - bootstrap, authentication, CSRF, sessions, validation, file storage, repositories, views
- `config/config.php` - reads the shared `../.env`
- `bin/` - CLI tools: `create_user.php`, `delete_user.php`, `seed_sample_files.php`
- `tests/run_tests.php` - test suite (`php php-admin/tests/run_tests.php`)

Uploaded files are stored in `../storage/uploads` (outside `public/`) and are served only by `public/file.php`.
URL with XAMPP: http://localhost/wt-portal/php-admin/public/ - see [../docs/SETUP.md](../docs/SETUP.md).
