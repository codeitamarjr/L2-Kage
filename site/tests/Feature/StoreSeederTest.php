<?php

namespace Tests\Feature;

use App\Models\GameAction;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreCategory;
use App\Rules\AboveResalePrice;
use App\Rules\DeliverableContents;
use App\Services\L2j\GameData;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_the_store_with_every_launch_product(): void
    {
        Product::query()->forceDelete();
        StoreCategory::query()->delete();

        $this->seed();

        $this->assertSame(44, Product::count());
        $this->assertSame(['gifts', 'passes', 'packages', 'chests', 'items', 'supplies'], StoreCategory::orderBy('sort')->pluck('slug')->all());
        $this->assertSame(39, Product::whereNotNull('class_group')->count());
    }

    public function test_running_it_again_changes_nothing_an_admin_did(): void
    {
        Product::where('slug', 'kage-pass')->update(['price_count' => 777]);
        Product::where('slug', 'supplies-hunter')->firstOrFail()->delete();
        Product::where('slug', 'supplies-traveler')->update(['is_active' => false]);
        StoreCategory::where('slug', 'gifts')->update(['name_en' => 'Presents']);

        (new StoreSeeder)->run();

        $this->assertSame(777, Product::where('slug', 'kage-pass')->value('price_count'));
        $this->assertTrue(Product::withTrashed()->where('slug', 'supplies-hunter')->firstOrFail()->trashed());
        $this->assertFalse(Product::where('slug', 'supplies-traveler')->firstOrFail()->is_active);
        $this->assertSame('Presents', StoreCategory::where('slug', 'gifts')->value('name_en'));
        $this->assertSame(44, Product::withTrashed()->count());
    }

    public function test_it_adds_a_missing_product_without_touching_the_rest(): void
    {
        Product::where('slug', 'supplies-hunter')->forceDelete();
        Product::where('slug', 'kage-pass')->update(['price_count' => 777]);

        (new StoreSeeder)->run();

        $this->assertSame(100_000, Product::where('slug', 'supplies-hunter')->value('price_count'));
        $this->assertSame(777, Product::where('slug', 'kage-pass')->value('price_count'));
    }

    public function test_every_seeded_product_can_really_be_delivered_and_not_resold_for_a_profit(): void
    {
        $this->artisan('itemdb:import')->assertSuccessful();
        $gameData = app(GameData::class);

        foreach (Product::where('type', '!=', Product::TYPE_HUNT_PASS)->get() as $product) {
            foreach ($product->items as $entry) {
                $this->assertTrue($gameData->itemExists($entry['item']), "{$product->slug} has item {$entry['item']}");
            }

            $this->assertTrue(
                Validator::make(['items' => $product->items], ['items' => [new DeliverableContents($product->bonus ?? [], $product->max_per_order)]])->passes(),
                "{$product->slug} can't be delivered",
            );
            $this->assertTrue(
                Validator::make(['price' => (int) $product->price_count], ['price' => [new AboveResalePrice($product->price_item, $product->items)]])->passes(),
                "{$product->slug} can be resold for a profit",
            );
        }
    }

    public function test_past_claims_become_orders_and_keep_their_limits(): void
    {
        DB::table('orders')->delete();
        $action = fn (string $status, ?int $item, int $count, string $items): int => DB::table('game_actions')->insertGetId([
            'action' => $item ? 'buy_pass' : 'send_mail', 'character_id' => 1, 'character_name' => 'Rich', 'item_id' => $item, 'count' => $count, 'items' => $items, 'status' => $status,
        ]);
        $common = ['account' => 'hero', 'character_id' => 1, 'character_name' => 'Rich', 'created_at' => '2026-10-29 10:00:00', 'updated_at' => '2026-10-29 10:00:00'];

        DB::table('gift_claims')->insert([...$common, 'week_start' => '2026-10-26', 'items' => json_encode([['id' => 91663, 'name' => 'L-Coin', 'count' => 1000, 'bonus' => true]]), 'game_action_id' => $action('done', null, 1, '91663:1000:0')]);
        DB::table('pass_claims')->insert([...$common, 'season' => 'season-1', 'game_action_id' => $action('failed', 91663, 1000, '3031:5000:0')]);
        DB::table('package_claims')->insert([...$common, 'season' => 'season-1', 'package' => 'elite:knight', 'game_action_id' => $action('done', 91663, 60000, '93291:1:8')]);
        DB::table('supply_purchases')->insert([...$common, 'kit' => 'hunter', 'quantity' => 2, 'game_action_id' => $action('done', 57, 200000, '1539:100:0')]);

        (include database_path('migrations/2026_11_01_120100_move_claims_to_store_orders.php'))->up();

        $this->assertSame(4, Order::count());
        $this->assertSame(44, Product::withTrashed()->count());

        $gift = Order::where('category', 'gifts')->sole();
        $this->assertSame('week:2026-10-26', $gift->limit_key);
        $this->assertSame('Weekly Gift Box', $gift->name['en']);
        $this->assertNull($gift->price_count);
        $this->assertTrue($gift->items[0]['bonus']);
        $this->assertSame('2026-10-29 10:00:00', $gift->created_at->toDateTimeString());

        // Its charge failed, so it could already be bought again.
        $this->assertNull(Order::where('category', 'passes')->sole()->limit_key);

        $package = Order::where('category', 'packages')->sole();
        $this->assertSame(Product::where('slug', 'package-knight-elite')->value('id'), $package->product_id);
        $this->assertSame('season:season-1', $package->limit_key);
        $this->assertSame(60_000, $package->price_count);
        $this->assertSame('L-Coin', $package->price_name);
        $this->assertSame(8, $package->items[0]['enchant']);
        $this->assertSame(GameAction::STATUS_DONE, $package->status());

        $kit = Order::where('category', 'supplies')->sole();
        $this->assertSame(2, $kit->quantity);
        $this->assertSame('Adena', $kit->price_name);
        $this->assertNull($kit->limit_key);
    }
}
