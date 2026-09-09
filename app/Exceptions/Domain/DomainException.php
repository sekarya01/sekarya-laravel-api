<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * Base for business-invariant violations thrown from Action classes.
 *
 * Actions stay HTTP-agnostic: they throw these, and bootstrap/app.php maps them
 * to status codes. No Action ever calls abort() or response().
 */
abstract class DomainException extends RuntimeException
{
    /** Stable machine-readable code for API clients. */
    abstract public function errorCode(): string;

    /** HTTP status the API layer should respond with. */
    public function httpStatus(): int
    {
        return 422;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [];
    }
}
