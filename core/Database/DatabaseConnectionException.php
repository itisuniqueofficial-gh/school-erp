<?php

declare(strict_types=1);

namespace Core\Database;

use RuntimeException;

/**
 * Raised when the application cannot establish a database connection.
 *
 * The exception message is deliberately generic and safe to surface to end
 * users. The underlying PDO error (which may reference the host/port/driver)
 * is preserved as the previous exception for server-side logging only, and is
 * never rendered to the client in production.
 */
final class DatabaseConnectionException extends RuntimeException
{
}
