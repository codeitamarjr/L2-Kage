<?php

namespace Tests\Feature;

use App\Models\Game\Account;
use App\Models\GameAction;
use App\Models\SupplyPurchase;
use App\Services\Orders;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    use RefreshDatabase;

    private const ADENA = 57;

    private const LCOIN = 91663;

    protected function setUp(): void
    {
        parent::setUp();

        // Stand-ins for the game's databases (read-only MariaDB in production).
        foreach (['game', 'login'] as $connection) {
            config(["database.connections.{$connection}" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
            DB::purge($connection);
        }

        Schema::connection('login')->create('accounts', function (Blueprint $table) {
            $table->string('login')->primary();
            $table->string('password');
            $table->string('email')->nullable();
            $table->integer('accessLevel')->default(0);
            $table->bigInteger('lastactive')->default(0);
            $table->timestamp('created_time')->nullable();
        });
        Schema::connection('game')->create('characters', function (Blueprint $table) {
            $table->integer('charId')->primary();
            $table->string('account_name');
            $table->string('char_name');
            $table->integer('level');
            $table->integer('classid');
            $table->bigInteger('deletetime')->default(0);
        });
        Schema::connection('game')->create('items', function (Blueprint $table) {
            $table->integer('object_id')->primary();
            $table->integer('owner_id');
            $table->integer('item_id');
            $table->bigInteger('count');
            $table->string('loc');
        });

        DB::connection('login')->table('accounts')->insert([['login' => 'hero', 'password' => 'x'], ['login' => 'other', 'password' => 'x']]);
        DB::connection('game')->table('characters')->insert([
            ['charId' => 1, 'account_name' => 'hero', 'char_name' => 'Rich', 'level' => 78, 'classid' => 263, 'deletetime' => 0],
            ['charId' => 2, 'account_name' => 'hero', 'char_name' => 'Fresh', 'level' => 5, 'classid' => 97, 'deletetime' => 0],
            ['charId' => 3, 'account_name' => 'other', 'char_name' => 'Stranger', 'level' => 80, 'classid' => 97, 'deletetime' => 0],
        ]);
        DB::connection('game')->table('items')->insert([
            ['object_id' => 10, 'owner_id' => 1, 'item_id' => self::ADENA, 'count' => 250_000, 'loc' => 'INVENTORY'],
            ['object_id' => 11, 'owner_id' => 1, 'item_id' => self::LCOIN, 'count' => 90_000, 'loc' => 'INVENTORY'],
            ['object_id' => 12, 'owner_id' => 2, 'item_id' => self::ADENA, 'count' => 5_000_000, 'loc' => 'WAREHOUSE'],
            ['object_id' => 13, 'owner_id' => 2, 'item_id' => self::LCOIN, 'count' => 1_000, 'loc' => 'INVENTORY'],
        ]);
    }

    private function account(): Account
    {
        return Account::findOrFail('hero');
    }

    public function test_the_store_lists_every_product_and_guests_see_no_account_data(): void
    {
        $this->get('/treasury')->assertOk()->assertInertia(fn ($page) => $page
            ->component('treasury/index')
            ->where('filter.category', 'main')
            ->where('owned', [])
            ->where('suggested', null)
            ->where('products.0.slug', 'weekly-gift-box')
            ->where('products.0.price', null)
            ->where('products.1.slug', 'kage-pass')
            ->where('products.2.slug', 'hunt-pass')
            ->has('products', 3 + count(config('l2j.supplies.kits')) + 3 * count(config('l2j.packages.archetypes')))
            ->missing('products.0.contents'));

        $this->get('/treasury/kage-pass')->assertOk()->assertInertia(fn ($page) => $page->where('buyer', null));
    }

    public function test_an_unknown_product_or_category_does_not_break(): void
    {
        $this->get('/treasury/nothing')->assertNotFound();
        $this->actingAs($this->account(), 'player')->post('/treasury/nothing', ['character_id' => 1])->assertNotFound();
        $this->get('/treasury?category=nonsense')->assertOk()->assertInertia(fn ($page) => $page->where('filter.category', 'main'));
    }

    public function test_a_product_page_shows_each_characters_balance_of_that_products_own_price(): void
    {
        $this->actingAs($this->account(), 'player')->get('/treasury/supplies-hunter')->assertOk()->assertInertia(fn ($page) => $page
            ->where('product.price', ['item' => self::ADENA, 'count' => 100_000, 'name' => 'Adena'])
            ->has('buyer.characters', 2)
            ->where('buyer.characters.0.name', 'Rich')
            ->where('buyer.characters.0.balance', 250_000)
            ->where('buyer.characters.1.name', 'Fresh')
            ->where('buyer.characters.1.balance', 0)); // its Adena is in the warehouse, which can't pay

        $this->actingAs($this->account(), 'player')->get('/treasury/kage-pass')->assertInertia(fn ($page) => $page
            ->where('buyer.characters.0.balance', 90_000)
            ->where('buyer.characters.1.balance', 1_000));

        $this->actingAs($this->account(), 'player')->get('/treasury/weekly-gift-box')->assertInertia(fn ($page) => $page
            ->where('product.minLevel', 20)
            ->where('buyer.characters.0.balance', null));
    }

    public function test_class_packages_put_the_characters_of_that_class_first(): void
    {
        $this->actingAs($this->account(), 'player')->get('/treasury/package-healer-starter')->assertInertia(fn ($page) => $page
            ->where('buyer.characters.0.name', 'Fresh')
            ->where('buyer.characters.1.name', 'Rich'));

        $this->actingAs($this->account(), 'player')->get('/treasury')->assertInertia(fn ($page) => $page->where('suggested', 'samurai'));
    }

    public function test_a_kit_is_paid_in_its_own_currency_and_multiplied_by_quantity(): void
    {
        $this->actingAs($this->account(), 'player')
            ->post('/treasury/supplies-hunter', ['character_id' => 1, 'quantity' => 2])
            ->assertRedirect('/account/orders')
            ->assertSessionHas('status', 'order-placed');

        $action = GameAction::sole();
        $this->assertSame(GameAction::ACTION_BUY_PASS, $action->action);
        $this->assertSame(self::ADENA, $action->item_id);
        $this->assertSame(200_000, $action->count);
        $this->assertSame('1539:100:0;5592:40:0;3031:200:0', $action->items);

        $order = app(Orders::class)->history($this->account())[0];
        $this->assertSame('supplies', $order['type']);
        $this->assertSame("Hunter's Kit × 2", $order['name']['en']);
        $this->assertSame(['count' => 200_000, 'currency' => 'Adena'], $order['price']);
    }

    public function test_any_item_can_be_a_price(): void
    {
        config(['l2j.supplies.kits.hunter.price' => ['item' => self::LCOIN, 'count' => 1_000]]);

        $this->actingAs($this->account(), 'player')->post('/treasury/supplies-hunter', ['character_id' => 2])->assertRedirect('/account/orders');

        $this->assertSame(self::LCOIN, GameAction::sole()->item_id);
    }

    public function test_a_character_that_cannot_pay_gets_nothing_queued(): void
    {
        $account = $this->account();

        // Fresh's Adena is all in the warehouse; Rich has enough for two kits, not three.
        $this->actingAs($account, 'player')->post('/treasury/supplies-hunter', ['character_id' => 2])->assertSessionHasErrors('character_id');
        $this->actingAs($account, 'player')->post('/treasury/supplies-hunter', ['character_id' => 1, 'quantity' => 3])->assertSessionHasErrors('character_id');
        // Someone else's character, and quantities out of range.
        $this->actingAs($account, 'player')->post('/treasury/supplies-traveler', ['character_id' => 3])->assertSessionHasErrors('character_id');
        $this->actingAs($account, 'player')->post('/treasury/supplies-traveler', ['character_id' => 1, 'quantity' => 0])->assertSessionHasErrors('quantity');
        $this->actingAs($account, 'player')->post('/treasury/supplies-traveler', ['character_id' => 1, 'quantity' => 11])->assertSessionHasErrors('quantity');

        $this->assertSame(0, GameAction::count());
        $this->assertSame(0, SupplyPurchase::count());
    }

    public function test_quantity_only_applies_to_what_can_be_bought_repeatedly(): void
    {
        $this->actingAs($this->account(), 'player')->post('/treasury/kage-pass', ['character_id' => 1, 'quantity' => 2])->assertSessionHasErrors('quantity');

        $this->assertSame(0, GameAction::count());
    }

    public function test_the_hunt_pass_cannot_be_bought_on_the_website(): void
    {
        $this->actingAs($this->account(), 'player')->post('/treasury/hunt-pass', ['character_id' => 1])->assertSessionHasErrors('character_id');

        $this->assertSame(0, GameAction::count());
    }

    public function test_guests_cannot_buy_and_come_back_to_the_product_after_signing_in(): void
    {
        $this->post('/treasury/supplies-hunter', ['character_id' => 1])->assertRedirect('/account');
        $this->assertSame(0, GameAction::count());

        DB::connection('login')->table('accounts')->where('login', 'hero')->update(['password' => base64_encode(sha1('secret', true))]);

        $this->get('/account?return=/treasury/supplies-hunter')->assertOk();
        $this->post('/account/login', ['login' => 'hero', 'password' => 'secret'])->assertRedirect(url('/treasury/supplies-hunter'));
    }

    public function test_signing_in_only_returns_to_the_treasury(): void
    {
        DB::connection('login')->table('accounts')->where('login', 'hero')->update(['password' => base64_encode(sha1('secret', true))]);

        $this->get('/account?return=https://evil.example/treasury/x')->assertOk();
        $this->post('/account/login', ['login' => 'hero', 'password' => 'secret'])->assertRedirect('/account');
    }

    public function test_what_the_account_already_holds_is_marked_and_the_orders_tab_is_only_history(): void
    {
        $account = $this->account();
        $this->actingAs($account, 'player')->post('/treasury/weekly-gift-box', ['character_id' => 1])->assertSessionHas('status', 'gift-opened');
        $this->actingAs($account, 'player')->post('/treasury/kage-pass', ['character_id' => 1]);
        $this->actingAs($account, 'player')->post('/treasury/package-samurai-starter', ['character_id' => 1]);

        $this->actingAs($account, 'player')->get('/treasury')->assertInertia(fn ($page) => $page
            ->where('owned', ['weekly-gift-box', 'kage-pass', 'package-samurai-starter']));
        $this->actingAs($account, 'player')->get('/treasury/weekly-gift-box')->assertInertia(fn ($page) => $page
            ->where('buyer.owned.characterName', 'Rich')
            ->whereNot('buyer.owned.nextAt', null));
        $this->actingAs($account, 'player')->get('/treasury/kage-pass')->assertInertia(fn ($page) => $page
            ->where('buyer.owned.status', GameAction::STATUS_PENDING));
        $this->actingAs($account, 'player')->get('/treasury/supplies-hunter')->assertInertia(fn ($page) => $page->where('buyer.owned', null));

        $this->actingAs($account, 'player')->get('/account/orders')->assertInertia(fn ($page) => $page
            ->missing('gift')
            ->has('orders', 3));
    }

    public function test_a_failed_charge_can_be_bought_again(): void
    {
        $account = $this->account();
        $this->actingAs($account, 'player')->post('/treasury/kage-pass', ['character_id' => 1]);
        GameAction::sole()->forceFill(['status' => GameAction::STATUS_FAILED])->save();

        $this->actingAs($account, 'player')->get('/treasury/kage-pass')->assertInertia(fn ($page) => $page->where('buyer.owned', null));
        $this->actingAs($account, 'player')->post('/treasury/kage-pass', ['character_id' => 1])->assertRedirect('/account/orders');
    }

    public function test_every_product_page_renders_and_the_sitemap_lists_them(): void
    {
        foreach (['weekly-gift-box', 'kage-pass', 'hunt-pass', 'supplies-traveler', 'package-gunner-elite'] as $slug) {
            $this->get("/treasury/{$slug}")->assertOk()->assertInertia(fn ($page) => $page->where('product.slug', $slug)->has('seo.title'));
        }

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/treasury/weekly-gift-box'))->assertSee(url('/treasury/package-gunner-elite'));
    }

    /** A kit priced under what its items are worth could be bought and resold to an NPC for a profit. */
    public function test_no_kit_is_priced_under_the_list_price_of_its_items(): void
    {
        $listPrices = [736 => 400, 1374 => 3_000, 1539 => 900, 5592 => 500, 3031 => 400];

        foreach (config('l2j.supplies.kits') as $key => $kit) {
            $worth = array_sum(array_map(fn (array $entry): int => $listPrices[$entry['item']] * $entry['count'], $kit['items']));
            $this->assertSame(self::ADENA, $kit['price']['item'], $key);
            $this->assertGreaterThanOrEqual($worth, $kit['price']['count'], $key);
        }
    }
}
