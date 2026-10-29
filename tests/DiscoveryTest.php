<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\Exception\MilkywayNotFoundException;
use Bankplanet9\MilkywayPayments\Model\IdentityDocumentType;
use Bankplanet9\MilkywayPayments\Tests\Support\FakeTokenProvider;
use Bankplanet9\MilkywayPayments\Tests\Support\MockHttpClient;
use Bankplanet9\MilkywayPayments\Tests\Support\TestSupport;
use PHPUnit\Framework\TestCase;

final class DiscoveryTest extends TestCase
{
    private const CATALOG = '{"destinations":['
        . '{"third_party_id":"zinda","partner_name":"Zinda Bank","country":"TJ","services":['
        . '{"service_id":"wallet","payout_method":"wallet_credit","recipient":"phone number",'
        . '"payout_currencies":["TJS","USD"]}]},'
        . '{"third_party_id":"bank-beta","country":"UZ","services":['
        . '{"service_id":"card-payout","payout_method":"card"}]}'
        . ']}';

    public function testDiscoveryWithoutCountryParsesCatalogAndAttachesBearer(): void
    {
        $mock = MockHttpClient::always(200, self::CATALOG);
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $destinations = $client->discovery();

        self::assertCount(2, $destinations);

        $zinda = $destinations[0];
        self::assertSame('zinda', $zinda->thirdPartyId);
        self::assertSame('Zinda Bank', $zinda->partnerName);
        self::assertSame('TJ', $zinda->country);
        self::assertCount(1, $zinda->services);

        $wallet = $zinda->services[0];
        self::assertSame('wallet', $wallet->serviceId);
        self::assertSame('wallet_credit', $wallet->payoutMethod);
        self::assertSame('phone number', $wallet->recipient);
        self::assertSame(['TJS', 'USD'], $wallet->payoutCurrencies);

        // Second destination has no partner_name and a currency-less service.
        self::assertNull($destinations[1]->partnerName);
        self::assertNull($destinations[1]->services[0]->recipient);
        self::assertNull($destinations[1]->services[0]->payoutCurrencies);

        $req = $mock->requests[0];
        self::assertSame('GET', $req->getMethod());
        self::assertStringEndsWith('/payments/v1/discovery', (string) $req->getUri());
        self::assertSame('', $req->getUri()->getQuery());
        self::assertSame('Bearer test-token', $req->getHeaderLine('Authorization'));
    }

    public function testDiscoveryDecodesPartyProfileAndToleratesItsAbsence(): void
    {
        $body = '{"destinations":[{"third_party_id":"bank-beta","services":['
            . '{"service_id":"card-payout","payout_method":"card","party_profile":{'
            . '"sender_info":{"required":["name.first","name.last","date_of_birth",'
            . '"identity_document_type","identity_document_number"],'
            . '"accepted":["name.full"],"identity_document_types":["passport"]},'
            . '"recipient_info":{"required":["name.full"]}}},'
            . '{"service_id":"wallet","payout_method":"wallet_credit"}]}]}';
        $mock = MockHttpClient::always(200, $body);
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $services = $client->discovery()[0]->services;

        $profile = $services[0]->partyProfile;
        self::assertNotNull($profile);
        self::assertNotNull($profile->senderInfo);
        self::assertSame(
            ['name.first', 'name.last', 'date_of_birth', 'identity_document_type', 'identity_document_number'],
            $profile->senderInfo->required,
        );
        self::assertSame(['name.full'], $profile->senderInfo->accepted);
        self::assertSame([IdentityDocumentType::Passport->value], $profile->senderInfo->identityDocumentTypes);
        self::assertNotNull($profile->recipientInfo);
        self::assertSame(['name.full'], $profile->recipientInfo->required);
        self::assertNull($profile->recipientInfo->accepted);
        self::assertNull($profile->recipientInfo->identityDocumentTypes);

        self::assertNull($services[1]->partyProfile);
    }

    public function testDiscoveryWithCountryAddsQuery(): void
    {
        $mock = MockHttpClient::always(200, '{"destinations":[]}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $destinations = $client->discovery('TJ');

        self::assertSame([], $destinations);
        self::assertStringContainsString('country=TJ', $mock->requests[0]->getUri()->getQuery());
    }

    public function testDiscoveryTreatsMissingCatalogAsEmptyList(): void
    {
        $mock = MockHttpClient::always(200, '{}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        self::assertSame([], $client->discovery());
    }

    public function testServiceSchemaReturnsRawSchemaAndEncodesPathSegments(): void
    {
        $schemaBody = '{"$schema":"https://json-schema.org/draft/2020-12/schema",'
            . '"type":"object","required":["passport"],'
            . '"properties":{"passport":{"type":"string"}}}';
        $mock = MockHttpClient::always(200, $schemaBody, 'application/schema+json');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $schema = $client->serviceSchema('bank beta', 'card/payout');

        self::assertSame('object', $schema['type']);
        self::assertSame(['passport'], $schema['required']);
        self::assertSame('string', $schema['properties']['passport']['type']);

        $req = $mock->requests[0];
        self::assertSame('GET', $req->getMethod());
        // Path segments are URL-encoded (space -> %20, slash -> %2F).
        self::assertStringEndsWith('/payments/v1/discovery/bank%20beta/card%2Fpayout/schema', (string) $req->getUri());
    }

    public function testServiceSchemaNotFoundMapsTo404Exception(): void
    {
        $mock = MockHttpClient::always(404, '{"error":"not found"}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $this->expectException(MilkywayNotFoundException::class);
        $client->serviceSchema('zinda', 'unknown');
    }
}
