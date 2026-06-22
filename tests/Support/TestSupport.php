<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests\Support;

use Bankplanet9\MilkywayPayments\MilkywayOptions;
use Bankplanet9\MilkywayPayments\MilkywayPaymentsClient;
use Nyholm\Psr7\Factory\Psr17Factory;

final class TestSupport
{
    public static function options(int $maxRetries = 3): MilkywayOptions
    {
        return new MilkywayOptions(
            baseUrl: 'https://milkyway.test',
            tokenUrl: 'https://keycloak.test/token',
            clientId: 'bank-alpha',
            clientSecret: 'secret',
            maxRetries: $maxRetries,
            retryBaseDelay: 0.001,
        );
    }

    /**
     * Build a client over the real auth + retry pipeline with a mock client at the
     * bottom, so retries and auth behave exactly as in production. The retry layer's
     * sleep is a no-op and its jitter is fixed, keeping tests fast and deterministic.
     */
    public static function buildClient(
        MockHttpClient $mock,
        FakeTokenProvider $tokenProvider,
        ?MilkywayOptions $options = null,
    ): MilkywayPaymentsClient {
        $opts = $options ?? self::options();
        $factory = new Psr17Factory();

        return new MilkywayPaymentsClient(
            options: $opts,
            httpClient: $mock,
            requestFactory: $factory,
            streamFactory: $factory,
            tokenProvider: $tokenProvider,
            retrySleeper: static fn (float $s) => null,
            retryJitter: static fn (): float => 0.5,
        );
    }
}
