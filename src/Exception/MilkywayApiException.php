<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Exception;

use Bankplanet9\MilkywayPayments\Internal\Json;
use RuntimeException;
use Throwable;

/**
 * Base exception for all errors returned by the MilkyWay Payments API.
 * Carries the HTTP status code, the server's `error` message when present, and
 * that message's leading code as {@see self::getErrorCode()} (e.g. `ErrPaymentRejected`).
 */
class MilkywayApiException extends RuntimeException
{
    /** @var int HTTP status code returned by the API (0 if the request never completed). */
    private int $statusCode;

    /** @var string|null Raw response body, useful for diagnostics. */
    private ?string $responseBody;

    private ?string $errorCode;

    public function __construct(
        int $statusCode,
        string $message,
        ?string $responseBody = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->errorCode = $responseBody !== null ? Json::errorCode(Json::extractErrorMessage($responseBody)) : null;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * The leading `Err...` identifier of the server's error message, e.g.
     * `ErrPaymentRejected`, `ErrRecipientBankError`, or `ErrValidationFailed` for
     * `ErrValidationFailed: amount_credit: ...`. Null when the message does not
     * start with one; the full text stays in {@see self::getMessage()}.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
