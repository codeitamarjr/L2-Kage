<?php

namespace App\Rules;

use App\Services\L2j\ItemDatabase;
use App\Services\StoreContents;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Adena price at or above the sum of the items' list prices in the datapack, so a product can't be bought and resold to
 * an NPC for a profit. Validates the price count; other currencies have no list price to compare with.
 */
class AboveResalePrice implements ValidationRule
{
    public const ADENA = 57;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(private ?int $priceItem, private array $items) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->priceItem !== self::ADENA) {
            return;
        }

        $database = app(ItemDatabase::class);
        $worth = 0;

        foreach ($this->items as $entry) {
            $item = StoreContents::itemKey((string) ($entry['item'] ?? ''));
            $worth += is_int($item) ? (int) $database->listPrice($item) * (int) ($entry['count'] ?? 1) : 0;
        }

        if ((int) $value < $worth) {
            $fail('Its items list for '.number_format($worth).' Adena, so at this price it could be bought and resold to an NPC for a profit.');
        }
    }
}
