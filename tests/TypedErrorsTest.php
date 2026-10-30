<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\Exception\MilkywayApiException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayPaymentRejectedException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayRecipientBankException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayServiceUnavailableException;
use Bankplanet9\MilkywayPayments\Exception\MilkywayValidationException;
use Bankplanet9\MilkywayPayments\Model\PayRequest;
use Bankplanet9\MilkywayPayments\Model\PrecheckRequest;
use Bankplanet9\MilkywayPayments\StatusReason;
use Bankplanet9\MilkywayPayments\Tests\Support\FakeTokenProvider;
use Bankplanet9\MilkywayPayments\Tests\Support\MockHttpClient;
use Bankplanet9\MilkywayPayments\Tests\Support\TestSupport;
use Bankplanet9\MilkywayPayments\TransactionStatus;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class TypedErrorsTest extends TestCase
{
    public function testPrecheck422MapsToPaymentRejectedAndIsNotRetried(): void
    {
        $mock = MockHttpClient::always(422, '{"error":"ErrPaymentRejected"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        try {
            $client->precheck($this->newPrecheck());
            self::fail('expected MilkywayPaymentRejectedException');
        } catch (MilkywayPaymentRejectedException $ex) {
            self::assertSame(422, $ex->getStatusCode());
            self::assertSame(StatusReason::PAYMENT_REJECTED, $ex->getErrorCode());
            self::assertNotInstanceOf(MilkywayServiceUnavailableException::class, $ex);
        }

        self::assertSame(1, $mock->callCount());
    }

    public function testPay422WithIdempotencyKeyIsNotRetried(): void
    {
        $mock = MockHttpClient::always(422, '{"error":"ErrPaymentRejected"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        $this->expectException(MilkywayPaymentRejectedException::class);
        try {
            $client->pay($this->newPay(), 'idem-key');
        } finally {
            self::assertSame(1, $mock->callCount());
        }
    }

    public function test502IsRecipientBankAndStillAServiceUnavailable(): void
    {
        $mock = MockHttpClient::always(502, '{"error":"ErrRecipientBankError"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 2));

        try {
            $client->precheck($this->newPrecheck());
            self::fail('expected MilkywayRecipientBankException');
        } catch (MilkywayServiceUnavailableException $ex) {
            self::assertInstanceOf(MilkywayRecipientBankException::class, $ex);
            self::assertSame(502, $ex->getStatusCode());
            self::assertSame('ErrRecipientBankError', $ex->getErrorCode());
        }

        self::assertSame(3, $mock->callCount()); // transient: initial + 2 retries, as before
    }

    public function testPay502WithoutIdempotencyKeyIsSentOnce(): void
    {
        $mock = MockHttpClient::always(502, '{"error":"ErrRecipientBankError"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        try {
            $client->pay($this->newPay(), idempotencyKey: null);
            self::fail('expected MilkywayRecipientBankException');
        } catch (MilkywayRecipientBankException) {
            // expected
        }

        self::assertSame(1, $mock->callCount());
    }

    public function testCancel503ExposesServiceAndThirdPartyIds(): void
    {
        $mock = MockHttpClient::always(503,
            '{"error":"ErrServiceUnavailable","service_id":"wallet","third_party_id":"recipient-bank"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 1));

        try {
            $client->cancel(4021);
            self::fail('expected MilkywayServiceUnavailableException');
        } catch (MilkywayServiceUnavailableException $ex) {
            self::assertNotInstanceOf(MilkywayRecipientBankException::class, $ex);
            self::assertSame(503, $ex->getStatusCode());
            self::assertSame(StatusReason::SERVICE_UNAVAILABLE, $ex->getErrorCode());
            self::assertSame('wallet', $ex->getServiceId());
            self::assertSame('recipient-bank', $ex->getThirdPartyId());
        }

        self::assertSame(2, $mock->callCount());
    }

    public function testCancel502MapsToRecipientBankAfterRetries(): void
    {
        $mock = new MockHttpClient(static function (int $i, RequestInterface $r): ResponseInterface {
            return MockHttpClient::response(502, '{"error":"ErrRecipientBankError"}');
        });
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 1));

        try {
            $client->cancel(4021);
            self::fail('expected MilkywayRecipientBankException');
        } catch (MilkywayRecipientBankException $ex) {
            self::assertSame(502, $ex->getStatusCode());
            self::assertNull($ex->getServiceId());
            self::assertNull($ex->getThirdPartyId());
        }

        self::assertSame(2, $mock->callCount());
    }

    public function testCancel502ThenSuccessRecovers(): void
    {
        $mock = new MockHttpClient(static function (int $i, RequestInterface $r): ResponseInterface {
            return $i < 1
                ? MockHttpClient::response(502, '{"error":"ErrRecipientBankError"}')
                : MockHttpClient::response(200, '{"status":2}');
        });
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 3));

        self::assertSame(TransactionStatus::Cancelled, $client->cancel(4021)->status);
        self::assertSame(2, $mock->callCount());
    }

    public function testValidationExceptionExposesField(): void
    {
        $mock = MockHttpClient::always(400, '{"error":"ErrRecipientInvalid","field":"recipient_id"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        try {
            $client->precheck($this->newPrecheck());
            self::fail('expected MilkywayValidationException');
        } catch (MilkywayValidationException $ex) {
            self::assertSame('recipient_id', $ex->getField());
            self::assertSame(StatusReason::RECIPIENT_INVALID, $ex->getErrorCode());
        }
        self::assertSame(1, $mock->callCount());
    }

    public function testValidationExceptionFieldIsNullWhenAbsent(): void
    {
        $mock = MockHttpClient::always(400, '{"error":"quote mismatch"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        try {
            $client->precheck($this->newPrecheck());
            self::fail('expected MilkywayValidationException');
        } catch (MilkywayValidationException $ex) {
            self::assertNull($ex->getField());
        }
    }

    public function testErrorCodeIsNullForNonJsonBody(): void
    {
        $mock = MockHttpClient::always(500, 'upstream exploded', 'text/plain');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider(), TestSupport::options(maxRetries: 0));

        try {
            $client->postcheck(1);
            self::fail('expected MilkywayServiceUnavailableException');
        } catch (MilkywayApiException $ex) {
            self::assertNull($ex->getErrorCode());
            self::assertSame('upstream exploded', $ex->getMessage());
        }
    }

    public function testStatusReasonConstantsMatchTheWireCodes(): void
    {
        self::assertSame('ErrPaymentRejected', StatusReason::PAYMENT_REJECTED);
        self::assertSame('ErrRecipientNotFound', StatusReason::RECIPIENT_NOT_FOUND);
        self::assertSame('ErrRecipientInvalid', StatusReason::RECIPIENT_INVALID);
        self::assertSame('ErrServiceUnavailable', StatusReason::SERVICE_UNAVAILABLE);
        self::assertSame('ErrPaymentCannotBeCanceled', StatusReason::PAYMENT_CANNOT_BE_CANCELED);
    }

    public function testPostcheckErrorIsTheReasonCode(): void
    {
        $mock = MockHttpClient::always(200, '{"transaction_id":4021,"status":3,"error":"ErrRecipientNotFound"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $result = $client->postcheck(4021);

        self::assertSame(TransactionStatus::Failed, $result->status);
        self::assertSame(StatusReason::RECIPIENT_NOT_FOUND, $result->error);
    }

    private function newPrecheck(): PrecheckRequest
    {
        return new PrecheckRequest(
            thirdPartyIdDebit: 'bank-beta',
            serviceId: 'card-payout',
            recipientId: 'r-1',
            amountCredit: BigDecimal::of('100'),
            currencyCredit: 'USD',
        );
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
