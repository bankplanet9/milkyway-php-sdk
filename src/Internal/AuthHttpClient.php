<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Internal;

use Bankplanet9\MilkywayPayments\Auth\AccessTokenProviderInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 wrapper that attaches a bearer token to every outgoing request. On a
 * 401 it forces a single token refresh and replays the request once — covering a
 * cached token that was revoked or expired early. A persistent 401 surfaces to the
 * caller (no infinite loop).
 */
final class AuthHttpClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly AccessTokenProviderInterface $tokenProvider,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $authorized = $this->authorize($request, forceRefresh: false);
        $response = $this->inner->sendRequest($authorized);

        if ($response->getStatusCode() !== 401) {
            return $response;
        }

        $replay = $this->authorize($request, forceRefresh: true);
        return $this->inner->sendRequest($replay);
    }

    private function authorize(RequestInterface $request, bool $forceRefresh): RequestInterface
    {
        $token = $this->tokenProvider->getAccessToken($forceRefresh);
        return $request->withHeader('Authorization', 'Bearer ' . $token);
    }
}
