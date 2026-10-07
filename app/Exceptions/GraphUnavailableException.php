<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The Neo4j client could not be built or reached. Callers that read the graph
 * catch this specifically so they can return a 503 instead of an unhandled 500.
 */
class GraphUnavailableException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
