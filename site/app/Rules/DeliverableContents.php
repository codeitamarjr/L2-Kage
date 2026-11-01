<?php

namespace App\Rules;

use App\Services\L2j\ItemDatabase;
use App\Services\StoreContents;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A product's items as the game server will accept them, so one can't be saved that fails after a player clicks Buy: 1 to
 * 32 items in an order (WebBridge MAX_ITEMS), and 1 of each non-stackable item per entry. Buying several at once multiplies
 * the counts, so that only works when everything stacks. Validates the items list.
 */
class DeliverableContents implements ValidationRule
{
    private const MAX_ITEMS = 32;

    /**
     * @param  array<int, array<string, mixed>>  $bonus
     */
    public function __construct(private array $bonus, private int $maxPerOrder) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $items = array_values((array) $value);
        $total = count($items) + ($this->bonus === [] ? 0 : 1);

        if ($total < 1 || $total > self::MAX_ITEMS) {
            $fail('A product needs 1 to '.self::MAX_ITEMS." items, counting the bonus as one; this has {$total}.");

            return;
        }

        $database = app(ItemDatabase::class);

        foreach ([...$items, ...array_values($this->bonus)] as $entry) {
            $item = StoreContents::itemKey((string) ($entry['item'] ?? ''));

            // Grade keys are scrolls, which stack.
            if (! is_int($item) || $database->isStackable($item) !== false) {
                continue;
            }

            if ((int) ($entry['count'] ?? 1) > 1) {
                $fail("Item {$item} doesn't stack, so its count must be 1. Add it as several entries to send more than one.");
            }

            if ($this->maxPerOrder > 1) {
                $fail("Item {$item} doesn't stack, so this can't be bought in quantity. Set the limit per order to 1.");
            }
        }
    }
}
