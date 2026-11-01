<?php

use App\Models\Product;
use App\Models\StoreCategory;
use App\Services\L2j\GameData;
use Database\Seeders\StoreSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fills the Store with the products that used to live in config/l2j.php and copies every past claim and purchase into
     * orders, keeping its week or season so nobody can buy again what they already hold. This runs when the site starts,
     * so a deploy never shows an empty Store. The old tables are left in place, untouched, until a later release drops them.
     */
    public function up(): void
    {
        (new StoreSeeder)->run();

        foreach (['gift_claims', 'pass_claims', 'package_claims', 'supply_purchases'] as $table) {
            foreach (DB::table($table)->orderBy('id')->get() as $row) {
                $this->copy($table, $row);
            }
        }
    }

    public function down(): void
    {
        DB::table('orders')->delete();
        DB::table('products')->delete();
        DB::table('store_categories')->delete();
    }

    private function copy(string $table, object $row): void
    {
        [$slug, $limitKey] = match ($table) {
            'gift_claims' => ['weekly-gift-box', 'week:'.substr((string) $row->week_start, 0, 10)],
            'pass_claims' => ['kage-pass', "season:{$row->season}"],
            'package_claims' => ['package-'.explode(':', $row->package.':')[1].'-'.explode(':', $row->package)[0], "season:{$row->season}"],
            'supply_purchases' => ["supplies-{$row->kit}", null],
        };

        $product = Product::withTrashed()->where('slug', $slug)->first();
        $category = $product ? StoreCategory::find($product->store_category_id) : null;
        $action = $row->game_action_id ? DB::table('game_actions')->find($row->game_action_id) : null;
        $paid = $table !== 'gift_claims';
        $failed = $action === null || $action->status === 'failed';
        $gameData = app(GameData::class);

        DB::table('orders')->insert([
            'account' => $row->account,
            'product_id' => $product?->id,
            'source' => 'purchase',
            'category' => $category->slug ?? 'store',
            'category_name' => json_encode($category?->localizedName() ?? ['en' => 'Store', 'pt' => 'Loja']),
            'name' => json_encode($product?->localizedName() ?? ['en' => $slug, 'pt' => $slug]),
            'quantity' => $row->quantity ?? 1,
            'price_item' => $paid ? $action?->item_id : null,
            'price_count' => $paid ? $action?->count : null,
            'price_name' => $paid && $action?->item_id ? $gameData->item((int) $action->item_id)['name'] : null,
            'character_id' => $row->character_id,
            'character_name' => $row->character_name,
            'items' => json_encode($table === 'gift_claims'
                ? array_map(fn (array $item): array => ['id' => $item['id'] ?? null, 'name' => $item['name'], 'count' => (int) $item['count'], 'enchant' => 0, 'bonus' => (bool) ($item['bonus'] ?? false)], json_decode((string) $row->items, true) ?: [])
                : $this->sentItems($action?->items, $gameData)),
            'game_action_id' => $action?->id,
            // A purchase whose charge failed in game could already be bought again.
            'limit_key' => $paid && $failed ? null : $limitKey,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }

    /**
     * What an in-game action sent ("itemId:count:enchant" entries joined by ";").
     *
     * @return list<array{id: int, name: string, count: int, enchant: int, bonus: bool}>
     */
    private function sentItems(?string $items, GameData $gameData): array
    {
        return array_values(array_map(function (string $entry) use ($gameData): array {
            [$id, $count, $enchant] = array_map('intval', explode(':', $entry)) + [0, 1, 0];

            return ['id' => $id, 'name' => $gameData->item($id)['name'], 'count' => $count, 'enchant' => $enchant, 'bonus' => false];
        }, array_filter(explode(';', (string) $items))));
    }
};
