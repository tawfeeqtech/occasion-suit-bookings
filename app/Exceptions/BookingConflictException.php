<?php

namespace App\Exceptions;

use Exception;

class BookingConflictException extends Exception
{
    /**
     * @param  list<array{id: string, reason: string, expected_ready_at?: string|null}>  $conflicts
     */
    public function __construct(
        string $message = 'One or more items are not available for the requested period.',
        public array $conflicts = [],
        int $code = 409,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
