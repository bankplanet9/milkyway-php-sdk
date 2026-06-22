<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

use RuntimeException;
use Throwable;

/**
 * Base exception for all errors returned by the MilkyWay Payments API.
 * Carries the HTTP status code and the server's `error` message when present.
 */
class MilkywayApiException extends RuntimeException
{
    /** @var int HTTP status code returned by the API (0 if the request never completed). */
    private int $statusCode;

    /** @var string|null Raw response body, useful for diagnostics. */
    private ?string $responseBody;

    public function __construct(
        int $statusCode,
        string $message,
        ?string $responseBody = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
