<?php

namespace App\Services\L2j;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Happening now": live state of epic bosses, raid bosses, castle sieges, the Grand Olympiad and the server's
 * seasonal events, each with a lore-flavoured headline. Sorted live, soon (< 24 h), scheduled, ready, slain.
 * Daily times use the app time zone, which must match the game server's (both UTC unless TZ is set).
 * Each event lists where it happens on the world map ("at", see WorldMap).
 */
class EventFeed
{
    private const DAY_MS = 86_400_000;

    private const STATE_ORDER = ['live' => 0, 'soon' => 1, 'scheduled' => 2, 'ready' => 3, 'slain' => 4];

    /**
     * Epic bosses in prestige order (the landing page shows the first ones when several share a state).
     * grandboss_data.status meanings come from each boss's AI script in the Essence datapack: Baium uses
     * 0 alive, 1 heroes entering, 2 fighting, 3 dead; the others 0 alive, 1 dead.
     * All are placed from their spawn in grandboss_data.
     *
     * @var array<int, array{place: string, fourState?: bool, at?: array{0: int, 1: int}, ready: string, gathering?: string, fighting?: string, slain: string}>
     */
    private const EPIC = [
        29068 => [
            'place' => "Antharas' Lair, Dragon Valley",
            'ready' => 'The Earth Dragon has woken beneath Dragon Valley. His tremors reach every town of Aden.',
            'slain' => 'Antharas has fallen. The earth is quiet... for now.',
        ],
        29020 => [
            'place' => 'Tower of Insolence', 'fourState' => true,
            'ready' => 'Emperor Baium sleeps sealed at the top of the Tower of Insolence. Only the bold will wake him.',
            'gathering' => 'Baium stirs! Someone has disturbed the emperor at the top of the Tower of Insolence.',
            'fighting' => 'Emperor Baium is awake and enraged. The Tower of Insolence shakes with battle!',
            'slain' => 'Baium has fallen once more. His throne at the top of the tower stands empty.',
        ],
        29022 => [
            'place' => "Devil's Isle",
            'ready' => 'Zaken, the cursed pirate captain, haunts the decks of his ship. The tide brings his crew back.',
            'slain' => 'Zaken has been sent to the depths. His ship drifts, silent.',
        ],
        29001 => [
            'place' => 'Ant Nest',
            'ready' => 'Queen Ant rules her nest beneath the ground. Her brood grows every hour.',
            'slain' => 'Queen Ant has been slain. Her nest lies leaderless.',
        ],
        29006 => [
            'place' => 'Cruma Tower',
            'ready' => 'Core hums deep inside Cruma Tower, guarded by its ancient machines.',
            'slain' => 'Core has been destroyed. Cruma Tower is silent.',
        ],
        29014 => [
            'place' => 'Sea of Spores',
            'ready' => 'Orfen, the Spore Mistress, haunts the Sea of Spores.',
            'slain' => 'Orfen has been slain. The Sea of Spores is calm for a while.',
        ],
    ];

    /** Castle icons on the map, in pixels, by castle.name. */
    private const CASTLES = [
        'Gludio' => [725, 1858], 'Dion' => [940, 2095], 'Giran' => [1395, 2025], 'Oren' => [1230, 1480], 'Aden' => [1563, 1340],
        'Innadril' => [1400, 2540], 'Goddard' => [1557, 1075], 'Rune' => [880, 1050], 'Schuttgart' => [1210, 545],
    ];

    private const OLYMPIAD_STADIUM = [715, 1200];

    /**
     * Seasonal events (data/scripts/events/<folder>/config.xml), by folder. Events not listed here still show while
     * active, with their config name and a generic headline. "place" is where players find them.
     *
     * @var array<string, array{title: string, place: string, headline: string, detail: string}>
     */
    private const SEASONAL = [
        'LeonasDungeonEvent' => [
            'title' => "Leona's Dungeon",
            'place' => 'Leona Blackbird in Giran and Aden',
            'headline' => 'Leona Blackbird opens a dungeon born of her own memory. Bring your command channel and train to become a hero.',
            'detail' => 'Command channel, level 76+',
        ],
        'LegendOfTheWaterTemple' => [
            'title' => 'Legend of the Water Temple',
            'place' => 'Researcher Adella in Giran and Aden',
            'headline' => 'Monsters posing as Water Spirits have seized the Water Temple. Fight to the top floor and claim the power of water.',
            'detail' => 'Party of 2 to 5, once a day',
        ],
        'LetterCollector' => [
            'title' => 'Letter Collector',
            'place' => 'Monsters all across Aden',
            'headline' => 'Letters are scattered across Aden. Gather them and spell the words to unseal their rewards.',
            'detail' => 'Monsters level 40+',
        ],
        'SquashEvent' => [
            'title' => 'Halloween Pumpkin Harvest',
            'place' => 'Puss the Cat in every town',
            'headline' => 'Strange pumpkins are sprouting across Aden. Gather Nectar, grow them and break them open for prizes.',
            'detail' => 'All levels, Halloween Kits inside',
        ],
        'HolidayOutOfSchedule' => [
            'title' => "Santa's Visit",
            'place' => 'Rudolph in every town',
            'headline' => 'Santa Claus is in Aden for one hour at 15:00 and 22:00 UTC. Rudolph will take you to him for a daily gift.',
            'detail' => 'All levels, one gift per account each day',
        ],
    ];

    /** Events further away than this show "Happening now" instead of a countdown to their end. */
    private const SEASONAL_COUNTDOWN_MS = 60 * self::DAY_MS;

    public function __construct(private GameConfig $config, private GameData $gameData) {}

    /**
     * @return array{generatedAt: string, live: int, events: list<array<string, mixed>>}
     */
    public function get(): array
    {
        // Cached per language: the headlines are written in the visitor's language.
        return Cache::remember('l2j.events.'.app()->getLocale(), config('l2j.cache_seconds'), fn (): array => $this->build((int) round(microtime(true) * 1000)));
    }

    /**
     * @return array{generatedAt: string, live: int, events: list<array<string, mixed>>}
     */
    public function build(int $now): array
    {
        $game = DB::connection('game');

        $events = [
            ...$this->epicBosses($game->table('grandboss_data')->get(['boss_id', 'loc_x', 'loc_y', 'status', 'respawn_time'])->all(), $now),
            ...$this->sieges($game->table('castle as c')->leftJoin('clan_data as cd', 'cd.hasCastle', '=', 'c.id')
                ->get(['c.name', 'c.siegeDate', 'cd.clan_name'])->all(), $now),
            ...$this->olympiad($game->table('olympiad_data')->first(['period', 'validation_end']), $now),
            ...$this->raidBosses($this->raidSpawns(), $now),
            ...$this->seasonal($now),
        ];

        usort($events, fn (array $a, array $b): int => (self::STATE_ORDER[$a['state']] <=> self::STATE_ORDER[$b['state']])
            ?: ($this->sortTime($a) <=> $this->sortTime($b))
            ?: (($a['priority'] ?? 99) <=> ($b['priority'] ?? 99)));

        return [
            'generatedAt' => self::iso($now),
            'live' => count(array_filter($events, fn (array $e): bool => $e['state'] === 'live')),
            'events' => array_values($events),
        ];
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function epicBosses(array $rows, int $now): array
    {
        $order = array_keys(self::EPIC);
        $events = [];

        foreach ($rows as $row) {
            $lore = self::EPIC[(int) $row->boss_id] ?? null;

            if ($lore === null) {
                continue;
            }

            $boss = $this->gameData->boss((int) $row->boss_id);
            $status = (int) $row->status;
            $respawn = (int) $row->respawn_time;
            $fourState = $lore['fourState'] ?? false;
            $dead = $fourState ? $status === 3 : $status === 1;

            [$state, $headline, $detail] = match (true) {
                $dead && $respawn > $now => ['slain', __($lore['slain']), __('Returns')],
                $fourState && $status === 1 => ['live', __($lore['gathering']), __('Challengers are entering the lair')],
                $fourState && $status === 2 => ['live', __($lore['fighting']), __('Battle in progress')],
                default => ['ready', __($lore['ready']), __('Awaits challengers')],
            };

            $events[] = [
                'id' => "epic-{$row->boss_id}",
                'category' => 'epic',
                'priority' => array_search((int) $row->boss_id, $order, true),
                'title' => $boss['name'],
                'level' => $boss['level'],
                'place' => __($lore['place']),
                'state' => $state,
                'headline' => $headline,
                'detail' => $detail,
                'returnsAt' => $state === 'slain' ? self::iso($respawn) : null,
                'at' => isset($lore['at'])
                    ? [self::pin($lore['at'], $lore['place'])]
                    : self::spawn((int) $row->loc_x, (int) $row->loc_y, $lore['place']),
            ];
        }

        return $events;
    }

    /**
     * Raid bosses from npc_respawns (the spawns the server saves: id, x, y, respawnTime), shaped like the
     * boss_id / loc_x / loc_y / respawn_time rows the feed and the map use. The server writes a row when it spawns a
     * saved NPC; only the ones that are raid bosses in the datapack count.
     *
     * @return list<object>
     */
    public function raidSpawns(): array
    {
        return DB::connection('game')->table('npc_respawns')
            ->get(['id as boss_id', 'x as loc_x', 'y as loc_y', 'respawnTime as respawn_time'])
            ->filter(fn (object $row): bool => $this->gameData->isRaidBoss((int) $row->boss_id))
            ->values()
            ->all();
    }

    /**
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function raidBosses(array $rows, int $now): array
    {
        $alive = array_filter($rows, fn (object $r): bool => (int) $r->respawn_time <= $now);
        $slain = array_filter($rows, fn (object $r): bool => (int) $r->respawn_time > $now);
        usort($slain, fn (object $a, object $b): int => (int) $a->respawn_time <=> (int) $b->respawn_time);

        $events = [[
            'id' => 'raids-roaming',
            'category' => 'raid',
            'title' => __('Raid Bosses'),
            'place' => __('All across Aden'),
            'state' => $alive ? 'ready' : 'slain',
            'headline' => __(':alive of :total raid bosses roam the lands of Aden. Gather your party and claim their treasures.', ['alive' => count($alive), 'total' => count($rows)]),
            'detail' => __(':count roaming', ['count' => count($alive)]),
        ]];

        // The next five to return: the ones players will race for.
        foreach (array_slice($slain, 0, 5) as $row) {
            $boss = $this->gameData->boss((int) $row->boss_id);
            $events[] = [
                'id' => "raid-{$row->boss_id}",
                'category' => 'raid',
                'title' => $boss['name'],
                'level' => $boss['level'],
                'place' => __('Raid Boss'),
                'state' => 'slain',
                'headline' => __(':boss was slain by brave adventurers. It will return, and the race for its treasure begins again.', ['boss' => $boss['name']]),
                'detail' => __('Returns'),
                'returnsAt' => self::iso((int) $row->respawn_time),
                'at' => self::spawn((int) $row->loc_x, (int) $row->loc_y, $boss['name']),
            ];
        }

        return $events;
    }

    /**
     * Castles sharing a siege time are grouped ("Sieges of Aden, Dion and Giran").
     *
     * @param  list<object>  $rows
     * @return list<array<string, mixed>>
     */
    private function sieges(array $rows, int $now): array
    {
        $length = (int) ($this->config->number('Siege.ini', 'SiegeLength', 60) * 60_000);
        $byStart = [];

        foreach ($rows as $castle) {
            $start = intdiv((int) $castle->siegeDate, 60_000) * 60_000; // the server adds a few ms per castle

            if ($start === 0 || $start + $length <= $now) {
                continue;
            }

            $byStart[$start][] = $castle;
        }

        ksort($byStart);
        $events = [];

        foreach ($byStart as $start => $castles) {
            $names = array_map(fn (object $c): string => $c->name, $castles);
            $list = count($names) > 1 ? implode(', ', array_slice($names, 0, -1)).' '.__('and').' '.end($names) : $names[0];
            $owners = array_map(fn (object $c): string => __(':clan holds :castle', ['clan' => $c->clan_name, 'castle' => $c->name]), array_filter($castles, fn (object $c): bool => $c->clan_name !== null));
            $state = self::scheduleState($now, $start, $start + $length);

            $events[] = [
                'id' => "siege-{$start}",
                'category' => 'siege',
                'title' => count($names) > 1 ? __('Sieges of :list', ['list' => $list]) : __('Siege of :list', ['list' => $list]),
                'place' => count($names) > 1 ? __(':count castles', ['count' => count($names)]) : __(':name Castle', ['name' => $list]),
                'state' => $state,
                'headline' => $state === 'live'
                    ? __('The Siege of :list rages! Banners clash at the castle walls. Join your clan and claim the throne.', ['list' => $list])
                    : __('War drums sound across Aden. Clans gather their banners for the Siege of :list.', ['list' => $list]),
                'detail' => $owners ? implode(' · ', $owners) : __('Every castle is unclaimed. Any clan can take a throne.'),
                'startsAt' => self::iso($start),
                'endsAt' => self::iso($start + $length),
                'at' => array_values(array_filter(array_map(
                    fn (string $name): ?array => isset(self::CASTLES[$name]) ? self::pin(self::CASTLES[$name], "{$name} Castle") : null,
                    $names,
                ))),
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function olympiad(?object $row, int $now): array
    {
        if ($row === null) {
            return [];
        }

        if ((int) $row->period === 1) {
            return [[
                'id' => 'olympiad', 'category' => 'olympiad', 'title' => __('Grand Olympiad'), 'place' => __('Olympiad Stadium'), 'state' => 'live',
                'headline' => __('The Olympiad season has ended. The new Heroes of Aden are being crowned!'),
                'detail' => __('Hero validation'),
                'endsAt' => (int) $row->validation_end ? self::iso((int) $row->validation_end) : null,
                'at' => [self::pin(self::OLYMPIAD_STADIUM, 'Olympiad Stadium')],
            ]];
        }

        if (! $this->config->flag('Olympiad.ini', 'OlympiadEnabled', true)) {
            return [];
        }

        // Competition period: the arena opens at OlympiadStartTime:OlympiadMin for OlympiadCPeriod, only on the
        // OlympiadCompetitionDays (1 = Sunday ... 7 = Saturday, Java's numbering).
        [$start, $end] = self::dailyWindow(
            $now,
            (int) $this->config->number('Olympiad.ini', 'OlympiadStartTime', 20),
            (int) $this->config->number('Olympiad.ini', 'OlympiadMin', 0),
            (int) $this->config->number('Olympiad.ini', 'OlympiadCPeriod', 14_400_000),
            array_map('intval', array_filter(explode(',', str_replace(' ', '', $this->config->get('Olympiad.ini', 'OlympiadCompetitionDays', '6,7'))))),
        );
        $state = self::scheduleState($now, $start, $end);

        return [[
            'id' => 'olympiad',
            'category' => 'olympiad',
            'title' => __('Grand Olympiad'),
            'place' => __('Olympiad Stadium'),
            'state' => $state,
            'headline' => $state === 'live'
                ? __('The Olympiad arena is open! Prove you are the best of your class and fight for the title of Hero.')
                : __('The Olympiad Stadium will open its gates soon. Noblesse, sharpen your blades.'),
            'detail' => __('Matches for Noblesse'),
            'startsAt' => self::iso($start),
            'endsAt' => self::iso($end),
            'at' => [self::pin(self::OLYMPIAD_STADIUM, 'Olympiad Stadium')],
        ]];
    }

    /**
     * Seasonal events running now, read from each event's config.xml in the datapack (mounted read-only): its name,
     * its active dates ("dd MM yyyy-dd MM yyyy", the server's time zone) and the NPCs it spawns, which become its
     * map points. Events whose dates have passed or not yet begun are left out, as the server ignores them.
     *
     * @return list<array<string, mixed>>
     */
    private function seasonal(int $now): array
    {
        $files = [];

        // An event's config in overrides/ replaces the datapack's, as it does on the game server.
        foreach ([config('l2j.events_dir'), config('l2j.events_overrides_dir')] as $dir) {
            foreach (glob(rtrim((string) $dir, '/').'/*/config.xml') ?: [] as $file) {
                $files[basename(dirname($file))] = $file;
            }
        }

        $events = [];

        foreach ($files as $folder => $file) {
            $xml = @simplexml_load_file($file);

            if ($xml === false || ! preg_match('/^(\d{2} \d{2} \d{4})-(\d{2} \d{2} \d{4})$/', trim((string) $xml['active']), $dates)) {
                continue;
            }

            $start = (int) CarbonImmutable::createFromFormat('d m Y', $dates[1], config('app.timezone'))->startOfDay()->getTimestampMs();
            $end = (int) CarbonImmutable::createFromFormat('d m Y', $dates[2], config('app.timezone'))->startOfDay()->getTimestampMs();

            if ($now < $start || $now >= $end) {
                continue;
            }

            $info = self::SEASONAL[$folder] ?? [
                'title' => (string) $xml['name'],
                'place' => 'All across Aden',
                'headline' => 'A seasonal event is under way in Aden. Join in while it lasts.',
                'detail' => 'Seasonal event',
            ];
            $at = [];

            foreach ($xml->spawnlist->add ?? [] as $spawn) {
                array_push($at, ...self::spawn((int) $spawn['x'], (int) $spawn['y'], $info['title']));
            }

            $events[] = [
                'id' => "event-{$folder}",
                'category' => 'event',
                'title' => __($info['title']),
                'place' => __($info['place']),
                'state' => 'live',
                'headline' => __($info['headline']),
                'detail' => __($info['detail']),
                'endsAt' => $end - $now <= self::SEASONAL_COUNTDOWN_MS ? self::iso($end) : null,
                'at' => $at,
            ];
        }

        return $events;
    }

    /**
     * A named map point from a position on the map image, in pixels.
     *
     * @param  array{0: int, 1: int}  $pixels
     * @return array{x: float, y: float, name: string}
     */
    private static function pin(array $pixels, string $name): array
    {
        return [...WorldMap::pixel($pixels[0], $pixels[1]), 'name' => $name];
    }

    /**
     * A named map point from game coordinates; empty when the spot is not on the map.
     *
     * @return list<array{x: float, y: float, name: string}>
     */
    private static function spawn(int $x, int $y, string $name): array
    {
        $point = WorldMap::project($x, $y);

        return $point === null ? [] : [[...$point, 'name' => $name]];
    }

    /**
     * The current or next window starting at hour:minute and lasting $lengthMs, checking yesterday's window too
     * for windows that run past midnight. With $days (1 = Sunday ... 7 = Saturday), only those weekdays count.
     *
     * @param  list<int>  $days
     * @return array{0: int, 1: int}
     */
    public static function dailyWindow(int $now, int $hour, int $minute, int $lengthMs, array $days = []): array
    {
        $today = CarbonImmutable::createFromTimestampMs($now, config('app.timezone'))->setTime($hour, $minute);

        foreach (range(-1, 8) as $dayOffset) {
            $day = $today->addDays($dayOffset);
            $start = (int) $day->getTimestampMs();

            if ($start + $lengthMs > $now && ($days === [] || in_array($day->dayOfWeek + 1, $days, true))) {
                return [$start, $start + $lengthMs];
            }
        }

        $start = (int) $today->addDays(9)->getTimestampMs();

        return [$start, $start + $lengthMs];
    }

    private static function scheduleState(int $now, int $start, int $end): string
    {
        if ($now >= $start && $now < $end) {
            return 'live';
        }

        return $start - $now < self::DAY_MS ? 'soon' : 'scheduled';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function sortTime(array $event): int
    {
        $time = $event['startsAt'] ?? $event['returnsAt'] ?? null;

        return $time ? (int) CarbonImmutable::parse($time)->getTimestampMs() : PHP_INT_MAX;
    }

    public static function iso(int $milliseconds): string
    {
        return CarbonImmutable::createFromTimestampMs($milliseconds)->toIso8601ZuluString('millisecond');
    }
}
