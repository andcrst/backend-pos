<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Exception domain dengan error code dan HTTP status yang dipetakan
 * ke response envelope oleh handler di bootstrap/app.php.
 */
class ApiException extends Exception
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'ERROR',
        public readonly int $status = 400,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function invalidCredentials(array $errors = []): self
    {
        return new self('Email atau password salah.', 'INVALID_CREDENTIALS', 422, $errors);
    }

    public static function accountInactive(): self
    {
        return new self('Akun Anda tidak aktif. Hubungi administrator.', 'ACCOUNT_INACTIVE', 403);
    }

    public static function resourceInUse(string $message = 'Data masih digunakan oleh record lain.'): self
    {
        return new self($message, 'RESOURCE_IN_USE', 409);
    }
}
