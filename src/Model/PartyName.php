<?php

declare(strict_types=1);

namespace Bankplanet9\MilkywayPayments\Model;

use Bankplanet9\MilkywayPayments\Internal\Json;

/** A natural person's name. Each part is optional and at most 256 characters. */
final class PartyName
{
    /**
     * @param string|null $first Given name.
     * @param string|null $last  Family name.
     * @param string|null $full  The name as written in the document, family name first,
     *                           with a patronymic if any (e.g. Petrov Ivan Sergeevich).
     */
    public function __construct(
        public readonly ?string $first = null,
        public readonly ?string $last = null,
        public readonly ?string $full = null,
    ) {
    }

    /**
     * The wire object with null and empty parts left out; an empty array when no part
     * is set.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return Json::withoutEmpty([
            'first' => $this->first,
            'last' => $this->last,
            'full' => $this->full,
        ]);
    }
}
