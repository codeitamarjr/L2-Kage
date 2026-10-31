<?php

namespace Tests\Feature;

use App\Models\Game\Account;
use App\Models\GameAction;
use App\Models\PassClaim;
use App\Services\KagePass;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KagePassTest extends TestCase
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
            $table->integer('classid')->default(0);
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
            ['charId' => 1, 'account_name' => 'hero', 'char_name' => 'Rich', 'level' => 20, 'deletetime' => 0],
            ['charId' => 2, 'account_name' => 'hero', 'char_name' => 'Poor', 'level' => 5, 'deletetime' => 0],
            ['charId' => 3, 'account_name' => 'other', 'char_name' => 'Stranger', 'level' => 80, 'deletetime' => 0],
        ]);
        DB::connection('game')->table('items')->insert([
            ['object_id' => 10, 'owner_id' => 1, 'item_id' => 91663, 'count' => 1_500, 'loc' => 'INVENTORY'],
            ['object_id' => 11, 'owner_id' => 2, 'item_id' => 91663, 'count' => 300, 'loc' => 'INVENTORY'],
            ['object_id' => 12, 'owner_id' => 2, 'item_id' => 91663, 'count' => 5_000, 'loc' => 'WAREHOUSE'],
            ['object_id' => 13, 'owner_id' => 3, 'item_id' => 91663, 'count' => 9_000, 'loc' => 'INVENTORY'],
        ]);
    }

    private function account(string $login = 'hero'): Account
    {
        return Account::findOrFail($login);
    }

    public function test_the_status_lists_each_characters_l_coin_in_the_bag(): void
    {
        $status = app(KagePass::class)->status($this->account());

        $this->assertTrue($status['enabled']);
        $this->assertSame(['name' => 'L-Coin', 'count' => 1_000], $status['price']);
        $this->assertCount(count(config('l2j.pass.rewards')), $status['rewards']);
        // Only the bag counts (not the warehouse), and only this account's characters.
        $this->assertSame([['Rich', 1_500], ['Poor', 300]], array_map(fn (array $c): array => [$c['name'], $c['coins']], $status['characters']));
        $this->assertNull($status['claim']);
    }

    public function test_claiming_queues_one_purchase_with_the_price_and_rewards(): void
    {
        $claim = app(KagePass::class)->claim($this->account(), 1);

        $action = GameAction::findOrFail($claim->game_action_id);
        $this->assertSame(GameAction::ACTION_BUY_PASS, $action->action);
        $this->assertSame([1, 91663, 1_000], [$action->character_id, $action->item_id, $action->count]);
        $this->assertSame(count(config('l2j.pass.rewards')), substr_count($action->items, ';') + 1);
        $this->assertStringStartsWith('3031:5000:0;', $action->items);
        $this->assertSame(config('l2j.pass.season'), $claim->season);
    }

    public function test_a_character_without_enough_l_coin_cannot_pay(): void
    {
        $this->expectException(ValidationException::class);

        app(KagePass::class)->claim($this->account(), 2);
    }

    public function test_only_your_own_characters_can_pay(): void
    {
        $this->expectException(ValidationException::class);

        app(KagePass::class)->claim($this->account(), 3);
    }

    public function test_one_pass_per_account_per_season(): void
    {
        $pass = app(KagePass::class);
        $pass->claim($this->account(), 1);

        try {
            $pass->claim($this->account(), 1);
            $this->fail('A second claim in the same season was accepted.');
        } catch (ValidationException) {
            $this->assertSame(1, PassClaim::count());
        }

        // A new season opens it again.
        config(['l2j.pass.season' => 'season-2']);
        $pass->claim($this->account(), 1);
        $this->assertSame(2, PassClaim::count());
    }

    public function test_a_purchase_that_failed_in_game_can_be_claimed_again(): void
    {
        $pass = app(KagePass::class);
        $first = $pass->claim($this->account(), 1);
        GameAction::whereKey($first->game_action_id)->update(['status' => GameAction::STATUS_FAILED, 'result' => 'Rich does not have 1000 L-Coin.']);

        $this->assertSame(GameAction::STATUS_FAILED, $pass->status($this->account())['claim']['status']);

        $second = $pass->claim($this->account(), 1);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, PassClaim::count());
    }

    public function test_the_pass_closes_after_its_last_day(): void
    {
        $pass = app(KagePass::class);
        $ends = CarbonImmutable::parse(config('l2j.pass.ends'));

        $this->assertTrue($pass->isOpen($ends->setTime(23, 0)));
        $this->assertFalse($pass->isOpen($ends->addDay()));
    }

    public function test_the_store_page_explains_both_passes_with_the_servers_numbers(): void
    {
        $dir = sys_get_temp_dir().'/passes-test-'.uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/HuntPass.ini", "EnabledHuntPass = True\nPremiumItemId = 91663\nPremiumItemCount = 1000\nPointsForStep = 300\nDayOfMonth = 16\n");
        config(['l2j.config_dir' => $dir, 'l2j.config_defaults_dir' => null]);

        $this->get('/passes')->assertRedirect('/treasury/kage-pass');
        $this->get('/treasury')->assertOk()->assertInertia(fn ($page) => $page
            ->component('treasury/index')
            ->where('lcoin.welcome', 1_000)
            ->where('lcoin.giftBox', 1_000));
        $this->get('/treasury/kage-pass')->assertOk()->assertInertia(fn ($page) => $page
            ->component('treasury/show')
            ->where('product.name.en', config('l2j.pass.name'))
            ->where('product.price.count', 1_000));
        $this->get('/treasury/hunt-pass')->assertOk()->assertInertia(fn ($page) => $page
            ->where('product.open', true)
            ->where('product.price', ['item' => 91663, 'count' => 1_000, 'name' => 'L-Coin'])
            ->where('product.hunt.steps', 280)
            ->has('product.hunt.firstSteps', 10)
            ->where('product.description.1.en', fn (string $text): bool => str_contains($text, '300 points complete a step'))
            ->where('product.steps.2.en', fn (string $text): bool => str_contains($text, 'on day 16')));

        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    public function test_players_claim_from_the_treasury_and_see_the_offer_on_the_landing_page(): void
    {
        $this->actingAs($this->account(), 'player')->post('/treasury/kage-pass', ['character_id' => 1])->assertRedirect('/account/orders');
        $this->assertSame(1, PassClaim::count());

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->where('pass.name', config('l2j.pass.name'))
            ->where('pass.price.count', 1_000));
    }
}
