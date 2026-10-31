<?php

namespace Tests\Feature;

use App\Mail\AccountMail;
use App\Models\Game\Account;
use App\Models\GameAction;
use App\Models\GiftClaim;
use App\Services\Orders;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrdersTest extends TestCase
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

        DB::connection('login')->table('accounts')->insert([['login' => 'hero', 'password' => 'x', 'email' => 'hero@example.com']]);
        DB::connection('game')->table('characters')->insert([
            ['charId' => 1, 'account_name' => 'hero', 'char_name' => 'Rich', 'level' => 78, 'classid' => 263, 'deletetime' => 0],
            ['charId' => 2, 'account_name' => 'hero', 'char_name' => 'Poor', 'level' => 40, 'classid' => 97, 'deletetime' => 0],
        ]);
        DB::connection('game')->table('items')->insert([
            ['object_id' => 10, 'owner_id' => 1, 'item_id' => 91663, 'count' => 90_000, 'loc' => 'INVENTORY'],
            ['object_id' => 11, 'owner_id' => 2, 'item_id' => 91663, 'count' => 300, 'loc' => 'INVENTORY'],
        ]);
    }

    private function account(): Account
    {
        return Account::findOrFail('hero');
    }

    public function test_buying_in_the_store_emails_a_confirmation_and_shows_the_order(): void
    {
        Mail::fake();

        $this->actingAs($this->account(), 'player')
            ->post('/treasury/package-samurai-starter', ['character_id' => 1])
            ->assertRedirect('/account/orders')
            ->assertSessionHas('status', 'order-placed');
        $this->actingAs($this->account(), 'player')
            ->post('/treasury/kage-pass', ['character_id' => 1])
            ->assertRedirect('/account/orders');

        Mail::assertSent(AccountMail::class, 2);
        Mail::assertSent(AccountMail::class, fn (AccountMail $mail): bool => $mail->hasTo('hero@example.com')
            && str_contains(implode(' ', $mail->lines), 'Samurai Package (Starter) for 5,000 L-Coin'));

        $this->actingAs($this->account(), 'player')->get('/account/orders')->assertOk()->assertInertia(fn ($page) => $page
            ->where('tab', 'orders')
            ->missing('gift')
            ->has('orders', 2)
            ->where('orders.0.status', GameAction::STATUS_PENDING));
    }

    public function test_the_history_lists_gift_boxes_passes_and_packages_with_what_was_sent(): void
    {
        $action = GameAction::create(['action' => GameAction::ACTION_SEND_MAIL, 'character_id' => 2, 'character_name' => 'Poor', 'items' => '91663:1000:0']);
        $action->forceFill(['status' => GameAction::STATUS_DONE])->save();
        $gift = GiftClaim::create(['account' => 'hero', 'week_start' => now()->startOfWeek(), 'character_id' => 2, 'character_name' => 'Poor', 'items' => [['id' => 91663, 'name' => 'L-Coin', 'count' => 1000, 'bonus' => false]], 'game_action_id' => $action->id]);
        $gift->forceFill(['created_at' => now()->subDay()])->save();
        $this->actingAs($this->account(), 'player')->post('/treasury/package-knight-elite', ['character_id' => 1]);

        $orders = app(Orders::class)->history($this->account());

        $this->assertSame(['package', 'gift'], array_column($orders, 'type'));
        $this->assertSame(['en' => 'Knight · Elite', 'pt' => 'Cavaleiro · Elite'], $orders[0]['name']);
        $this->assertSame(['count' => 60_000, 'currency' => 'L-Coin'], $orders[0]['price']);
        $this->assertSame(8, $orders[0]['items'][0]['enchant']);
        $this->assertCount(12, $orders[0]['items']);
        $this->assertNull($orders[1]['price']);
        $this->assertSame(GameAction::STATUS_DONE, $orders[1]['status']);
    }

    public function test_the_old_store_addresses_moved(): void
    {
        $this->get('/account/store')->assertRedirect('/account/orders');
        $this->get('/store')->assertRedirect('/treasury');
    }
}
