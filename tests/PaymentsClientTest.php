<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\Exception\MilkywayExposureBlockedException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayNotFoundException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayValidationException;
use Bankplanet9\MilkywayPayments\Model\PayRequest;
use Bankplanet9\MilkywayPayments\Model\PrecheckRequest;
use Bankplanet9\MilkywayPayments\Tests\Support\FakeTokenProvider;
use Bankplanet9\MilkywayPayments\Tests\Support\MockHttpClient;
use Bankplanet9\MilkywayPayments\Tests\Support\TestSupport;
use Bankplanet9\MilkywayPayments\TransactionStatus;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class PaymentsClientTest extends TestCase
{
    public function testPrecheckParsesDecimalsLosslesslyAndAttachesBearer(): void
    {
        $mock = MockHttpClient::always(200,
            '{"third_party_id":"bank-beta","amount_credit":100.00,"amount_debit":1267500.00,"rate":12675.00,"commission":1.50}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $result = $client->precheck(new PrecheckRequest(
            thirdPartyIdDebit: 'bank-beta',
            serviceId: 'card-payout',
            recipientId: 'recipient-9999',
            amountCredit: BigDecimal::of('100.00'),
            currencyCredit: 'USD',
        ));

        self::assertTrue(BigDecimal::of('12675.00')->isEqualTo($result->rate));
        self::assertTrue(BigDecimal::of('1267500.00')->isEqualTo($result->amountDebit));
        self::assertTrue(BigDecimal::of('1.50')->isEqualTo($result->commission));
        // Scale preserved from the wire literal (precision, not just value).
        self::assertSame('12675.00', (string) $result->rate);

        $req = $mock->requests[0];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/payments/v1/precheck', (string) $req->getUri());
        self::assertSame('Bearer test-token', $req->getHeaderLine('Authorization'));
    }

    public function testPrecheckSerializesSnakeCaseAsNumberAndOmitsNullData(): void
    {
        $mock = MockHttpClient::always(200, '{}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $client->precheck(new PrecheckRequest(
            thirdPartyIdDebit: 'bank-beta',
            serviceId: 'card-payout',
            recipientId: 'r-1',
            amountCredit: BigDecimal::of('50'),
            currencyCredit: 'USD',
        ));

        $body = (string) $mock->requests[0]->getBody();
        self::assertStringContainsString('"third_party_id_debit":"bank-beta"', $body);
        // Money must be a bare JSON number, never a quoted string.
        self::assertStringContainsString('"amount_credit":50', $body);
        self::assertStringNotContainsString('"amount_credit":"50"', $body);
        self::assertStringNotContainsString('"data"', $body);
    }

    public function testPayPreservesDecimalScaleOnTheWire(): void
    {
        $mock = MockHttpClient::always(200, '{"transaction_id":1}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $client->pay(new PayRequest(
            thirdPartyIdDebit: 'bank-beta',
            serviceId: 'card-payout',
            senderId: 's-1',
            recipientId: 'r-1',
            amountCredit: BigDecimal::of('100.00'),
            currencyCredit: 'USD',
        ), 'k');

        $body = (string) $mock->requests[0]->getBody();
        self::assertStringContainsString('"amount_credit":100.00', $body);
    }

    public function testPayReturnsTransactionIdAndForwardsIdempotencyKey(): void
    {
        $mock = MockHttpClient::always(200, '{"transaction_id":4021}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $id = $client->pay(new PayRequest(
            thirdPartyIdDebit: 'bank-beta',
            serviceId: 'card-payout',
            senderId: 's-1',
            recipientId: 'r-1',
            amountCredit: BigDecimal::of('100'),
            currencyCredit: 'USD',
        ), idempotencyKey: 'key-123');

        self::assertSame(4021, $id);
        self::assertSame('key-123', $mock->requests[0]->getHeaderLine('Idempotency-Key'));
    }

    /**
     * @dataProvider statusCodes
     */
    public function testPostcheckMapsIntegerStatusToEnum(int $code, TransactionStatus $expected): void
    {
        $mock = MockHttpClient::always(200, '{"transaction_id":4021,"status":' . $code . '}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $result = $client->postcheck(4021);

        self::assertSame($expected, $result->status);
        self::assertSame(4021, $result->transactionId);
    }

    /** @return array<string, array{int, TransactionStatus}> */
    public static function statusCodes(): array
    {
        return [
            'pending' => [0, TransactionStatus::Pending],
            'done' => [1, TransactionStatus::Done],
            'failed' => [3, TransactionStatus::Failed],
            'stuck' => [5, TransactionStatus::Stuck],
        ];
    }

    public function testHealthcheckReturnsPlaintextBody(): void
    {
        $mock = MockHttpClient::always(200, 'Service is available', 'text/plain');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $body = $client->healthcheck('bank-beta', 'card-payout');

        self::assertSame('Service is available', $body);
        $query = $mock->requests[0]->getUri()->getQuery();
        self::assertStringContainsString('third_party_id=bank-beta', $query);
        self::assertStringContainsString('service_id=card-payout', $query);
    }

    public function testValidationErrorMapsToTypedExceptionWithMessage(): void
    {
        $mock = MockHttpClient::always(400, '{"error":"amount_credit must be > 0"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        try {
            $client->precheck(new PrecheckRequest('b', 's', 'r', BigDecimal::zero(), 'USD'));
            self::fail('expected MilkywayValidationException');
        } catch (MilkywayValidationException $ex) {
            self::assertSame(400, $ex->getStatusCode());
            self::assertSame('amount_credit must be > 0', $ex->getMessage());
        }
    }

    public function testExposureBlockMapsTo402Exception(): void
    {
        $mock = MockHttpClient::always(402, '{"error":"exposure limit exceeded"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $this->expectException(MilkywayExposureBlockedException::class);
        $client->pay(new PayRequest('b', 's', 'x', 'r', BigDecimal::of('1'), 'USD'), 'k');
    }

    public function testPostcheckNotOwnedMapsTo404Exception(): void
    {
        $mock = MockHttpClient::always(404, '{"error":"not found"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $this->expectException(MilkywayNotFoundException::class);
        $client->postcheck(999);
    }

    public function testCancelReturnsResultingStatus(): void
    {
        $mock = MockHttpClient::always(200, '{"status":2}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $result = $client->cancel(4021);

        self::assertSame(TransactionStatus::Cancelled, $result->status);
        self::assertStringContainsString('"transaction_id":4021', (string) $mock->requests[0]->getBody());
    }
}
