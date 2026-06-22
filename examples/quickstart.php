<?php

declare(strict_types=1);

/**
 * Runnable example. From the repo root:
 *   composer install
 *   php examples/quickstart.php
 *
 * Set the credentials below (or read them from your environment) first.
 */

require __DIR__ . '/../vendor/autoload.php';

use Bankplanet9\MilkywayPayments\Exception\MilkywayApiException;
use Bankplanet9\MilkywayPayments\MilkywayOptions;
use Bankplanet9\MilkywayPayments\MilkywayPaymentsClient;
use Bankplanet9\MilkywayPayments\Model\PayRequest;
use Bankplanet9\MilkywayPayments\Model\PrecheckRequest;
use Brick\Math\BigDecimal;

$client = new MilkywayPaymentsClient(new MilkywayOptions(
    baseUrl:      getenv('MILKYWAY_BASE_URL') ?: 'https://milkyway.stage.planet9.ae',
    tokenUrl:     getenv('MILKYWAY_TOKEN_URL') ?: 'https://keycloak.ac8o.planet9.ae/realms/planet9-stage/protocol/openid-connect/token',
    clientId:     getenv('MILKYWAY_CLIENT_ID') ?: 'your-client-id',
    clientSecret: getenv('MILKYWAY_CLIENT_SECRET') ?: 'your-client-secret',
));

try {
    echo 'Health: ' . $client->healthcheck('bank-beta', 'card-payout') . PHP_EOL;

    $quote = $client->precheck(new PrecheckRequest(
        thirdPartyIdDebit: 'bank-beta',
        serviceId:         'card-payout',
        recipientId:       'recipient-9999',
        amountCredit:      BigDecimal::of('100.00'),
        currencyCredit:    'USD',
    ));
    printf("Rate %s, debit %s %s, commission %s\n",
        $quote->rate, $quote->amountDebit, $quote->currencyDebit, $quote->commission);

    $transactionId = $client->pay(new PayRequest(
        thirdPartyIdDebit: 'bank-beta',
        serviceId:         'card-payout',
        senderId:          'sender-0001',
        recipientId:       'recipient-9999',
        amountCredit:      BigDecimal::of('100.00'),
        currencyCredit:    'USD',
        data:              ['passport' => 'AA1234567'],
    ), idempotencyKey: bin2hex(random_bytes(16)));

    $result = $client->waitForCompletion($transactionId);
    printf("Transaction %d final status: %s\n", $result->transactionId, $result->status->name);
} catch (MilkywayApiException $e) {
    fprintf(STDERR, "API error (HTTP %d): %s\n", $e->getStatusCode(), $e->getMessage());
    exit(1);
}
