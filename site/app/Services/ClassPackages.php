<?php

namespace App\Services;

use App\Models\Game\Account;
use App\Models\Game\Character;
use App\Models\GameAction;
use App\Models\PackageClaim;
use App\Services\L2j\GameData;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Class packages (config l2j.packages): a full set of gear for one archetype in one tier, bought with L-Coin from one
 * of the account's characters' bags. Like the Kage Pass, the website only queues the purchase (game_actions
 * "buy_pass"); the WebBridge inside the game server takes the L-Coin and mails the gear in one step, in several mails
 * when it is more than 8 items. Each package (tier + archetype) once per game account per season; a purchase that
 * failed in game (not enough L-Coin by then) can be made again.
 */
class ClassPackages
{
    public function __construct(private GameData $gameData) {}

    /**
     * The catalog for the landing page, /packages and the player area: tiers with their price, and each archetype's
     * contents per tier.
     *
     * @return array<string, mixed>
     */
    public function offer(): array
    {
        $tiers = config('l2j.packages.tiers');

        return [
            'enabled' => (bool) config('l2j.packages.enabled'),
            'season' => config('l2j.packages.season'),
            'currency' => $this->gameData->item((int) config('l2j.packages.price_item'))['name'],
            'tiers' => array_map(
                fn (string $key, array $tier): array => ['key' => $key, 'name' => $tier['name'], 'grade' => $tier['grade'], 'price' => $tier['price'], 'enchant' => $tier['enchant']],
                array_keys($tiers),
                $tiers,
            ),
            'archetypes' => array_map(
                fn (string $key, array $archetype): array => [
                    'key' => $key,
                    'name' => $archetype['name'],
                    'role' => $archetype['role'],
                    'armor' => $archetype['armor'],
                    'contents' => array_map(
                        fn (string $tier): array => array_map(
                            fn (array $item): array => ['id' => $item['item'], 'name' => $this->gameData->item($item['item'])['name'], 'enchant' => $item['enchant']],
                            $this->items($tier, $key),
                        ),
                        array_combine(array_keys($tiers), array_keys($tiers)),
                    ),
                ],
                array_keys(config('l2j.packages.archetypes')),
                config('l2j.packages.archetypes'),
            ),
        ];
    }

    /**
     * The player area panel: the catalog, the account's characters (with their L-Coin and suggested archetype) and
     * this season's purchases.
     *
     * @return array<string, mixed>
     */
    public function status(Account $account): array
    {
        $coins = $this->coins($account);

        return [
            ...$this->offer(),
            'characters' => Character::query()
                ->where('account_name', $account->login)
                ->where('deletetime', 0)
                ->orderByDesc('level')
                ->get(['charId', 'char_name', 'level', 'classid'])
                ->map(fn (Character $c): array => [
                    'id' => $c->charId,
                    'name' => $c->char_name,
                    'level' => $c->level,
                    'className' => $this->gameData->className($c->classid),
                    'archetype' => $this->archetypeFor($c->classid),
                    'coins' => $coins[$c->charId] ?? 0,
                ])
                ->all(),
            'claims' => PackageClaim::with('gameAction')
                ->where('account', $account->login)
                ->where('season', config('l2j.packages.season'))
                ->latest('id')
                ->get()
                ->map(fn (PackageClaim $claim): array => [
                    'package' => $claim->package,
                    'characterName' => $claim->character_name,
                    'claimedAt' => $claim->created_at->toIso8601String(),
                    'status' => $claim->gameAction->status ?? GameAction::STATUS_FAILED,
                ])
                ->all(),
        ];
    }

    /**
     * Buys one package for one of the account's characters: records the purchase and queues the in-game charge and
     * mail, in one transaction.
     *
     * @throws ValidationException when packages are closed, the package is unknown or already bought, or the
     *                             character can't pay
     */
    public function claim(Account $account, int $characterId, string $tier, string $archetype): PackageClaim
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['package' => $message]);

        if (! config('l2j.packages.enabled')) {
            $fail(__('Class packages are closed for now.'));
        }

        $tierConfig = config("l2j.packages.tiers.{$tier}");

        if (! is_array($tierConfig) || ! is_array(config("l2j.packages.archetypes.{$archetype}"))) {
            $fail(__('Choose one of the packages.'));
        }

        $character = Character::query()->where('account_name', $account->login)->where('deletetime', 0)->find($characterId, ['charId', 'char_name']);

        if ($character === null) {
            $fail(__('Choose one of your characters.'));
        }

        $price = (int) $tierConfig['price'];

        if (($this->coins($account)[$character->charId] ?? 0) < $price) {
            $fail(__(':name needs :count L-Coin in the bag to buy this package.', ['name' => $character->char_name, 'count' => number_format($price)]));
        }

        $season = (string) config('l2j.packages.season');
        $package = "{$tier}:{$archetype}";
        $previous = PackageClaim::with('gameAction')->where('account', $account->login)->where('season', $season)->where('package', $package)->first();

        // A purchase whose charge failed in game (not enough L-Coin by then) gives way to a new one.
        if ($previous && ($previous->gameAction === null || $previous->gameAction->status === GameAction::STATUS_FAILED)) {
            $previous->delete();
        }

        $name = config("l2j.packages.archetypes.{$archetype}.name.en").' Package ('.$tierConfig['name']['en'].')';

        try {
            return DB::transaction(function () use ($account, $character, $price, $season, $package, $tier, $archetype, $name): PackageClaim {
                $action = GameAction::create([
                    'action' => GameAction::ACTION_BUY_PASS,
                    'character_id' => $character->charId,
                    'character_name' => $character->char_name,
                    'item_id' => (int) config('l2j.packages.price_item'),
                    'item_name' => $name,
                    'count' => $price,
                    'items' => implode(';', array_map(fn (array $item): string => "{$item['item']}:1:{$item['enchant']}", $this->items($tier, $archetype))),
                    'mail_subject' => config('l2j.packages.mail_subject'),
                    'mail_text' => config('l2j.packages.mail_text'),
                    'note' => number_format($price).' L-Coin',
                    'requested_by' => "class package ({$account->login})",
                ]);

                return PackageClaim::create([
                    'account' => $account->login,
                    'season' => $season,
                    'package' => $package,
                    'character_id' => $character->charId,
                    'character_name' => $character->char_name,
                    'game_action_id' => $action->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $fail(__('You have already bought this package this season.'));
        }
    }

    /**
     * A package's gear: the weapon (and shield) and armor at the tier's enchant, then the jewelry.
     *
     * @return list<array{item: int, enchant: int}>
     */
    public function items(string $tier, string $archetype): array
    {
        $tierConfig = config("l2j.packages.tiers.{$tier}");
        $archetypeConfig = config("l2j.packages.archetypes.{$archetype}");
        $enchant = (int) $tierConfig['enchant'];

        $gear = [$archetypeConfig['weapons'][$tier]];

        if ($archetypeConfig['shield'] ?? false) {
            $gear[] = $tierConfig['shield'];
        }

        return [
            ...array_map(fn (int $item): array => ['item' => $item, 'enchant' => $enchant], [...$gear, ...$tierConfig['armor'][$archetypeConfig['armor']]]),
            ...array_map(fn (int $item): array => ['item' => $item, 'enchant' => 0], $tierConfig['jewelry']),
        ];
    }

    /** The archetype suggested for a class, or null when none lists it. */
    public function archetypeFor(int $classId): ?string
    {
        foreach (config('l2j.packages.archetypes') as $key => $archetype) {
            if (in_array($classId, $archetype['classes'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * L-Coin in each of the account's characters' bags, as last saved by the game server.
     *
     * @return array<int, int> character id => count
     */
    private function coins(Account $account): array
    {
        return Character::bagCounts($account->login, (int) config('l2j.packages.price_item'));
    }
}
