<?php

namespace App\Services\L2j;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The public item database (/items) over the itemdb_* tables (see the itemdb:import command).
 *
 * Drop and spoil chances are worked out the way the game server rolls them (L2J Mobius' drop groups and rate
 * multipliers): each item's chance is multiplied by the rate that applies to it (per-item rate, herb,
 * raid boss, or the drop / spoil rate), then by its group's chance. When a group's total passes 100%, the group always
 * drops and the extra becomes more of the item (precise drop calculation). Rates are read live from the config.
 * Not included: champion bonuses and the penalty for killing monsters far below your level.
 */
class ItemDatabase
{
    public const PER_PAGE = 40;

    public const CATEGORIES = ['weapon', 'armor', 'jewelry', 'accessory', 'etc'];

    public const GRADES = ['D', 'C', 'B', 'A', 'S', 'R', 'R99'];

    /** Most "dropped by" / "spoiled from" rows shown on an item page (the best chances first). */
    private const MAX_SOURCES = 200;

    /** @var array<string, mixed>|null */
    private ?array $rates = null;

    public function __construct(private GameConfig $config) {}

    public function isLoaded(): bool
    {
        return DB::table('itemdb_meta')->where('key', 'hash')->exists();
    }

    /**
     * Whether an item stacks in a bag and a mail, or null when the database doesn't know the item.
     */
    public function isStackable(int $id): ?bool
    {
        $details = DB::table('itemdb_items')->where('id', $id)->value('details');

        return $details === null ? null : (bool) (json_decode($details, true)['flags']['stackable'] ?? false);
    }

    /**
     * What an NPC shop lists an item for, in Adena, or null when the database doesn't know the item.
     */
    public function listPrice(int $id): ?int
    {
        $price = DB::table('itemdb_items')->where('id', $id)->value('price');

        return $price === null ? null : (int) $price;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function search(string $term, ?string $category, ?string $grade): LengthAwarePaginator
    {
        $term = trim($term);

        return DB::table('itemdb_items')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    // Every word must appear, in any order: "kamael teleport scroll" finds "Kamael Village Teleportation Scroll".
                    $query->where(function ($query) use ($term): void {
                        foreach (preg_split('/\s+/', $term) as $word) {
                            $query->where('name', 'like', '%'.addcslashes($word, '%_\\').'%');
                        }
                    });

                    if (ctype_digit($term)) {
                        $query->orWhere('id', (int) $term);
                    }
                });
            })
            ->when(in_array($category, self::CATEGORIES, true), fn ($query) => $query->where('category', $category))
            ->when($grade === 'none', fn ($query) => $query->whereNull('grade'))
            ->when(in_array($grade, self::GRADES, true), fn ($query) => $query->where('grade', $grade))
            // Exact name first, then names starting with the search, then the rest.
            ->when($term !== '', fn ($query) => $query->orderByRaw(
                'CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(name) LIKE ? THEN 1 ELSE 2 END',
                [mb_strtolower($term), addcslashes(mb_strtolower($term), '%_\\').'%'],
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (object $item): array => $this->summary($item));
    }

    /**
     * Everything the item page shows, or null for an unknown id.
     *
     * @return array<string, mixed>|null
     */
    public function item(int $id): ?array
    {
        $item = DB::table('itemdb_items')->find($id);

        if ($item === null) {
            return null;
        }

        $details = json_decode($item->details, true);

        return [
            ...$this->summary($item),
            'weight' => (int) $item->weight,
            'price' => (int) $item->price,
            'crystals' => $item->crystals === null ? null : (int) $item->crystals,
            'soulshots' => $details['ss'],
            'spiritshots' => $details['sps'],
            'mpConsume' => $details['mp'],
            'flags' => $details['flags'],
            'stats' => $details['stats'],
            'drops' => $this->sources($id, 'drop'),
            'spoils' => $this->sources($id, 'spoil'),
            'merchants' => $this->merchants($id),
            'craftedBy' => $this->recipes('product_id', $id),
            'usedIn' => $this->recipes('id', DB::table('itemdb_recipe_ingredients')->where('item_id', $id)->pluck('recipe_id')->all()),
            'teaches' => $this->recipes('recipe_item_id', $id),
        ];
    }

    /** Readable names for item kinds in descriptions (the rest are capitalised as they are). */
    private const KIND_NAMES = [
        'ancientsword' => 'ancient sword', 'dualdagger' => 'dual daggers', 'dual' => 'dual swords', 'dualfist' => 'fists',
        'pole' => 'polearm', 'chest' => 'upper body armor', 'legs' => 'lower body armor', 'fullarmor' => 'full armor',
        'onepiece' => 'full armor', 'head' => 'helmet', 'feet' => 'boots', 'neck' => 'necklace', 'rear;lear' => 'earring',
        'rfinger;lfinger' => 'ring', 'hair' => 'hair accessory', 'hair2' => 'hair accessory', 'hairall' => 'hair accessory',
        'deco1' => 'talisman', 'lbracelet' => 'bracelet', 'rbracelet' => 'bracelet', 'waist' => 'belt', 'back' => 'cloak',
        'underwear' => 'shirt', 'adena' => 'currency', 'other' => 'item', 'etc' => 'weapon',
    ];

    /**
     * A one-line summary for search results: "Kukuri: D-grade dagger. P. Atk. 56, M. Atk. 39. Dropped by ...".
     *
     * @param  array<string, mixed>  $item  from item()
     */
    public function description(array $item): string
    {
        $kind = __(self::KIND_NAMES[$item['kind']] ?? str_replace('_', ' ', strtolower($item['kind'])));
        $parts = [$item['grade']
            ? __(':name: :grade-grade :kind.', ['name' => $item['name'], 'grade' => $item['grade'], 'kind' => $kind])
            : __(':name: :kind.', ['name' => $item['name'], 'kind' => $kind])];

        $labels = ['pAtk' => 'P. Atk.', 'mAtk' => 'M. Atk.', 'pDef' => 'P. Def.', 'mDef' => 'M. Def.', 'pAtkSpd' => 'Atk. Spd.'];
        $stats = [];

        foreach ($labels as $key => $label) {
            if (isset($item['stats'][$key]) && $item['stats'][$key]['op'] === 'set') {
                $stats[] = "{$label} {$item['stats'][$key]['val']}";
            }
        }

        if ($stats) {
            $parts[] = implode(', ', $stats).'.';
        }

        if ($item['drops']['total']) {
            $top = $item['drops']['rows'][0];
            $chance = $top['chance'] >= 1 ? round($top['chance'], 1) : round($top['chance'], 3);
            $more = $item['drops']['total'] - 1;
            $parts[] = $more
                ? __('Dropped by :npc (:chance%) and :more more.', ['npc' => $top['npc']['name'], 'chance' => $chance, 'more' => $more])
                : __('Dropped by :npc (:chance%).', ['npc' => $top['npc']['name'], 'chance' => $chance]);
        }

        if ($item['spoils']['total']) {
            $parts[] = trans_choice('Spoiled from :count monster.|Spoiled from :count monsters.', $item['spoils']['total']);
        }

        if ($item['merchants']) {
            $parts[] = trans_choice('Sold by :count merchant.|Sold by :count merchants.', count($item['merchants']));
        }

        if ($item['craftedBy']) {
            $parts[] = __('Craftable.');
        }

        return implode(' ', $parts);
    }

    /**
     * The rates that apply to drops, for the page header.
     *
     * @return array{drop: float, spoil: float, raid: float, adenaAmount: float}
     */
    public function rateSummary(): array
    {
        $rates = $this->rates();

        return [
            'drop' => $rates['drop']['chance'],
            'spoil' => $rates['spoil']['chance'],
            'raid' => $rates['raid']['chance'],
            'adenaAmount' => $rates['byItem']['amount'][57] ?? $rates['drop']['amount'],
        ];
    }

    /**
     * Name, kind, grade and icon of several items at once, keyed by id (for item lists elsewhere on the site).
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function summaries(array $ids): array
    {
        return DB::table('itemdb_items')->whereIn('id', array_unique($ids))->get()
            ->mapWithKeys(fn (object $item): array => [(int) $item->id => $this->summary($item)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(object $item): array
    {
        $icon = $item->icon ? preg_replace('/^icon\./i', '', $item->icon) : null;

        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'type' => $item->type,
            'kind' => $item->kind,
            'category' => $item->category,
            'grade' => $item->grade,
            // Real icons go in public/assets/icons/<name>.webp (see README); until then the page draws one.
            'icon' => $icon && is_file(public_path("assets/icons/{$icon}.webp")) ? "/assets/icons/{$icon}.webp" : null,
        ];
    }

    /**
     * Monsters that drop or spoil the item, with chances and amounts at this server's rates.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private function sources(int $itemId, string $kind): array
    {
        $rows = DB::table('itemdb_drops')->where('item_id', $itemId)->where('kind', $kind)->get();

        if ($rows->isEmpty()) {
            return ['rows' => [], 'total' => 0];
        }

        $groups = DB::table('itemdb_drops')->whereIn('group_id', $rows->pluck('group_id')->unique())->get()->groupBy('group_id');
        $npcs = DB::table('itemdb_npcs')->whereIn('id', $rows->pluck('npc_id')->unique())->get()->keyBy('id');
        $herbs = $this->herbIds($groups->flatten(1)->pluck('item_id')->unique());

        $sources = $rows->map(function (object $row) use ($groups, $npcs, $herbs): ?array {
            $npc = $npcs->get($row->npc_id);

            if ($npc === null) {
                return null;
            }

            $roll = $this->roll($row, $groups->get($row->group_id), in_array($npc->type, ['RaidBoss', 'GrandBoss'], true), $herbs);

            return [
                'npc' => ['id' => (int) $npc->id, 'name' => $npc->name, 'title' => $npc->title, 'level' => (int) $npc->level, 'type' => $npc->type],
                ...$roll,
            ];
        })->filter()
            // The datapack has look-alike NPCs (same name, level and drops under different ids): list them once.
            ->unique(fn (array $s): string => implode('|', [$s['npc']['name'], $s['npc']['level'], $s['min'], $s['max'], $s['chance']]))
            ->sortByDesc('chance')->values();

        return ['rows' => $sources->take(self::MAX_SOURCES)->all(), 'total' => $sources->count()];
    }

    /**
     * Chance (percent) and amount of one item from its group, as the server rolls it.
     *
     * @param  Collection<int, object>  $group
     * @param  list<int>  $herbs
     * @return array{chance: float, min: int, max: int}
     */
    public function roll(object $row, Collection $group, bool $raid, array $herbs = []): array
    {
        $expected = fn (object $r): float => $r->chance * $this->multiplier($r, 'chance', $raid, $herbs) * $r->group_chance / 100;
        $mine = $expected($row);
        $total = $group->count() > 1 ? $group->sum($expected) : $mine;
        $amount = $this->multiplier($row, 'amount', $raid, $herbs);
        $min = (int) ($row->min * $amount);
        $max = (int) ($row->max * $amount);

        if ($total <= 100) {
            return ['chance' => round($mine, 6), 'min' => $min, 'max' => $max];
        }

        // Over 100%: the group always drops, this item with its share, and the extra multiplies the amount.
        return ['chance' => round($mine / $total * 100, 6), 'min' => $min * (int) floor($total / 100), 'max' => $max * (int) ceil($total / 100)];
    }

    /**
     * @param  list<int>  $herbs
     */
    private function multiplier(object $row, string $what, bool $raid, array $herbs): float
    {
        $rates = $this->rates();

        return match (true) {
            isset($rates['byItem'][$what][$row->item_id]) => $rates['byItem'][$what][$row->item_id],
            in_array((int) $row->item_id, $herbs, true) => $rates['herb'][$what],
            $raid => $rates['raid'][$what],
            default => $rates[$row->kind === 'spoil' ? 'spoil' : 'drop'][$what],
        };
    }

    /**
     * @param  Collection<int, int>  $itemIds
     * @return list<int>
     */
    private function herbIds(Collection $itemIds): array
    {
        return DB::table('itemdb_items')->whereIn('id', $itemIds)->where('details', 'like', '%"herb":true%')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function merchants(int $itemId): array
    {
        return DB::table('itemdb_shop_items as s')
            ->join('itemdb_npcs as n', 'n.id', '=', 's.npc_id')
            ->where('s.item_id', $itemId)
            ->orderBy('n.name')
            ->get(['n.id', 'n.name', 'n.title'])
            ->unique('id')
            ->map(fn (object $npc): array => ['id' => (int) $npc->id, 'name' => $npc->name, 'title' => $npc->title])
            ->values()
            ->all();
    }

    /**
     * Recipes matching a column (product, recipe scroll, or a list of recipe ids), with item names filled in.
     *
     * @param  int|list<int>  $value
     * @return list<array<string, mixed>>
     */
    private function recipes(string $column, int|array $value): array
    {
        $recipes = DB::table('itemdb_recipes')->whereIn($column, (array) $value)->orderBy('level')->limit(100)->get();

        if ($recipes->isEmpty()) {
            return [];
        }

        $ids = $recipes->flatMap(fn (object $r): array => [
            $r->recipe_item_id, $r->product_id, ...array_column(json_decode($r->ingredients, true), 0),
            ...($r->rare ? [json_decode($r->rare, true)['id']] : []),
        ])->unique();
        $items = DB::table('itemdb_items')->whereIn('id', $ids)->get()->keyBy('id')->map(fn (object $item): array => $this->summary($item));
        $ref = fn (int $id, int $count): array => ['item' => $items->get($id) ?? ['id' => $id, 'name' => "Item {$id}"], 'count' => $count];

        return $recipes->map(function (object $r) use ($ref): array {
            $rare = $r->rare ? json_decode($r->rare, true) : null;

            return [
                'id' => (int) $r->id,
                'recipe' => $ref((int) $r->recipe_item_id, 1)['item'],
                'level' => (int) $r->level,
                'dwarven' => (bool) $r->dwarven,
                'successRate' => (int) $r->success_rate,
                'mpCost' => (int) $r->mp_cost,
                'product' => $ref((int) $r->product_id, (int) $r->product_count),
                'rare' => $rare ? [...$ref($rare['id'], $rare['count']), 'chance' => $rare['rarity']] : null,
                'ingredients' => array_map(fn (array $i): array => $ref($i[0], $i[1]), json_decode($r->ingredients, true)),
            ];
        })->all();
    }

    /**
     * The drop rates from Rates.ini (the server's defaults where the overrides don't set them).
     *
     * @return array<string, mixed>
     */
    private function rates(): array
    {
        if ($this->rates !== null) {
            return $this->rates;
        }

        $rate = fn (string $key): float => $this->config->number('Rates.ini', $key, 1);
        $byItem = function (string $key): array {
            $map = [];

            foreach (array_filter(explode(';', str_replace(' ', '', $this->config->get('Rates.ini', $key, '57,1')))) as $pair) {
                [$item, $value] = array_pad(explode(',', $pair), 2, '1');
                $map[(int) $item] = (float) $value;
            }

            return $map;
        };

        return $this->rates = [
            'drop' => ['chance' => $rate('DeathDropChanceMultiplier'), 'amount' => $rate('DeathDropAmountMultiplier')],
            'spoil' => ['chance' => $rate('SpoilDropChanceMultiplier'), 'amount' => $rate('SpoilDropAmountMultiplier')],
            'raid' => ['chance' => $rate('RaidDropChanceMultiplier'), 'amount' => $rate('RaidDropAmountMultiplier')],
            'herb' => ['chance' => $rate('HerbDropChanceMultiplier'), 'amount' => $rate('HerbDropAmountMultiplier')],
            'byItem' => ['chance' => $byItem('DropChanceMultiplierByItemId'), 'amount' => $byItem('DropAmountMultiplierByItemId')],
        ];
    }
}
