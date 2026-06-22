# MilkyWay Payments SDK for PHP

[![Packagist](https://img.shields.io/packagist/v/bankplanet9/milkyway-payments.svg?logo=packagist)](https://packagist.org/packages/bankplanet9/milkyway-payments)
[![CI](https://github.com/bankplanet9/milkyway-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/bankplanet9/milkyway-php-sdk/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Official PHP client for the **MilkyWay Payments API** (`/payments/v1`) — the
partner-facing API banks use to initiate, quote, track, and cancel cross-bank
payments.

Batteries included:

- **Keycloak client-credentials auth** with in-memory token caching and automatic
  refresh (plus a one-shot refresh-and-replay on `401`).
- **Retries** with exponential backoff + jitter on transient failures (5xx, 408,
  network), while deterministic errors (400/401/402/404) are never retried.
- **Typed models & exceptions** — money is [`brick/math`](https://github.com/brick/math)
  `BigDecimal` (never `float`), status is a backed `enum`, and each HTTP error maps
  to a specific exception type.
- **Client-agnostic** — built on PSR-18 (HTTP client) and PSR-17 (factories) with
  [`php-http/discovery`](https://docs.php-http.org/en/latest/discovery.html)
  auto-discovery, so you can plug in Guzzle, Symfony HttpClient, or anything else.

Requires **PHP 8.1+**.

## Install

```bash
composer require bankplanet9/milkyway-payments
```

You also need a PSR-18 client and PSR-17 factories. If your project does not
already provide them, install a pair the SDK can auto-discover, e.g.:

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

## Quick start

```php
use Bankplanet9\MilkywayPayments\MilkywayOptions;
use Bankplanet9\MilkywayPayments\MilkywayPaymentsClient;
use Bankplanet9\MilkywayPayments\Model\PayRequest;
use Bankplanet9\MilkywayPayments\Model\PrecheckRequest;
use Brick\Math\BigDecimal;

$client = new MilkywayPaymentsClient(new MilkywayOptions(
    baseUrl:      'https://milkyway.stage.planet9.ae',
    tokenUrl:     'https://keycloak.ac8o.planet9.ae/realms/planet9-stage/protocol/openid-connect/token',
    clientId:     'your-client-id',      // issued to your institution
    clientSecret: 'your-client-secret',
));

// 1. Is the recipient bank's service online?
$client->healthcheck('bank-beta', 'card-payout');

// 2. Quote the payment (FX markup + commission applied here).
$quote = $client->precheck(new PrecheckRequest(
    thirdPartyIdDebit: 'bank-beta',
    serviceId:         'card-payout',
    recipientId:       'recipient-9999',
    amountCredit:      BigDecimal::of('100.00'),
    currencyCredit:    'USD',
));
printf("Rate %s, debit %s %s, commission %s\n",
    $quote->rate, $quote->amountDebit, $quote->currencyDebit, $quote->commission);

// 3. Initiate the payment. Pass an idempotency key so retries are safe.
$transactionId = $client->pay(new PayRequest(
    thirdPartyIdDebit: 'bank-beta',
    serviceId:         'card-payout',
    senderId:          'sender-0001',
    recipientId:       'recipient-9999',
    amountCredit:      BigDecimal::of('100.00'),
    currencyCredit:    'USD',
    data:              ['passport' => 'AA1234567'],
), idempotencyKey: bin2hex(random_bytes(16)));

// 4. Poll until the payment reaches a terminal status.
$result = $client->waitForCompletion($transactionId);
printf("Final status: %s\n", $result->status->name);
```

### Bring your own HTTP client

```php
$client = new MilkywayPaymentsClient(
    options:    $options,
    httpClient: $myPsr18Client,          // used for API calls
    // tokenHttpClient: a SEPARATE plain client for the token endpoint (must not be
    //                  wrapped in retry/auth); defaults to its own discovered client.
    requestFactory: $myPsr17RequestFactory,
    streamFactory:  $myPsr17StreamFactory,
);
```

## Money & precision

All monetary fields (`amountCredit`, `amountDebit`, `rate`, `commission`) are
`Brick\Math\BigDecimal`. The API encodes money as raw JSON numbers; the SDK reads
those values from their **literal textual form** and builds `BigDecimal` from the
string, so no binary-float rounding ever touches your amounts — and the original
scale (e.g. `100.00`) is preserved on the way out, too.

## The `data` field

Each service requires extra per-partner fields (sender name, document number,
birthday, …) in the `data` array. **Which keys are required depends on your
`serviceId` and the recipient bank** — look them up in the Услуги registry at
<https://milkyway-docs.stage.planet9.ae>. The server validates `data` against the
service's JSON Schema during precheck, so a missing field is rejected before any
money moves.

## Errors

Every API error throws a subclass of `MilkywayApiException` (carrying
`getStatusCode()` and the server's message via `getMessage()` / `getResponseBody()`):

| HTTP | Exception | Meaning |
| --- | --- | --- |
| 400 | `MilkywayValidationException` | Bad request (invalid amount, missing field, unresolvable FX rate). |
| 401 | `MilkywayAuthException` | Token missing/invalid (also thrown if token acquisition fails). |
| 402 | `MilkywayExposureBlockedException` | Payment would breach a block-action exposure limit. |
| 404 | `MilkywayNotFoundException` | Transaction not found or not owned by your institution. |
| 5xx | `MilkywayServiceUnavailableException` | API or downstream recipient unavailable (retried automatically first). |

## Retries & idempotency

Transient failures (5xx, 408, network exceptions) are retried automatically with
exponential backoff + jitter, tunable via `MilkywayOptions`
(`maxRetries`, `retryBaseDelay`, `requestTimeout`). Deterministic 4xx responses are
never retried.

**`pay()` is only auto-retried when you supply an `idempotencyKey`** — without one,
a retry could create a duplicate payment, so the SDK sends it exactly once.

## Configuration

| Option | Default | Purpose |
| --- | --- | --- |
| `baseUrl` | — (required) | Payments API base URL. |
| `tokenUrl` | — (required) | Keycloak token endpoint. |
| `clientId` / `clientSecret` | — (required) | Your institution's credentials. |
| `scope` | none | Optional OAuth scope. |
| `tokenRefreshSkew` | 30s | Refresh this long before token expiry. |
| `requestTimeout` | 30s | Per-attempt request timeout (enforce via your HTTP client). |
| `maxRetries` | 3 | Max transient-failure retries. |
| `retryBaseDelay` | 0.5s | Base delay for exponential backoff. |

> Note: per-attempt timeout is honored by the underlying PSR-18 client — configure
> it on the client you pass in (e.g. Guzzle `timeout`), since PSR-18 has no portable
> timeout API.

## Building from source

```bash
composer install
composer test        # phpunit
composer lint        # php -l over all sources
```

## Releasing

Releases are **fully automated** by [semantic-release](https://semantic-release.gitbook.io/)
on every push to `main`:

1. Conventional commits are analysed (`feat:` → minor, `fix:`/`perf:` → patch,
   `!` / `BREAKING CHANGE` → major). No releasable commits → no release.
2. A GitHub release + `vX.Y.Z` tag is created with generated notes.
3. **Packagist** picks up the new tag automatically and publishes the new version.

There is **no build artifact and no registry token** for PHP — the git tag *is* the
release, and Packagist syncs from it via a GitHub webhook.

### One-time Packagist setup (maintainers)

Do this once so step 3 above works:

1. Sign in at <https://packagist.org> and **Submit** the repository URL
   `https://github.com/bankplanet9/milkyway-php-sdk` to register the
   `bankplanet9/milkyway-payments` package.
2. Enable auto-updates so each tag syncs automatically. Easiest: install the
   **Packagist** GitHub application (Packagist → your package → *Settings* shows the
   exact webhook/hook instructions), or add the GitHub service hook with your
   Packagist API token. Once connected, every `vX.Y.Z` tag pushed by semantic-release
   appears on Packagist within seconds.

No secrets are stored in CI; the `release` job only needs the default `GITHUB_TOKEN`.

## License

MIT — see [LICENSE](LICENSE). Copyright (c) 2026 Planet9.
