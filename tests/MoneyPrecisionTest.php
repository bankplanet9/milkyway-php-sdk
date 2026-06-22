<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\Internal\Json;
use Bankplanet9\MilkywayPayments\Model\PrecheckResult;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class MoneyPrecisionTest extends TestCase
{
    public function testDecimalStringExtractsLiteralTokenVerbatim(): void
    {
        // A value that a binary float cannot represent exactly.
        $body = '{"amount_debit":1267500.10,"rate":0.1}';

        self::assertSame('1267500.10', Json::decimalString($body, 'amount_debit'));
        self::assertSame('0.1', Json::decimalString($body, 'rate'));
    }

    public function testDecimalStringHandlesHighPrecisionWithoutLoss(): void
    {
        $body = '{"rate":12675.123456789012345}';
        $literal = Json::decimalString($body, 'rate');

        self::assertSame('12675.123456789012345', $literal);
        self::assertSame('12675.123456789012345', (string) BigDecimal::of($literal));
    }

    public function testDecimalStringAcceptsQuotedNumbers(): void
    {
        $body = '{"commission":"1.50"}';
        self::assertSame('1.50', Json::decimalString($body, 'commission'));
    }

    public function testMissingDecimalFieldDefaultsToZero(): void
    {
        $result = PrecheckResult::fromJson('{"third_party_id":"bank-beta"}');

        self::assertTrue(BigDecimal::zero()->isEqualTo($result->rate));
        self::assertSame('bank-beta', $result->thirdPartyId);
        self::assertNull($result->currencyDebit);
    }

    public function testExtractErrorMessageFromJson(): void
    {
        self::assertSame('boom', Json::extractErrorMessage('{"error":"boom"}'));
    }

    public function testExtractErrorMessageFromPlainText(): void
    {
        self::assertSame('recipient down', Json::extractErrorMessage('recipient down'));
    }

    public function testExtractErrorMessageEmptyReturnsNull(): void
    {
        self::assertNull(Json::extractErrorMessage('   '));
    }
}
