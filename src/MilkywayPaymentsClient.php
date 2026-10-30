<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments;

use Bankplanet9\MilkywayPayments\Auth\AccessTokenProviderInterface;
use Bankplanet9\MilkywayPayments\Auth\KeycloakTokenProvider;
use Bankplanet9\MilkywayPayments\Exception\MilkywayApiException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayAuthException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayExposureBlockedException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayNotFoundException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayPaymentRejectedException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayRecipientBankException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayServiceUnavailableException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayValidationException;
use Bankplanet9\MilkywayPayments\Internal\AuthHttpClient;
use Bankplanet9\MilkywayPayments\Internal\Json;
use Bankplanet9\MilkywayPayments\Internal\RetryingHttpClient;
use Bankplanet9\MilkywayPayments\Model\CancelResult;
use Bankplanet9\MilkywayPayments\Model\Destination;
use Bankplanet9\MilkywayPayments\Model\PayRequest;
use Bankplanet9\MilkywayPayments\Model\PostcheckResult;
use Bankplanet9\MilkywayPayments\Model\PrecheckRequest;
use Bankplanet9\MilkywayPayments\Model\PrecheckResult;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Client for the MilkyWay Payments API (`/payments/v1`).
 *
 * Self-contained: builds the Keycloak token + auth + retry pipeline over a
 * PSR-18 client (auto-discovered, or one you supply). Money is exact via
 * {@see \Brick\Math\BigDecimal}; transaction status is a {@see TransactionStatus}
 * enum; every API error maps to a typed exception.
 */
final class MilkywayPaymentsClient
{
    private const PAYMENTS_PATH = 'payments/v1';

    private readonly ClientInterface $pipeline;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly string $baseUrl;

    /**
     * @param MilkywayOptions $options    Endpoint, credentials, and resilience tuning.
     * @param ClientInterface|null $httpClient  PSR-18 client for API calls. Auto-discovered when null.
     * @param ClientInterface|null $tokenHttpClient  PSR-18 client for the token endpoint only; it must NOT
     *                                    route through retry/auth (avoids recursion). Defaults to a separate
     *                                    auto-discovered client.
     * @param RequestFactoryInterface|null $requestFactory PSR-17 request factory. Auto-discovered when null.
     * @param StreamFactoryInterface|null $streamFactory   PSR-17 stream factory. Auto-discovered when null.
     * @param AccessTokenProviderInterface|null $tokenProvider Override the token provider (e.g. for tests).
     * @param callable(float):void|null $retrySleeper Sleep seam for the retry layer (seconds); tests pass a no-op.
     * @param callable():float|null $retryJitter Jitter seam for the retry layer, returning [0, 1).
     */
    public function __construct(
        private readonly MilkywayOptions $options,
        ?ClientInterface $httpClient = null,
        ?ClientInterface $tokenHttpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?AccessTokenProviderInterface $tokenProvider = null,
        ?callable $retrySleeper = null,
        ?callable $retryJitter = null,
    ) {
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->baseUrl = $options->normalizedBaseUrl();

        $apiClient = $httpClient ?? Psr18ClientDiscovery::find();

        $tokenProvider ??= new KeycloakTokenProvider(
            $tokenHttpClient ?? Psr18ClientDiscovery::find(),
            $this->requestFactory,
            $this->streamFactory,
            $options,
        );

        // Pipeline order mirrors the reference SDK: retry(outer) -> auth -> network.
        // A 401 inside auth triggers one refresh-and-replay; the retry layer never
        // retries deterministic 4xx.
        $auth = new AuthHttpClient($apiClient, $tokenProvider);
        $this->pipeline = new RetryingHttpClient($auth, $options, $retrySleeper, $retryJitter);
    }

    /**
     * GET healthcheck — returns the recipient service's plain-text status string.
     *
     * @throws MilkywayApiException
     */
    public function healthcheck(string $thirdPartyId, string $serviceId): string
    {
        if (trim($thirdPartyId) === '') {
            throw new InvalidArgumentException('thirdPartyId is required.');
        }
        if (trim($serviceId) === '') {
            throw new InvalidArgumentException('serviceId is required.');
        }

        $uri = self::PAYMENTS_PATH . '/healthcheck?third_party_id=' . rawurlencode($thirdPartyId)
            . '&service_id=' . rawurlencode($serviceId);

        $request = $this->requestFactory->createRequest('GET', $this->baseUrl . $uri)
            ->withHeader('Accept', 'text/plain');
        $response = $this->send($request);

        $body = (string) $response->getBody();
        $this->throwIfError($response, $body);
        return $body;
    }

    /**
     * GET discovery — the catalog of payout destinations and the services each
     * offers. Pass an ISO 3166-1 alpha-2 country (case-insensitive) to filter to
     * one country, or omit it to list every destination. Each service carries its
     * party profile (which `senderInfo` / `recipientInfo` fields the recipient bank
     * requires); the per-service `data` JSON Schema is excluded here — fetch it via
     * {@see self::serviceSchema()}.
     *
     * @return list<Destination>
     *
     * @throws MilkywayApiException
     */
    public function discovery(?string $country = null): array
    {
        $uri = self::PAYMENTS_PATH . '/discovery';
        if ($country !== null && trim($country) !== '') {
            $uri .= '?country=' . rawurlencode($country);
        }

        $request = $this->requestFactory->createRequest('GET', $this->baseUrl . $uri)
            ->withHeader('Accept', 'application/json');
        $body = $this->sendForJson($request);
        return Destination::listFromJson($body);
    }

    /**
     * GET discovery schema — the Draft-2020 JSON Schema for a (destination, service)
     * `data` extras (never identity; most services take none), returned as an
     * associative array. This is the same schema the server enforces at submit, so you
     * can build and self-validate `data` up front.
     *
     * @return array<string, mixed>
     *
     * @throws MilkywayApiException
     */
    public function serviceSchema(string $thirdPartyId, string $serviceId): array
    {
        if (trim($thirdPartyId) === '') {
            throw new InvalidArgumentException('thirdPartyId is required.');
        }
        if (trim($serviceId) === '') {
            throw new InvalidArgumentException('serviceId is required.');
        }

        $uri = self::PAYMENTS_PATH . '/discovery/' . rawurlencode($thirdPartyId)
            . '/' . rawurlencode($serviceId) . '/schema';

        $request = $this->requestFactory->createRequest('GET', $this->baseUrl . $uri)
            ->withHeader('Accept', 'application/schema+json, application/json');
        $body = $this->sendForJson($request);
        return Json::decodeObject($body);
    }

    /**
     * POST precheck — quote rate + commission without creating a payment.
     *
     * @throws MilkywayValidationException       400; {@see MilkywayValidationException::getField()} names the field.
     * @throws MilkywayExposureBlockedException  402.
     * @throws MilkywayPaymentRejectedException  422 — the recipient bank refused the payment; not retried.
     * @throws MilkywayRecipientBankException    502 — the recipient bank's connector failed (a 5xx subclass).
     * @throws MilkywayServiceUnavailableException 5xx — the recipient service is unavailable.
     * @throws MilkywayApiException
     */
    public function precheck(PrecheckRequest $request): PrecheckResult
    {
        $httpRequest = $this->jsonPost(self::PAYMENTS_PATH . '/precheck', $request->toArray());
        $body = $this->sendForJson($httpRequest);
        return PrecheckResult::fromJson($body);
    }

    /**
     * POST pay — initiate a payment, returning the transaction id.
     *
     * Pass an $idempotencyKey to make the call safely retryable; without one the
     * SDK sends it exactly once (a retry could create a duplicate payment).
     *
     * @throws MilkywayValidationException       400; {@see MilkywayValidationException::getField()} names the field.
     * @throws MilkywayExposureBlockedException  402.
     * @throws MilkywayPaymentRejectedException  422 — the recipient bank refused the payment; not retried.
     * @throws MilkywayRecipientBankException    502 — the recipient bank's connector failed (a 5xx subclass).
     * @throws MilkywayServiceUnavailableException 5xx — the recipient service is unavailable.
     * @throws MilkywayApiException
     */
    public function pay(PayRequest $request, ?string $idempotencyKey = null): int
    {
        $httpRequest = $this->jsonPost(self::PAYMENTS_PATH, $request->toArray());

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $httpRequest = $httpRequest->withHeader('Idempotency-Key', $idempotencyKey);
        } else {
            $httpRequest = $httpRequest->withHeader(RetryingHttpClient::NO_RETRY_HEADER, '1');
        }

        $body = $this->sendForJson($httpRequest);
        $obj = Json::decodeObject($body);
        return isset($obj['transaction_id']) ? (int) $obj['transaction_id'] : 0;
    }

    /**
     * GET postcheck — current status of a transaction.
     *
     * @throws MilkywayApiException
     */
    public function postcheck(int $transactionId): PostcheckResult
    {
        $uri = self::PAYMENTS_PATH . '/postcheck?transaction_id=' . $transactionId;
        $request = $this->requestFactory->createRequest('GET', $this->baseUrl . $uri)
            ->withHeader('Accept', 'application/json');
        $body = $this->sendForJson($request);
        return PostcheckResult::fromJson($body);
    }

    /**
     * POST cancel — request cancellation, returning the resulting status.
     *
     * A 403 (`ErrPaymentCannotBeCanceled`) surfaces as a {@see MilkywayApiException}
     * with status 403; the payment is unchanged.
     *
     * @throws MilkywayNotFoundException         404 — not found or not yours.
     * @throws MilkywayRecipientBankException    502 — the recipient bank's connector failed (a 5xx subclass).
     * @throws MilkywayServiceUnavailableException 503 — the recipient bank could not be reached.
     * @throws MilkywayApiException
     */
    public function cancel(int $transactionId): CancelResult
    {
        $httpRequest = $this->jsonPost(self::PAYMENTS_PATH . '/cancel', ['transaction_id' => $transactionId]);
        $body = $this->sendForJson($httpRequest);
        return CancelResult::fromJson($body);
    }

    /**
     * Poll postcheck with exponential backoff until the transaction reaches a
     * terminal status or the polling budget is exhausted (last status returned).
     *
     * @throws MilkywayApiException
     */
    public function waitForCompletion(int $transactionId, ?PollOptions $pollOptions = null): PostcheckResult
    {
        $opts = $pollOptions ?? new PollOptions();
        $start = microtime(true);
        $delay = $opts->initialDelay;

        $result = $this->postcheck($transactionId);
        while (!$result->status->isTerminal()) {
            $elapsed = microtime(true) - $start;
            if ($elapsed + $delay > $opts->timeout) {
                return $result;
            }

            if ($delay > 0) {
                usleep((int) round($delay * 1_000_000));
            }

            $delay = min($delay * $opts->backoffMultiplier, $opts->maxDelay);
            $result = $this->postcheck($transactionId);
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonPost(string $uri, array $payload): RequestInterface
    {
        $json = Json::encode($payload);
        return $this->requestFactory->createRequest('POST', $this->baseUrl . $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($json));
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->pipeline->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new MilkywayServiceUnavailableException(0, 'The request to the Payments API failed.', null, $e);
        }
    }

    private function sendForJson(RequestInterface $request): string
    {
        $response = $this->send($request);
        $body = (string) $response->getBody();
        $this->throwIfError($response, $body);
        return $body;
    }

    private function throwIfError(ResponseInterface $response, string $body): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }
        throw $this->toException($status, $body);
    }

    private function toException(int $status, string $body): MilkywayApiException
    {
        $message = Json::extractErrorMessage($body) ?? "Request failed with status {$status}.";

        return match (true) {
            $status === 400 => new MilkywayValidationException($message, $body),
            $status === 401 => new MilkywayAuthException($message, $body),
            $status === 402 => new MilkywayExposureBlockedException($message, $body),
            $status === 404 => new MilkywayNotFoundException($message, $body),
            $status === 422 => new MilkywayPaymentRejectedException($message, $body),
            $status === 502 => new MilkywayRecipientBankException($message, $body),
            $status >= 500 => new MilkywayServiceUnavailableException($status, $message, $body),
            default => new MilkywayApiException($status, $message, $body),
        };
    }
}
