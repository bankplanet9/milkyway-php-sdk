<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\Auth\KeycloakTokenProvider;
use Bankplanet9\MilkywayPayments\Exception\MilkywayAuthException;
use Bankplanet9\MilkywayPayments\Tests\Support\MockHttpClient;
use Bankplanet9\MilkywayPayments\Tests\Support\TestSupport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class TokenProviderTest extends TestCase
{
    /** @var float Mutable clock shared with the provider under test. */
    private float $now = 0.0;

    private function build(MockHttpClient $mock): KeycloakTokenProvider
    {
        $this->now = 0.0;
        $factory = new Psr17Factory();
        return new KeycloakTokenProvider(
            $mock,
            $factory,
            $factory,
            TestSupport::options(),
            fn (): float => $this->now,
        );
    }

    private function tokenStub(int $expiresIn = 3600): MockHttpClient
    {
        return MockHttpClient::always(200,
            '{"access_token":"tok-' . $expiresIn . '","token_type":"Bearer","expires_in":' . $expiresIn . '}');
    }

    public function testCachesTokenAcrossCalls(): void
    {
        $mock = $this->tokenStub();
        $provider = $this->build($mock);

        $a = $provider->getAccessToken(false);
        $b = $provider->getAccessToken(false);

        self::assertSame($a, $b);
        self::assertSame(1, $mock->callCount()); // acquired once, then cached
    }

    public function testRefreshesAfterExpiryMinusSkew(): void
    {
        $mock = $this->tokenStub(3600);
        $provider = $this->build($mock);

        $provider->getAccessToken(false);

        // Advance past (expiry - 30s skew): expiresAt=3600s, threshold=3570s.
        $this->now += 3580;
        $provider->getAccessToken(false);

        self::assertSame(2, $mock->callCount());
    }

    public function testTokenStillCachedJustBeforeSkewThreshold(): void
    {
        $mock = $this->tokenStub(3600);
        $provider = $this->build($mock);

        $provider->getAccessToken(false);
        $this->now += 3560; // still before 3570 threshold
        $provider->getAccessToken(false);

        self::assertSame(1, $mock->callCount());
    }

    public function testForceRefreshBypassesCache(): void
    {
        $mock = $this->tokenStub();
        $provider = $this->build($mock);

        $provider->getAccessToken(false);
        $provider->getAccessToken(true);

        self::assertSame(2, $mock->callCount());
    }

    public function testSendsClientCredentialsForm(): void
    {
        $mock = $this->tokenStub();
        $provider = $this->build($mock);

        $provider->getAccessToken(false);

        $body = (string) $mock->requests[0]->getBody();
        self::assertStringContainsString('grant_type=client_credentials', $body);
        self::assertStringContainsString('client_id=bank-alpha', $body);
        self::assertStringContainsString('client_secret=secret', $body);
        self::assertSame('application/x-www-form-urlencoded', $mock->requests[0]->getHeaderLine('Content-Type'));
    }

    public function testFailedAcquisitionThrowsAuthException(): void
    {
        $mock = MockHttpClient::always(400, '{"error":"invalid_client"}');
        $provider = $this->build($mock);

        $this->expectException(MilkywayAuthException::class);
        $provider->getAccessToken(false);
    }

    public function testMissingAccessTokenThrowsAuthException(): void
    {
        $mock = MockHttpClient::always(200, '{"token_type":"Bearer","expires_in":3600}');
        $provider = $this->build($mock);

        $this->expectException(MilkywayAuthException::class);
        $provider->getAccessToken(false);
    }
}
