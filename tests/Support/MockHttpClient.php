<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Test double for the innermost PSR-18 client. Records every request it sees and
 * returns responses from a caller-supplied responder. No live network.
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var callable(int, RequestInterface): ResponseInterface */
    private $responder;

    /** @param callable(int, RequestInterface): ResponseInterface $responder */
    public function __construct(callable $responder)
    {
        $this->responder = $responder;
    }

    /** Always return the same status + body. */
    public static function always(int $status, string $body, string $contentType = 'application/json'): self
    {
        return new self(static fn (int $i, RequestInterface $r): ResponseInterface => self::response($status, $body, $contentType));
    }

    public static function response(int $status, string $body, string $contentType = 'application/json'): ResponseInterface
    {
        return new Response($status, ['Content-Type' => $contentType], $body);
    }

    public function callCount(): int
    {
        return count($this->requests);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $index = count($this->requests);
        $this->requests[] = $request;

        // The responder may return a response or throw a PSR-18 ClientExceptionInterface.
        return ($this->responder)($index, $request);
    }
}
