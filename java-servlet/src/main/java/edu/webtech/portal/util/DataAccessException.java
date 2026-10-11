package edu.webtech.portal.util;

/**
 * Unchecked exception thrown by the DAO layer when the database fails.
 * getMessage() is always safe to display; the SQLException cause is logged.
 */
public class DataAccessException extends RuntimeException {

    private static final long serialVersionUID = 1L;

    public DataAccessException(String userMessage, Throwable cause) {
        super(userMessage, cause);
    }
}
