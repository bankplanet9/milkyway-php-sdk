<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\Exception\MilkywayAuthException;
use Bankplanet9\MilkywayPayments\Tests\Support\FakeTokenProvider;
use Bankplanet9\MilkywayPayments\Tests\Support\MockHttpClient;
use Bankplanet9\MilkywayPayments\Tests\Support\TestSupport;
use Bankplanet9\MilkywayPayments\TransactionStatus;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class AuthHandlerTest extends TestCase
{
    public function testOn401ForcesTokenRefreshAndReplaysOnce(): void
    {
        // First call 401 (stale token), second succeeds after refresh.
        $mock = new MockHttpClient(static function (int $i, RequestInterface $r): ResponseInterface {
            return $i === 0
                ? MockHttpClient::response(401, '{"error":"expired"}')
                : MockHttpClient::response(200, '{"transaction_id":4021,"status":1}');
        });
        $tokenProvider = new FakeTokenProvider();
        $client = TestSupport::buildClient($mock, $tokenProvider);

        $result = $client->postcheck(4021);

        self::assertSame(TransactionStatus::Done, $result->status);
        self::assertSame(2, $mock->callCount());
        self::assertSame(1, $tokenProvider->forceRefreshCount);

        // The replay carries the freshly fetched bearer token.
        self::assertSame('Bearer test-token', $mock->requests[1]->getHeaderLine('Authorization'));
    }

    public function testPersistent401SurfacesAsAuthExceptionAfterSingleReplay(): void
    {
        $mock = MockHttpClient::always(401, '{"error":"invalid"}');
        $tokenProvider = new FakeTokenProvider();
        $client = TestSupport::buildClient($mock, $tokenProvider);

        try {
            $client->postcheck(4021);
            self::fail('expected MilkywayAuthException');
        } catch (MilkywayAuthException $ex) {
            self::assertSame(401, $ex->getStatusCode());
        }

        self::assertSame(2, $mock->callCount()); // original + one replay, no infinite loop
        self::assertSame(1, $tokenProvider->forceRefreshCount);
    }
}
