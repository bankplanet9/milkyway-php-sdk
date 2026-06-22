<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\MilkywayOptions;
use Bankplanet9\MilkywayPayments\TransactionStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase
{
    public function testRejectsEmptyBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MilkywayOptions(baseUrl: '', tokenUrl: 't', clientId: 'c', clientSecret: 's');
    }

    public function testRejectsNegativeMaxRetries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MilkywayOptions(baseUrl: 'b', tokenUrl: 't', clientId: 'c', clientSecret: 's', maxRetries: -1);
    }

    public function testNormalizedBaseUrlHasSingleTrailingSlash(): void
    {
        $opts = new MilkywayOptions(baseUrl: 'https://x.test///', tokenUrl: 't', clientId: 'c', clientSecret: 's');
        self::assertSame('https://x.test/', $opts->normalizedBaseUrl());
    }

    public function testTransactionStatusTerminalSet(): void
    {
        self::assertTrue(TransactionStatus::Done->isTerminal());
        self::assertTrue(TransactionStatus::Cancelled->isTerminal());
        self::assertTrue(TransactionStatus::Failed->isTerminal());
        self::assertTrue(TransactionStatus::Stuck->isTerminal());
        self::assertFalse(TransactionStatus::Pending->isTerminal());
        self::assertFalse(TransactionStatus::CancelPending->isTerminal());
    }
}
