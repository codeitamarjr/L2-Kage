<?php

namespace App\Services;

use App\Services\L2j\GameData;

/**
 * What's inside a product: items, each an item id or a grade key (which becomes the scroll for the receiving character's
 * equipment grade, see config l2j.store.grades), plus optional bonus items of which one is rolled by weight.
 * The admin form saves every field as text, so everything passes through normalize() first.
 */
class StoreContents
{
    public function __construct(private GameData $gameData) {}

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     * @param  array<int, array<string, mixed>>|null  $bonus
     * @return array{items: list<array{item: int|string, count: int, enchant: int}>, bonus: list<array{item: int|string, count: int, enchant: int, weight: int}>}
     */
    public function normalize(?array $items, ?array $bonus): array
    {
        $entry = fn (array $entry): array => [
            'item' => self::itemKey($entry['item']),
            'count' => (int) ($entry['count'] ?? 1),
            'enchant' => (int) ($entry['enchant'] ?? 0),
        ];

        return [
            'items' => array_map($entry, array_values($items ?? [])),
            'bonus' => array_map(fn (array $item): array => [...$entry($item), 'weight' => (int) ($item['weight'] ?? 1)], array_values($bonus ?? [])),
        ];
    }

    /**
     * What a character of this level receives: every item, then one bonus rolled by weight for each of quantity, with
     * equal bonuses added together.
     *
     * @param  array{items: list<array<string, mixed>>, bonus: list<array<string, mixed>>}  $contents
     * @return list<array{id: int, name: string, count: int, enchant: int, bonus: bool}>
     */
    public function roll(array $contents, int $level, int $quantity = 1): array
    {
        $sent = array_map(fn (array $entry): array => $this->sent($entry, $level, $entry['count'] * $quantity, false), $contents['items']);
        $bonuses = [];

        if ($contents['bonus'] !== []) {
            for ($i = 0; $i < $quantity; $i++) {
                $won = $this->sent($this->pick($contents['bonus']), $level, null, true);
                $key = "{$won['id']}:{$won['enchant']}";
                $bonuses[$key] = isset($bonuses[$key]) ? [...$won, 'count' => $bonuses[$key]['count'] + $won['count']] : $won;
            }
        }

        return [...$sent, ...array_values($bonuses)];
    }

    /**
     * The contents as a product's page lists them: each bonus with its chance.
     *
     * @param  array{items: list<array<string, mixed>>, bonus: list<array<string, mixed>>}  $contents
     * @return array{items: list<array{id: int|null, name: string, count: int, enchant: int}>, bonus: list<array{id: int|null, name: string, count: int, enchant: int, chance: float}>}
     */
    public function describe(array $contents): array
    {
        $total = array_sum(array_column($contents['bonus'], 'weight'));

        return [
            'items' => array_map(fn (array $entry): array => $this->described($entry), $contents['items']),
            'bonus' => array_map(fn (array $entry): array => [
                ...$this->described($entry),
                'chance' => $total === 0 ? 0.0 : round($entry['weight'] / $total * 100, 1),
            ], $contents['bonus']),
        ];
    }

    /**
     * @param  array{items: list<array<string, mixed>>, bonus: list<array<string, mixed>>}  $contents
     */
    public function hasGradeItems(array $contents): bool
    {
        return collect([...$contents['items'], ...$contents['bonus']])->contains(fn (array $entry): bool => is_string($entry['item']));
    }

    /**
     * What was rolled in the in-game action's format ("itemId:count:enchant" entries joined by ";").
     *
     * @param  list<array{id: int, count: int, enchant: int}>  $items
     */
    public function encode(array $items): string
    {
        return implode(';', array_map(fn (array $item): string => "{$item['id']}:{$item['count']}:{$item['enchant']}", $items));
    }

    /**
     * An item id as a number ("57" -> 57), or a grade key as it is ("enchant_weapon").
     */
    public static function itemKey(int|string $item): int|string
    {
        return is_numeric($item) ? (int) $item : $item;
    }

    /**
     * The keys an item can use instead of an id.
     *
     * @return list<string>
     */
    public static function gradeKeys(): array
    {
        $grades = config('l2j.store.grades');

        return array_values(array_diff(array_keys((array) reset($grades)), ['from']));
    }

    /**
     * @param  list<array<string, mixed>>  $bonus
     * @return array<string, mixed>
     */
    private function pick(array $bonus): array
    {
        $ticket = random_int(1, max(1, (int) array_sum(array_column($bonus, 'weight'))));

        foreach ($bonus as $entry) {
            $ticket -= $entry['weight'];

            if ($ticket <= 0) {
                return $entry;
            }
        }

        return end($bonus);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{id: int, name: string, count: int, enchant: int, bonus: bool}
     */
    private function sent(array $entry, int $level, ?int $count, bool $bonus): array
    {
        $id = is_string($entry['item']) ? $this->grade($level)[$entry['item']] : $entry['item'];

        return ['id' => $id, 'name' => $this->gameData->item($id)['name'], 'count' => $count ?? $entry['count'], 'enchant' => $entry['enchant'], 'bonus' => $bonus];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{id: int|null, name: string, count: int, enchant: int}
     */
    private function described(array $entry): array
    {
        $isGrade = is_string($entry['item']);

        return [
            'id' => $isGrade ? null : $entry['item'],
            'name' => $isGrade ? $this->gradeLabel($entry['item']) : $this->gameData->item($entry['item'])['name'],
            'count' => $entry['count'],
            'enchant' => $entry['enchant'],
        ];
    }

    /**
     * The scrolls for a level's equipment grade.
     *
     * @return array<string, int>
     */
    private function grade(int $level): array
    {
        $grades = config('l2j.store.grades');
        $match = reset($grades);

        foreach ($grades as $grade) {
            if ($level >= $grade['from']) {
                $match = $grade;
            }
        }

        return $match;
    }

    private function gradeLabel(string $key): string
    {
        return match ($key) {
            'enchant_weapon' => __('Scroll: Enchant Weapon (your grade)'),
            'enchant_armor' => __('Scroll: Enchant Armor (your grade)'),
            'blessed_enchant_weapon' => __('Protecting Scroll: Enchant Weapon (your grade)'),
            default => $key,
        };
    }
}
