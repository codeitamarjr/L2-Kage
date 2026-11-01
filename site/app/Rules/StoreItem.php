<?php

namespace App\Rules;

use App\Services\L2j\GameData;
use App\Services\StoreContents;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A product's or gift box's item: an item id that exists on this server, or a grade key from config l2j.store.grades
 * ("enchant_weapon", ...), which becomes the scroll for the receiving character's grade.
 */
class StoreItem implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $item = StoreContents::itemKey((string) $value);

        if (is_int($item) ? ! app(GameData::class)->itemExists($item) : ! in_array($item, StoreContents::gradeKeys(), true)) {
            $fail('Use an item id that exists on this server, or one of: '.implode(', ', StoreContents::gradeKeys()).'.');
        }
    }
}
