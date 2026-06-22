<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments;

use InvalidArgumentException;

/**
 * Configuration for {@see MilkywayPaymentsClient}: API endpoint, Keycloak
 * client-credentials, and resilience tuning. Immutable — construct once and
 * pass to the client.
 */
final class MilkywayOptions
{
    /**
     * @param string $baseUrl          Base URL of the Payments API, e.g. https://milkyway.stage.planet9.ae
     * @param string $tokenUrl         Keycloak token endpoint for the client-credentials grant.
     * @param string $clientId         Keycloak client id issued to your institution (becomes the `azp` claim).
     * @param string $clientSecret     Keycloak client secret issued to your institution.
     * @param string|null $scope       Optional OAuth scope to request. Null by default.
     * @param float $tokenRefreshSkew  How long (seconds) before a token's stated expiry it is considered
     *                                  stale and refreshed. Guards against clock skew. Default 30s.
     * @param float $requestTimeout    Per-attempt request timeout in seconds. Default 30s.
     * @param int $maxRetries          Max automatic retries for transient failures (5xx, 408, network). Default 3.
     * @param float $retryBaseDelay    Base delay (seconds) for exponential backoff between retries. Default 0.5s.
     */
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $tokenUrl,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly ?string $scope = null,
        public readonly float $tokenRefreshSkew = 30.0,
        public readonly float $requestTimeout = 30.0,
        public readonly int $maxRetries = 3,
        public readonly float $retryBaseDelay = 0.5,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (trim($this->baseUrl) === '') {
            throw new InvalidArgumentException('baseUrl is required.');
        }
        if (trim($this->tokenUrl) === '') {
            throw new InvalidArgumentException('tokenUrl is required.');
        }
        if (trim($this->clientId) === '') {
            throw new InvalidArgumentException('clientId is required.');
        }
        if (trim($this->clientSecret) === '') {
            throw new InvalidArgumentException('clientSecret is required.');
        }
        if ($this->maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries cannot be negative.');
        }
    }

    /** Base address with a single trailing slash, ready for relative path joins. */
    public function normalizedBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/';
    }
}
