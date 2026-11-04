<?php

namespace App\Services\L2j;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Server status for the About page: login/game server up, players online, rates, world stats,
 * top players and castle owners. Cached for l2j.cache_seconds so visitors can't load the database.
 */
class ServerStatus
{
    /** Playable races by characters.race (the Race enum of L2J Mobius Essence; 6-29 are NPC races). */
    public const RACES = [0 => 'Human', 1 => 'Elf', 2 => 'Dark Elf', 3 => 'Orc', 4 => 'Dwarf', 5 => 'Kamael', 30 => 'Sylph', 31 => 'High Elf'];

    public function __construct(private GameConfig $config) {}

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return Cache::remember('l2j.status', config('l2j.cache_seconds'), fn (): array => $this->build());
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $world = null;

        try {
            $world = $this->world();
        } catch (Throwable $exception) {
            report($exception);
        }

        return [
            'generatedAt' => now()->toIso8601String(),
            'server' => [
                'name' => config('l2j.server_name'),
                'chronicle' => config('l2j.chronicle'),
                'maxLevel' => (int) $this->config->number('Player.ini', 'MaximumPlayerLevel', 99),
                // The hosts file needs an IP; with a DNS name (play.example.com) it's resolved here, so the
                // page follows the home IP when it changes. Launcher .bat files can use the name itself.
                'address' => $this->publicIp(),
                'hostname' => filter_var(config('l2j.public_host'), FILTER_VALIDATE_IP) ? null : config('l2j.public_host'),
                'loginPort' => (int) config('l2j.login_port'),
                'gamePort' => (int) config('l2j.game_port'),
                'geodataRegions' => $this->geodataRegions(),
                // The game server's clock: events and daily resets follow it.
                'timezone' => (string) config('app.timezone'),
                'autoCreateAccounts' => (bool) config('l2j.accounts.auto_create'),
            ],
            'status' => [
                'login' => self::isListening((string) config('l2j.login_host'), 2106),
                'game' => self::isListening((string) config('l2j.game_host'), (int) config('l2j.game_port')),
                'database' => $world !== null,
            ],
            'rates' => $this->rates(),
            ...($world ?? []),
        ];
    }

    public static function isListening(string $host, int $port, float $timeoutSeconds = 1.5): bool
    {
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, $timeoutSeconds);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * Rates from Rates.ini as the game server reads it (overrides over defaults); missing keys are retail x1.
     *
     * @return array<string, float>
     */
    public function rates(): array
    {
        $adena = 1.0;

        foreach (explode(';', $this->config->get('Rates.ini', 'DropAmountMultiplierByItemId', '57,1')) as $pair) {
            [$itemId, $multiplier] = array_pad(explode(',', $pair), 2, null);

            if ((int) $itemId === 57) {
                $adena = (float) $multiplier;
            }
        }

        return [
            'xp' => $this->config->number('Rates.ini', 'RateXp', 1),
            'sp' => $this->config->number('Rates.ini', 'RateSp', 1),
            'adena' => $adena,
            'drop' => $this->config->number('Rates.ini', 'DeathDropChanceMultiplier', 1),
            'spoil' => $this->config->number('Rates.ini', 'SpoilDropChanceMultiplier', 1),
            'raid' => $this->config->number('Rates.ini', 'RaidDropChanceMultiplier', 1),
            'questXp' => $this->config->number('Rates.ini', 'RateQuestRewardXP', 1),
            'questAdena' => $this->config->number('Rates.ini', 'RateQuestRewardAdena', 1),
        ];
    }

    private function geodataRegions(): int
    {
        $files = glob(rtrim((string) config('l2j.geodata_dir'), '/').'/*.l2j');

        return $files === false ? 0 : count($files);
    }

    /**
     * Game Masters (accesslevel > 0) are left out of rankings so staff characters don't top the lists.
     *
     * @return array<string, mixed>
     */
    private function world(): array
    {
        $game = DB::connection('game');
        $totals = $game->selectOne('SELECT
            (SELECT COUNT(*) FROM characters) AS characters,
            (SELECT COUNT(*) FROM characters WHERE online = 1) AS online,
            (SELECT COUNT(*) FROM clan_data) AS clans');
        $accounts = DB::connection('login')->table('accounts')->count('login');
        $races = $game->table('characters')->selectRaw('race, COUNT(*) AS total')->groupBy('race')->pluck('total', 'race');

        $raceName = fn (int $race): string => self::RACES[$race] ?? 'Unknown';

        return [
            'players' => ['online' => (int) $totals->online],
            'totals' => ['accounts' => $accounts, 'characters' => (int) $totals->characters, 'clans' => (int) $totals->clans],
            'races' => array_map(fn (string $name, int $race): array => [
                'name' => $name,
                'count' => (int) ($races[$race] ?? 0),
            ], array_values(self::RACES), array_keys(self::RACES)),
            'top' => [
                'level' => $game->table('characters')->where('accesslevel', 0)
                    ->orderByDesc('level')->orderByDesc('exp')->limit(5)
                    ->get(['char_name', 'level', 'race'])
                    ->map(fn (object $c): array => ['name' => $c->char_name, 'level' => (int) $c->level, 'race' => $raceName((int) $c->race)])
                    ->all(),
                'pvp' => $game->table('characters')->where('accesslevel', 0)->where('pvpkills', '>', 0)
                    ->orderByDesc('pvpkills')->limit(5)
                    ->get(['char_name', 'pvpkills', 'level', 'race'])
                    ->map(fn (object $c): array => ['name' => $c->char_name, 'pvp' => (int) $c->pvpkills, 'level' => (int) $c->level, 'race' => $raceName((int) $c->race)])
                    ->all(),
            ],
            'castles' => $game->table('castle as c')
                ->leftJoin('clan_data as cd', 'cd.hasCastle', '=', 'c.id')
                ->orderBy('c.id')
                ->get(['c.name', 'cd.clan_name'])
                ->map(fn (object $c): array => ['name' => $c->name, 'owner' => $c->clan_name])
                ->all(),
        ];
    }

    private function publicIp(): ?string
    {
        $host = config('l2j.public_host');

        if (! $host || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host ?: null;
        }

        $ip = gethostbyname($host);

        return $ip === $host ? null : $ip;
    }
}
