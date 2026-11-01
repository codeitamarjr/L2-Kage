<?php

namespace Tests\Concerns;

use App\Models\Game\Account;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stand-ins for the game's databases (read-only MariaDB in production), with two accounts: hero has a rich level 78 Samurai
 * and a poor level 5 Healer, other has a level 80 Healer.
 */
trait FakesGameDatabases
{
    private const ADENA = 57;

    private const LCOIN = 91663;

    protected function fakeGameDatabases(): void
    {
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

        DB::connection('login')->table('accounts')->insert([
            ['login' => 'hero', 'password' => 'x', 'email' => 'hero@example.com'],
            ['login' => 'other', 'password' => 'x', 'email' => null],
        ]);
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

    private function account(string $login = 'hero'): Account
    {
        return Account::findOrFail($login);
    }
}
