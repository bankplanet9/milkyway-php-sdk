<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Internal;

use Bankplanet9\MilkywayPayments\Exception\MilkywayApiException;
use Bankplanet9\MilkywayPayments\Model\RawNumber;
use JsonException;

/**
 * JSON helpers that preserve full numeric precision for monetary fields.
 *
 * The Payments API encodes money as raw JSON numbers (e.g. 1267500.00). PHP's
 * native json_decode would coerce these to binary floats and lose precision, so
 * we decode with JSON_BIGINT_AS_STRING and, crucially, re-read decimals from
 * their original textual form. Callers build BigDecimal from the string the wire
 * actually carried — never from a float.
 */
final class Json
{
    /**
     * Decode a JSON object into an associative array. Large integers are kept as
     * strings; floats remain floats here, so monetary fields must instead be read
     * via {@see self::decimalString()} which extracts the literal token from the
     * raw JSON text.
     *
     * @return array<string, mixed>
     */
    public static function decodeObject(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $e) {
            throw new MilkywayApiException(0, 'Failed to parse the API response.', $body, $e);
        }

        if (!is_array($decoded)) {
            throw new MilkywayApiException(0, 'The API returned a non-object JSON response.', $body);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Extract the *literal* numeric token for a top-level object key, as it appeared
     * in the raw JSON text, so it can be fed verbatim into BigDecimal without any
     * float round-trip. Returns null when the key is absent or null-valued.
     *
     * Only the value's textual form matters; we accept the value whether the server
     * wrote it as a JSON number (12675.00) or a JSON string ("12675.00").
     */
    public static function decimalString(string $body, string $key): ?string
    {
        // Match: "key" : <number>   (optionally quoted). Anchored to a top-level-ish key token.
        $pattern = '/"' . preg_quote($key, '/') . '"\s*:\s*("?)(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)\1/';
        if (preg_match($pattern, $body, $m) === 1) {
            return $m[2];
        }

        // Fall back to the decoded value (covers exotic encodings); may lose precision,
        // but only reached when the literal scan failed.
        $decoded = self::decodeObject($body);
        if (!array_key_exists($key, $decoded) || $decoded[$key] === null) {
            return null;
        }
        $value = $decoded[$key];
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return null;
    }

    /**
     * Encode a payload to a compact JSON string. Used for request bodies.
     *
     * {@see \Bankplanet9\MilkywayPayments\Model\RawNumber} values serialize to a
     * quoted placeholder which is then rewritten into a bare JSON number literal,
     * keeping monetary fields as full-precision unquoted numbers.
     *
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        $placeholders = self::collectRawNumbers($payload);
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new MilkywayApiException(0, 'Failed to encode the request payload.', null, $e);
        }

        foreach ($placeholders as $placeholder => $literal) {
            // Replace the quoted placeholder string with the bare numeric literal.
            $json = str_replace('"' . $placeholder . '"', $literal, $json);
        }
        return $json;
    }

    /**
     * Drop null, empty-string and empty-array members of a request object, so the
     * wire never carries `""`, `[]` or an empty nested object (e.g. a party's `name`
     * or `address`).
     *
     * @param array<string, mixed> $members
     * @return array<string, mixed>
     */
    public static function withoutEmpty(array $members): array
    {
        return array_filter($members, static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param mixed $node
     * @return array<string, string> placeholder => numeric literal
     */
    private static function collectRawNumbers(mixed $node, array &$acc = []): array
    {
        if ($node instanceof RawNumber) {
            $acc[$node->placeholder()] = $node->literal;
            return $acc;
        }
        if (is_array($node)) {
            foreach ($node as $child) {
                self::collectRawNumbers($child, $acc);
            }
        }
        return $acc;
    }

    /**
     * Pull the server's `{"error": "..."}` message out of a response body, or the
     * trimmed plain-text body when it is not JSON. Returns null when neither yields
     * a usable message.
     */
    public static function extractErrorMessage(string $body): ?string
    {
        if (trim($body) === '') {
            return null;
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // Non-JSON body (e.g. plain-text 500 from healthcheck) — use it as-is.
            return trim($body);
        }
        if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
            return $decoded['error'];
        }
        return null;
    }
}
