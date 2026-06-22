<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\PollOptions;
use Bankplanet9\MilkywayPayments\Tests\Support\FakeTokenProvider;
use Bankplanet9\MilkywayPayments\Tests\Support\MockHttpClient;
use Bankplanet9\MilkywayPayments\Tests\Support\TestSupport;
use Bankplanet9\MilkywayPayments\TransactionStatus;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class WaitForCompletionTest extends TestCase
{
    private function fastPoll(): PollOptions
    {
        return new PollOptions(
            initialDelay: 0.001,
            maxDelay: 0.005,
            backoffMultiplier: 2.0,
            timeout: 10.0,
        );
    }

    public function testPollsUntilTerminalStatus(): void
    {
        // pending, pending, done
        $mock = new MockHttpClient(static function (int $i, RequestInterface $r): ResponseInterface {
            $status = $i < 2 ? 0 : 1;
            return MockHttpClient::response(200, '{"transaction_id":4021,"status":' . $status . '}');
        });
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $result = $client->waitForCompletion(4021, $this->fastPoll());

        self::assertSame(TransactionStatus::Done, $result->status);
        self::assertSame(3, $mock->callCount());
    }

    public function testReturnsLastStatusWhenPollBudgetExhausted(): void
    {
        $mock = MockHttpClient::always(200, '{"transaction_id":4021,"status":0}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $shortBudget = new PollOptions(
            initialDelay: 0.005,
            maxDelay: 0.005,
            backoffMultiplier: 2.0,
            timeout: 0.02,
        );

        $result = $client->waitForCompletion(4021, $shortBudget);

        self::assertSame(TransactionStatus::Pending, $result->status);
    }

    public function testTerminalOnFirstPollDoesNotSleep(): void
    {
        $mock = MockHttpClient::always(200, '{"transaction_id":4021,"status":1}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $result = $client->waitForCompletion(4021, $this->fastPoll());

        self::assertSame(TransactionStatus::Done, $result->status);
        self::assertSame(1, $mock->callCount());
    }
}
