<?php
/**
 * Application exception types.  Each carries a message that is SAFE to show
 * to the user; technical details are written to the error log only.
 */
declare(strict_types=1);

/** Base class for expected, user-presentable errors. */
class AppException extends RuntimeException
{
    public function httpStatus(): int
    {
        return 400;
    }
}

/** The database server cannot be reached or the credentials are wrong. */
final class DatabaseUnavailableException extends AppException
{
    public function httpStatus(): int
    {
        return 503;
    }
}

/** A requested student / file does not exist. */
final class NotFoundException extends AppException
{
    public function httpStatus(): int
    {
        return 404;
    }
}

/** The user is logged in but lacks permission (or the CSRF token is bad). */
final class ForbiddenException extends AppException
{
    public function httpStatus(): int
    {
        return 403;
    }
}

/** Input failed server-side validation; $errors maps field => message. */
final class ValidationException extends AppException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors, string $message = 'Please correct the highlighted fields.')
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 422;
    }
}

/** An uploaded file was rejected (size, type, upload error ...). */
final class UploadException extends AppException
{
    public function __construct(string $message, public readonly string $field = 'file')
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
