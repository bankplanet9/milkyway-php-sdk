<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Auth;

use Bankplanet9\MilkywayPayments\Exception\MilkywayAuthException;
use Bankplanet9\MilkywayPayments\Internal\Json;
use Bankplanet9\MilkywayPayments\MilkywayOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Acquires access tokens via the Keycloak client-credentials grant and caches
 * them until shortly before expiry.
 *
 * The token endpoint is reached through a *plain* PSR-18 client supplied to this
 * provider — it must NOT route through the SDK's retry/auth pipeline (that would
 * recurse). This is a single-process, in-memory cache (PHP's shared-nothing model
 * means there is no cross-request token to share); no locking is needed.
 */
final class KeycloakTokenProvider implements AccessTokenProviderInterface
{
    private ?string $cachedToken = null;
    private float $expiresAt = 0.0;

    /** @var callable():float Clock seam for tests; defaults to wall-clock seconds. */
    private $clock;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly MilkywayOptions $options,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    public function getAccessToken(bool $forceRefresh = false): string
    {
        if (!$forceRefresh && $this->cachedToken !== null && ($this->clock)() < $this->expiresAt - $this->options->tokenRefreshSkew) {
            return $this->cachedToken;
        }

        return $this->acquire();
    }

    private function acquire(): string
    {
        $form = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->options->clientId,
            'client_secret' => $this->options->clientSecret,
        ];
        if ($this->options->scope !== null && trim($this->options->scope) !== '') {
            $form['scope'] = $this->options->scope;
        }

        $request = $this->requestFactory
            ->createRequest('POST', $this->options->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(http_build_query($form)));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new MilkywayAuthException('Failed to reach the Keycloak token endpoint.', null, $e);
        }

        $body = (string) $response->getBody();
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new MilkywayAuthException("Token acquisition failed with status {$status}.", $body);
        }

        $parsed = Json::decodeObject($body);
        $accessToken = isset($parsed['access_token']) && is_string($parsed['access_token']) ? $parsed['access_token'] : '';
        if ($accessToken === '') {
            throw new MilkywayAuthException('Token endpoint response contained no access_token.', $body);
        }
        $expiresIn = isset($parsed['expires_in']) && is_numeric($parsed['expires_in']) ? (float) $parsed['expires_in'] : 0.0;

        $this->cachedToken = $accessToken;
        $this->expiresAt = ($this->clock)() + $expiresIn;
        return $accessToken;
    }
}
