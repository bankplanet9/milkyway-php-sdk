<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\Exception\MilkywayExposureBlockedException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayServiceUnavailableException;
use Bankplanet9\MilkywayPayments\Model\PayRequest;
use Bankplanet9\MilkywayPayments\Tests\Support\FakeTokenProvider;
use Bankplanet9\MilkywayPayments\Tests\Support\MockHttpClient;
use Bankplanet9\MilkywayPayments\Tests\Support\NetworkException;
use Bankplanet9\MilkywayPayments\Tests\Support\TestSupport;
use Bankplanet9\MilkywayPayments\TransactionStatus;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ResilienceTest extends TestCase
{
    public function testRetriesTransient5xxThenSucceeds(): void
    {
        $mock = new MockHttpClient(static function (int $i, RequestInterface $r): ResponseInterface {
            return $i < 2
                ? MockHttpClient::response(503, '{"error":"down"}')
                : MockHttpClient::response(200, '{"transaction_id":4021,"status":1}');
        });
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        $result = $client->postcheck(4021);

        self::assertSame(TransactionStatus::Done, $result->status);
        self::assertSame(3, $mock->callCount());
    }

    public function testRetriesNetworkExceptionThenSucceeds(): void
    {
        $mock = new MockHttpClient(static function (int $i, RequestInterface $r): ResponseInterface {
            if ($i < 1) {
                throw new NetworkException($r);
            }
            return MockHttpClient::response(200, '{"transaction_id":4021,"status":1}');
        });
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        $result = $client->postcheck(4021);

        self::assertSame(TransactionStatus::Done, $result->status);
        self::assertSame(2, $mock->callCount());
    }

    public function testGivesUpAfterMaxRetriesAndThrows(): void
    {
        $mock = MockHttpClient::always(503, '{"error":"down"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 2));

        try {
            $client->postcheck(4021);
            self::fail('expected MilkywayServiceUnavailableException');
        } catch (MilkywayServiceUnavailableException $ex) {
            self::assertSame(503, $ex->getStatusCode());
        }

        self::assertSame(3, $mock->callCount()); // initial + 2 retries
    }

    public function testDoesNotRetryDeterministic402(): void
    {
        $mock = MockHttpClient::always(402, '{"error":"blocked"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        try {
            $client->pay($this->newPay(), 'idem-key');
            self::fail('expected MilkywayExposureBlockedException');
        } catch (MilkywayExposureBlockedException) {
            // expected
        }

        self::assertSame(1, $mock->callCount());
    }

    public function testPayWithoutIdempotencyKeyIsNotRetriedOn5xx(): void
    {
        $mock = MockHttpClient::always(503, '{"error":"down"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        try {
            $client->pay($this->newPay(), idempotencyKey: null);
            self::fail('expected MilkywayServiceUnavailableException');
        } catch (MilkywayServiceUnavailableException) {
            // expected
        }

        self::assertSame(1, $mock->callCount()); // suppressed: no duplicate-payment risk
    }

    public function testPayWithIdempotencyKeyIsRetriedOn5xx(): void
    {
        $mock = new MockHttpClient(static function (int $i, RequestInterface $r): ResponseInterface {
            return $i < 1
                ? MockHttpClient::response(503, '{"error":"down"}')
                : MockHttpClient::response(200, '{"transaction_id":7}');
        });
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        $id = $client->pay($this->newPay(), idempotencyKey: 'idem-key');

        self::assertSame(7, $id);
        self::assertSame(2, $mock->callCount());
    }

    public function testNoRetryMarkerHeaderIsStrippedFromOutgoingRequest(): void
    {
        $mock = MockHttpClient::always(200, '{"transaction_id":1}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $client->pay($this->newPay(), idempotencyKey: null);

        self::assertFalse($mock->requests[0]->hasHeader('X-Milkyway-No-Retry'));
    }

    private function newPay(): PayRequest
    {
        return new PayRequest(
            thirdPartyIdDebit: 'bank-beta',
            serviceId: 'card-payout',
            senderId: 's-1',
            recipientId: 'r-1',
            amountCredit: BigDecimal::of('100'),
            currencyCredit: 'USD',
        );
    }
}
