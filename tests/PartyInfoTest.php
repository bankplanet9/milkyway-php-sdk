<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Tests;

use Bankplanet9\MilkywayPayments\Model\IdentityDocumentType;
use Bankplanet9\MilkywayPayments\Model\PartyAddress;
use Bankplanet9\MilkywayPayments\Model\PartyInfo;
use Bankplanet9\MilkywayPayments\Model\PartyName;
use Bankplanet9\MilkywayPayments\Model\PartyType;
use Bankplanet9\MilkywayPayments\Model\PayRequest;
use Bankplanet9\MilkywayPayments\Model\PrecheckRequest;
use Bankplanet9\MilkywayPayments\Tests\Support\FakeTokenProvider;
use Bankplanet9\MilkywayPayments\Tests\Support\MockHttpClient;
use Bankplanet9\MilkywayPayments\Tests\Support\TestSupport;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class PartyInfoTest extends TestCase
{
    public function testPrecheckSerializesEveryPartyFieldUnderItsWireKey(): void
    {
        $mock = MockHttpClient::always(200, '{}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $client->precheck(new PrecheckRequest(
            thirdPartyIdDebit: 'bank-beta',
            serviceId: 'card-payout',
            recipientId: 'r-1',
            amountCredit: BigDecimal::of('50'),
            currencyCredit: 'USD',
            senderInfo: new PartyInfo(
                partyType: PartyType::Natural,
                name: new PartyName(first: 'Ivan', last: 'Petrov', full: 'Petrov Ivan Sergeevich'),
                dateOfBirth: '1990-04-18',
                identityDocumentType: IdentityDocumentType::Passport,
                identityDocumentNumber: 'AB1234567',
                identityDocumentCountry: 'TJ',
                countryOfResidence: 'AE',
                address: new PartyAddress(
                    country: 'AE',
                    city: 'Dubai',
                    street: 'Sheikh Zayed Rd',
                    building: '12',
                    postalCode: '00000',
                    line: ['Sheikh Zayed Rd 12, Dubai'],
                ),
            ),
        ));

        $body = self::body($mock);
        self::assertSame(
            '{"party_type":"natural",'
            . '"name":{"first":"Ivan","last":"Petrov","full":"Petrov Ivan Sergeevich"},'
            . '"date_of_birth":"1990-04-18",'
            . '"identity_document_type":"passport",'
            . '"identity_document_number":"AB1234567",'
            . '"identity_document_country":"TJ",'
            . '"country_of_residence":"AE",'
            . '"address":{"country":"AE","city":"Dubai","street":"Sheikh Zayed Rd",'
            . '"building":"12","postal_code":"00000","line":["Sheikh Zayed Rd 12, Dubai"]}}',
            self::encode($body['sender_info']),
        );
        self::assertArrayNotHasKey('recipient_info', $body);
    }

    public function testPayOmitsNullAndEmptyPartyFieldsAndEmptySubObjects(): void
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
            senderInfo: new PartyInfo(
                name: new PartyName(first: 'Ivan', last: 'Petrov', full: ''),
                identityDocumentType: IdentityDocumentType::NationalId,
                identityDocumentNumber: 'A-1',
                countryOfResidence: '',
                address: new PartyAddress(line: []),
            ),
            recipientInfo: new PartyInfo(
                name: new PartyName(),
                address: new PartyAddress(country: 'TJ', city: 'Dushanbe', street: ''),
            ),
        ), idempotencyKey: 'k');

        $raw = (string) $mock->requests[0]->getBody();
        self::assertStringContainsString(
            '"sender_info":{"party_type":"natural","name":{"first":"Ivan","last":"Petrov"},'
            . '"identity_document_type":"national_id","identity_document_number":"A-1"}',
            $raw,
        );
        self::assertStringContainsString(
            '"recipient_info":{"party_type":"natural","address":{"country":"TJ","city":"Dushanbe"}}',
            $raw,
        );
        self::assertStringNotContainsString('"data"', $raw);
        self::assertStringNotContainsString('""', $raw);
        self::assertStringNotContainsString('[]', $raw);
        self::assertStringNotContainsString('{}', $raw);
    }

    public function testDataIsSentAlongsidePartyInfo(): void
    {
        $mock = MockHttpClient::always(200, '{}');
        $client = TestSupport::buildClient($mock, new FakeTokenProvider());

        $client->precheck(new PrecheckRequest(
            thirdPartyIdDebit: 'bank-beta',
            serviceId: 'card-payout',
            recipientId: 'r-1',
            amountCredit: BigDecimal::of('50'),
            currencyCredit: 'USD',
            data: ['test_outcome' => 'done'],
            senderInfo: new PartyInfo(name: new PartyName(full: 'Petrov Ivan')),
        ));

        $body = self::body($mock);
        self::assertSame('{"party_type":"natural","name":{"full":"Petrov Ivan"}}', self::encode($body['sender_info']));
        self::assertSame(['test_outcome' => 'done'], $body['data']);
    }

    public function testEnumsCarryTheWireVocabulary(): void
    {
        self::assertSame(
            ['passport', 'national_id', 'driver_license', 'residence_permit', 'tax_id', 'social_security', 'other'],
            array_map(static fn (IdentityDocumentType $t): string => $t->value, IdentityDocumentType::cases()),
        );
        self::assertSame(['natural', 'legal'], array_map(static fn (PartyType $t): string => $t->value, PartyType::cases()));
    }

    /** @return array<string, mixed> */
    private static function body(MockHttpClient $mock): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $mock->requests[0]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
