<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Internal;

use Bankplanet9\MilkywayPayments\MilkywayOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Client-agnostic retry wrapper around any PSR-18 client.
 *
 * Retries transient failures — HTTP 5xx, 408, and network exceptions
 * ({@see ClientExceptionInterface}) — with exponential backoff plus jitter.
 * Deterministic outcomes (400/401/402/404/422 and other 4xx) are never retried.
 *
 * A request carrying the {@see self::NO_RETRY_HEADER} header is sent exactly once
 * (used for a non-idempotent `pay` without an Idempotency-Key, where a retry could
 * create a duplicate payment). The marker header is stripped before the request
 * leaves the SDK.
 */
final class RetryingHttpClient implements ClientInterface
{
    /** Internal marker header opting a request out of automatic retries. */
    public const NO_RETRY_HEADER = 'X-Milkyway-No-Retry';

    /** @var callable(float):void Sleep seam for tests; receives seconds. */
    private $sleeper;

    /** @var callable():float Jitter seam for tests; returns a value in [0, 1). */
    private $jitter;

    public function __construct(
        private readonly ClientInterface $inner,
        private readonly MilkywayOptions $options,
        ?callable $sleeper = null,
        ?callable $jitter = null,
    ) {
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
        $this->jitter = $jitter ?? static fn (): float => mt_rand() / mt_getrandmax();
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $suppressRetry = $request->hasHeader(self::NO_RETRY_HEADER);
        $request = $request->withoutHeader(self::NO_RETRY_HEADER);

        $maxAttempts = $suppressRetry ? 1 : $this->options->maxRetries + 1;
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                $response = $this->inner->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                ($this->sleeper)($this->backoffDelay($attempt));
                continue;
            }

            if ($attempt >= $maxAttempts || !$this->isTransient($response->getStatusCode())) {
                return $response;
            }

            ($this->sleeper)($this->backoffDelay($attempt));
        }
    }

    private function isTransient(int $status): bool
    {
        return $status >= 500 || $status === 408;
    }

    /** Exponential backoff with full jitter: base * 2^(attempt-1) * [0,1). */
    private function backoffDelay(int $attempt): float
    {
        $exponential = $this->options->retryBaseDelay * (2 ** ($attempt - 1));
        return $exponential * ($this->jitter)();
    }
}
