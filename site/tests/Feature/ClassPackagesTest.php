<?php

namespace Tests\Feature;

use App\Models\Game\Account;
use App\Models\GameAction;
use App\Models\PackageClaim;
use App\Services\ClassPackages;
use App\Services\L2j\GameData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassPackagesTest extends TestCase
{
    use RefreshDatabase;

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
            ['charId' => 2, 'account_name' => 'hero', 'char_name' => 'Poor', 'level' => 40, 'classid' => 97, 'deletetime' => 0],
            ['charId' => 3, 'account_name' => 'other', 'char_name' => 'Stranger', 'level' => 80, 'classid' => 5, 'deletetime' => 0],
        ]);
        DB::connection('game')->table('items')->insert([
            ['object_id' => 10, 'owner_id' => 1, 'item_id' => 91663, 'count' => 70_000, 'loc' => 'INVENTORY'],
            ['object_id' => 11, 'owner_id' => 2, 'item_id' => 91663, 'count' => 4_000, 'loc' => 'INVENTORY'],
            ['object_id' => 12, 'owner_id' => 2, 'item_id' => 91663, 'count' => 90_000, 'loc' => 'WAREHOUSE'],
            ['object_id' => 13, 'owner_id' => 3, 'item_id' => 91663, 'count' => 90_000, 'loc' => 'INVENTORY'],
        ]);
    }

    private function account(string $login = 'hero'): Account
    {
        return Account::findOrFail($login);
    }

    public function test_every_package_item_exists_in_the_datapack(): void
    {
        $gameData = app(GameData::class);
        $packages = app(ClassPackages::class);

        foreach (array_keys(config('l2j.packages.tiers')) as $tier) {
            foreach (array_keys(config('l2j.packages.archetypes')) as $archetype) {
                foreach ($packages->items($tier, $archetype) as $item) {
                    $this->assertStringNotContainsString('Unknown item', $gameData->item($item['item'])['name'], "{$tier}:{$archetype} has item {$item['item']}");
                }
            }
        }
    }

    public function test_a_package_has_the_weapon_and_armor_at_the_tiers_enchant_then_the_jewelry(): void
    {
        $items = app(ClassPackages::class)->items('elite', 'knight');

        // Weapon, shield, five armor pieces, five jewels.
        $this->assertCount(12, $items);
        $this->assertSame(['item' => 93291, 'enchant' => 8], $items[0]);
        $this->assertSame(['item' => 95666, 'enchant' => 8], $items[1]);
        $this->assertSame(0, $items[11]['enchant']);
        // No shield: weapon, five armor pieces, five jewels.
        $this->assertCount(11, app(ClassPackages::class)->items('starter', 'samurai'));
    }

    public function test_the_status_suggests_each_characters_archetype(): void
    {
        $status = app(ClassPackages::class)->status($this->account());

        $this->assertSame(
            [['Rich', 'samurai', 70_000], ['Poor', 'healer', 4_000]],
            array_map(fn (array $c): array => [$c['name'], $c['archetype'], $c['coins']], $status['characters']),
        );
        $this->assertSame([], $status['claims']);
    }

    public function test_buying_queues_one_purchase_with_the_tiers_price_and_the_gear(): void
    {
        $claim = app(ClassPackages::class)->claim($this->account(), 1, 'elite', 'samurai');

        $action = GameAction::findOrFail($claim->game_action_id);
        $this->assertSame(GameAction::ACTION_BUY_PASS, $action->action);
        $this->assertSame([1, 91663, 60_000], [$action->character_id, $action->item_id, $action->count]);
        $this->assertStringStartsWith('93291:1:8;', $action->items);
        $this->assertSame(11, substr_count($action->items, ';') + 1);
        $this->assertSame('elite:samurai', $claim->package);
    }

    public function test_a_character_without_enough_l_coin_cannot_pay(): void
    {
        $this->expectException(ValidationException::class);

        app(ClassPackages::class)->claim($this->account(), 2, 'starter', 'healer');
    }

    public function test_only_your_own_characters_can_pay(): void
    {
        $this->expectException(ValidationException::class);

        app(ClassPackages::class)->claim($this->account(), 3, 'starter', 'knight');
    }

    public function test_unknown_packages_are_refused(): void
    {
        $this->expectException(ValidationException::class);

        app(ClassPackages::class)->claim($this->account(), 1, 'legendary', 'knight');
    }

    public function test_each_package_once_per_account_per_season(): void
    {
        $packages = app(ClassPackages::class);
        $packages->claim($this->account(), 1, 'starter', 'samurai');

        try {
            $packages->claim($this->account(), 1, 'starter', 'samurai');
            $this->fail('A second purchase of the same package in the same season was accepted.');
        } catch (ValidationException) {
            $this->assertSame(1, PackageClaim::count());
        }

        // Another package is fine, and a new season opens the first one again.
        $packages->claim($this->account(), 1, 'advanced', 'samurai');
        config(['l2j.packages.season' => 'season-2']);
        $packages->claim($this->account(), 1, 'starter', 'samurai');
        $this->assertSame(3, PackageClaim::count());
    }

    public function test_a_purchase_that_failed_in_game_can_be_made_again(): void
    {
        $packages = app(ClassPackages::class);
        $first = $packages->claim($this->account(), 1, 'starter', 'samurai');
        GameAction::whereKey($first->game_action_id)->update(['status' => GameAction::STATUS_FAILED]);

        $second = $packages->claim($this->account(), 1, 'starter', 'samurai');
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, PackageClaim::count());
    }

    public function test_players_buy_from_the_treasury_and_see_the_packages_on_the_landing_page(): void
    {
        $this->actingAs($this->account(), 'player')
            ->post('/treasury/package-samurai-advanced', ['character_id' => 1])
            ->assertRedirect('/account/orders');
        $this->assertSame(1, PackageClaim::count());

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->has('packages.tiers', 3)
            ->where('packages.tiers.2.price', 60_000)
            ->has('packages.archetypes', count(config('l2j.packages.archetypes'))));

        $this->get('/packages')->assertRedirect('/treasury?category=packages');
        $this->get('/treasury/package-knight-elite')->assertOk()->assertInertia(fn ($page) => $page
            ->component('treasury/show')
            ->where('product.price.count', 60_000)
            ->has('product.contents.0.items', 12));
    }
}
