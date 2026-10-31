<?php

namespace Tests\Feature;

use App\Models\Game\Account;
use App\Models\GameAction;
use App\Models\GiftClaim;
use App\Services\GiftBox;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GiftBoxTest extends TestCase
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
            $table->bigInteger('deletetime')->default(0);
        });

        DB::connection('login')->table('accounts')->insert([
            ['login' => 'hero', 'password' => 'x'],
            ['login' => 'other', 'password' => 'x'],
        ]);
        DB::connection('game')->table('characters')->insert([
            ['charId' => 1, 'account_name' => 'hero', 'char_name' => 'Valiant', 'level' => 45, 'deletetime' => 0],
            ['charId' => 2, 'account_name' => 'hero', 'char_name' => 'Newbie', 'level' => 5, 'deletetime' => 0],
            ['charId' => 3, 'account_name' => 'other', 'char_name' => 'Stranger', 'level' => 80, 'deletetime' => 0],
        ]);
    }

    private function account(string $login = 'hero'): Account
    {
        return Account::findOrFail($login);
    }

    public function test_a_box_holds_every_guaranteed_item_and_one_bonus_of_the_characters_grade(): void
    {
        config(['l2j.gift_box.bonus' => [['item' => 'enchant_weapon', 'count' => 1, 'weight' => 1]]]);

        $items = app(GiftBox::class)->roll(45);

        $this->assertCount(count(config('l2j.gift_box.guaranteed')) + 1, $items);
        $this->assertSame([951, 1, true], [end($items)['id'], end($items)['count'], end($items)['bonus']]); // C-grade at 45
        $box = app(GiftBox::class)->roll(80);
        $this->assertSame(959, end($box)['id']); // S-grade at 80
    }

    public function test_claiming_queues_one_mail_with_all_items(): void
    {
        $claim = app(GiftBox::class)->claim($this->account(), 1);

        $action = GameAction::findOrFail($claim->game_action_id);
        $this->assertSame(GameAction::ACTION_SEND_MAIL, $action->action);
        $this->assertSame(1, $action->character_id);
        $this->assertSame(GameAction::STATUS_PENDING, $action->status);
        $this->assertSame(count($claim->items), substr_count($action->items, ';') + 1);
        $this->assertStringStartsWith('57:250000:0;', $action->items);
        $this->assertSame(config('l2j.gift_box.mail_subject'), $action->mail_subject);
    }

    public function test_one_box_per_account_per_week(): void
    {
        $box = app(GiftBox::class);
        $monday = CarbonImmutable::parse('2026-10-26 00:00');
        $box->claim($this->account(), 1, $monday->addDays(2));

        try {
            $box->claim($this->account(), 1, $monday->addDays(6)->setTime(23, 59));
            $this->fail('A second box in the same week was allowed.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('already opened', $exception->getMessage());
        }

        $box->claim($this->account(), 1, $monday->addWeek());
        $this->assertSame(2, GiftClaim::count());
        $this->assertSame(2, GameAction::count()); // the refused claim left nothing behind
    }

    public function test_only_own_characters_of_the_minimum_level_can_receive_it(): void
    {
        foreach ([2 => 'level too low', 3 => 'another account', 99 => 'unknown'] as $characterId => $case) {
            try {
                app(GiftBox::class)->claim($this->account(), $characterId);
                $this->fail("Claim allowed for {$case}.");
            } catch (ValidationException) {
                $this->assertSame(0, GiftClaim::count(), $case);
            }
        }
    }

    public function test_players_open_the_box_from_the_treasury(): void
    {
        $this->post('/treasury/weekly-gift-box', ['character_id' => 1])->assertRedirect('/account');
        $this->assertSame(0, GiftClaim::count(), 'guests cannot claim');

        $this->actingAs($this->account(), 'player')
            ->post('/treasury/weekly-gift-box', ['character_id' => 1])
            ->assertRedirect('/account/orders')
            ->assertSessionHas('status', 'gift-opened')
            ->assertSessionHasNoErrors();

        $status = app(GiftBox::class)->status($this->account());
        $this->assertSame('Valiant', $status['claim']['characterName']);
        $this->assertSame(GameAction::STATUS_PENDING, $status['claim']['status']);
        $this->assertSame([['id' => 1, 'name' => 'Valiant', 'level' => 45]], $status['eligible']);
    }
}
