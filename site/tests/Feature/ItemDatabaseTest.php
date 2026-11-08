<?php

namespace Tests\Feature;

use App\Services\L2j\ItemDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $configDir;

    protected function setUp(): void
    {
        parent::setUp();

        // The server's rates: drop and spoil x3, raid x1, Adena amount x4 (as in overrides/game/config).
        $this->configDir = sys_get_temp_dir().'/itemdb-test-'.uniqid();
        mkdir($this->configDir);
        file_put_contents("{$this->configDir}/Rates.ini", implode("\n", [
            'DeathDropChanceMultiplier = 3', 'SpoilDropChanceMultiplier = 3', 'RaidDropChanceMultiplier = 1',
            'DropAmountMultiplierByItemId = 57,4', 'DropChanceMultiplierByItemId = 57,1',
        ]));
        config(['l2j.config_dir' => $this->configDir, 'l2j.config_defaults_dir' => null]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->configDir}/*"));
        rmdir($this->configDir);

        parent::tearDown();
    }

    private function import(): void
    {
        $this->artisan('itemdb:import')->assertSuccessful();
    }

    public function test_the_export_imports_once_and_skips_when_unchanged(): void
    {
        $this->import();

        $this->assertGreaterThan(25000, DB::table('itemdb_items')->count());
        $this->assertGreaterThan(11000, DB::table('itemdb_drops')->count());
        $this->assertGreaterThan(500, DB::table('itemdb_recipes')->count());

        $this->artisan('itemdb:import')->expectsOutput('Item database is up to date.')->assertSuccessful();
    }

    /**
     * @param  list<array{chance: float, item?: int}>  $items
     * @return array{chance: float, min: int, max: int}
     */
    private function roll(array $items, float $groupChance, string $kind = 'drop', bool $raid = false): array
    {
        $group = collect($items)->map(fn (array $i, int $n): object => (object) [
            'item_id' => $i['item'] ?? 1000 + $n, 'kind' => $kind, 'min' => 1, 'max' => 2, 'chance' => $i['chance'], 'group_chance' => $groupChance,
        ]);

        return app(ItemDatabase::class)->roll($group->first(), $group, $raid);
    }

    public function test_chances_follow_the_servers_rates(): void
    {
        // 10% in a 50% group, x3 drop rate: 15% (the group totals 45%, under 100%).
        $this->assertEqualsWithDelta(15, $this->roll([['chance' => 10], ['chance' => 20]], 50)['chance'], 1e-9);
        // Spoil uses the spoil rate, raid bosses the raid rate.
        $this->assertEqualsWithDelta(15, $this->roll([['chance' => 10], ['chance' => 20]], 50, 'spoil')['chance'], 1e-9);
        $this->assertEqualsWithDelta(5, $this->roll([['chance' => 10], ['chance' => 20]], 50, raid: true)['chance'], 1e-9);
        // 10% next to 90% in a 50% group, x3: the group reaches 150%, so it always drops and this item is 10% of it.
        $this->assertEqualsWithDelta(10, $this->roll([['chance' => 10], ['chance' => 90]], 50)['chance'], 1e-9);
    }

    public function test_a_group_over_100_percent_always_drops_and_multiplies_the_amount(): void
    {
        // Group 70% with one item at 100%, x3: 210% -> always, 2 to 3 times the amount (1-2 -> 2-6).
        $this->assertSame(['chance' => 100.0, 'min' => 2, 'max' => 6], $this->roll([['chance' => 100]], 70));
        // Two items 50/50 in that group: each 50% of the time.
        $this->assertEqualsWithDelta(50, $this->roll([['chance' => 50], ['chance' => 50]], 70)['chance'], 1e-9);
    }

    public function test_adena_uses_its_own_rates(): void
    {
        // Adena: chance x1, amount x4.
        $this->assertSame(['chance' => 20.0, 'min' => 4, 'max' => 8], $this->roll([['chance' => 40, 'item' => 57], ['chance' => 20]], 50));
    }

    public function test_search_and_item_pages(): void
    {
        $this->import();

        $this->get('/items?q=kukuri')->assertOk()->assertInertia(fn ($page) => $page
            ->component('items/index')
            ->where('results.data.0.name', 'Kukuri'));

        $this->get('/items?q=member+teleport+party')->assertOk()->assertInertia(fn ($page) => $page
            ->where('results.data.0.name', 'Party Member Teleport Scroll'));

        $this->get('/items/223')->assertOk()->assertInertia(fn ($page) => $page
            ->component('items/show')
            ->where('item.name', 'Kukuri')
            ->where('item.grade', 'D')
            ->where('item.stats.pAtk.val', 56)
            ->where('item.price', 967000)
            ->where('item.drops.rows', fn ($rows) => collect($rows)->contains(fn ($r) => $r['npc']['name'] === 'Goblin Bandit'
                && abs($r['chance'] - 1.145 * 3 * 0.0045 / 100) < 1e-6)) // monster: x3
            ->where('item.merchants', fn ($merchants) => count($merchants) > 0));

        $this->get('/items/999999')->assertNotFound();
    }
}
